<?php

namespace Tests\Feature;

use App\Models\{Customer, JustCallUserMapping, Permission, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDateAndQuickCallTest extends TestCase
{
    use RefreshDatabase;

    private function agent(array $permissions = ['customers.view', 'customers.edit', 'calls.initiate', 'export.crm']): User
    {
        $role = Role::create(['slug' => 'test-'.uniqid(), 'name' => 'Sales Agent '.uniqid()]);
        foreach ($permissions as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);
        return $user;
    }

    private function customer(User $user, string $date, string $name = 'Test Customer'): Customer
    {
        $customer = Customer::create(['name' => $name, 'owner_id' => $user->id, 'phone' => '+919876543210', 'is_active' => true]);
        $customer->forceFill(['created_at' => $date])->save();
        return $customer;
    }

    public function dateCases(): array
    {
        return [
            'from' => [['from_date' => '2026-08-02'], ['Middle', 'Late']],
            'to' => [['to_date' => '2026-08-02'], ['Early', 'Middle']],
            'range' => [['from_date' => '2026-08-02', 'to_date' => '2026-08-02'], ['Middle']],
        ];
    }

    /** @dataProvider dateCases */
    public function test_date_filters_and_exports(array $filters, array $expected): void
    {
        $user = $this->agent();
        $this->customer($user, '2026-08-01 00:00:00', 'Early');
        $this->customer($user, '2026-08-02 23:59:59', 'Middle');
        $this->customer($user, '2026-08-03 00:00:00', 'Late');
        $this->actingAs($user)->get(route('customers.index', $filters))->assertOk()
            ->assertViewHas('customers', fn ($rows) => $rows->pluck('name')->sort()->values()->all() === collect($expected)->sort()->values()->all());
        $csv = $this->get(route('import-export.export', ['resource' => 'customers'] + $filters))->assertOk()->streamedContent();
        foreach (['Early', 'Middle', 'Late'] as $name) {
            if (in_array($name, $expected)) $this->assertStringContainsString($name, $csv);
            else $this->assertStringNotContainsString($name, $csv);
        }
        $this->assertStringNotContainsString('9876543210', $csv);
    }

    public function test_invalid_ranges_are_rejected_for_listing_and_export(): void
    {
        $this->actingAs($this->agent());
        foreach ([route('customers.index'), route('import-export.export', 'customers')] as $url) {
            $this->getJson($url.'?from_date=2026-08-03&to_date=2026-08-01')->assertUnprocessable()->assertJsonValidationErrors('to_date');
            $this->getJson($url.'?from_date=not-a-date')->assertUnprocessable()->assertJsonValidationErrors('from_date');
        }
    }

    public function test_search_pagination_and_selected_dates_are_preserved(): void
    {
        $user = $this->agent();
        for ($i = 0; $i < 17; $i++) $this->customer($user, '2026-08-02', 'Find '.$i);
        $this->customer($user, '2026-08-01', 'Find old');
        $filters = ['search' => 'Find', 'from_date' => '2026-08-02', 'to_date' => '2026-08-02', 'status' => 'active'];
        $this->actingAs($user)->get(route('customers.index', $filters))->assertOk()
            ->assertViewHas('customers', fn ($rows) => $rows->total() === 17)
            ->assertSee('from_date=2026-08-02')->assertSee('to_date=2026-08-02');
        $this->get(route('customers.index', $filters + ['page' => 2]))->assertOk()
            ->assertViewHas('customers', fn ($rows) => $rows->count() === 2);
    }

    public function test_quick_call_is_before_view_and_only_id_is_required_without_leaking_numbers(): void
    {
        config(['justcall.enabled' => true]);
        $user = $this->agent();
        JustCallUserMapping::create(['user_id' => $user->id, 'justcall_user_id' => 'agent-test', 'is_active' => true]);
        $customer = $this->customer($user, '2026-08-02');
        $this->actingAs($user)->get(route('customers.index'))->assertOk()
            ->assertSeeInOrder(['Call Customer', '>View</a>', '>Edit</a>'], false)
            ->assertDontSee('9876543210', false);
        $this->get(route('customers.show', $customer))->assertOk()->assertDontSee('9876543210', false);
        $response = $this->postJson(route('customers.justcall.call', $customer))->assertUnprocessable()
            ->assertJsonPath('ok', false)->assertJsonPath('masked_number', 'XXXXXXXX3210');
        foreach (['9876543210', 'url"', 'destination', 'api_key', 'api_secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
        $this->assertArrayNotHasKey('number', $response->json());
        $this->post(route('customers.justcall.call', $customer))->assertRedirect()->assertSessionHas('error');
    }

    public function test_unauthorized_users_cannot_see_or_use_call_action(): void
    {
        config(['justcall.enabled' => true]);
        $user = $this->agent(['customers.view']);
        $customer = $this->customer($user, '2026-08-02');
        $this->actingAs($user)->get(route('customers.index'))->assertOk()->assertDontSee('Call Customer');
        $this->postJson(route('customers.justcall.call', $customer))->assertForbidden();
        $this->getJson(route('screen-pop.current'))->assertForbidden();
        $other = $this->agent();
        $this->actingAs($other)->postJson(route('customers.justcall.call', $customer))->assertForbidden();
    }

    public function test_call_icon_stays_visible_before_view_when_setup_is_missing(): void
    {
        $user = $this->agent();
        $customer = $this->customer($user, '2026-08-02');
        $this->actingAs($user);
        foreach ([false, true] as $enabled) {
            config(['justcall.enabled' => $enabled]);
            $this->get(route('customers.index'))->assertOk()
                ->assertSeeInOrder(['Call Customer', '>View</a>', '>Edit</a>'], false)
                ->assertDontSee('9876543210', false);
            $this->postJson(route('customers.justcall.call', $customer))->assertUnprocessable()
                ->assertJsonPath('ok', false)
                ->assertJsonPath('message', $enabled
                    ? 'Your CRM user is not mapped to an active JustCall user.'
                    : 'JustCall integration is disabled.');
        }
    }
}
