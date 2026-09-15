<?php

namespace Tests\Feature;

use App\Filament\Pages\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Profile > Signature — each user can save two signatures (see
 * App\Models\User's signature_path/signature_path_2 pair) and choose
 * which one is their default, same two-slot pattern as the module's
 * organization stamps (see DocumentSigningOrganizationTest).
 */
class EditProfileSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_save_a_signature_into_the_first_slot(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'signature_source_1' => 'uploaded',
                'uploaded_signature_1' => UploadedFile::fake()->image('signature.png'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertTrue($user->hasSignatureInSlot(1));
        $this->assertFalse($user->hasSignatureInSlot(2));
        $this->assertSame($user->signature_path, $user->defaultSignaturePath());
        Storage::disk('local')->assertExists($user->signature_path);
    }

    public function test_both_slots_can_be_saved_and_the_second_set_as_default(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'signature_source_1' => 'uploaded',
                'uploaded_signature_1' => UploadedFile::fake()->image('formal.png'),
                'signature_source_2' => 'uploaded',
                'uploaded_signature_2' => UploadedFile::fake()->image('initials.png'),
                'default_signature_slot' => 2,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertTrue($user->hasSignatureInSlot(1));
        $this->assertTrue($user->hasSignatureInSlot(2));
        $this->assertSame(2, $user->default_signature_slot);
        $this->assertSame($user->signature_path_2, $user->defaultSignaturePath());
    }

    public function test_default_slot_falls_back_when_the_chosen_default_is_empty(): void
    {
        Storage::fake('local');
        $path = 'signatures/users/1-existing.png';
        Storage::disk('local')->put($path, 'existing-bytes');

        $user = User::factory()->create(['signature_path' => $path, 'default_signature_slot' => 2]);

        $this->assertSame($path, $user->defaultSignaturePath());
    }

    public function test_removing_a_signature_deletes_its_file_and_clears_the_slot(): void
    {
        Storage::fake('local');
        $path = 'signatures/users/1-old.png';
        Storage::disk('local')->put($path, 'old-bytes');

        $user = User::factory()->create(['signature_path' => $path]);
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm(['remove_signature_1' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertFalse($user->hasSignatureInSlot(1));
        Storage::disk('local')->assertMissing($path);
    }

    public function test_replacing_a_signature_removes_the_old_file(): void
    {
        Storage::fake('local');
        $oldPath = 'signatures/users/1-old.png';
        Storage::disk('local')->put($oldPath, 'old-bytes');

        $user = User::factory()->create(['signature_path' => $oldPath]);
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'signature_source_1' => 'uploaded',
                'uploaded_signature_1' => UploadedFile::fake()->image('new.png'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($user->signature_path);
        $this->assertNotSame($oldPath, $user->signature_path);
    }

    public function test_a_default_signature_slot_pointing_at_a_removed_signature_cannot_be_saved(): void
    {
        Storage::fake('local');
        $path1 = 'signatures/users/1-one.png';
        $path2 = 'signatures/users/1-two.png';
        Storage::disk('local')->put($path1, 'one');
        Storage::disk('local')->put($path2, 'two');

        $user = User::factory()->create([
            'signature_path' => $path1,
            'signature_path_2' => $path2,
            'default_signature_slot' => 2,
        ]);
        $this->actingAs($user);

        // Removing slot 2 while it's still the chosen default — the
        // save should fall the default back to slot 1 rather than
        // persist a default pointing at nothing.
        Livewire::test(EditProfile::class)
            ->fillForm(['remove_signature_2' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertFalse($user->hasSignatureInSlot(2));
        $this->assertSame(1, $user->default_signature_slot);
        $this->assertSame($path1, $user->defaultSignaturePath());
    }
}
