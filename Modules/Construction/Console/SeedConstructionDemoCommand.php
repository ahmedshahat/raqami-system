<?php

namespace Modules\Construction\Console;

use App\Business;
use App\Contact;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Construction\Entities\ConstructionProject;
use Modules\Construction\Entities\ConstructionBoqCostAllocation;
use Modules\Construction\Entities\ConstructionBoqItem;
use Modules\Construction\Entities\ConstructionBoqVersion;
use Modules\Construction\Entities\ConstructionCostCode;
use Modules\Construction\Entities\ConstructionMeasurement;
use Modules\Construction\Entities\ConstructionCustomerCertificate;

class SeedConstructionDemoCommand extends Command
{
    protected $signature = 'construction:seed-demo
                            {business : Business ID that will own the demo project}
                            {--force : Allow seeding outside the local environment}';

    protected $description = 'Create or refresh the Construction demo project, contract, BOQ and cost codes';

    public function handle(): int
    {
        if (! app()->environment('local') && ! $this->option('force')) {
            $this->error('Demo data is restricted to the local environment.');

            return self::FAILURE;
        }

        $businessId = (int) $this->argument('business');
        $business = Business::query()->find($businessId);
        if (! $business) {
            $this->error("Business {$businessId} was not found.");

            return self::FAILURE;
        }

        $user = User::query()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->orderByRaw('id = ? desc', [$business->owner_id])
            ->orderBy('id')
            ->first();
        if (! $user) {
            $this->error("Business {$businessId} has no active user.");

            return self::FAILURE;
        }

        $project = DB::transaction(function () use ($businessId, $user) {
            $customer = Contact::query()->updateOrCreate(
                [
                    'business_id' => $businessId,
                    'supplier_business_name' => 'شركة النور للتنمية التعليمية [تجريبي]',
                ],
                [
                    'type' => 'customer',
                    'name' => 'إدارة التعاقدات - شركة النور',
                    'mobile' => '01000000001',
                    'city' => 'القاهرة الجديدة',
                    'country' => 'مصر',
                    'created_by' => $user->id,
                    'contact_status' => 'active',
                ]
            );
            $consultant = Contact::query()->updateOrCreate(
                [
                    'business_id' => $businessId,
                    'supplier_business_name' => 'مكتب الأفق للاستشارات الهندسية [تجريبي]',
                ],
                [
                    'type' => 'customer',
                    'name' => 'م. أحمد الاستشاري',
                    'mobile' => '01000000002',
                    'city' => 'القاهرة الجديدة',
                    'country' => 'مصر',
                    'created_by' => $user->id,
                    'contact_status' => 'active',
                ]
            );

            $project = ConstructionProject::withTrashed()
                ->where('business_id', $businessId)
                ->where('code', 'DEMO-SCHOOL-001')
                ->first() ?? new ConstructionProject();
            $project->fill([
                'business_id' => $businessId,
                'code' => 'DEMO-SCHOOL-001',
                'name' => 'إنشاء مدرسة النور الدولية [مشروع تجريبي]',
                'customer_id' => $customer->id,
                'consultant_contact_id' => $consultant->id,
                'consultant_name' => 'مكتب الأفق للاستشارات الهندسية',
                'manager_id' => $user->id,
                'location' => 'القاهرة الجديدة - التجمع الخامس',
                'start_date' => '2026-01-01',
                'end_date' => '2027-06-30',
                'status' => 'active',
                'description' => 'إنشاء مدرسة متكاملة تشمل المبنى الرئيسي والملاعب والسور والأعمال الكهروميكانيكية.',
                'created_by' => $user->id,
                'deleted_at' => null,
            ]);
            $project->save();

            $contract = $project->contracts()->updateOrCreate(
                ['is_primary' => true],
                [
                    'business_id' => $businessId,
                    'contract_number' => 'CONT-DEMO-2026-001',
                    'title' => 'عقد إنشاء مدرسة النور الدولية',
                    'contract_type' => 'remeasurement',
                    'signed_at' => '2025-12-15',
                    'original_value' => 12500000,
                    'advance_payment_value' => 1250000,
                    'retention_percent' => 5,
                    'performance_bond_value' => 625000,
                    'payment_terms_days' => 30,
                    'warranty_months' => 12,
                    'status' => 'active',
                    'activated_at' => now(),
                    'activated_by' => $user->id,
                    'notes' => 'مستخلص شهري طبقًا للكميات المنفذة والمعتمدة من الاستشاري.',
                    'created_by' => $user->id,
                ]
            );

            $project->members()->syncWithoutDetaching([
                $user->id => [
                    'business_id' => $businessId,
                    'access_level' => 'manage',
                    'assigned_by' => $user->id,
                ],
            ]);

            $costCodes = collect([
                ['MAT-CON', 'خرسانة وحديد تسليح', 'material', 10],
                ['LAB-CON', 'عمالة أعمال الخرسانة', 'labor', 20],
                ['EQ-CON', 'معدات أعمال الخرسانة', 'equipment', 30],
                ['MAT-BRK', 'طوب ومونة المباني', 'material', 40],
                ['SUB-BRK', 'مقاول باطن أعمال المباني', 'subcontract', 50],
                ['MAT-ELC', 'خامات الأعمال الكهربائية', 'material', 60],
                ['LAB-ELC', 'عمالة الأعمال الكهربائية', 'labor', 70],
                ['OH-SITE', 'مصروفات الموقع غير المباشرة', 'overhead', 80],
            ])->mapWithKeys(function (array $row) use ($businessId) {
                $code = ConstructionCostCode::query()->updateOrCreate(
                    ['business_id' => $businessId, 'code' => $row[0]],
                    ['name' => $row[1], 'category' => $row[2], 'sort_order' => $row[3], 'is_active' => true]
                );

                return [$row[0] => $code];
            });

            $boq = ConstructionBoqVersion::query()->firstOrCreate(
                ['business_id' => $businessId, 'project_id' => $project->id, 'version_number' => 1],
                ['name' => 'بنود المشروع الأساسية - مشروع المدرسة', 'status' => 'draft', 'created_by' => $user->id]
            );

            if ($boq->isEditable()) {
                $concreteSection = $this->boqItem($boq, $project, null, 'section', '01', 'الأعمال الخرسانية', null, 0, 0, 10);
                $foundations = $this->boqItem($boq, $project, $concreteSection->id, 'item', '01.001', 'خرسانة مسلحة للأساسات', 'م3', 450, 4200, 20);
                $frame = $this->boqItem($boq, $project, $concreteSection->id, 'item', '01.002', 'خرسانة مسلحة للأعمدة والأسقف', 'م3', 780, 4800, 30);
                $masonrySection = $this->boqItem($boq, $project, null, 'section', '02', 'الأعمال المعمارية', null, 0, 0, 40);
                $masonry = $this->boqItem($boq, $project, $masonrySection->id, 'item', '02.001', 'مباني طوب أسمنتي شامل المونة', 'م2', 5200, 520, 50);
                $electricalSection = $this->boqItem($boq, $project, null, 'section', '03', 'الأعمال الكهربائية', null, 0, 0, 60);
                $electrical = $this->boqItem($boq, $project, $electricalSection->id, 'item', '03.001', 'توريد وتركيب شبكة الكهرباء الداخلية', 'مقطوعية', 1, 1900000, 70);

                $this->allocation($boq, $foundations, $costCodes['MAT-CON'], 1120000);
                $this->allocation($boq, $foundations, $costCodes['LAB-CON'], 260000);
                $this->allocation($boq, $foundations, $costCodes['EQ-CON'], 85000);
                $this->allocation($boq, $frame, $costCodes['MAT-CON'], 2180000);
                $this->allocation($boq, $frame, $costCodes['LAB-CON'], 520000);
                $this->allocation($boq, $frame, $costCodes['EQ-CON'], 160000);
                $this->allocation($boq, $masonry, $costCodes['MAT-BRK'], 1040000);
                $this->allocation($boq, $masonry, $costCodes['SUB-BRK'], 620000);
                $this->allocation($boq, $electrical, $costCodes['MAT-ELC'], 980000);
                $this->allocation($boq, $electrical, $costCodes['LAB-ELC'], 310000);
                $this->allocation($boq, $electrical, $costCodes['OH-SITE'], 145000);
                $boq->refreshTotals();
                $boq->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
            }
            $contract->update(['boq_version_id' => $boq->id, 'original_value' => $boq->sales_total]);

            $measurement = ConstructionMeasurement::query()->firstOrCreate(
                ['business_id' => $businessId, 'project_id' => $project->id, 'number' => 'MSR-DEMO-001'],
                [
                    'measurement_date' => '2026-02-28',
                    'period_from' => '2026-02-01',
                    'period_to' => '2026-02-28',
                    'status' => 'approved',
                    'notes' => 'حصر تجريبي للأعمال المنفذة خلال شهر فبراير.',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'created_by' => $user->id,
                ]
            );

            if ($measurement->status === 'approved' && $boq->status === 'approved') {
                foreach ([
                    '01.001' => 45,
                    '01.002' => 78,
                    '02.001' => 520,
                    '03.001' => 0.15,
                ] as $itemCode => $quantity) {
                    $boqItem = $boq->items()->where('code', $itemCode)->first();
                    if ($boqItem) {
                        $measurement->items()->updateOrCreate(
                            ['boq_item_id' => $boqItem->id, 'structure_id' => null],
                            [
                                'business_id' => $businessId,
                                'project_id' => $project->id,
                                'executed_quantity' => $quantity,
                                'approved_quantity' => $quantity,
                                'notes' => 'كمية معتمدة طبقًا لمحضر الحصر التجريبي.',
                            ]
                        );
                    }
                }
            }

            $certificate = ConstructionCustomerCertificate::query()->firstOrCreate(
                ['business_id' => $businessId, 'project_id' => $project->id, 'number' => 'IPC-DEMO-001'],
                [
                    'boq_version_id' => $boq->id,
                    'certificate_date' => '2026-02-28',
                    'period_from' => '2026-02-01',
                    'period_to' => '2026-02-28',
                    'status' => 'draft',
                    'retention_percent' => 5,
                    'advance_recovery_value' => 100000,
                    'other_deductions_value' => 25000,
                    'tax_percent' => 14,
                    'notes' => 'مستخلص مالك تجريبي ناتج من الحصر المعتمد.',
                    'created_by' => $user->id,
                ]
            );

            if ($certificate->status === 'draft') {
                foreach ($measurement->items()->with('boqItem')->get() as $measurementItem) {
                    $quantity = (float) $measurementItem->approved_quantity;
                    $unitPrice = (float) $measurementItem->boqItem->sales_unit_price;
                    $certificate->items()->updateOrCreate(
                        ['boq_item_id' => $measurementItem->boq_item_id],
                        [
                            'business_id' => $businessId,
                            'project_id' => $project->id,
                            'previous_quantity' => 0,
                            'submitted_quantity' => $quantity,
                            'approved_quantity' => 0,
                            'unit_price' => $unitPrice,
                            'previous_amount' => 0,
                            'submitted_amount' => round($quantity * $unitPrice, 4),
                            'approved_amount' => 0,
                        ]
                    );
                    $measurementItem->update(['certificate_id' => $certificate->id]);
                }
                $certificate->recalculate(false);
            }

            return $project;
        });

        $this->info("Demo construction project is ready: {$project->code} (ID {$project->id}).");

        return self::SUCCESS;
    }

    private function boqItem(
        ConstructionBoqVersion $boq,
        ConstructionProject $project,
        ?int $parentId,
        string $rowType,
        string $code,
        string $description,
        ?string $unit,
        float $quantity,
        float $unitPrice,
        int $sortOrder
    ): ConstructionBoqItem {
        return $boq->items()->updateOrCreate(
            ['code' => $code],
            [
                'business_id' => $project->business_id,
                'project_id' => $project->id,
                'parent_id' => $parentId,
                'row_type' => $rowType,
                'description' => $description,
                'unit' => $unit,
                'contract_quantity' => $quantity,
                'sales_unit_price' => $unitPrice,
                'sales_total' => round($quantity * $unitPrice, 4),
                'item_kind' => 'standard',
                'sort_order' => $sortOrder,
            ]
        );
    }

    private function allocation(
        ConstructionBoqVersion $boq,
        ConstructionBoqItem $item,
        ConstructionCostCode $costCode,
        float $estimatedCost
    ): void {
        ConstructionBoqCostAllocation::query()->updateOrCreate(
            ['boq_item_id' => $item->id, 'cost_code_id' => $costCode->id],
            [
                'business_id' => $boq->business_id,
                'project_id' => $boq->project_id,
                'boq_version_id' => $boq->id,
                'estimated_cost' => $estimatedCost,
            ]
        );
    }
}
