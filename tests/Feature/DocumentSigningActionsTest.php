<?php

namespace Tests\Feature;

use App\Enums\DocumentSigningRole;
use App\Enums\DocumentStatus;
use App\Enums\SignerStatus;
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
 * Covers the Sign/Reject Filament Actions through Livewire's real
 * mount/fill/call lifecycle (not just the underlying service directly),
 * so the actions' `visible()` gates and wiring into
 * DocumentSigningService are all exercised the same way a browser
 * request would exercise them. signAction() no longer takes any form
 * input — it auto-selects the signer's saved profile signature if they
 * have one, otherwise falls back to their account name as a typed
 * signature (see DocumentResource::signAction()) — so these tests just
 * call the action and assert which one it picked.
 */
class DocumentSigningActionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeSignee(): User
    {
        $user = User::factory()->create();
        $user->documentSigningRoles()->create(['role' => DocumentSigningRole::Signee]);

        return $user;
    }

    private function makePendingDocument(User $uploader, User $signer1, User $signer2): Document
    {
        // A hand-rolled minimal PDF byte string isn't valid enough for
        // FPDI to actually parse (it needs a real xref table to import
        // pages when stamping) — build a genuine one instead.
        $pdf = new Fpdi();
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'Action test document');

        $path = 'documents/uploads/action-test.pdf';
        Storage::disk('local')->makeDirectory('documents/uploads');
        $pdf->Output('F', Storage::disk('local')->path($path));

        $document = Document::create([
            'title' => 'Action Test Document',
            'file_path' => $path,
            'file_original_name' => 'action-test.pdf',
            'uploaded_by' => $uploader->id,
            'signing_mode' => 'sequential',
            'status' => DocumentStatus::Pending,
        ]);

        $document->signers()->create(['user_id' => $signer1->id, 'order' => 1, 'role_label' => 'Reviewer']);
        $document->signers()->create(['user_id' => $signer2->id, 'order' => 2, 'role_label' => 'Approver']);

        return $document->load('signers');
    }

    public function test_sign_action_types_the_signers_name_when_they_have_no_saved_signature(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = User::factory()->create();
        $uploader->assignRole('admin');
        $signer1 = $this->makeSignee();
        $signer2 = $this->makeSignee();

        $document = $this->makePendingDocument($uploader, $signer1, $signer2);

        $this->actingAs($signer1);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertActionVisible('sign')
            ->assertActionHidden('downloadSigned')
            ->mountAction('sign')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $document->refresh()->load('signers');
        $row = $document->signers->firstWhere('user_id', $signer1->id);

        $this->assertSame(SignerStatus::Signed, $row->status);
        $this->assertSame(\App\Enums\SignatureType::Typed, $row->signature_type);
        $this->assertSame($signer1->name, $row->signature_value);
        $this->assertSame(64, strlen($row->hash));
        $this->assertSame(DocumentStatus::Pending, $document->status);
    }

    public function test_second_sequential_signature_completes_the_document_and_stamps_it(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = User::factory()->create();
        $uploader->assignRole('admin');
        $signer1 = $this->makeSignee();
        $signer2 = $this->makeSignee();

        $document = $this->makePendingDocument($uploader, $signer1, $signer2);

        app(\App\Services\DocumentSigning\DocumentSigningService::class)
            ->sign($document, $signer1, \App\Enums\SignatureType::Typed, 'Signer One');

        $this->actingAs($signer2);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->mountAction('sign')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $document->refresh()->load('signers');

        $this->assertSame(DocumentStatus::Signed, $document->status);
        $this->assertNotNull($document->signed_file_path);
        Storage::disk('local')->assertExists($document->signed_file_path);

        $row2 = $document->signers->firstWhere('user_id', $signer2->id);
        $this->assertSame(\App\Enums\SignatureType::Typed, $row2->signature_type);
        $this->assertSame($signer2->name, $row2->signature_value);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertActionVisible('downloadSigned')
            ->assertActionHidden('sign');
    }

    public function test_sign_action_reuses_a_saved_profile_signature(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = User::factory()->create();
        $uploader->assignRole('admin');
        $signer1 = $this->makeSignee();
        $signer2 = $this->makeSignee();

        $profileSignaturePath = 'signatures/users/'.$signer1->id.'-profile.png';
        Storage::disk('local')->put($profileSignaturePath, 'fake-png-bytes');
        $signer1->update(['signature_path' => $profileSignaturePath]);

        $document = $this->makePendingDocument($uploader, $signer1, $signer2);

        $this->actingAs($signer1);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->mountAction('sign')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $document->refresh()->load('signers');
        $row = $document->signers->firstWhere('user_id', $signer1->id);

        $this->assertSame(\App\Enums\SignatureType::Saved, $row->signature_type);
        $this->assertNotSame($profileSignaturePath, $row->signature_value);
        Storage::disk('local')->assertExists($row->signature_value);
        $this->assertSame('fake-png-bytes', Storage::disk('local')->get($row->signature_value));
    }

    /**
     * The Sign modal's own "Reject" button doesn't mount a second,
     * separate action — it re-runs signAction() itself with
     * `arguments: ['reject' => true]` (see
     * DocumentResource::signAction()'s extraModalFooterActions()), so
     * this exercises that branch specifically: blocked without a
     * reason, then succeeds once one is given.
     */
    public function test_reject_from_within_the_sign_modal_requires_a_reason(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = User::factory()->create();
        $uploader->assignRole('admin');
        $signer1 = $this->makeSignee();
        $signer2 = $this->makeSignee();

        $document = $this->makePendingDocument($uploader, $signer1, $signer2);
        $document->update(['signing_mode' => 'parallel']);

        $this->actingAs($signer1);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->mountAction('sign')
            ->callMountedAction(['reject' => true])
            ->assertHasActionErrors(['reason']);

        $document->refresh();
        $this->assertSame(DocumentStatus::Pending, $document->status);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->mountAction('sign')
            ->setActionData(['reason' => 'Wrong document version attached.'])
            ->callMountedAction(['reject' => true])
            ->assertHasNoActionErrors();

        $document->refresh()->load('signers');

        $this->assertSame(DocumentStatus::Rejected, $document->status);
        $this->assertSame('Wrong document version attached.', $document->rejection_reason);
        $this->assertSame(SignerStatus::Rejected, $document->signers->firstWhere('user_id', $signer1->id)->status);
    }

    public function test_reject_action_out_of_turn_halts_the_document(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $uploader = User::factory()->create();
        $uploader->assignRole('admin');
        $signer1 = $this->makeSignee();
        $signer2 = $this->makeSignee();

        $document = $this->makePendingDocument($uploader, $signer1, $signer2);

        // signer2 is #2 in sequence — not their turn to sign, but they
        // can still reject to stop a bad document early.
        $this->actingAs($signer2);

        Livewire::test(ViewDocument::class, ['record' => $document->getKey()])
            ->assertActionVisible('reject')
            ->mountAction('reject')
            ->setActionData(['reason' => 'Wrong attachment.'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $document->refresh()->load('signers');

        $this->assertSame(DocumentStatus::Rejected, $document->status);
        $this->assertSame('Wrong attachment.', $document->rejection_reason);
        $this->assertSame(SignerStatus::Rejected, $document->signers->firstWhere('user_id', $signer2->id)->status);
        $this->assertSame(SignerStatus::Pending, $document->signers->firstWhere('user_id', $signer1->id)->status);
    }
}
