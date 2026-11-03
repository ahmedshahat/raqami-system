<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Modules\Construction\Entities\ConstructionProject;
use Tests\TestCase;

class ConstructionProjectModelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_project_can_exist_before_a_contract_and_contract_links_to_approved_boq(): void
    {
        $this->assertTrue(Schema::hasTable('construction_projects'));
        $this->assertTrue(Schema::hasTable('construction_contracts'));
        $this->assertTrue(Schema::hasTable('construction_project_members'));
        $this->assertTrue(Schema::hasColumn('construction_contracts', 'boq_version_id'));
        $this->assertTrue(Schema::hasColumn('construction_contracts', 'sequence_number'));
        $this->assertTrue(Schema::hasColumn('construction_projects', 'next_contract_sequence'));

        $project = ConstructionProject::create([
            'business_id' => 900001,
            'code' => 'TEST-SCHOOL-001',
            'name' => 'Construction module test school',
            'customer_id' => 900001,
            'status' => 'draft',
            'created_by' => 900001,
        ]);

        $this->assertFalse($project->contracts()->exists());

        $boq = $project->boqVersions()->create([
            'business_id' => 900001,
            'version_number' => 1,
            'name' => 'Approved tender BOQ',
            'status' => 'approved',
            'sales_total' => 1250000,
            'created_by' => 900001,
        ]);
        $project->contracts()->create([
            'business_id' => 900001,
            'boq_version_id' => $boq->id,
            'contract_number' => 'CTR-001',
            'title' => 'School construction contract',
            'contract_type' => 'remeasurement',
            'original_value' => 1250000,
            'retention_percent' => 5,
            'is_primary' => true,
            'status' => 'draft',
            'created_by' => 900001,
        ]);
        $project->members()->attach(900001, [
            'business_id' => 900001,
            'access_level' => 'manage',
            'assigned_by' => 900001,
        ]);

        $this->assertSame('1250000.0000', $project->primaryContract()->first()->original_value);
        $this->assertSame($boq->id, $project->primaryContract()->first()->boqVersion->id);
        $this->assertSame('manage', DB::table('construction_project_members')
            ->where('project_id', $project->id)
            ->where('user_id', 900001)
            ->value('access_level'));
    }
}
