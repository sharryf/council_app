<?php

namespace Tests\Feature;

use App\Enums\BureauRole;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bureau is a separate Filament panel (not a Resource inside /admin) —
 * see App\Providers\Filament\BureauPanelProvider for why (fully
 * RTL/Dhivehi, vs. the rest of the app's English/LTR). This covers the
 * panel-level shell (auth gate, RTL) and the role vocabulary
 * (App\Enums\BureauRole, User::hasBureauRole()) — Agenda/Meeting/
 * Minutes features aren't built yet.
 */
class BureauPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unauthenticated_request_is_redirected_away_from_bureau(): void
    {
        $this->get('/bureau')->assertRedirect();
    }

    public function test_an_authenticated_user_can_reach_the_bureau_dashboard_and_it_renders_rtl(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/bureau');

        $response->assertSuccessful();
        $response->assertSee('dir="rtl"', false);
    }

    public function test_a_user_with_no_bureau_role_does_not_have_one(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasBureauRole(BureauRole::President));
        $this->assertSame([], $user->bureauRoleList());
    }

    public function test_an_assigned_bureau_role_is_recognized(): void
    {
        $user = User::factory()->create();
        $user->bureauRoles()->create(['role' => BureauRole::Councillor]);

        $this->assertTrue($user->hasBureauRole(BureauRole::Councillor));
        $this->assertFalse($user->hasBureauRole(BureauRole::President));
        $this->assertSame([BureauRole::Councillor], $user->bureauRoleList());
    }

    /**
     * `admin` is Users-page administration only (see
     * UserResource::canAccess()) — it grants nothing in any module,
     * Bureau included. An admin's own Bureau capability is assigned
     * explicitly, same as anyone else's.
     */
    public function test_the_system_admin_role_grants_no_bureau_role_by_itself(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        foreach (BureauRole::cases() as $role) {
            $this->assertFalse($admin->hasBureauRole($role));
        }

        $this->assertSame([], $admin->bureauRoleList());

        $admin->bureauRoles()->create(['role' => BureauRole::President]);
        $admin->refresh();

        $this->assertTrue($admin->hasBureauRole(BureauRole::President));
        $this->assertFalse($admin->hasBureauRole(BureauRole::Councillor));
    }
}
