<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Construction\Entities\ConstructionProject;
use Tests\TestCase;

class ConstructionQuickCreateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_short_creation_forms_render_as_modals_and_accept_json_without_changing_legacy_routes(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $projectsPage = $this->get(route('construction.projects.index'))->assertOk()
            ->assertSee(route('construction.quotes.index', ['open_create' => 1]))
            ->assertSee('ct-project-create')
            ->assertSee(__('construction::lang.new_project'))
            ->assertSee(route('construction.projects.store'));
        $this->assertSame(1, substr_count($projectsPage->getContent(), 'data-target="#ct-project-create"'));
        $this->assertSame(0, preg_match_all('/<a\b[^>]*\bdata-ct-workspace\b/i', $projectsPage->getContent()));
        $this->get(route('construction.overview.index'))->assertOk()
            ->assertSee(route('construction.quotes.index', ['open_create' => 1]))
            ->assertDontSee(route('construction.projects.create'));

        $projectResponse = $this->postJson(route('construction.projects.store'), [
            'code' => 'TEST-QUICK-CREATE', 'name' => 'Quick creation project',
            'customer_id' => $customer->id, 'consultant_selection' => 'none', 'status' => 'draft',
        ])->assertCreated()->assertJsonStructure(['id', 'url', 'message']);
        $projectId = $projectResponse->json('id');
        $this->get(route('construction.projects.show', $projectId))->assertOk()
            ->assertSee('ct-project-hero')
            ->assertSee('ct-project-steps')
            ->assertSee('ct-project-next')
            ->assertSee('إضافة بنود المشروع')
            ->assertSee('يجب تجهيز بنود المشروع أولًا')
            ->assertDontSee('المقايسة');
        $initialBoq = ConstructionProject::findOrFail($projectId)->boqVersions()->firstOrFail();
        $unit = Unit::where('business_id', $business->id)->firstOrFail();
        $this->postJson(route('construction.projects.boq.items.store', [$projectId, $initialBoq->id]), [
            'items' => [['description' => 'System unit item', 'unit' => $unit->short_name, 'contract_quantity' => 1, 'sales_unit_price' => 50]],
        ])->assertStatus(422);
        $this->post(route('construction.projects.boq.items.store', [$projectId, $initialBoq->id]), [
            'items' => [['description' => 'System unit item', 'unit_id' => $unit->id, 'contract_quantity' => 1, 'sales_unit_price' => 50]],
        ])->assertRedirect();
        $item = $initialBoq->items()->firstOrFail();
        $this->assertSame($unit->id, $item->unit_id);
        $this->assertSame($unit->short_name, $item->unit);

        $this->get(route('construction.projects.create'))->assertOk();
        $this->get(route('construction.projects.measurements.index', $projectId))->assertOk()
            ->assertSee('ct-measurement-create');
        $this->get(route('construction.projects.certificates.index', $projectId))->assertOk()
            ->assertSee('ct-certificate-create');
        $this->get(route('construction.cost-codes.index'))->assertOk()
            ->assertSee('ct-cost-code-create');

        $versionResponse = $this->postJson(route('construction.projects.boq.store', $projectId), [
            'name' => 'Quick version',
        ])->assertCreated()->assertJsonStructure(['id', 'url', 'message']);
        $this->get(route('construction.projects.boq.show', [$projectId, $versionResponse->json('id')]))
            ->assertOk()->assertSee('ct-boq-version-create');
        $this->postJson(route('construction.cost-codes.store'), [
            'code' => 'TEST-QUICK-COST', 'name' => 'Quick cost code', 'category' => 'material',
        ])->assertCreated()->assertJsonStructure(['id', 'message']);

        $initialBoq->update(['status' => 'approved', 'sales_total' => 2357000]);
        $contractCreate = $this->get(route('construction.projects.contracts.create', [
            'project' => $projectId, 'boq_version_id' => $initialBoq->id,
        ]))->assertOk()->assertSee("boq.addEventListener('change', syncValue)", false);
        $this->assertMatchesRegularExpression(
            '/<option value="'.preg_quote((string) $initialBoq->id, '/').'"[^>]*selected[^>]*>/',
            $contractCreate->getContent()
        );
        preg_match('/<input[^>]*id="contract-original-value"[^>]*>/', $contractCreate->getContent(), $contractValueInput);
        $this->assertNotEmpty($contractValueInput);
        $this->assertStringContainsString('value="2,357,000"', $contractValueInput[0]);
        $this->assertStringContainsString('readonly', $contractValueInput[0]);
        $this->get(route('construction.projects.show', $projectId))->assertOk()
            ->assertSee('إنشاء واعتماد العقد')
            ->assertSee(route('construction.projects.contracts.create', $projectId));
        $contract = ConstructionProject::findOrFail($projectId)->contracts()->create([
            'business_id' => $business->id, 'boq_version_id' => $initialBoq->id,
            'contract_number' => 'CTR-WORKSPACE', 'is_primary' => true,
            'status' => 'active', 'created_by' => $user->id,
        ]);
        $this->get(route('construction.projects.show', $projectId))->assertOk()
            ->assertSee('إنشاء واعتماد حصر أعمال')
            ->assertSee(route('construction.projects.contracts.show', [$projectId, $contract->id]));
        ConstructionProject::findOrFail($projectId)->measurements()->create([
            'business_id' => $business->id, 'number' => 'MSR-WORKSPACE',
            'measurement_date' => now()->toDateString(), 'status' => 'approved', 'created_by' => $user->id,
        ]);
        $this->get(route('construction.projects.show', $projectId))->assertOk()
            ->assertSee('إنشاء مستخلص مالك')
            ->assertSee(route('construction.projects.certificates.index', $projectId));
    }
}
