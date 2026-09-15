<?php

namespace Tests\Feature;

use App\Enums\DocumentSigningRole;
use App\Enums\DocumentStatus;
use App\Filament\Resources\DocumentSigning\Documents\Pages\ListDocuments;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

/**
 * Once a document leaves Draft it's part of the signing audit trail, so
 * DocumentResource::canDelete() only allows an untouched Draft, deleted
 * by its own uploader. deleteBulkAction()'s authorizeIndividualRecords()
 * is what actually enforces this — it silently excludes non-Draft
 * records from the delete regardless of what's selected. The button
 * itself can't be hidden based on whether the selection is deletable:
 * that was tried and reverted (see git history) because Filament's row
 * checkboxes and the bulk-action button share the same visibility
 * check, so gating it on selection state either hid the checkboxes
 * before anything was selected, or wiped out the whole bulk toolbar
 * the moment an unauthorized-only selection triggered a real
 * Livewire round-trip. The button stays visible whenever anything is
 * selected — same as Filament's default — and relies on the
 * per-record authorization plus Filament's own "you don't have
 * permission to delete :count" notification for the records it skips.
 */
class DocumentDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function realPdfPath(string $relativePath): string
    {
        $pdf = new Fpdi();
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'x');

        Storage::disk('local')->makeDirectory(dirname($relativePath));
        $pdf->Output('F', Storage::disk('local')->path($relativePath));

        return $relativePath;
    }

    private function makeEditor(): User
    {
        $user = User::factory()->create();
        $user->documentSigningRoles()->create(['role' => DocumentSigningRole::Editor]);

        return $user;
    }

    /**
     * With both a Draft and a Signed document selected, the button is
     * visible (the Draft makes it deletable at all) — but
     * authorizeIndividualRecords() must still filter the Signed one out
     * of the actual delete, not just the button-visibility check.
     */
    public function test_a_signed_document_survives_a_bulk_delete_even_when_selected_alongside_a_deletable_draft(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $editor = $this->makeEditor();
        $this->actingAs($editor);

        $draft = Document::create([
            'title' => 'Draft Doc',
            'file_path' => $this->realPdfPath('documents/uploads/draft2.pdf'),
            'file_original_name' => 'draft2.pdf',
            'uploaded_by' => $editor->id,
            'signing_mode' => 'sequential',
            'status' => DocumentStatus::Draft,
        ]);

        $signed = Document::create([
            'title' => 'Signed Doc',
            'file_path' => $this->realPdfPath('documents/uploads/signed.pdf'),
            'file_original_name' => 'signed.pdf',
            'uploaded_by' => $editor->id,
            'signing_mode' => 'sequential',
            'status' => DocumentStatus::Signed,
        ]);

        Livewire::test(ListDocuments::class)
            ->callTableBulkAction('delete', [$draft->id, $signed->id]);

        $this->assertDatabaseMissing('documents', ['id' => $draft->id]);
        $this->assertDatabaseHas('documents', ['id' => $signed->id]);
    }

    public function test_a_draft_document_can_be_bulk_deleted_by_its_uploader(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $editor = $this->makeEditor();
        $this->actingAs($editor);

        $document = Document::create([
            'title' => 'Draft Doc',
            'file_path' => $this->realPdfPath('documents/uploads/draft.pdf'),
            'file_original_name' => 'draft.pdf',
            'uploaded_by' => $editor->id,
            'signing_mode' => 'sequential',
            'status' => DocumentStatus::Draft,
        ]);

        Livewire::test(ListDocuments::class)
            ->callTableBulkAction('delete', [$document->id]);

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    }

    public function test_a_signed_document_alone_survives_a_bulk_delete_attempt(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $editor = $this->makeEditor();
        $this->actingAs($editor);

        $document = Document::create([
            'title' => 'Signed Doc',
            'file_path' => $this->realPdfPath('documents/uploads/signed-alone.pdf'),
            'file_original_name' => 'signed-alone.pdf',
            'uploaded_by' => $editor->id,
            'signing_mode' => 'sequential',
            'status' => DocumentStatus::Signed,
        ]);

        Livewire::test(ListDocuments::class)
            ->callTableBulkAction('delete', [$document->id]);

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }
}
