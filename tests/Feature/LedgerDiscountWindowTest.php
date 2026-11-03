<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LedgerDiscountWindowTest extends TestCase
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

    protected function tearDown(): void
    {
        // الصلاحيات المتسجلة في الاختبار بتترجع مع الـ transaction، فمتفضلش في الكاش
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
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

    private function makeContact($type, $business_id = null)
    {
        return Contact::create([
            'business_id' => $business_id ?: $this->business->id,
            'type' => $type,
            'contact_type' => 'individual',
            'first_name' => 'Discount',
            'name' => 'Discount',
            'contact_id' => 'LDW-'.uniqid(),
            'mobile' => '0500000000',
            'created_by' => $this->user->id,
            'contact_status' => 'active',
        ]);
    }

    private function save($contact, $extra = [])
    {
        return $this->page()->postJson('/ledger-discount', array_merge([
            'contact_id' => $contact->id,
            'amount' => '25',
            'note' => 'window test',
            'date' => now()->format($this->business->date_format.' '.($this->business->time_format == 12 ? 'h:i A' : 'H:i')),
        ], $extra));
    }

    public function test_window_is_on_the_new_and_classic_contact_pages()
    {
        $contact = $this->makeContact('customer');

        foreach (['', '?classic=1'] as $query) {
            $response = $this->page()->get('/contacts/'.$contact->id.$query);
            $response->assertOk();
            $response->assertSee('id="add_discount_form"', false);
            $response->assertSee('id="discount_date"', false);
            $response->assertSee(__('ledger_discount.title_add'));
            $response->assertSee(__('ledger_discount.hint_customer'));
        }
    }

    public function test_customer_and_supplier_discounts_get_the_right_kind()
    {
        $customer = $this->makeContact('customer');
        $supplier = $this->makeContact('supplier');

        $this->save($customer)->assertJson(['success' => true]);
        $this->save($supplier)->assertJson(['success' => true]);

        $this->assertSame('sell_discount', Transaction::where('contact_id', $customer->id)->where('type', 'ledger_discount')->value('sub_type'));
        $this->assertSame('purchase_discount', Transaction::where('contact_id', $supplier->id)->where('type', 'ledger_discount')->value('sub_type'));
    }

    public function test_both_type_contact_chooses_the_kind()
    {
        $contact = $this->makeContact('both');

        $this->page()->get('/contacts/'.$contact->id)->assertSee('name="sub_type"', false);
        $this->save($contact, ['sub_type' => 'purchase_discount'])->assertJson(['success' => true]);

        $this->assertSame('purchase_discount', Transaction::where('contact_id', $contact->id)->where('type', 'ledger_discount')->value('sub_type'));
    }

    public function test_discount_cannot_be_added_to_a_contact_of_another_business()
    {
        $other = Business::where('id', '!=', $this->business->id)->firstOrFail();
        $contact = $this->makeContact('customer', $other->id);

        $this->save($contact)->assertJson(['success' => 0]);

        $this->assertSame(0, Transaction::where('contact_id', $contact->id)->where('type', 'ledger_discount')->count());
    }

    public function test_edit_window_opens_and_saves()
    {
        $contact = $this->makeContact('customer');
        $this->save($contact)->assertJson(['success' => true]);
        $discount = Transaction::where('contact_id', $contact->id)->where('type', 'ledger_discount')->firstOrFail();

        $response = $this->page()->get('/ledger-discount/'.$discount->id.'/edit');
        $response->assertOk();
        $response->assertSee('id="edit_discount_form"', false);
        $response->assertSee('id="edit_discount_date"', false);
        $response->assertSee(__('ledger_discount.title_edit'));
        $response->assertSee('window test');

        $this->page()->putJson('/ledger-discount/'.$discount->id, [
            'amount' => '40',
            'note' => 'changed',
            'date' => now()->format($this->business->date_format.' '.($this->business->time_format == 12 ? 'h:i A' : 'H:i')),
        ])->assertJson(['success' => true]);

        $this->assertEquals(40, $discount->fresh()->final_total);
        $this->assertSame('changed', $discount->fresh()->additional_notes);
    }

    private function actAsEmployee($permissions)
    {
        $employee = $this->user->replicate(['email', 'username'])->unsetRelations();
        $employee->username = 'ldw'.uniqid();
        $employee->email = $employee->username.'@test.local';
        $employee->save();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $employee->givePermissionTo($permissions);

        $this->user = $employee;
        $this->actingAs($employee);
    }

    private function makeDiscount($contact)
    {
        $this->save($contact)->assertJson(['success' => true]);

        return Transaction::where('contact_id', $contact->id)->where('type', 'ledger_discount')->firstOrFail();
    }

    private function discountPayload($amount)
    {
        return [
            'amount' => $amount,
            'note' => 'changed',
            'date' => now()->format($this->business->date_format.' '.($this->business->time_format == 12 ? 'h:i A' : 'H:i')),
        ];
    }

    public function test_employee_without_the_permission_is_refused_everywhere()
    {
        $contact = $this->makeContact('customer');
        $discount = $this->makeDiscount($contact);

        $this->actAsEmployee(['customer.view', 'supplier.view']);

        $this->save($contact)->assertForbidden();
        $this->page()->get('/ledger-discount/'.$discount->id.'/edit')->assertForbidden();
        $this->page()->putJson('/ledger-discount/'.$discount->id, $this->discountPayload('1'))->assertForbidden();
        $this->page()->deleteJson('/ledger-discount/'.$discount->id)->assertForbidden();

        $this->assertEquals(25, $discount->fresh()->final_total);
        $this->assertSame(1, Transaction::where('contact_id', $contact->id)->where('type', 'ledger_discount')->count());
    }

    public function test_employee_without_the_permission_sees_no_discount_controls()
    {
        $contact = $this->makeContact('customer');
        $this->makeDiscount($contact);

        $this->actAsEmployee(['customer.view', 'supplier.view']);

        foreach (['', '?classic=1'] as $query) {
            $response = $this->page()->get('/contacts/'.$contact->id.$query);
            $response->assertOk();
            $response->assertDontSee('data-target="#add_discount_modal"', false);
            $response->assertDontSee('id="add_discount_form"', false);
        }

        foreach (['format_1', 'format_3', 'format_1&ui=v2', 'format_3&ui=v2'] as $format) {
            $ledger = $this->page()->get('/contacts/ledger?contact_id='.$contact->id.'&format='.$format);
            $ledger->assertOk();
            $ledger->assertDontSee('delete_ledger_discount', false);
        }
    }

    public function test_employee_with_the_permission_can_add_edit_and_delete()
    {
        $contact = $this->makeContact('customer');

        $this->actAsEmployee(['customer.view', 'supplier.view', 'contact.ledger_discount']);

        $this->page()->get('/contacts/'.$contact->id)->assertSee('id="add_discount_form"', false);

        $discount = $this->makeDiscount($contact);

        foreach (['format_1', 'format_3', 'format_1&ui=v2', 'format_3&ui=v2'] as $format) {
            $this->page()->get('/contacts/ledger?contact_id='.$contact->id.'&format='.$format)
                ->assertSee('delete_ledger_discount', false);
        }

        $this->page()->get('/ledger-discount/'.$discount->id.'/edit')->assertOk();
        $this->page()->putJson('/ledger-discount/'.$discount->id, $this->discountPayload('40'))->assertJson(['success' => true]);
        $this->assertEquals(40, $discount->fresh()->final_total);

        $this->page()->deleteJson('/ledger-discount/'.$discount->id)->assertJson(['success' => true]);
        $this->assertSame(0, Transaction::where('contact_id', $contact->id)->where('type', 'ledger_discount')->count());
    }

    public function test_role_screens_offer_the_permission_and_saving_creates_it()
    {
        $this->page()->get('/roles/create')->assertSee('value="contact.ledger_discount"', false);

        Permission::where('name', 'contact.ledger_discount')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $name = 'LDW '.uniqid();
        $this->page()->post('/roles', ['name' => $name, 'permissions' => ['contact.ledger_discount']]);

        $role = Role::where('name', $name.'#'.$this->business->id)->firstOrFail();
        $this->assertTrue($role->hasPermissionTo('contact.ledger_discount'));

        $this->assertMatchesRegularExpression(
            '/<input[^>]*checked[^>]*value="contact\.ledger_discount"/',
            $this->page()->get('/roles/'.$role->id.'/edit')->getContent()
        );
    }
}
