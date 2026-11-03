<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Transaction;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Construction\Entities\ConstructionCustomerCertificate;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Support\CustomerCertificatesCollectionReport;
use Tests\TestCase;

class ConstructionCustomerCertificatesCollectionReportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_report_tracks_unbilled_partial_paid_and_receivable_aging(): void
    {
        $business = Business::whereHas('locations')->whereIn('id', Unit::select('business_id'))->firstOrFail();
        $user = User::findOrFail($business->owner_id);
        $customer = Contact::where('business_id', $business->id)->whereIn('type', ['customer', 'both'])->firstOrFail();
        $locationId = $business->locations()->value('id');
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $project = ConstructionProject::create([
            'business_id' => $business->id, 'code' => 'COLL-'.uniqid(), 'name' => 'Customer collection report test',
            'customer_id' => $customer->id, 'status' => 'active', 'created_by' => $user->id,
        ]);
        $boq = $project->boqVersions()->create([
            'business_id' => $business->id, 'version_number' => 1, 'name' => 'Collection report BOQ',
            'status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now(), 'created_by' => $user->id,
        ]);

        $unbilled = $this->certificate($business->id, $project->id, $boq->id, $user->id, 'UNBILLED', 1000, 50, 950, now()->subDays(10));
        $partial = $this->certificate($business->id, $project->id, $boq->id, $user->id, 'PARTIAL', 1200, 60, 1140, now()->subDays(45));
        $paid = $this->certificate($business->id, $project->id, $boq->id, $user->id, 'PAID', 800, 40, 760, now()->subDays(20));

        $partialInvoice = $this->invoice($business->id, $locationId, $customer->id, $user->id, 'INV-PARTIAL', 1140, 'partial', now()->subDays(45));
        $paidInvoice = $this->invoice($business->id, $locationId, $customer->id, $user->id, 'INV-PAID', 760, 'paid', now()->subDays(20));
        $partial->update(['invoice_transaction_id' => $partialInvoice->id]);
        $paid->update(['invoice_transaction_id' => $paidInvoice->id]);
        $this->payment($partialInvoice->id, 500, $user->id, now()->subDays(30));
        $this->payment($paidInvoice->id, 760, $user->id, now()->subDays(15));

        $report = app(CustomerCertificatesCollectionReport::class)->build($business->id, $project->id, $customer->id, null, now()->toDateString(), null);
        $this->assertEquals(3000, $report['summary']['approved_work']);
        $this->assertEquals(150, $report['summary']['retention']);
        $this->assertEquals(2850, $report['summary']['net_due']);
        $this->assertEquals(1900, $report['summary']['invoiced']);
        $this->assertEquals(1260, $report['summary']['collected']);
        $this->assertEquals(640, $report['summary']['outstanding']);
        $this->assertEquals(950, $report['summary']['unbilled']);
        $this->assertSame(1, $report['summary']['unbilled_count']);
        $this->assertEquals(640, $report['aging']['days_31_60']);
        $this->assertSame('unbilled', $report['rows']->firstWhere('certificate.id', $unbilled->id)['status']);
        $this->assertSame('partial', $report['rows']->firstWhere('certificate.id', $partial->id)['status']);
        $this->assertSame('paid', $report['rows']->firstWhere('certificate.id', $paid->id)['status']);

        $filtered = app(CustomerCertificatesCollectionReport::class)->build($business->id, $project->id, null, null, now()->toDateString(), 'partial');
        $this->assertCount(1, $filtered['rows']);
        $this->assertEquals(640, $filtered['summary']['outstanding']);

        $params = ['project_id' => $project->id, 'customer_id' => $customer->id, 'to_date' => now()->toDateString()];
        $response = $this->get(route('construction.reports.customer-certificates-collection.index', $params));
        $response->assertOk()->assertSee(__('construction::lang.customer_collection_report'))
            ->assertSee($unbilled->number)->assertSee($partialInvoice->invoice_no)
            ->assertSee(__('construction::lang.date_range_last_6_months'));
        $this->assertMatchesRegularExpression('/ct-date-range-start"\s+hidden/', $response->getContent());
        $customResponse = $this->get(route('construction.reports.customer-certificates-collection.index', $params + [
            'date_range' => 'custom', 'from_date' => now()->subMonths(2)->toDateString(),
        ]));
        $customResponse->assertOk();
        $this->assertDoesNotMatchRegularExpression('/ct-date-range-start"\s+hidden/', $customResponse->getContent());
        $this->assertMatchesRegularExpression('/value="custom"\s+selected(?:="selected")?/', $customResponse->getContent());
        $customResponse->assertSee('data-selected-range="custom"', false);
        $this->get(route('construction.reports.customer-certificates-collection.print', $params))->assertOk()
            ->assertSee(__('construction::lang.customer_collection_report_print_subtitle'))->assertSee($paidInvoice->invoice_no);
    }

    private function certificate(int $businessId, int $projectId, int $boqId, int $userId, string $suffix, float $gross, float $retention, float $net, $date): ConstructionCustomerCertificate
    {
        return ConstructionCustomerCertificate::create([
            'business_id' => $businessId, 'project_id' => $projectId, 'boq_version_id' => $boqId,
            'number' => 'COLL-'.$suffix.'-'.uniqid(), 'certificate_date' => $date->toDateString(), 'status' => 'approved',
            'current_approved_gross' => $gross, 'retention_percent' => 5, 'retention_value' => $retention,
            'net_due' => $net, 'created_by' => $userId, 'approved_by' => $userId, 'approved_at' => now(),
        ]);
    }

    private function invoice(int $businessId, int $locationId, int $customerId, int $userId, string $number, float $amount, string $paymentStatus, $date): Transaction
    {
        return Transaction::create([
            'business_id' => $businessId, 'location_id' => $locationId, 'type' => 'sell', 'status' => 'final',
            'payment_status' => $paymentStatus, 'transaction_date' => $date, 'invoice_no' => $number.'-'.uniqid(),
            'contact_id' => $customerId, 'final_total' => $amount, 'total_before_tax' => $amount, 'created_by' => $userId,
        ]);
    }

    private function payment(int $transactionId, float $amount, int $userId, $paidOn): void
    {
        DB::table('transaction_payments')->insert([
            'transaction_id' => $transactionId, 'amount' => $amount, 'method' => 'cash', 'is_return' => 0,
            'paid_on' => $paidOn, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
