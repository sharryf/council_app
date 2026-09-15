<?php

namespace Tests\Feature;

use App\Enums\DocumentSigningRole;
use App\Filament\Resources\DocumentSigning\Documents\Pages\DocumentSigningOrganization;
use App\Models\DocumentSigningOrganization as DocumentSigningOrganizationModel;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Settings > Organization — Admin-only, manages the module's two named
 * stamp slots (see App\Models\DocumentSigningOrganization, a
 * single-row settings table with a stamp_path/stamp_path_2 pair).
 */
class DocumentSigningOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_admin_cannot_reach_the_organization_page(): void
    {
        $this->seed(RoleSeeder::class);
        $editor = User::factory()->create();
        $editor->documentSigningRoles()->create(['role' => DocumentSigningRole::Editor]);

        $this->actingAs($editor)
            ->get(DocumentSigningOrganization::getUrl())
            ->assertForbidden();
    }

    public function test_the_module_admin_role_can_upload_the_first_stamp(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $moduleAdmin = User::factory()->create();
        $moduleAdmin->documentSigningRoles()->create(['role' => DocumentSigningRole::Admin]);

        $this->actingAs($moduleAdmin);

        Livewire::test(DocumentSigningOrganization::class)
            ->fillForm(['stamp' => UploadedFile::fake()->image('stamp.png')])
            ->call('save')
            ->assertHasNoFormErrors();

        $organization = DocumentSigningOrganizationModel::current();

        $this->assertTrue($organization->hasStampInSlot(1));
        $this->assertFalse($organization->hasStampInSlot(2));
        Storage::disk('local')->assertExists($organization->stamp_path);
    }

    public function test_both_stamp_slots_can_be_uploaded_with_labels_independently(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $moduleAdmin = User::factory()->create();
        $moduleAdmin->documentSigningRoles()->create(['role' => DocumentSigningRole::Admin]);

        $this->actingAs($moduleAdmin);

        Livewire::test(DocumentSigningOrganization::class)
            ->fillForm([
                'stamp_label' => 'Council Stamp',
                'stamp' => UploadedFile::fake()->image('council.png'),
                'stamp_label_2' => 'Secretariat Stamp',
                'stamp_2' => UploadedFile::fake()->image('secretariat.png'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $organization = DocumentSigningOrganizationModel::current();

        $this->assertTrue($organization->hasStampInSlot(1));
        $this->assertTrue($organization->hasStampInSlot(2));
        $this->assertSame('Council Stamp', $organization->stampLabelForSlot(1));
        $this->assertSame('Secretariat Stamp', $organization->stampLabelForSlot(2));

        $available = $organization->availableStamps();
        $this->assertCount(2, $available);
    }

    public function test_a_stamp_label_can_be_updated_without_reuploading_the_image(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $existingPath = 'document-signing/organization/existing.png';
        Storage::disk('local')->put($existingPath, 'existing-bytes');
        DocumentSigningOrganizationModel::current()->update([
            'stamp_path' => $existingPath,
            'stamp_label' => 'Old Label',
        ]);

        $moduleAdmin = User::factory()->create();
        $moduleAdmin->documentSigningRoles()->create(['role' => DocumentSigningRole::Admin]);

        $this->actingAs($moduleAdmin);

        Livewire::test(DocumentSigningOrganization::class)
            ->fillForm(['stamp_label' => 'New Label'])
            ->call('save')
            ->assertHasNoFormErrors();

        $organization = DocumentSigningOrganizationModel::current();

        $this->assertSame('New Label', $organization->stampLabelForSlot(1));
        $this->assertSame($existingPath, $organization->stamp_path);
        Storage::disk('local')->assertExists($existingPath);
    }

    public function test_uploading_a_new_stamp_removes_the_old_file(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $moduleAdmin = User::factory()->create();
        $moduleAdmin->documentSigningRoles()->create(['role' => DocumentSigningRole::Admin]);

        $oldPath = 'document-signing/organization/old-stamp.png';
        Storage::disk('local')->put($oldPath, 'old-bytes');
        DocumentSigningOrganizationModel::current()->update(['stamp_path' => $oldPath]);

        $this->actingAs($moduleAdmin);

        Livewire::test(DocumentSigningOrganization::class)
            ->fillForm(['stamp' => UploadedFile::fake()->image('new-stamp.png')])
            ->call('save')
            ->assertHasNoFormErrors();

        Storage::disk('local')->assertMissing($oldPath);
    }
}
