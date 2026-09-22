<?php

namespace Tests\Feature;

use App\Enums\DocumentSigningRole;
use App\Enums\DocumentStatus;
use App\Filament\Resources\DocumentSigning\Documents\Pages\ViewDocument;
use App\Filament\Resources\DocumentSigning\Documents\Widgets\PendingSignaturesWidget;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentSigning\DocumentSigningService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

/**
 * Void (see App\Enums\DocumentStatus::Voided) is an administrative
 * cancellation of a Pending document — distinct from Reject, which is
 * always a specific signer's own objection. Scoped to the document's
 * uploader (while they still hold the Editor role) or a system-wide
 * `admin`, and only while the document is still Pending.
 */
class DocumentSigningVoidTest extends TestCase
{
    use RefreshDatabase;

    private function makeEditor(): User
    {
        $user = User::factory()->create();
        $user->documentSigningRoles()->create(['role' => DocumentSigningRole::Editor]);

        return $user;
    }

    private function makeSignee(): User
    {
        $user = User::factory()->create();
        $user->documentSigningRoles()->create(['role' => DocumentSigningRole::Signee]);

        return $user;
    }

    private function makePendingDocument(User $uploader, User $signer): Document
    {
        $pdf = new Fpdi();
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'Void test document');

        $path = 'documents/uploads/void-test.pdf';
        Storage::disk('local')->makeDirectory('documents/uploads');
        $pdf->Output('F', Storage::disk('local')->path($path));

        $document = Document::create([
            'title' => 'Void Test Document',
            'file_path' => $path,
            'file_original_name' => 'void-test.pdf',
            'uploaded_by' => $uploader->id,
            'signing_mode' => 'sequential',
            'status' => DocumentStatus::Pending,
        ]);

        $document->signers()->create(['user_id' => $signer->id, 'order' => 1, 'role_label' => 'Signer']);

        return $document->load('signers');
    }

    public function test_the_uploader_can_void_their_own_pending_document(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = $this->makeEditor();
        $signer = $this->makeSignee();
        $document = $this->makePendingDocument($uploader, $signer);

        $this->actingAs($uploader);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertActionVisible('void')
            ->mountAction('void')
            ->setActionData(['reason' => 'Wrong file attached.'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $document->refresh();

        $this->assertSame(DocumentStatus::Voided, $document->status);
        $this->assertSame('Wrong file attached.', $document->void_reason);
        $this->assertSame($uploader->id, $document->voided_by);
        $this->assertNotNull($document->voided_at);
    }

    /**
     * `admin` is Users-page administration only — DocumentSigningRole::
     * Admin, this module's own admin-equivalent role, is explicitly
     * documented to not grant document capability either (see that
     * enum). Voiding stays uploader-only, full stop — a system admin
     * granted only Viewer (enough to load and see the document) still
     * can't void someone else's.
     */
    public function test_a_system_admin_cannot_void_a_document_they_did_not_upload(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = $this->makeEditor();
        $signer = $this->makeSignee();
        $document = $this->makePendingDocument($uploader, $signer);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->documentSigningRoles()->create(['role' => DocumentSigningRole::Viewer]);

        $this->actingAs($admin);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertActionHidden('void');

        $this->assertSame(DocumentStatus::Pending, $document->refresh()->status);
    }

    /**
     * DocumentResource::getEloquentQuery() already scopes a plain
     * Editor to documents they're personally involved with, so a
     * completely uninvolved Editor can't even load this page — the
     * Viewer role (full visibility) is what makes this a meaningful
     * check on voidAction() itself: seeing the document isn't the same
     * as being allowed to void it.
     */
    public function test_another_editor_who_did_not_upload_it_cannot_void_it(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = $this->makeEditor();
        $signer = $this->makeSignee();
        $document = $this->makePendingDocument($uploader, $signer);

        $otherEditor = $this->makeEditor();
        $otherEditor->documentSigningRoles()->create(['role' => DocumentSigningRole::Viewer]);
        $this->actingAs($otherEditor);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertActionHidden('void');

        $this->assertSame(DocumentStatus::Pending, $document->refresh()->status);
    }

    public function test_a_draft_document_cannot_be_voided(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = $this->makeEditor();
        $signer = $this->makeSignee();
        $document = $this->makePendingDocument($uploader, $signer);
        $document->update(['status' => DocumentStatus::Draft]);

        $this->actingAs($uploader);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertActionHidden('void');
    }

    public function test_a_signed_document_cannot_be_voided(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = $this->makeEditor();
        $signer = $this->makeSignee();
        $document = $this->makePendingDocument($uploader, $signer);
        $document->update(['status' => DocumentStatus::Signed]);

        $this->actingAs($uploader);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertActionHidden('void');
    }

    public function test_voiding_requires_a_reason(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = $this->makeEditor();
        $signer = $this->makeSignee();
        $document = $this->makePendingDocument($uploader, $signer);

        $this->actingAs($uploader);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->mountAction('void')
            ->callMountedAction()
            ->assertHasActionErrors(['reason']);

        $this->assertSame(DocumentStatus::Pending, $document->refresh()->status);
    }

    public function test_a_voided_document_can_no_longer_be_signed_or_rejected(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = $this->makeEditor();
        $signer = $this->makeSignee();
        $document = $this->makePendingDocument($uploader, $signer);

        app(DocumentSigningService::class)->void($document, $uploader, 'Wrong document.');

        $this->actingAs($signer);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertActionHidden('sign')
            ->assertActionHidden('reject');
    }

    public function test_a_voided_document_drops_out_of_the_pending_signatures_widget_count(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = $this->makeEditor();
        $signer = $this->makeSignee();
        $document = $this->makePendingDocument($uploader, $signer);

        $this->actingAs($signer);
        $this->assertSame(1, app(PendingSignaturesWidget::class)->getPendingCount());

        app(DocumentSigningService::class)->void($document, $uploader, 'No longer needed.');

        $this->assertSame(0, app(PendingSignaturesWidget::class)->getPendingCount());
    }
}
