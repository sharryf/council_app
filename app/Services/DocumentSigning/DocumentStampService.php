<?php

namespace App\Services\DocumentSigning;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\DocumentSignerPlacement;
use App\Models\DocumentSigningOrganization;
use App\Models\DocumentStamp;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

/**
 * Builds the final "signed" PDF once a document is fully signed.
 *
 * Signers/stamps placed via the upload wizard (see CreateDocument) get
 * their signature or the organization stamp — whichever of the two
 * configured stamps the uploader picked, see DocumentStamp::stamp_slot
 * and DocumentSigningOrganization — composited directly onto every
 * page/position they were dropped on, see DocumentSigner::placements()
 * (a signer can have more than one placement, e.g. initials on every
 * page plus a full signature on the last — the same signature image is
 * reused for each). No certificate/audit page gets appended — the
 * output is exactly the original page count, with only the placed
 * signatures/stamp added. The per-signer verification hash still
 * exists (`document_signers.hash`, shown in the app's own document
 * view) — it's just not printed into the PDF itself. A signer with no
 * placements at all (e.g. a document created outside the wizard, or in
 * a test) simply won't have their signature appear anywhere in this
 * output — there's no fallback page left to list it on.
 */
class DocumentStampService
{
    public function generate(Document $document): string
    {
        $document->loadMissing('signers.user', 'signers.placements');
        $stamps = $document->stamps;

        $pdf = new Fpdi();
        // This service manages page flow itself, one imported template
        // page at a time — FPDF's automatic page break (on by default)
        // would otherwise silently insert an extra blank page the
        // moment a Typed signature's Cell() call lands close enough to
        // a page's bottom margin, corrupting the "exact original page
        // count" guarantee documented above.
        $pdf->SetAutoPageBreak(false);
        $pageCount = $pdf->setSourceFile(Storage::disk('local')->path($document->file_path));

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $templateId = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            foreach ($document->signers as $signer) {
                foreach ($signer->placements->where('page_number', $pageNumber) as $placement) {
                    $this->placeSigner($pdf, $signer, $placement, $size['width'], $size['height']);
                }
            }

            foreach ($stamps->where('page_number', $pageNumber) as $stamp) {
                $this->placeStamp($pdf, $stamp, $size['width'], $size['height']);
            }
        }

        $relativePath = "documents/{$document->id}/signed.pdf";
        Storage::disk('local')->makeDirectory("documents/{$document->id}");
        $pdf->Output('F', Storage::disk('local')->path($relativePath));

        return $relativePath;
    }

    private function placeSigner(Fpdi $pdf, DocumentSigner $signer, DocumentSignerPlacement $placement, float $pageWidth, float $pageHeight): void
    {
        $x = $placement->position_x * $pageWidth;
        $y = $placement->position_y * $pageHeight;
        $width = $placement->box_width * $pageWidth;
        $height = $placement->box_height * $pageHeight;

        $imagePath = $signer->signatureDiskPath();

        if ($imagePath && file_exists($imagePath)) {
            [$fittedX, $fittedY, $fittedWidth, $fittedHeight] = $this->fitWithinBox($imagePath, $x, $y, $width, $height);
            $pdf->Image($imagePath, $fittedX, $fittedY, $fittedWidth, $fittedHeight, 'PNG');
        } else {
            $pdf->SetXY($x, $y);
            $pdf->SetFont('Helvetica', 'I', $this->fontSizeToFit($height));
            $pdf->Cell($width, $height, (string) $signer->signature_value, 0, 0, 'C');
        }
    }

    private function placeStamp(Fpdi $pdf, DocumentStamp $stamp, float $pageWidth, float $pageHeight): void
    {
        $organization = DocumentSigningOrganization::current();
        $relativePath = $organization->stampPathForSlot($stamp->stamp_slot);

        if (blank($relativePath)) {
            return;
        }

        $stampPath = Storage::disk('local')->path($relativePath);

        if (! file_exists($stampPath)) {
            return;
        }

        [$fittedX, $fittedY, $fittedWidth, $fittedHeight] = $this->fitWithinBox(
            $stampPath,
            $stamp->position_x * $pageWidth,
            $stamp->position_y * $pageHeight,
            $stamp->box_width * $pageWidth,
            $stamp->box_height * $pageHeight,
        );

        $pdf->Image($stampPath, $fittedX, $fittedY, $fittedWidth, $fittedHeight);
    }

    /**
     * Fits an image inside a placed box without distorting it — the box
     * itself (from position_x/y/box_width/box_height) is just where the
     * uploader dropped and sized it in the wizard, which won't
     * generally match the image's own aspect ratio. Scales to the most
     * restrictive dimension and centers the result within the box,
     * letterboxed rather than stretched.
     *
     * @return array{0: float, 1: float, 2: float, 3: float} [x, y, width, height]
     */
    private function fitWithinBox(string $imagePath, float $boxX, float $boxY, float $boxWidth, float $boxHeight): array
    {
        $dimensions = @getimagesize($imagePath);

        if (! $dimensions || $dimensions[0] <= 0 || $dimensions[1] <= 0) {
            return [$boxX, $boxY, $boxWidth, $boxHeight];
        }

        $imageAspect = $dimensions[0] / $dimensions[1];
        $boxAspect = $boxWidth / $boxHeight;

        if ($imageAspect > $boxAspect) {
            $width = $boxWidth;
            $height = $boxWidth / $imageAspect;
        } else {
            $height = $boxHeight;
            $width = $boxHeight * $imageAspect;
        }

        return [
            $boxX + ($boxWidth - $width) / 2,
            $boxY + ($boxHeight - $height) / 2,
            $width,
            $height,
        ];
    }

    /**
     * Roughly fits a typed signature's font size to the placed box's
     * height — FPDF cells don't auto-shrink text, so an unusually short
     * box would otherwise overflow.
     */
    private function fontSizeToFit(float $boxHeightMm): int
    {
        return (int) max(8, min(24, $boxHeightMm * 2));
    }
}
