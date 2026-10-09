<?php

namespace Tests\Feature;

use App\Business;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Construction\Entities\ConstructionAccountingSetting;
use Modules\Construction\Entities\ConstructionProject;
use Tests\TestCase;

class ConstructionAccountingSettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_construction_accounts_are_created_linked_and_not_duplicated(): void
    {
        $project = ConstructionProject::query()->firstOrFail();
        $business = Business::findOrFail($project->business_id);
        $user = User::findOrFail($business->owner_id);
        $this->actingAs($user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

        $this->get(route('construction.settings.accounting.index'))
            ->assertOk()->assertSee(__('construction::lang.create_construction_accounts'));

        $before = \DB::table('accounting_accounts')->where('business_id', $business->id)->count();
        $this->post(route('construction.settings.accounting.create-accounts'))->assertRedirect();
        $settings = ConstructionAccountingSetting::where('business_id', $business->id)->firstOrFail();
        foreach (ConstructionAccountingSetting::ACCOUNT_FIELDS as $field) {
            $this->assertNotEmpty($settings->{$field}, $field.' was not linked');
        }
        $afterFirstRun = \DB::table('accounting_accounts')->where('business_id', $business->id)->count();
        $this->assertGreaterThanOrEqual($before, $afterFirstRun);

        $this->post(route('construction.settings.accounting.create-accounts'))->assertRedirect();
        $this->assertSame($afterFirstRun, \DB::table('accounting_accounts')->where('business_id', $business->id)->count());
    }
}
