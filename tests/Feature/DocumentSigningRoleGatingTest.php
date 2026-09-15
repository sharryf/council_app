<?php

namespace Tests\Feature;

use App\Enums\DocumentSigningRole;
use App\Enums\DocumentStatus;
use App\Filament\Resources\DocumentSigning\Documents\DocumentResource;
use App\Filament\Resources\DocumentSigning\Documents\Pages\ListDocuments;
use App\Filament\Resources\DocumentSigning\Documents\Pages\ViewDocument;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

/**
 * Document Signing's own Admin/Editor/Signee/Viewer roles (see
 * App\Enums\DocumentSigningRole) — a user with none of these can still
 * reach the module (see ModuleAccessRestrictionTest for that separate
 * concern) but can't create, sign, or see anyone else's documents.
 */
class DocumentSigningRoleGatingTest extends TestCase
{
    use RefreshDatabase;

    private function makeDocument(User $uploader): Document
    {
        $pdf = new Fpdi();
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'Gating test document');

        $path = 'documents/uploads/gating-test.pdf';
        Storage::disk('local')->makeDirectory('documents/uploads');
        $pdf->Output('F', Storage::disk('local')->path($path));

        return Document::create([
            'title' => 'Gating Test Document',
            'file_path' => $path,
            'file_original_name' => 'gating-test.pdf',
            'uploaded_by' => $uploader->id,
            'signing_mode' => 'sequential',
            'status' => DocumentStatus::Draft,
        ]);
    }

    public function test_a_user_with_no_roles_cannot_reach_the_create_page(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(DocumentResource::getUrl('create'))
            ->assertForbidden();
    }

    public function test_the_editor_role_can_reach_the_create_page(): void
    {
        $this->seed(RoleSeeder::class);
        $editor = User::factory()->create();
        $editor->documentSigningRoles()->create(['role' => DocumentSigningRole::Editor]);

        $this->actingAs($editor)
            ->get(DocumentResource::getUrl('create'))
            ->assertSuccessful();
    }

    public function test_viewer_does_not_see_the_new_document_button_but_editor_does(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $viewer = User::factory()->create();
        $viewer->documentSigningRoles()->create(['role' => DocumentSigningRole::Viewer]);

        $this->actingAs($viewer);
        Livewire::test(ListDocuments::class)->assertActionHidden('create');

        $editor = User::factory()->create();
        $editor->documentSigningRoles()->create(['role' => DocumentSigningRole::Editor]);

        $this->actingAs($editor);
        Livewire::test(ListDocuments::class)->assertActionVisible('create');
    }

    public function test_editor_role_without_signee_cannot_sign_their_own_document(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $editor = User::factory()->create();
        $editor->documentSigningRoles()->create(['role' => DocumentSigningRole::Editor]);

        $document = $this->makeDocument($editor);
        $document->update(['status' => DocumentStatus::Pending]);
        $document->signers()->create(['user_id' => $editor->id, 'order' => 1, 'role_label' => 'Signer']);

        $this->actingAs($editor);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertActionHidden('sign');
    }

    public function test_signee_role_can_sign_a_document_they_are_listed_on(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = User::factory()->create();
        $uploader->assignRole('admin');

        $signee = User::factory()->create();
        $signee->documentSigningRoles()->create(['role' => DocumentSigningRole::Signee]);

        $document = $this->makeDocument($uploader);
        $document->update(['status' => DocumentStatus::Pending]);
        $document->signers()->create(['user_id' => $signee->id, 'order' => 1, 'role_label' => 'Signer']);

        $this->actingAs($signee);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertActionVisible('sign');
    }

    public function test_admin_holds_every_document_signing_role_without_explicit_rows(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue($admin->documentSigningRoles->isEmpty());
        $this->assertTrue($admin->hasDocumentSigningRole(DocumentSigningRole::Admin));
        $this->assertTrue($admin->hasDocumentSigningRole(DocumentSigningRole::Editor));
        $this->assertTrue($admin->hasDocumentSigningRole(DocumentSigningRole::Signee));
        $this->assertTrue($admin->hasDocumentSigningRole(DocumentSigningRole::Viewer));
    }

    public function test_a_user_without_the_viewer_role_only_sees_documents_they_are_involved_in(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $editor = User::factory()->create();
        $editor->documentSigningRoles()->create(['role' => DocumentSigningRole::Editor]);

        $someoneElse = User::factory()->create();
        $someoneElse->documentSigningRoles()->create(['role' => DocumentSigningRole::Editor]);

        $ownDocument = $this->makeDocument($editor);
        $othersDocument = $this->makeDocument($someoneElse);

        $this->actingAs($editor);

        Livewire::test(ListDocuments::class)
            ->assertCanSeeTableRecords([$ownDocument])
            ->assertCanNotSeeTableRecords([$othersDocument]);
    }

    public function test_the_viewer_role_sees_every_document(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $viewer = User::factory()->create();
        $viewer->documentSigningRoles()->create(['role' => DocumentSigningRole::Viewer]);

        $someoneElse = User::factory()->create();
        $document = $this->makeDocument($someoneElse);

        $this->actingAs($viewer);

        Livewire::test(ListDocuments::class)
            ->assertCanSeeTableRecords([$document]);
    }
}
