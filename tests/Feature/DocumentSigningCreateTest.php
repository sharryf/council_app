<?php

namespace Tests\Feature;

use App\Enums\DocumentSigningRole;
use App\Enums\DocumentStatus;
use App\Filament\Resources\DocumentSigning\Documents\Pages\CreateDocument;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

/**
 * The upload wizard (see CreateDocument) is a from-scratch two-step
 * Livewire page, not a standard Filament form — step 2's PDF preview,
 * drag-and-drop placement, and auto-detect are pure client-side Alpine
 * (resources/views/.../create-document.blade.php) and out of reach
 * here; this covers the PHP side both steps call into.
 */
class DocumentSigningCreateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A hand-rolled minimal PDF byte string isn't valid enough for FPDI
     * to parse (no real xref table) — continueToPlacement() calls
     * setSourceFile() to count pages, so it needs a genuine PDF.
     */
    private function realPdfBytes(int $pages = 1): string
    {
        $pdf = new Fpdi();

        for ($i = 0; $i < $pages; $i++) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', '', 12);
            $pdf->Cell(0, 10, "Page {$i}: for Ibrahim Waheed to sign.");
        }

        return $pdf->Output('S');
    }

    private function makeEditor(): User
    {
        $user = User::factory()->create();
        $user->documentSigningRoles()->create(['role' => DocumentSigningRole::Editor]);

        return $user;
    }

    /**
     * mount() blocks non-Editors from the create page at all — see
     * DocumentSigningRoleGatingTest::test_a_user_with_no_roles_cannot_reach_the_create_page()
     * for that gate via a real HTTP request. sendToSign() carries the
     * same check independently as defense-in-depth, since a Livewire
     * action call doesn't re-run mount().
     */
    public function test_continue_to_placement_stores_the_file_and_counts_pages(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $editor = $this->makeEditor();
        $this->actingAs($editor);

        $file = UploadedFile::fake()->createWithContent('memo.pdf', $this->realPdfBytes(3));

        Livewire::test(CreateDocument::class)
            ->set('file', $file)
            ->call('continueToPlacement')
            ->assertSet('step', 2)
            ->assertSet('pageCount', 3)
            ->assertSet('uploadedFileName', 'memo.pdf');
    }

    /**
     * The free FPDI parser can't read every PDF encoding (notably some
     * cross-reference/compression schemes used by headless-browser
     * "print to PDF" pipelines and some AI tools) — continueToPlacement()
     * should catch that and notify rather than crash with a raw 500.
     */
    public function test_continue_to_placement_gracefully_rejects_a_pdf_fpdi_cannot_parse(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $editor = $this->makeEditor();
        $this->actingAs($editor);

        // Passes the `mimes:pdf` rule (starts with a PDF header) but
        // has no real xref table — the same reason realPdfBytes()
        // above exists instead of a hand-rolled string.
        $file = UploadedFile::fake()->createWithContent('broken.pdf', "%PDF-1.4\nnot a real pdf structure");

        Livewire::test(CreateDocument::class)
            ->set('file', $file)
            ->call('continueToPlacement')
            ->assertSet('step', 1);

        $this->assertSame(0, Document::count());
    }

    public function test_send_to_sign_creates_a_document_with_placed_signers_and_stamps_and_submits_it(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $editor = $this->makeEditor();
        $signer1 = User::factory()->create();
        $signer1->documentSigningRoles()->createMany([
            ['role' => DocumentSigningRole::Signee],
        ]);
        $signer2 = User::factory()->create();
        $signer2->documentSigningRoles()->create(['role' => DocumentSigningRole::Signee]);

        $this->actingAs($editor);

        $file = UploadedFile::fake()->createWithContent('memo.pdf', $this->realPdfBytes(2));

        $component = Livewire::test(CreateDocument::class)
            ->set('documentTitle', 'Test Memo')
            ->set('file', $file)
            ->call('continueToPlacement');

        $component->call('sendToSign', 'sequential', [
            ['user_id' => $signer1->id],
            ['user_id' => $signer2->id],
        ], [
            ['user_id' => $signer1->id, 'page' => 1, 'x' => 0.1, 'y' => 0.7, 'w' => 0.3, 'h' => 0.08],
            ['user_id' => $signer2->id, 'page' => 2, 'x' => 0.5, 'y' => 0.8, 'w' => 0.3, 'h' => 0.08],
        ], [
            ['slot' => 2, 'page' => 1, 'x' => 0.6, 'y' => 0.1, 'w' => 0.2, 'h' => 0.15],
        ]);

        $document = Document::sole();

        $this->assertSame('Test Memo', $document->title);
        $this->assertSame($editor->id, $document->uploaded_by);
        $this->assertSame('memo.pdf', $document->file_original_name);
        $this->assertSame(DocumentStatus::Pending, $document->status);
        Storage::disk('local')->assertExists($document->file_path);

        $this->assertCount(2, $document->signers);
        $row1 = $document->signers->firstWhere('user_id', $signer1->id);
        $this->assertSame(1, $row1->order);
        $placement1 = $row1->placements->sole();
        $this->assertSame(1, $placement1->page_number);
        $this->assertEqualsWithDelta(0.1, $placement1->position_x, 0.001);
        $this->assertEqualsWithDelta(0.7, $placement1->position_y, 0.001);

        $row2 = $document->signers->firstWhere('user_id', $signer2->id);
        $this->assertSame(2, $row2->order);
        $this->assertSame(2, $row2->placements->sole()->page_number);

        $this->assertCount(1, $document->stamps);
        $this->assertSame(2, $document->stamps->first()->stamp_slot);
        $this->assertSame(1, $document->stamps->first()->page_number);
        $this->assertEqualsWithDelta(0.6, $document->stamps->first()->position_x, 0.001);
    }

    /**
     * The wizard only ever lets one stamp be placed (the "Add" buttons
     * hide once one exists — create-document.blade.php) — sendToSign()
     * enforces the same limit server-side in case of a direct call with
     * more than one, taking only the first rather than erroring.
     */
    public function test_only_the_first_stamp_is_kept_if_more_than_one_is_submitted(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $editor = $this->makeEditor();
        $signee = User::factory()->create();
        $signee->documentSigningRoles()->create(['role' => DocumentSigningRole::Signee]);

        $this->actingAs($editor);

        $file = UploadedFile::fake()->createWithContent('memo.pdf', $this->realPdfBytes(1));

        Livewire::test(CreateDocument::class)
            ->set('file', $file)
            ->call('continueToPlacement')
            ->call('sendToSign', 'sequential', [
                ['user_id' => $signee->id],
            ], [
                ['user_id' => $signee->id, 'page' => 1, 'x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.1],
            ], [
                ['slot' => 1, 'page' => 1, 'x' => 0.6, 'y' => 0.1, 'w' => 0.2, 'h' => 0.15],
                ['slot' => 2, 'page' => 1, 'x' => 0.1, 'y' => 0.6, 'w' => 0.2, 'h' => 0.15],
            ]);

        $document = Document::sole();

        $this->assertCount(1, $document->stamps);
        $this->assertSame(1, $document->stamps->first()->stamp_slot);
    }

    public function test_a_stamp_placed_with_no_slot_defaults_to_slot_one(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $editor = $this->makeEditor();
        $signee = User::factory()->create();
        $signee->documentSigningRoles()->create(['role' => DocumentSigningRole::Signee]);

        $this->actingAs($editor);

        $file = UploadedFile::fake()->createWithContent('memo.pdf', $this->realPdfBytes(1));

        Livewire::test(CreateDocument::class)
            ->set('file', $file)
            ->call('continueToPlacement')
            ->call('sendToSign', 'sequential', [
                ['user_id' => $signee->id],
            ], [
                ['user_id' => $signee->id, 'page' => 1, 'x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.1],
            ], [
                ['page' => 1, 'x' => 0.6, 'y' => 0.1, 'w' => 0.2, 'h' => 0.15],
            ]);

        $document = Document::sole();

        $this->assertSame(1, $document->stamps->sole()->stamp_slot);
    }

    /**
     * The actual point of splitting placement off document_signers
     * into its own table — a signee can be placed more than once (e.g.
     * initials on page 1 plus a full signature on page 2), and both
     * placements land on the same DocumentSigner row.
     */
    public function test_a_signee_can_have_multiple_placements_across_different_pages(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $editor = $this->makeEditor();
        $signee = User::factory()->create();
        $signee->documentSigningRoles()->create(['role' => DocumentSigningRole::Signee]);

        $this->actingAs($editor);

        $file = UploadedFile::fake()->createWithContent('memo.pdf', $this->realPdfBytes(2));

        Livewire::test(CreateDocument::class)
            ->set('file', $file)
            ->call('continueToPlacement')
            ->call('sendToSign', 'sequential', [
                ['user_id' => $signee->id],
            ], [
                ['user_id' => $signee->id, 'page' => 1, 'x' => 0.1, 'y' => 0.1, 'w' => 0.1, 'h' => 0.05],
                ['user_id' => $signee->id, 'page' => 2, 'x' => 0.5, 'y' => 0.8, 'w' => 0.3, 'h' => 0.08],
            ], []);

        $document = Document::sole();

        $this->assertCount(1, $document->signers);
        $signerRow = $document->signers->sole();
        $this->assertCount(2, $signerRow->placements);
        $this->assertSame([1, 2], $signerRow->placements->pluck('page_number')->sort()->values()->all());
    }

    /**
     * getOrganizationStamps() is what the wizard's Alpine component
     * uses to decide which "Add stamp" buttons to show (see
     * create-document.blade.php) — this covers the PHP side of that
     * without needing a browser.
     */
    public function test_get_organization_stamps_reflects_configured_slots(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $editor = $this->makeEditor();
        $this->actingAs($editor);

        \App\Models\DocumentSigningOrganization::current()->update([
            'stamp_path' => 'document-signing/organization/one.png',
            'stamp_label' => 'Council Stamp',
        ]);

        $component = Livewire::test(CreateDocument::class);

        $this->assertSame(
            [['slot' => 1, 'label' => 'Council Stamp']],
            $component->instance()->getOrganizationStamps(),
        );
    }

    public function test_send_to_sign_requires_at_least_one_signer(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $editor = $this->makeEditor();
        $this->actingAs($editor);

        $file = UploadedFile::fake()->createWithContent('memo.pdf', $this->realPdfBytes(1));

        Livewire::test(CreateDocument::class)
            ->set('file', $file)
            ->call('continueToPlacement')
            ->call('sendToSign', 'sequential', [], [], []);

        $this->assertSame(0, Document::count());
    }

    public function test_title_is_optional_and_falls_back_to_the_filename_for_display(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $editor = $this->makeEditor();
        $signee = User::factory()->create();
        $signee->documentSigningRoles()->create(['role' => DocumentSigningRole::Signee]);

        $this->actingAs($editor);

        $file = UploadedFile::fake()->createWithContent('board-memo.pdf', $this->realPdfBytes(1));

        Livewire::test(CreateDocument::class)
            ->set('file', $file)
            ->call('continueToPlacement')
            ->call('sendToSign', 'sequential', [
                ['user_id' => $signee->id],
            ], [
                ['user_id' => $signee->id, 'page' => 1, 'x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.1],
            ], []);

        $document = Document::sole();

        $this->assertNull($document->title);
        $this->assertSame('board-memo.pdf', $document->displayTitle());
    }
}
