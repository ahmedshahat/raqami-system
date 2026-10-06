<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Unit;
use App\User;
use App\VariationLocationDetails;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Construction\Entities\ConstructionMaterialDocument;
use Modules\Construction\Entities\ConstructionProject;
use Tests\TestCase;

class ConstructionMaterialWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_approved_issue_deducts_stock_and_approved_return_restores_it_once(): void
    {
        $candidate = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->join('products as p', 'p.id', '=', 'pl.product_id')
            ->join('variation_location_details as vld', function ($join) {
                $join->on('vld.product_id', '=', 'pl.product_id')
                    ->on('vld.variation_id', '=', 'pl.variation_id')
                    ->on('vld.location_id', '=', 't.location_id');
            })
            ->where('t.type', 'purchase')->where('t.status', 'received')->where('p.enable_stock', 1)
            ->where('pl.purchase_price_inc_tax', '>', 0)
            ->whereRaw('(pl.quantity - pl.quantity_sold - pl.quantity_adjusted - pl.quantity_returned - pl.mfg_quantity_used) >= 2')
            ->where('vld.qty_available', '>=', 2)
            ->select('t.business_id', 't.location_id', 'pl.product_id', 'pl.variation_id', 'vld.id as stock_id')
            ->first();
        $this->assertNotNull($candidate, 'The test database needs one stocked product with an available purchase lot.');

        $business = Business::findOrFail($candidate->business_id);
        $this->assertTrue(Unit::where('business_id', $business->id)->exists());
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $project = ConstructionProject::create([
            'business_id' => $business->id, 'code' => 'MAT-TEST-'.uniqid(), 'name' => 'Material workflow test',
            'customer_id' => $customer->id, 'status' => 'active', 'created_by' => $user->id,
        ]);
        $this->get(route('construction.materials.index'))->assertOk()->assertSee(__('construction::lang.material_documents'));
        $this->get(route('construction.materials.create'))->assertOk()->assertSee($project->code);
        $issue = ConstructionMaterialDocument::create([
            'business_id' => $business->id, 'project_id' => $project->id, 'location_id' => $candidate->location_id,
            'type' => 'issue', 'number' => 'MAT-ISS-TEST-'.uniqid(), 'document_date' => now()->toDateString(),
            'status' => 'draft', 'created_by' => $user->id,
        ]);
        $issue->lines()->create([
            'business_id' => $business->id, 'project_id' => $project->id, 'product_id' => $candidate->product_id,
            'variation_id' => $candidate->variation_id, 'quantity' => 1,
        ]);
        $deletableDraft = ConstructionMaterialDocument::create([
            'business_id' => $business->id, 'project_id' => $project->id, 'location_id' => $candidate->location_id,
            'type' => 'issue', 'number' => 'MAT-DELETE-TEST-'.uniqid(), 'document_date' => now()->toDateString(),
            'status' => 'draft', 'created_by' => $user->id,
        ]);
        $this->delete(route('construction.materials.destroy', $deletableDraft->id))
            ->assertRedirect(route('construction.materials.index'));
        $this->assertDatabaseMissing('construction_material_documents', ['id' => $deletableDraft->id]);
        $before = (float) VariationLocationDetails::findOrFail($candidate->stock_id)->qty_available;
        $adjustmentTotalBefore = (float) app(TransactionUtil::class)->getTransactionTotals(
            $business->id, ['stock_adjustment'], null, null, $candidate->location_id
        )['total_adjustment'];

        $this->get(route('construction.materials.index'))->assertOk()
            ->assertSee(__('construction::lang.operations'))
            ->assertSee(route('construction.materials.edit', $issue->id));
        $this->get(route('construction.materials.edit', $issue->id))->assertOk()->assertSee($issue->number);
        $this->put(route('construction.materials.update', $issue->id), [
            'number' => $issue->number,
            'document_date' => now()->toDateString(),
            'notes' => 'Updated draft document',
        ])->assertRedirect(route('construction.materials.show', $issue->id));
        $lineId = $issue->lines()->value('id');
        $this->put(route('construction.materials.lines.update', [$issue->id, $lineId]), [
            'quantity' => 1,
            'notes' => 'Updated draft line',
        ])->assertRedirect();
        $this->assertSame('Updated draft line', $issue->lines()->findOrFail($lineId)->notes);
        $this->get(route('construction.materials.show', $issue->id))->assertOk()->assertSee(__('construction::lang.add_material'));

        $this->post(route('construction.materials.approve', $issue->id))->assertRedirect();
        $issue->refresh();
        $this->assertSame('approved', $issue->status);
        $this->assertNotNull($issue->stock_adjustment_transaction_id);
        $this->assertSame($issue->id, (int) $issue->stockAdjustment->construction_material_document_id);
        $this->assertEquals($before - 1, (float) VariationLocationDetails::findOrFail($candidate->stock_id)->qty_available);
        $this->assertGreaterThan(0, (float) $issue->lines()->value('total_cost'));
        $this->assertEquals($adjustmentTotalBefore, (float) app(TransactionUtil::class)->getTransactionTotals(
            $business->id, ['stock_adjustment'], null, null, $candidate->location_id
        )['total_adjustment']);
        $stockDetails = app(ProductUtil::class)->getVariationStockDetails($business->id, $candidate->variation_id, $candidate->location_id);
        $this->assertEquals(1, (float) $stockDetails['total_construction_issue']);
        $this->assertStringContainsString(
            __('construction::lang.material_issue_stock_history'),
            collect(app(ProductUtil::class)->getVariationStockHistory($business->id, $candidate->variation_id, $candidate->location_id))->pluck('type_label')->implode('|')
        );
        $this->get(route('construction.materials.show', $issue->id))->assertOk()->assertSee($issue->number);
        $this->get(route('construction.materials.preview', $issue->id))->assertOk()
            ->assertSee(__('construction::lang.material_issue_print_title'))
            ->assertSee(__('construction::lang.storekeeper_delivery'))
            ->assertSee(__('construction::lang.project_manager_approval'));
        $this->get(route('construction.materials.print', $issue->id))->assertOk()->assertSee('window.print()', false);

        $this->post(route('construction.materials.approve', $issue->id))->assertStatus(422);
        $this->get(route('construction.materials.edit', $issue->id))->assertStatus(422);
        $this->delete(route('construction.materials.destroy', $issue->id))->assertStatus(422);
        $this->assertEquals($before - 1, (float) VariationLocationDetails::findOrFail($candidate->stock_id)->qty_available);

        $returnResponse = $this->post(route('construction.materials.returns.store', $issue->id), [
            'number' => 'MAT-RET-TEST-'.uniqid(), 'document_date' => now()->toDateString(),
            'lines' => [['id' => $issue->lines()->value('id'), 'quantity' => '0.5']],
        ])->assertRedirect();
        $return = ConstructionMaterialDocument::where('parent_issue_id', $issue->id)->latest('id')->firstOrFail();
        $this->assertStringContainsString(route('construction.materials.show', $return->id), $returnResponse->headers->get('Location'));

        $this->post(route('construction.materials.approve', $return->id))->assertRedirect();
        $return->refresh();
        $this->assertSame('approved', $return->status);
        $this->assertEquals($before - 0.5, (float) VariationLocationDetails::findOrFail($candidate->stock_id)->qty_available);
        $stockDetailsAfterReturn = app(ProductUtil::class)->getVariationStockDetails($business->id, $candidate->variation_id, $candidate->location_id);
        $this->assertEquals(0.5, (float) $stockDetailsAfterReturn['total_construction_return']);
        $historyAfterReturn = collect(app(ProductUtil::class)->getVariationStockHistory($business->id, $candidate->variation_id, $candidate->location_id));
        $this->assertTrue($historyAfterReturn->contains('type', 'construction_material_return'));
        $this->assertEqualsWithDelta(
            (float) $issue->lines()->sum('total_cost') / 2,
            (float) $return->lines()->sum('total_cost'),
            0.01
        );
    }
}
