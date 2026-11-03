<?php

namespace Tests\Feature;

use App\Business;
use App\Contact;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ContactFormV2Test extends TestCase
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

    private function sessionData()
    {
        return [
            'user.business_id' => $this->business->id,
            'user.id' => $this->user->id,
            'business' => $this->business,
            'currency' => ['symbol' => 'SAR', 'thousand_separator' => ',', 'decimal_separator' => '.'],
        ];
    }

    private function ajax()
    {
        return $this->withSession($this->sessionData())->withHeaders(['X-Requested-With' => 'XMLHttpRequest']);
    }

    private function makeContact($extra = [])
    {
        return Contact::create(array_merge([
            'business_id' => $this->business->id,
            'type' => 'customer',
            'contact_type' => 'individual',
            'first_name' => 'Old',
            'name' => 'Old',
            'contact_id' => 'CFV2-'.uniqid(),
            'mobile' => '0500000000',
            'created_by' => $this->user->id,
            'contact_status' => 'active',
        ], $extra));
    }

    private function fieldNames($html)
    {
        preg_match_all('/name="([^"]+)"/', $html, $matches);

        return array_unique($matches[1]);
    }

    public function test_add_window_starts_on_individual_with_the_requested_type(): void
    {
        $html = $this->ajax()->get('/contacts/create?type=supplier')->assertOk()->assertViewIs('contact.form_v2')->getContent();

        $this->assertStringContainsString('id="contact_add_form"', $html);
        $this->assertStringContainsString(__('contact_form.title_new_supplier'), $html);
        $this->assertMatchesRegularExpression('/value="individual"\s+checked/', $html);
        $this->assertMatchesRegularExpression('/<option value="supplier" selected/', $html);
    }

    public function test_add_window_offers_only_supplier_and_customer(): void
    {
        $types = $this->ajax()->get('/contacts/create')->assertOk()->viewData('types');

        $this->assertSame(['customer', 'supplier'], collect(array_keys($types))->sort()->values()->all());
        $this->assertArrayNotHasKey('both', Contact::getContactTypes());
    }

    public function test_edit_window_offers_both_only_to_a_contact_saved_as_both(): void
    {
        $both = $this->makeContact(['type' => 'both']);
        $customer = $this->makeContact();

        $html = $this->ajax()->get('/contacts/'.$both->id.'/edit')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="both" selected/', $html);

        $this->assertArrayNotHasKey('both', $this->ajax()->get('/contacts/'.$customer->id.'/edit')->assertOk()->viewData('types'));
    }

    public function test_window_sends_every_field_the_store_and_update_read(): void
    {
        $names = $this->fieldNames($this->ajax()->get('/contacts/create?type=customer')->getContent());

        // حقول زاتكا (الرقم الضريبي والعنوان الوطني) لازم تفضل موجودة
        foreach (['type', 'contact_type_radio', 'supplier_business_name', 'first_name', 'tax_number', 'pay_term_number', 'pay_term_type', 'mobile', 'city', 'state', 'country', 'address_line_1', 'zip_code', 'contact_id', 'email', 'position', 'street_name', 'building_number', 'additional_number', 'credit_limit', 'opening_balance'] as $field) {
            $this->assertContains($field, $names, $field);
        }
    }

    public function test_add_window_leaves_out_the_rarely_used_fields(): void
    {
        $names = $this->fieldNames($this->ajax()->get('/contacts/create?type=customer')->getContent());

        foreach (['prefix', 'middle_name', 'last_name', 'alternate_number', 'landline', 'dob', 'land_mark', 'address_line_2', 'shipping_address'] as $field) {
            $this->assertNotContains($field, $names, $field);
        }
    }

    public function test_edit_window_keeps_rarely_used_fields_that_have_a_value(): void
    {
        $contact = $this->makeContact(['prefix' => 'Dr', 'middle_name' => 'Mid', 'landline' => '0123', 'land_mark' => 'Near park', 'address_line_2' => 'Floor 2', 'shipping_address' => 'Depot', 'dob' => '1990-01-05']);

        $names = $this->fieldNames($this->ajax()->get('/contacts/'.$contact->id.'/edit')->assertOk()->getContent());

        foreach (['prefix', 'middle_name', 'landline', 'land_mark', 'address_line_2', 'shipping_address', 'dob'] as $field) {
            $this->assertContains($field, $names, $field);
        }
        foreach (['last_name', 'alternate_number'] as $field) {
            $this->assertNotContains($field, $names, $field);
        }
    }

    public function test_type_field_is_hidden_only_when_the_window_opens_on_a_type(): void
    {
        $hidden = '/<div class="rr-field"\s+style="display: none;"\s*>\s*<label class="rr-field__label" for="contact_type"/';

        $this->assertMatchesRegularExpression($hidden, $this->ajax()->get('/contacts/create?type=supplier')->getContent());
        $this->assertDoesNotMatchRegularExpression($hidden, $this->ajax()->get('/contacts/create')->getContent());
        $this->assertDoesNotMatchRegularExpression($hidden, $this->ajax()->get('/contacts/'.$this->makeContact()->id.'/edit')->getContent());
    }

    public function test_edit_window_shows_saved_values_and_infers_a_missing_kind(): void
    {
        $contact = $this->makeContact(['contact_type' => null, 'first_name' => null, 'name' => '', 'supplier_business_name' => 'Acme & Sons', 'city' => 'Jeddah', 'credit_limit' => 500]);

        $html = $this->ajax()->get('/contacts/'.$contact->id.'/edit')->assertOk()->assertViewIs('contact.form_v2')->getContent();

        $this->assertStringContainsString('id="contact_edit_form"', $html);
        $this->assertStringContainsString('id="hidden_id" value="'.$contact->id.'"', $html);
        $this->assertStringContainsString('Acme &amp; Sons', $html);
        $this->assertStringContainsString('value="Jeddah"', $html);
        $this->assertMatchesRegularExpression('/value="business"\s+checked/', $html);
    }

    public function test_saved_custom_field_without_a_label_stays_visible(): void
    {
        $contact = $this->makeContact(['custom_field7' => 'kept value']);

        $this->ajax()->get('/contacts/'.$contact->id.'/edit')->assertOk()->assertSee('kept value');
    }

    public function test_update_from_the_window_keeps_fields_it_does_not_send(): void
    {
        $contact = $this->makeContact(['custom_field9' => 'untouched']);

        $this->ajax()->put('/contacts/'.$contact->id, [
            'type' => 'customer',
            'contact_type_radio' => 'business',
            'supplier_business_name' => 'New Co',
            'first_name' => 'Old',
            'mobile' => '0511111111',
            'contact_id' => $contact->contact_id,
            'opening_balance' => '0',
            'city' => 'Riyadh',
        ])->assertOk()->assertJson(['success' => true]);

        $fresh = $contact->fresh();
        $this->assertSame('business', $fresh->contact_type);
        $this->assertSame('New Co', $fresh->supplier_business_name);
        $this->assertSame('Riyadh', $fresh->city);
        $this->assertSame('untouched', $fresh->custom_field9);
    }

    public function test_add_window_saves_a_business_supplier(): void
    {
        $this->ajax()->post('/contacts', [
            'type' => 'supplier',
            'contact_type_radio' => 'business',
            'supplier_business_name' => 'CFV2 Supplies',
            'mobile' => '0522222222',
            'opening_balance' => '0',
            'pay_term_type' => 'days',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('contacts', [
            'business_id' => $this->business->id,
            'supplier_business_name' => 'CFV2 Supplies',
            'contact_type' => 'business',
            'type' => 'supplier',
        ]);
    }

    public function test_address_is_required_only_for_a_business_linked_to_zatca(): void
    {
        $contact = $this->makeContact(['type' => 'customer']);
        $url = '/contacts/'.$contact->id.'/edit';

        \App\BusinessLocation::where('business_id', $this->business->id)->update(['zatca_response' => null]);
        $this->ajax()->get($url)->assertOk()->assertSee('data-rr-zatca-active="0"', false);

        \App\BusinessLocation::where('business_id', $this->business->id)->limit(1)->update(['zatca_response' => '{"ok":1}']);
        $this->ajax()->get($url)->assertOk()->assertSee('data-rr-zatca-active="1"', false);

        // نشاط تاني مربوط بزاتكا مش بيأثر
        \App\BusinessLocation::where('business_id', $this->business->id)->update(['zatca_response' => '']);
        \App\BusinessLocation::where('business_id', '!=', $this->business->id)->limit(1)->update(['zatca_response' => '{"ok":1}']);
        $this->ajax()->get($url)->assertOk()->assertSee('data-rr-zatca-active="0"', false);
    }
}
