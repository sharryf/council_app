<?php

namespace Tests\Feature;

use App\Filament\Pages\EditProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Name/email are identity fields other parts of the app key off of, so
 * only a system-wide admin may change them from Profile — a non-admin
 * still sees their own current values there, just can't edit them (see
 * EditProfile::getNameFormComponent()/getEmailFormComponent()).
 */
class EditProfileIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_admin_cannot_change_their_own_name_or_email(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['name' => 'Original Name', 'email' => 'original@council.test']);
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm(['name' => 'Hacked Name', 'email' => 'hacked@council.test', 'currentPassword' => 'password'])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame('Original Name', $user->name);
        $this->assertSame('original@council.test', $user->email);
    }

    public function test_an_admin_can_change_their_own_name_and_email(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['name' => 'Original Admin', 'email' => 'admin-original@council.test']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(EditProfile::class)
            ->fillForm(['name' => 'New Admin Name', 'email' => 'admin-new@council.test', 'currentPassword' => 'password'])
            ->call('save')
            ->assertHasNoFormErrors();

        $admin->refresh();
        $this->assertSame('New Admin Name', $admin->name);
        $this->assertSame('admin-new@council.test', $admin->email);
    }
}
