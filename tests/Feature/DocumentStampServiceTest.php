<?php

namespace Tests\Feature;

use App\Enums\DocumentSigningRole;
use App\Enums\DocumentStatus;
use App\Enums\SignatureType;
use App\Models\Document;
use App\Models\DocumentSigningOrganization;
use App\Models\User;
use App\Services\DocumentSigning\DocumentSigningService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

/**
 * The upload wizard's placement data (page/x/y/w/h — see CreateDocument
 * and DocumentSigner::placements()) is meant to end up composited
 * directly onto the document's own pages when it's finally signed —
 * no separate certificate/audit page gets appended. This exercises
 * that path against a real multi-page PDF.
 */
class DocumentStampServiceTest extends TestCase
{
    use RefreshDatabase;

    private function realPdfPath(string $relativePath, int $pages = 2): string
    {
        $pdf = new Fpdi();

        for ($i = 0; $i < $pages; $i++) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', '', 12);
            $pdf->Cell(0, 10, "Page {$i}");
        }

        Storage::disk('local')->makeDirectory(dirname($relativePath));
        $pdf->Output('F', Storage::disk('local')->path($relativePath));

        return $relativePath;
    }

    public function test_a_placed_signature_and_stamp_are_composited_onto_their_pages(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $signer = User::factory()->create();
        $signer->documentSigningRoles()->create(['role' => DocumentSigningRole::Signee]);

        $filePath = $this->realPdfPath('documents/uploads/placed.pdf', pages: 2);

        $document = Document::create([
            'title' => 'Placed Document',
            'file_path' => $filePath,
            'file_original_name' => 'placed.pdf',
            'uploaded_by' => $signer->id,
            'signing_mode' => 'sequential',
            'status' => DocumentStatus::Pending,
        ]);

        $document->signers()->create([
            'user_id' => $signer->id,
            'order' => 1,
            'role_label' => 'Signer',
        ])->placements()->create([
            'page_number' => 2,
            'position_x' => 0.1,
            'position_y' => 0.8,
            'box_width' => 0.3,
            'box_height' => 0.08,
        ]);

        $document->stamps()->create([
            'page_number' => 1,
            'position_x' => 0.6,
            'position_y' => 0.1,
            'box_width' => 0.2,
            'box_height' => 0.15,
        ]);

        $organization = DocumentSigningOrganization::current();
        $stampPath = 'document-signing/organization/test-stamp.png';
        // Real PNG bytes (1x1 transparent pixel) — FPDI's Image() needs
        // to actually parse this, not just find a file at the path.
        Storage::disk('local')->put(
            $stampPath,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
        $organization->update(['stamp_path' => $stampPath]);

        $document->refresh()->load('signers');

        app(DocumentSigningService::class)->sign(
            $document->load('signers'),
            $signer,
            SignatureType::Typed,
            'Signer Name',
        );

        $document->refresh();

        $this->assertSame(DocumentStatus::Signed, $document->status);
        $this->assertNotNull($document->signed_file_path);
        Storage::disk('local')->assertExists($document->signed_file_path);

        // Exactly the original 2 pages — no certificate/audit page appended.
        $verify = new Fpdi();
        $pageCount = $verify->setSourceFile(Storage::disk('local')->path($document->signed_file_path));
        $this->assertSame(2, $pageCount);
    }

    /**
     * A signer can have more than one placement (see
     * DocumentSigner::placements()) — e.g. initials on page 1 plus a
     * full signature on page 2. generate() has to walk both of a
     * signer's placements rather than assuming exactly one, composited
     * separately on each of their own pages.
     */
    public function test_a_signer_with_two_placements_is_composited_on_both_of_their_pages(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $signer = User::factory()->create();
        $signer->documentSigningRoles()->create(['role' => DocumentSigningRole::Signee]);

        $filePath = $this->realPdfPath('documents/uploads/multi-placement.pdf', pages: 2);

        $document = Document::create([
            'title' => 'Multi Placement Document',
            'file_path' => $filePath,
            'file_original_name' => 'multi-placement.pdf',
            'uploaded_by' => $signer->id,
            'signing_mode' => 'sequential',
            'status' => DocumentStatus::Pending,
        ]);

        $signerRow = $document->signers()->create([
            'user_id' => $signer->id,
            'order' => 1,
            'role_label' => 'Signer',
        ]);

        $signerRow->placements()->create([
            'page_number' => 1,
            'position_x' => 0.05,
            'position_y' => 0.9,
            'box_width' => 0.1,
            'box_height' => 0.05,
        ]);

        $signerRow->placements()->create([
            'page_number' => 2,
            'position_x' => 0.1,
            'position_y' => 0.8,
            'box_width' => 0.3,
            'box_height' => 0.08,
        ]);

        app(DocumentSigningService::class)->sign(
            $document->refresh()->load('signers.placements'),
            $signer,
            SignatureType::Typed,
            'Signer Name',
        );

        $document->refresh();

        $this->assertSame(DocumentStatus::Signed, $document->status);
        Storage::disk('local')->assertExists($document->signed_file_path);

        $verify = new Fpdi();
        $pageCount = $verify->setSourceFile(Storage::disk('local')->path($document->signed_file_path));
        $this->assertSame(2, $pageCount);
    }

    /**
     * The organization can have two stamps configured (see
     * App\Models\DocumentSigningOrganization) — a placement's
     * stamp_slot picks which image actually gets composited, not just
     * whichever one happens to be uploaded first.
     */
    public function test_a_stamp_placed_in_the_second_slot_uses_the_second_stamp_image(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $signer = User::factory()->create();
        $signer->documentSigningRoles()->create(['role' => DocumentSigningRole::Signee]);

        $filePath = $this->realPdfPath('documents/uploads/second-slot.pdf', pages: 1);

        $document = Document::create([
            'title' => 'Second Slot Document',
            'file_path' => $filePath,
            'file_original_name' => 'second-slot.pdf',
            'uploaded_by' => $signer->id,
            'signing_mode' => 'sequential',
            'status' => DocumentStatus::Pending,
        ]);

        $document->signers()->create([
            'user_id' => $signer->id,
            'order' => 1,
            'role_label' => 'Signer',
        ])->placements()->create([
            'page_number' => 1,
            'position_x' => 0.1,
            'position_y' => 0.8,
            'box_width' => 0.3,
            'box_height' => 0.08,
        ]);

        $document->stamps()->create([
            'stamp_slot' => 2,
            'page_number' => 1,
            'position_x' => 0.6,
            'position_y' => 0.1,
            'box_width' => 0.2,
            'box_height' => 0.15,
        ]);

        // Only slot 2 has an image configured — if placeStamp() ignored
        // stamp_slot and always read slot 1, this would find nothing
        // and silently skip the stamp instead of compositing it.
        $stampPath = 'document-signing/organization/second-stamp.png';
        Storage::disk('local')->put(
            $stampPath,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
        DocumentSigningOrganization::current()->update(['stamp_path_2' => $stampPath]);

        app(DocumentSigningService::class)->sign(
            $document->refresh()->load('signers'),
            $signer,
            SignatureType::Typed,
            'Signer Name',
        );

        $document->refresh();

        $this->assertSame(DocumentStatus::Signed, $document->status);
        Storage::disk('local')->assertExists($document->signed_file_path);
    }

    public function test_a_stamp_in_a_slot_with_no_image_configured_is_skipped_without_erroring(): void
    {
        Storage::fake('local');
        $this->seed(RoleSeeder::class);

        $signer = User::factory()->create();
        $signer->documentSigningRoles()->create(['role' => DocumentSigningRole::Signee]);

        $filePath = $this->realPdfPath('documents/uploads/no-stamp-image.pdf', pages: 1);

        $document = Document::create([
            'title' => 'No Stamp Image Document',
            'file_path' => $filePath,
            'file_original_name' => 'no-stamp-image.pdf',
            'uploaded_by' => $signer->id,
            'signing_mode' => 'sequential',
            'status' => DocumentStatus::Pending,
        ]);

        $document->signers()->create([
            'user_id' => $signer->id,
            'order' => 1,
            'role_label' => 'Signer',
        ])->placements()->create([
            'page_number' => 1,
            'position_x' => 0.1,
            'position_y' => 0.8,
            'box_width' => 0.3,
            'box_height' => 0.08,
        ]);

        // Placed in slot 2, but the organization never configured a
        // slot-2 image (stamp_path_2 stays null).
        $document->stamps()->create([
            'stamp_slot' => 2,
            'page_number' => 1,
            'position_x' => 0.6,
            'position_y' => 0.1,
            'box_width' => 0.2,
            'box_height' => 0.15,
        ]);

        app(DocumentSigningService::class)->sign(
            $document->refresh()->load('signers'),
            $signer,
            SignatureType::Typed,
            'Signer Name',
        );

        $this->assertSame(DocumentStatus::Signed, $document->refresh()->status);
    }
}
