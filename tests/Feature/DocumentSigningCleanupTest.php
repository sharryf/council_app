<?php

namespace Tests\Feature;

use App\Enums\DocumentSigningRole;
use App\Enums\DocumentStatus;
use App\Filament\Resources\DocumentSigning\Documents\Pages\DocumentSigningCleanup;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

/**
 * Settings → Delete — gated to this module's own Admin role (same as
 * Roles/Organization, unlike the reverted permanent-delete bulk action
 * which used the system-wide `admin`). Only Signed/Rejected/Voided
 * documents older than the chosen retention period are eligible —
 * never Draft (the ordinary per-record delete already covers that) or
 * Pending (still active) — and the retention period can never go below
 * 365 days, enforced both by the field's own validation and again in
 * the query itself.
 */
class DocumentSigningCleanupTest extends TestCase
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

    private function makeModuleAdmin(): User
    {
        $user = User::factory()->create();
        $user->documentSigningRoles()->create(['role' => DocumentSigningRole::Admin]);

        return $user;
    }

    private function makeDocument(string $status, \DateTimeInterface $createdAt, ?string $pathSuffix = null): Document
    {
        $path = $this->realPdfPath('documents/uploads/cleanup-'.($pathSuffix ?? uniqid()).'.pdf');

        $document = Document::create([
            'title' => 'Cleanup Test Doc',
            'file_path' => $path,
            'file_original_name' => 'cleanup.pdf',
            'uploaded_by' => User::factory()->create()->id,
            'signing_mode' => 'sequential',
            'status' => $status,
        ]);

        $document->forceFill(['created_at' => $createdAt])->save();

        return $document;
    }

    public function test_a_non_admin_editor_cannot_reach_the_cleanup_page(): void
    {
        $this->seed(RoleSeeder::class);
        $editor = User::factory()->create();
        $editor->documentSigningRoles()->create(['role' => DocumentSigningRole::Editor]);

        $this->actingAs($editor)
            ->get(DocumentSigningCleanup::getUrl())
            ->assertForbidden();
    }

    public function test_the_module_admin_role_can_reach_the_cleanup_page(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->makeModuleAdmin();

        $this->actingAs($admin)
            ->get(DocumentSigningCleanup::getUrl())
            ->assertSuccessful();
    }

    public function test_old_signed_rejected_and_voided_documents_are_all_eligible(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $admin = $this->makeModuleAdmin();

        $old = now()->subDays(400);
        $signed = $this->makeDocument(DocumentStatus::Signed->value, $old, 'signed');
        $rejected = $this->makeDocument(DocumentStatus::Rejected->value, $old, 'rejected');
        $voided = $this->makeDocument(DocumentStatus::Voided->value, $old, 'voided');

        $this->actingAs($admin);

        Livewire::test(DocumentSigningCleanup::class)
            ->mountAction('deleteOld')
            ->callMountedAction();

        $this->assertDatabaseMissing('documents', ['id' => $signed->id]);
        $this->assertDatabaseMissing('documents', ['id' => $rejected->id]);
        $this->assertDatabaseMissing('documents', ['id' => $voided->id]);
    }

    public function test_a_recent_signed_document_survives(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $admin = $this->makeModuleAdmin();

        $recent = $this->makeDocument(DocumentStatus::Signed->value, now()->subDays(30), 'recent');

        $this->actingAs($admin);

        Livewire::test(DocumentSigningCleanup::class)
            ->mountAction('deleteOld')
            ->callMountedAction();

        $this->assertDatabaseHas('documents', ['id' => $recent->id]);
    }

    public function test_pending_and_draft_documents_are_never_deleted_regardless_of_age(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $admin = $this->makeModuleAdmin();

        $veryOld = now()->subDays(2000);
        $pending = $this->makeDocument(DocumentStatus::Pending->value, $veryOld, 'pending');
        $draft = $this->makeDocument(DocumentStatus::Draft->value, $veryOld, 'draft');

        $this->actingAs($admin);

        Livewire::test(DocumentSigningCleanup::class)
            ->mountAction('deleteOld')
            ->callMountedAction();

        $this->assertDatabaseHas('documents', ['id' => $pending->id]);
        $this->assertDatabaseHas('documents', ['id' => $draft->id]);
    }

    /**
     * Submitting a value below the 365-day floor must not actually
     * shrink the retention window — the query itself clamps to the
     * floor regardless of what was typed.
     */
    public function test_the_365_day_floor_holds_even_if_a_shorter_value_is_submitted(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $admin = $this->makeModuleAdmin();

        // 100 days old — would be eligible under a (disallowed) 30-day
        // window, but not under the enforced 365-day floor.
        $tooRecentForFloor = $this->makeDocument(DocumentStatus::Signed->value, now()->subDays(100), 'too-recent');

        $this->actingAs($admin);

        Livewire::test(DocumentSigningCleanup::class)
            ->fillForm(['days' => 30])
            ->mountAction('deleteOld')
            ->callMountedAction();

        $this->assertDatabaseHas('documents', ['id' => $tooRecentForFloor->id]);
    }

    public function test_deleting_removes_the_files_from_disk_too(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        $admin = $this->makeModuleAdmin();

        $old = now()->subDays(400);
        $document = $this->makeDocument(DocumentStatus::Signed->value, $old, 'with-file');

        $signedCopyPath = "documents/{$document->id}/signed.pdf";
        Storage::disk('local')->put($signedCopyPath, 'fake-signed-bytes');
        $document->update(['signed_file_path' => $signedCopyPath]);

        Storage::disk('local')->assertExists($document->file_path);
        Storage::disk('local')->assertExists($signedCopyPath);

        $this->actingAs($admin);

        Livewire::test(DocumentSigningCleanup::class)
            ->mountAction('deleteOld')
            ->callMountedAction();

        Storage::disk('local')->assertMissing($document->file_path);
        Storage::disk('local')->assertMissing($signedCopyPath);
    }
}
