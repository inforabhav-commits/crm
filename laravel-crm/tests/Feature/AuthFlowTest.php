<?php

namespace Tests\Feature;

use App\Models\User;
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
        User::create([
            'name' => 'CRM Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

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

        $this->get('/dashboard')
            ->assertRedirect('/login');
    }
}
