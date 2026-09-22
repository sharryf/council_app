<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * EditUser's two self-protection guards: you can never delete or
 * deactivate the account you're currently signed in as, since
 * canAccessPanel() gates login on is_active alone — doing either to
 * yourself would lock you out on the very next request.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_system_admin_cannot_deactivate_their_own_account(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_a_system_admin_can_deactivate_another_user(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $other = User::factory()->create(['is_active' => true]);
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $other->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($other->fresh()->is_active);
    }

    public function test_a_no_op_save_does_not_strip_the_admins_own_admin_role_or_active_flag(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $admin->refresh();
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->hasRole('admin'));
    }

    /**
     * The actual cause of a real, hard-to-diagnose bug: a browser that
     * has an account's own credentials saved will silently autofill the
     * password field when that account edits itself (nothing else on
     * the form matches a saved credential the same way) — resaving,
     * even with no visible change, then re-hashed that same password,
     * which quietly logged the editor out on their very next request
     * (AuthenticateSession comparing session against the now-different
     * hash). UserForm::configure() guards this two ways: autocomplete
     * hints that most browsers respect, and this — the real fix — which
     * treats a resubmission of the account's own current password as
     * untouched rather than writing a needless new hash of it.
     */
    public function test_resubmitting_the_same_password_does_not_change_its_hash(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $originalHash = $admin->password;

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->fillForm(['password' => 'password'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($originalHash, $admin->fresh()->password);
    }

    public function test_submitting_a_genuinely_new_password_does_change_its_hash(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $originalHash = $admin->password;

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->fillForm(['password' => 'a-genuinely-new-password'])
            ->call('save')
            ->assertHasNoFormErrors();

        $newHash = $admin->fresh()->password;
        $this->assertNotSame($originalHash, $newHash);
        $this->assertTrue(Hash::check('a-genuinely-new-password', $newHash));
    }

    public function test_a_system_admin_cannot_delete_their_own_account(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->assertActionHidden('delete');

        $this->assertNotNull($admin->fresh());
    }
}
