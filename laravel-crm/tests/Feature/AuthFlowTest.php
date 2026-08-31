<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard_to_login()
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_user_can_login_open_dashboard_and_logout()
    {
        $role = Role::create(['name' => 'Agent', 'slug' => 'agent']);
        $permission = Permission::create(['name' => 'View leads', 'slug' => 'leads.view']);
        $role->permissions()->attach($permission);

        $user = User::create([
            'name' => 'CRM Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->roles()->attach($role);

        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Daily Work Queue');

        $this->get('/module/leads')
            ->assertOk()
            ->assertSee('Coming in next module');

        $this->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();

        $this->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_logout_invalidates_session_and_regenerates_csrf_token()
    {
        $role = Role::create(['name' => 'Agent', 'slug' => 'agent']);
        $user = User::create([
            'name' => 'CRM User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->roles()->attach($role);

        $this->actingAs($user);
        $oldSessionId = session()->getId();
        $oldToken = csrf_token();

        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertNotSame($oldToken, csrf_token());
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_expired_logout_csrf_redirects_to_login_without_419()
    {
        $user = User::create([
            'name' => 'Expired Session User',
            'email' => 'expired@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->actingAs($user);
        $request = Request::create('/logout', 'POST');
        $request->setLaravelSession(app('session.store'));
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);

        $response = app(\App\Exceptions\Handler::class)->render($request, new TokenMismatchException());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/login', $response->headers->get('Location'));
        $this->assertGuest();
    }

    public function test_authenticated_pages_are_not_browser_cached()
    {
        $role = Role::create(['name' => 'Agent', 'slug' => 'agent']);
        $permission = Permission::create(['name' => 'View leads', 'slug' => 'leads.view']);
        $role->permissions()->attach($permission);
        $user = User::create([
            'name' => 'Cache Test User',
            'email' => 'cache@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => 'limited@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post('/login', [
            'email' => 'limited@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
