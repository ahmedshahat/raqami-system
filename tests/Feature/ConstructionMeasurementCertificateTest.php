<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Modules\Construction\Entities\ConstructionProject;
use Tests\TestCase;

class ConstructionMeasurementCertificateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_approved_measurement_can_feed_a_customer_certificate_with_correct_net_due(): void
    {
        $this->assertTrue(Schema::hasTable('construction_measurements'));
        $this->assertTrue(Schema::hasTable('construction_measurement_items'));
        $this->assertTrue(Schema::hasTable('construction_customer_certificates'));
        $this->assertTrue(Schema::hasTable('construction_customer_certificate_items'));

        $project = ConstructionProject::create([
            'business_id' => 900003,
            'code' => 'TEST-IPC-001',
            'name' => 'Certificate test project',
            'customer_id' => 900003,
            'status' => 'active',
            'created_by' => 900003,
        ]);
        $boq = $project->boqVersions()->create([
            'business_id' => 900003,
            'version_number' => 1,
            'name' => 'Approved BOQ',
            'status' => 'approved',
            'created_by' => 900003,
        ]);
        $boqItem = $boq->items()->create([
            'business_id' => 900003,
            'project_id' => $project->id,
            'row_type' => 'item',
            'code' => '01.001',
            'description' => 'Concrete work',
            'unit' => 'm3',
            'contract_quantity' => 100,
            'sales_unit_price' => 2500,
            'sales_total' => 250000,
            'item_kind' => 'standard',
        ]);
        $measurement = $project->measurements()->create([
            'business_id' => 900003,
            'number' => 'MSR-001',
            'measurement_date' => '2026-09-14',
            'status' => 'approved',
            'approved_by' => 900003,
            'approved_at' => now(),
            'created_by' => 900003,
        ]);
        $measurementItem = $measurement->items()->create([
            'business_id' => 900003,
            'project_id' => $project->id,
            'boq_item_id' => $boqItem->id,
            'executed_quantity' => 10,
            'approved_quantity' => 10,
        ]);
        $certificate = $project->customerCertificates()->create([
            'business_id' => 900003,
            'boq_version_id' => $boq->id,
            'number' => 'IPC-001',
            'certificate_date' => '2026-09-14',
            'status' => 'approved',
            'retention_percent' => 5,
            'advance_recovery_value' => 1000,
            'other_deductions_value' => 500,
            'tax_percent' => 14,
            'created_by' => 900003,
        ]);
        $certificate->items()->create([
            'business_id' => 900003,
            'project_id' => $project->id,
            'boq_item_id' => $boqItem->id,
            'previous_quantity' => 0,
            'submitted_quantity' => 10,
            'approved_quantity' => 10,
            'unit_price' => 2500,
            'previous_amount' => 0,
            'submitted_amount' => 25000,
            'approved_amount' => 25000,
        ]);
        $measurementItem->update(['certificate_id' => $certificate->id]);
        $certificate->recalculate(true);
        $certificate->refresh();

        $this->assertSame('25000.0000', $certificate->current_approved_gross);
        $this->assertSame('1250.0000', $certificate->retention_value);
        $this->assertSame('3500.0000', $certificate->tax_value);
        $this->assertSame('25750.0000', $certificate->net_due);
        $this->assertSame($certificate->id, $measurementItem->fresh()->certificate_id);
    }
}
