<?php

namespace App\Filament\Inventory\Resources\IssueRequests\Pages;

use App\Enums\InventoryIssueRequestLineStatus;
use App\Enums\InventoryIssueRequestStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventorySourceType;
use App\Filament\Inventory\Resources\IssueRequests\IssueRequestResource;
use App\Models\InventoryIssueRequest;
use App\Models\InventoryIssueRequestLine;
use App\Models\InventorySetting;
use App\Models\User;
use App\Services\Inventory\DocumentLock;
use App\Services\Inventory\StockMovementService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use InvalidArgumentException;

/**
 * Spec 10.6's storekeeper screen: lines sorted by item name, an
 * editable Issue Now quantity per line, receiver name + optional
 * signature. A dedicated page (not a modal action) for the same reason
 * Bureau's RecordMinutes is one — this is real per-line data entry, not
 * a single confirmation. Unlike RecordMinutes, this has no Filament
 * Schema either — plain public Livewire properties bound via
 * wire:model in the Blade view, the same lighter-weight approach
 * RecordMinutes itself uses throughout.
 */
class IssueGoods extends Page
{
    use InteractsWithRecord;

    protected static string $resource = IssueRequestResource::class;

    protected string $view = 'filament.inventory.pages.issue-goods';

    /** @var array<int, string> line id => quantity to issue now */
    public array $issueQuantities = [];

    public string $receivedByName = '';

    public ?string $signatureDataUrl = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(IssueRequestResource::canIssue($this->getRequest()), 403);

        $this->receivedByName = $this->getRequest()->received_by_name
            ?: ($this->getRequest()->recipient?->name ?? $this->getRequest()->requester->name);

        foreach ($this->linesForIssue() as $line) {
            $remaining = bcsub((string) ($line->approved_qty ?? $line->requested_qty), (string) $line->issued_qty, 3);
            $this->issueQuantities[$line->id] = Number::format((float) (bccomp($remaining, '0', 3) > 0 ? $remaining : '0'));
        }
    }

    public function getRequest(): InventoryIssueRequest
    {
        /** @var InventoryIssueRequest $request */
        $request = $this->getRecord();

        return $request;
    }

    public function getTitle(): string
    {
        return "Issue {$this->getRequest()->request_no}";
    }

    public function isSkippingApproval(): bool
    {
        return $this->getRequest()->status === InventoryIssueRequestStatus::Submitted;
    }

    public function signatureCaptureEnabled(): bool
    {
        return InventorySetting::getBool('enable_signature_capture');
    }

    /**
     * Only lines with something left to issue, sorted by item name —
     * this store doesn't use bin locations, so there's no physical
     * walking-order to sort by (see spec 10.6's original reasoning,
     * kept here only as history).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\InventoryIssueRequestLine>
     */
    public function linesForIssue(): \Illuminate\Support\Collection
    {
        return $this->getRequest()->lines()->with('item.uom')
            ->when(
                ! $this->isSkippingApproval(),
                fn ($query) => $query->whereIn('line_status', [InventoryIssueRequestLineStatus::Approved, InventoryIssueRequestLineStatus::PartiallyIssued]),
            )
            ->get()
            ->sortBy(fn ($line) => $line->item->name)
            ->values();
    }

    /**
     * The "Issue Now" number input's step, matched to the item's UoM
     * so the up/down arrows move by whole units for a UoM like PC
     * (0 decimal places) instead of always nudging by 0.001.
     */
    public function stepFor(InventoryIssueRequestLine $line): string
    {
        $decimals = $line->item->uom?->decimal_places ?? 0;

        return bcdiv('1', bcpow('10', (string) $decimals), $decimals);
    }

    public function submitIssue(): void
    {
        abort_unless(IssueRequestResource::canIssue($this->getRequest()), 403);

        if ($this->signatureCaptureEnabled() && blank($this->signatureDataUrl)) {
            Notification::make()->title("Please capture the receiver's signature before confirming.")->danger()->send();

            return;
        }

        DocumentLock::once("issue-request:{$this->getRequest()->id}", function () {
            $this->doSubmitIssue();
        });
    }

    private function doSubmitIssue(): void
    {
        $request = $this->getRequest()->fresh();

        if (! IssueRequestResource::canIssue($request)) {
            return;
        }

        $request->loadMissing(['lines.item', 'location']);
        $skipApproval = $request->status === InventoryIssueRequestStatus::Submitted;

        $anythingToIssue = collect($this->issueQuantities)->contains(fn ($qty) => bccomp((string) $qty, '0', 3) > 0);

        if (! $anythingToIssue) {
            Notification::make()->title('Enter a quantity to issue for at least one line.')->danger()->send();

            return;
        }

        $performer = auth()->user();
        /** @var User $performer */
        $movements = app(StockMovementService::class);

        try {
            DB::transaction(function () use ($request, $movements, $performer, $skipApproval) {
                // Captured fresh per batch — never carried forward from an
                // earlier receipt, so a batch nobody re-signed for simply
                // has no signature of its own rather than borrowing
                // someone else's (the exact bug this receipt history
                // exists to fix).
                $capturedSignaturePath = null;
                if (filled($this->signatureDataUrl)) {
                    [, $encoded] = explode(',', $this->signatureDataUrl, 2);
                    $capturedSignaturePath = "inventory/issue-signatures/{$request->id}-".now()->timestamp.'.png';
                    Storage::disk('local')->put($capturedSignaturePath, base64_decode($encoded));
                }

                $receivedByName = $this->receivedByName ?: $request->received_by_name;

                $receipt = $request->receipts()->create([
                    'issued_by' => $performer->id,
                    'issued_at' => now(),
                    'received_by_name' => $receivedByName,
                    'receiver_signature_path' => $capturedSignaturePath,
                ]);

                foreach ($request->lines as $line) {
                    $qty = (string) ($this->issueQuantities[$line->id] ?? '0');

                    if (bccomp($qty, '0', 3) <= 0) {
                        continue;
                    }

                    if ($skipApproval) {
                        $line->update(['approved_qty' => $line->requested_qty, 'line_status' => InventoryIssueRequestLineStatus::Approved]);
                    }

                    $ceiling = bcsub((string) $line->approved_qty, (string) $line->issued_qty, 3);

                    if (bccomp($qty, $ceiling, 3) > 0) {
                        throw new InvalidArgumentException("Cannot issue more than the approved balance for {$line->item->code}.");
                    }

                    if (! $skipApproval) {
                        $movements->release($line->item, $request->location, $qty);
                    }

                    $movements->record(
                        item: $line->item,
                        location: $request->location,
                        type: InventoryMovementType::Issue,
                        quantity: $qty,
                        performer: $performer,
                        sourceType: InventorySourceType::Issue,
                        sourceId: $request->id,
                        sourceLineId: $line->id,
                        sourceNo: $request->request_no,
                        issueReceiptId: $receipt->id,
                    );

                    $newIssued = bcadd((string) $line->issued_qty, $qty, 3);
                    $line->update([
                        'issued_qty' => $newIssued,
                        'line_status' => bccomp($newIssued, (string) $line->approved_qty, 3) >= 0
                            ? InventoryIssueRequestLineStatus::Issued
                            : InventoryIssueRequestLineStatus::PartiallyIssued,
                    ]);
                }

                $allIssued = $request->lines()->get()->every(
                    fn ($line): bool => $line->line_status === InventoryIssueRequestLineStatus::Rejected
                        || $line->line_status === InventoryIssueRequestLineStatus::Cancelled
                        || bccomp((string) $line->issued_qty, (string) ($line->approved_qty ?? '0'), 3) >= 0
                );

                $request->update([
                    'status' => $allIssued ? InventoryIssueRequestStatus::Issued : InventoryIssueRequestStatus::PartiallyIssued,
                    'issued_by' => $performer->id,
                    'issued_at' => now(),
                    'received_by_name' => $receivedByName,
                    'receiver_signature_path' => $capturedSignaturePath ?: $request->receiver_signature_path,
                ]);

                $request->approvalActions()->create([
                    'action' => 'ISSUED',
                    'action_by' => $performer->id,
                    'action_at' => now(),
                ]);
            });
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        app(\App\Services\Inventory\InventoryNotifier::class)->itemsIssued($request->fresh());

        Notification::make()->title("{$request->request_no} issued.")->success()->send();

        $this->redirect(IssueRequestResource::getUrl('view', ['record' => $request]));
    }
}
