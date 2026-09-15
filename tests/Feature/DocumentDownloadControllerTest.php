<?php

namespace Tests\Feature;

use App\Enums\DocumentSigningRole;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

/**
 * DocumentDownloadController::authorizeAccess() gates both the original
 * and signed download routes (also used to feed the Preview modal's
 * pdf.js viewer, via the same URLs). Originally only recognized the
 * uploader, a listed signer, or a system-wide `admin` — silently
 * missing this module's own Viewer role (App\Enums\DocumentSigningRole
 * — "see every uploaded document, not just their own"), even though
 * DocumentResource::getEloquentQuery() already gives a Viewer full
 * visibility in the list. A Viewer could see a document row but not
 * actually open or download it.
 */
class DocumentDownloadControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeDocumentWithFile(User $uploader, ?User $signer = null): Document
    {
        $pdf = new Fpdi();
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'x');

        $path = 'documents/uploads/download-test.pdf';
        Storage::disk('local')->makeDirectory('documents/uploads');
        $pdf->Output('F', Storage::disk('local')->path($path));

        $document = Document::create([
            'title' => 'Download Test Doc',
            'file_path' => $path,
            'file_original_name' => 'download-test.pdf',
            'uploaded_by' => $uploader->id,
            'signing_mode' => 'sequential',
            'status' => DocumentStatus::Pending,
        ]);

        if ($signer) {
            $document->signers()->create(['user_id' => $signer->id, 'order' => 1, 'role_label' => 'Signer']);
        }

        return $document;
    }

    public function test_the_uploader_can_download_the_original(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = User::factory()->create();
        $document = $this->makeDocumentWithFile($uploader);

        $this->actingAs($uploader)
            ->get(route('documents.download.original', $document))
            ->assertSuccessful();
    }

    public function test_a_listed_signer_can_download_the_original(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = User::factory()->create();
        $signer = User::factory()->create();
        $document = $this->makeDocumentWithFile($uploader, $signer);

        $this->actingAs($signer)
            ->get(route('documents.download.original', $document))
            ->assertSuccessful();
    }

    public function test_a_system_admin_can_download_the_original(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = User::factory()->create();
        $document = $this->makeDocumentWithFile($uploader);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('documents.download.original', $document))
            ->assertSuccessful();
    }

    /**
     * The actual bug fix: a user with only the module's Viewer role,
     * uninvolved with this specific document, can now download it —
     * matching what the role is documented to grant.
     */
    public function test_a_user_with_only_the_viewer_role_can_download_the_original(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = User::factory()->create();
        $document = $this->makeDocumentWithFile($uploader);

        $viewer = User::factory()->create();
        $viewer->documentSigningRoles()->create(['role' => DocumentSigningRole::Viewer]);

        $this->actingAs($viewer)
            ->get(route('documents.download.original', $document))
            ->assertSuccessful();
    }

    public function test_a_user_with_only_the_viewer_role_can_download_the_signed_copy(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = User::factory()->create();
        $document = $this->makeDocumentWithFile($uploader);

        $signedPath = "documents/{$document->id}/signed.pdf";
        Storage::disk('local')->put($signedPath, 'fake-signed-bytes');
        $document->update(['status' => DocumentStatus::Signed, 'signed_file_path' => $signedPath]);

        $viewer = User::factory()->create();
        $viewer->documentSigningRoles()->create(['role' => DocumentSigningRole::Viewer]);

        $this->actingAs($viewer)
            ->get(route('documents.download.signed', $document))
            ->assertSuccessful();
    }

    public function test_an_uninvolved_user_without_the_viewer_role_is_forbidden(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = User::factory()->create();
        $document = $this->makeDocumentWithFile($uploader);

        // Holds Editor (can create their own documents) but has no
        // connection to this particular one and no Viewer role.
        $outsider = User::factory()->create();
        $outsider->documentSigningRoles()->create(['role' => DocumentSigningRole::Editor]);

        $this->actingAs($outsider)
            ->get(route('documents.download.original', $document))
            ->assertForbidden();
    }
}
