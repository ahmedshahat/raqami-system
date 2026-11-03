<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\CustomerGroup;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerGroupScreenTest extends TestCase
{
    use DatabaseTransactions;

    private $business;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::whereHas('locations')->firstOrFail();
        $this->user = User::findOrFail($this->business->owner_id);
        $this->actingAs($this->user);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    private function page()
    {
        return $this->withSession([
            'user.business_id' => $this->business->id,
            'user.id' => $this->user->id,
            'business' => $this->business,
            'currency' => ['symbol' => 'SAR', 'thousand_separator' => ',', 'decimal_separator' => '.'],
        ]);
    }

    private function ajax($method, $url, $data = [])
    {
        return $this->page()->json($method, $url, $data, ['X-Requested-With' => 'XMLHttpRequest']);
    }

    private function makeGroup($extra = [])
    {
        return CustomerGroup::create(array_merge([
            'business_id' => $this->business->id,
            'name' => 'Group '.uniqid(),
            'amount' => 0,
            'price_calculation_type' => 'percentage',
            'created_by' => $this->user->id,
        ], $extra));
    }

    public function test_page_uses_the_new_design()
    {
        $response = $this->page()->get('/customer-group');

        $response->assertOk();
        $response->assertSee('rr-page', false);
        $response->assertSee('id="rr_cg_list"', false);
        $response->assertDontSee('customer_groups_table', false);
    }

    public function test_list_shows_pricing_and_customer_count()
    {
        $raise = $this->makeGroup(['name' => 'Raise Group', 'amount' => 5]);
        $this->makeGroup(['name' => 'Cut Group', 'amount' => -7]);
        Contact::create([
            'business_id' => $this->business->id,
            'type' => 'customer',
            'contact_type' => 'individual',
            'name' => 'In Group',
            'first_name' => 'In Group',
            'contact_id' => 'CGS-'.uniqid(),
            'mobile' => '0500000000',
            'created_by' => $this->user->id,
            'contact_status' => 'active',
            'customer_group_id' => $raise->id,
        ]);

        $html = $this->page()->get('/customer-group', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->getContent();

        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringContainsString('Raise Group', $html);
        $this->assertStringContainsString(__('customer_group.pricing_increase'), $html);
        $this->assertStringContainsString(__('customer_group.pricing_decrease'), $html);
        $this->assertStringContainsString(__('customer_group.customers_count', ['count' => 1]), $html);
        $this->assertStringContainsString('data-customers="1"', $html);
    }

    public function test_list_does_not_show_groups_of_another_business()
    {
        $other = Business::where('id', '!=', $this->business->id)->firstOrFail();
        $this->makeGroup(['business_id' => $other->id, 'name' => 'Foreign Group']);

        $this->page()->get('/customer-group')->assertDontSee('Foreign Group');
    }

    public function test_add_and_edit_windows_open()
    {
        $group = $this->makeGroup(['name' => 'Edit Me', 'amount' => 3]);

        $this->ajax('GET', '/customer-group/create')->assertOk()->assertSee(__('customer_group.add'));
        $this->ajax('GET', '/customer-group/'.$group->id.'/edit')->assertOk()
            ->assertSee(__('customer_group.edit'))
            ->assertSee('value="Edit Me"', false);
    }

    public function test_group_can_be_saved_updated_and_deleted()
    {
        $this->ajax('POST', '/customer-group', ['name' => 'Saved Group', 'price_calculation_type' => 'percentage', 'amount' => '-5'])
            ->assertJson(['success' => true]);
        $group = CustomerGroup::where('business_id', $this->business->id)->where('name', 'Saved Group')->firstOrFail();
        $this->assertEquals(-5, $group->amount);

        $this->ajax('PUT', '/customer-group/'.$group->id, ['name' => 'Renamed Group', 'price_calculation_type' => 'percentage', 'amount' => '10'])
            ->assertJson(['success' => true]);
        $this->assertEquals(10, $group->fresh()->amount);
        $this->assertSame('Renamed Group', $group->fresh()->name);

        $this->ajax('DELETE', '/customer-group/'.$group->id)->assertJson(['success' => true]);
        $this->assertNull(CustomerGroup::find($group->id));
    }

    public function test_edit_of_another_business_group_is_not_found()
    {
        $other = Business::where('id', '!=', $this->business->id)->firstOrFail();
        $group = $this->makeGroup(['business_id' => $other->id]);

        $this->ajax('GET', '/customer-group/'.$group->id.'/edit')->assertNotFound();
    }
}
