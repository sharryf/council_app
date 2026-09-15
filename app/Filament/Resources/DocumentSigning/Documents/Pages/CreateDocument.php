<?php

namespace App\Filament\Resources\DocumentSigning\Documents\Pages;

use App\Enums\DocumentSigningRole;
use App\Enums\DocumentStatus;
use App\Filament\Resources\DocumentSigning\Documents\DocumentResource;
use App\Models\Document;
use App\Models\DocumentSigningOrganization;
use App\Models\User;
use App\Services\DocumentSigning\DocumentSigningService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\FpdiException;

/**
 * A from-scratch two-step wizard rather than Filament's usual
 * CreateRecord: step 2 is a client-heavy PDF page/placement designer
 * (canvas render + drag-and-drop, via resources/views' Alpine
 * component) that doesn't fit CreateRecord's single-form lifecycle.
 * Alpine owns all of step 2's interactive state locally (signees,
 * their placement boxes — a signee can have more than one — stamp
 * position, current page, drag) — nothing round-trips to the server
 * until sendToSign() at the very end, which receives the finished
 * signers/placements/stamps arrays as JSON and creates everything in
 * one transaction. See resources/views/filament/resources/document-signing/documents/pages/create-document.blade.php.
 */
class CreateDocument extends Page
{
    use WithFileUploads;

    protected static string $resource = DocumentResource::class;

    protected string $view = 'filament.resources.document-signing.documents.pages.create-document';

    public int $step = 1;

    public ?TemporaryUploadedFile $file = null;

    public ?string $documentTitle = null;

    public ?string $uploadedFilePath = null;

    public ?string $uploadedFileName = null;

    public int $pageCount = 1;

    public ?string $fileBase64 = null;

    public function mount(): void
    {
        abort_unless(DocumentResource::canCreate(), 403);
    }

    public function getTitle(): string
    {
        return 'New document';
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function getEligibleSignees(): array
    {
        return User::all()
            ->filter(fn (User $user): bool => $user->hasDocumentSigningRole(DocumentSigningRole::Signee))
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->values()
            ->all();
    }

    /**
     * The organization's configured stamps (see App\Models\DocumentSigningOrganization) —
     * the wizard shows one "Add stamp" control per entry here so the
     * uploader picks which one to place; a document without any of
     * these configured yet simply gets no stamp option at all.
     *
     * @return array<int, array{slot: int, label: string}>
     */
    public function getOrganizationStamps(): array
    {
        return collect(DocumentSigningOrganization::current()->availableStamps())
            ->map(fn (array $stamp): array => ['slot' => $stamp['slot'], 'label' => $stamp['label']])
            ->all();
    }

    public function continueToPlacement(): void
    {
        $this->validate([
            'file' => 'required|file|mimes:pdf|max:20480',
        ]);

        $path = $this->file->store('documents/uploads', 'local');
        $absolutePath = Storage::disk('local')->path($path);

        $pdf = new Fpdi();

        // The free FPDI parser doesn't support every PDF encoding —
        // notably some cross-reference/compression schemes used by
        // PDFs from headless-browser "print to PDF" pipelines and some
        // AI tools (only setasign's paid PDF-Parser add-on covers the
        // full range). Fail with a normal in-app notification here
        // rather than a raw 500 — this is the earliest point that can
        // ever discover a PDF FPDI can't read, so guarding it here is
        // enough; nothing downstream (placement, signing, stamping)
        // reaches a file that failed this check.
        try {
            $pageCount = $pdf->setSourceFile($absolutePath);
        } catch (FpdiException $exception) {
            Storage::disk('local')->delete($path);

            Notification::make()
                ->title('This PDF couldn\'t be read')
                ->body('It may use a compression or encryption method this app doesn\'t support. Try opening it and re-saving it — e.g. "Print to PDF" or "Save As" in a PDF viewer — then upload the new copy.')
                ->danger()
                ->send();

            return;
        }

        $this->uploadedFilePath = $path;
        $this->uploadedFileName = $this->file->getClientOriginalName();
        $this->pageCount = $pageCount;
        $this->fileBase64 = base64_encode(Storage::disk('local')->get($path));
        $this->step = 2;
    }

    public function backToUpload(): void
    {
        if ($this->uploadedFilePath) {
            Storage::disk('local')->delete($this->uploadedFilePath);
        }

        $this->uploadedFilePath = null;
        $this->uploadedFileName = null;
        $this->fileBase64 = null;
        $this->step = 1;
    }

    /**
     * @param  array<int, array{user_id: int}>  $signers
     * @param  array<int, array{user_id: int, page: int, x: float, y: float, w: float, h: float}>  $placements
     * @param  array<int, array{slot: int, page: int, x: float, y: float, w: float, h: float}>  $stamps
     */
    public function sendToSign(string $signingMode, array $signers, array $placements, array $stamps): void
    {
        abort_unless(DocumentResource::canCreate(), 403);

        if (blank($this->uploadedFilePath)) {
            Notification::make()->title('Upload a document first')->danger()->send();

            return;
        }

        if (empty($signers)) {
            Notification::make()->title('Add at least one signee before sending')->danger()->send();

            return;
        }

        // The wizard only ever lets one stamp be placed per document
        // (the "Add" buttons hide once one exists) — this is the same
        // rule enforced server-side in case of a direct/crafted call.
        $stamps = array_slice($stamps, 0, 1);

        $userIds = User::query()
            ->whereIn('id', collect($signers)->pluck('user_id'))
            ->get()
            ->keyBy('id');

        $document = DB::transaction(function () use ($signingMode, $signers, $placements, $stamps, $userIds): Document {
            $document = Document::create([
                'title' => filled($this->documentTitle) ? $this->documentTitle : null,
                'file_path' => $this->uploadedFilePath,
                'file_original_name' => $this->uploadedFileName,
                'uploaded_by' => auth()->id(),
                'signing_mode' => $signingMode === 'parallel' ? 'parallel' : 'sequential',
                'status' => DocumentStatus::Draft,
            ]);

            $signerRows = [];

            foreach ($signers as $index => $signer) {
                if (! $userIds->has($signer['user_id'])) {
                    continue;
                }

                $signerRows[$signer['user_id']] = $document->signers()->create([
                    'user_id' => $signer['user_id'],
                    'order' => $index + 1,
                    'role_label' => 'Signer',
                ]);
            }

            // A signee can have zero, one, or several placements (e.g.
            // initials on every page plus a full signature on the
            // last) — each one just points back at whichever signer
            // row it belongs to, ignoring any for a user_id that
            // didn't make it into $signerRows above.
            foreach ($placements as $placement) {
                $signerRow = $signerRows[$placement['user_id']] ?? null;

                if (! $signerRow) {
                    continue;
                }

                $signerRow->placements()->create([
                    'page_number' => $placement['page'],
                    'position_x' => $placement['x'],
                    'position_y' => $placement['y'],
                    'box_width' => $placement['w'],
                    'box_height' => $placement['h'],
                ]);
            }

            foreach ($stamps as $stamp) {
                $document->stamps()->create([
                    'stamp_slot' => $stamp['slot'] ?? 1,
                    'page_number' => $stamp['page'],
                    'position_x' => $stamp['x'],
                    'position_y' => $stamp['y'],
                    'box_width' => $stamp['w'],
                    'box_height' => $stamp['h'],
                ]);
            }

            $document->refresh()->load('signers.placements');
            app(DocumentSigningService::class)->submit($document);

            return $document;
        });

        Notification::make()->title('Sent for signing')->success()->send();

        $this->redirect(DocumentResource::getUrl('view', ['record' => $document]));
    }
}
