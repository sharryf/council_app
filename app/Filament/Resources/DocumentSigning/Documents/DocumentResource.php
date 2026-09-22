<?php

namespace App\Filament\Resources\DocumentSigning\Documents;

use App\Enums\DocumentSigningRole;
use App\Enums\DocumentStatus;
use App\Enums\SignatureType;
use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\DocumentSigning\Documents\Pages\CreateDocument;
use App\Filament\Resources\DocumentSigning\Documents\Pages\EditDocument;
use App\Filament\Resources\DocumentSigning\Documents\Pages\ListDocuments;
use App\Filament\Resources\DocumentSigning\Documents\Pages\ViewDocument;
use App\Filament\Resources\DocumentSigning\Documents\Schemas\DocumentForm;
use App\Filament\Resources\DocumentSigning\Documents\Schemas\DocumentInfolist;
use App\Filament\Resources\DocumentSigning\Documents\Tables\DocumentsTable;
use App\Models\Document;
use App\Models\DocumentSignerPlacement;
use App\Models\DocumentSigningOrganization;
use App\Models\DocumentStamp;
use App\Models\User;
use App\Services\DocumentSigning\DocumentSigningService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentResource extends Resource
{
    use HasModuleAccess;

    protected static ?string $moduleKey = 'document-signing';

    protected static ?string $model = Document::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'Documents';

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return DocumentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DocumentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentsTable::configure($table);
    }

    /**
     * Title is optional in the upload wizard (see CreateDocument) — fall
     * back to the original filename anywhere Filament shows a record's
     * title (breadcrumbs, global search) rather than a blank string.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof Document ? $record->displayTitle() : parent::getRecordTitle($record);
    }

    /**
     * Without the Viewer role, a user is scoped to documents they're
     * personally involved with — uploaded (Editor) or a listed signer
     * on (Signee) — everywhere a query resolves a Document: the list
     * table, and also View/Edit route-model-binding, which don't
     * otherwise check per-record visibility (see
     * Filament\Resources\Resource::getEloquentQuery()'s own security
     * note — this is that override).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        /** @var User $user */
        $user = auth()->user();

        if ($user->hasDocumentSigningRole(DocumentSigningRole::Viewer)) {
            return $query;
        }

        return $query->where(function ($scoped) use ($user) {
            $scoped->where('uploaded_by', $user->id)
                ->orWhereHas('signers', fn ($signers) => $signers->where('user_id', $user->id));
        });
    }

    /**
     * The Editor role, per App\Enums\DocumentSigningRole — this module
     * defines its own role vocabulary rather than using the generic
     * Viewer/Editor/Approver ranking (App\Filament\Concerns\HasModuleAccess's
     * default), since "roles are different for each app".
     */
    public static function canCreate(): bool
    {
        return auth()->user()?->hasDocumentSigningRole(DocumentSigningRole::Editor) ?? false;
    }

    /**
     * Editor role, the uploader, and only while it's still a Draft —
     * once submitted, its content and signer list are locked so that
     * what each signer reviewed is what they actually signed.
     */
    public static function canEdit(Model $record): bool
    {
        return static::canCreate()
            && $record->status === DocumentStatus::Draft
            && $record->uploaded_by === auth()->id();
    }

    /**
     * Once a document leaves Draft it's part of the signing audit
     * trail — deleting it would destroy that record, so only an
     * untouched Draft can be removed, and only by its uploader.
     */
    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'view' => ViewDocument::route('/{record}'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }

    public static function submitAction(): Action
    {
        return Action::make('submit')
            ->label('Submit for signing')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('Signers will be able to review and sign once submitted. The document and signer list can no longer be edited after this.')
            ->visible(fn (Document $record): bool => static::canCreate()
                && $record->status === DocumentStatus::Draft
                && $record->uploaded_by === auth()->id())
            ->action(function (Document $record): void {
                app(DocumentSigningService::class)->submit($record);

                Notification::make()->title('Submitted for signing')->success()->send();
            });
    }

    /**
     * No signature-type choice here — the previous Drawn/Typed/Saved
     * toggle was removed in favor of just using whatever signature a
     * signee already has: their default saved profile signature
     * (`User::defaultSignaturePath()`, see [Navigation & profile]) if
     * they have one, otherwise their account name stands in as a typed
     * signature. There's no in-the-moment drawing or free-text override
     * anymore — a signee who wants a drawn signature sets one up once
     * on their profile page and every document then uses it
     * automatically.
     */
    public static function signAction(): Action
    {
        return Action::make('sign')
            ->label('Sign')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('primary')
            ->visible(fn (Document $record): bool => (auth()->user()?->hasDocumentSigningRole(DocumentSigningRole::Signee) ?? false)
                && app(DocumentSigningService::class)->canSign($record, auth()->user()))
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading('Sign document')
            ->modalSubmitActionLabel('Sign')
            // A "Reject" button that opens its own fully independent
            // modal (with its own schema/confirmation) isn't something
            // extraModalFooterActions actually supports — it exists for
            // *variants of this same mounted action* (see
            // Filament\Actions\AttachAction's "Attach another", the
            // reference this is modeled on): makeModalSubmitAction()
            // re-runs THIS action's own ->action() closure below, just
            // with different $arguments, rather than mounting a second,
            // separate action. So Reject-from-inside-Sign shares this
            // modal's own `reason` field instead of the standalone
            // rejectAction()'s — validated manually in ->action() below
            // since it's only required for the reject variant.
            ->extraModalFooterActions(fn (Action $action, Document $record): array => [
                $action->makeModalSubmitAction('rejectFromSign', ['reject' => true])
                    ->label('Reject')
                    ->color('danger')
                    ->visible(fn (): bool => app(DocumentSigningService::class)->canReject($record, auth()->user())),
            ])
            ->schema(fn (Document $record): array => [
                View::make('filament.resources.document-signing.documents.partials.document-preview')
                    ->viewData(fn (): array => [
                        'fileUrl' => route('documents.download.original', $record),
                        'highlight' => static::signerHighlight($record, auth()->user()),
                        'stamps' => static::stampBoxes($record),
                    ]),
                Textarea::make('reason')
                    ->label('Reason for rejection')
                    ->helperText('Only needed if you reject instead of signing.')
                    ->rows(2),
            ])
            ->action(function (Schema $schema, Document $record, array $data, array $arguments): void {
                $user = auth()->user();

                if ($arguments['reject'] ?? false) {
                    if (blank($data['reason'] ?? null)) {
                        // The field's error needs to be keyed by its full
                        // Livewire state path (e.g. "mountedActions.0.data.reason"),
                        // not just "reason" — Filament's schema components
                        // look errors up by their own statePath, and a
                        // plain "reason" key silently goes nowhere (no
                        // inline error, no toast) since it never matches.
                        throw ValidationException::withMessages([
                            $schema->getStatePath().'.reason' => 'A reason is required to reject.',
                        ]);
                    }

                    app(DocumentSigningService::class)->reject($record, $user, $data['reason']);

                    Notification::make()->title('Document rejected')->warning()->send();

                    return;
                }

                if ($user->hasSignature()) {
                    $type = SignatureType::Saved;
                    $value = static::copySavedSignature($record, $user);
                } else {
                    $type = SignatureType::Typed;
                    $value = $user->name;
                }

                app(DocumentSigningService::class)->sign($record, $user, $type, $value);

                Notification::make()->title('Signed successfully')->success()->send();
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Document $record): bool => (auth()->user()?->hasDocumentSigningRole(DocumentSigningRole::Signee) ?? false)
                && app(DocumentSigningService::class)->canReject($record, auth()->user()))
            ->schema([
                Textarea::make('reason')
                    ->label('Reason for rejection')
                    ->required(),
            ])
            ->action(function (Document $record, array $data): void {
                app(DocumentSigningService::class)->reject($record, auth()->user(), $data['reason']);

                Notification::make()->title('Document rejected')->warning()->send();
            });
    }

    /**
     * An administrative cancellation of a Pending document — for
     * "wrong file, already sent to signers" situations that Reject
     * (always a specific signer's own objection) doesn't cover. Scoped
     * to the document's own uploader (Editor role, same ownership check
     * as canEdit()) or a system-wide `admin` — deliberately the
     * system-wide role here, not this module's own DocumentSigningRole::Admin,
     * which grants nothing document-related by itself (see the
     * Settings pages it actually governs).
     */
    public static function voidAction(): Action
    {
        return Action::make('void')
            ->label('Void')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('This cancels the document without deleting it — signers can no longer sign or reject it. Use this for a document that was sent to the wrong signers or contains the wrong file.')
            ->visible(fn (Document $record): bool => app(DocumentSigningService::class)->canVoid($record)
                && $record->uploaded_by === auth()->id()
                && static::canCreate())
            ->schema([
                Textarea::make('reason')
                    ->label('Reason for voiding')
                    ->required(),
            ])
            ->action(function (Document $record, array $data): void {
                app(DocumentSigningService::class)->void($record, auth()->user(), $data['reason']);

                Notification::make()->title('Document voided')->success()->send();
            });
    }

    public static function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading(fn (Document $record): string => $record->displayTitle())
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(fn (Document $record) => view('filament.resources.document-signing.documents.partials.document-preview', [
                'fileUrl' => $record->status === DocumentStatus::Signed && $record->signed_file_path
                    ? route('documents.download.signed', $record)
                    : route('documents.download.original', $record),
                'highlight' => [],
                'stamps' => static::stampBoxes($record),
            ]));
    }

    /**
     * Placeholder outlines for where the organization stamp will land.
     * The real stamp image is only composited into the PDF's actual
     * pixels once the document reaches Signed (see
     * DocumentStampService) — before that, a preview shows an outline
     * instead. Once Signed, previewAction() already points at the
     * signed copy, which has the real stamp baked in, so no overlay is
     * needed (and would just double up on top of the real thing).
     *
     * @return array<int, array{page: int, x: float, y: float, w: float, h: float, label: string, color: string}>
     */
    private static function stampBoxes(Document $record): array
    {
        if ($record->status === DocumentStatus::Signed) {
            return [];
        }

        $organization = DocumentSigningOrganization::current();

        return $record->stamps
            ->map(fn (DocumentStamp $stamp): array => [
                'page' => $stamp->page_number,
                'x' => $stamp->position_x,
                'y' => $stamp->position_y,
                'w' => $stamp->box_width,
                'h' => $stamp->box_height,
                'label' => $organization->stampLabelForSlot($stamp->stamp_slot),
                'color' => '#B8934A',
            ])
            ->all();
    }

    /**
     * Every spot the signed-in user's own signature will land, so
     * signAction()'s preview can point them all out before they commit
     * to signing — a blank "type your name" box a page or two into the
     * document is easy to miss otherwise. A signer can have more than
     * one placement (e.g. initials on every page plus a full signature
     * on the last) — same shape as stampBoxes() below, just scoped to
     * this one signer. Empty when they have no placement at all (e.g. a
     * document created before the wizard existed).
     *
     * @return array<int, array{page: int, x: float, y: float, w: float, h: float, label: string, color: string}>
     */
    private static function signerHighlight(Document $record, ?User $user): array
    {
        $signer = $user ? app(DocumentSigningService::class)->signerFor($record, $user) : null;

        if (! $signer) {
            return [];
        }

        return $signer->placements
            ->map(fn (DocumentSignerPlacement $placement): array => [
                'page' => $placement->page_number,
                'x' => $placement->position_x,
                'y' => $placement->position_y,
                'w' => $placement->box_width,
                'h' => $placement->box_height,
                'label' => 'You sign here',
                'color' => '#0E7A82',
            ])
            ->all();
    }

    public static function downloadOriginalAction(): Action
    {
        return Action::make('downloadOriginal')
            ->label('Download original')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->url(fn (Document $record): string => route('documents.download.original', $record))
            ->openUrlInNewTab();
    }

    public static function downloadSignedAction(): Action
    {
        return Action::make('downloadSigned')
            ->label('Download Signed')
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->color('primary')
            ->visible(fn (Document $record): bool => $record->status === DocumentStatus::Signed)
            ->url(fn (Document $record): string => route('documents.download.signed', $record))
            ->openUrlInNewTab();
    }

    /**
     * Filament's plain CreateAction / EditAction / DeleteBulkAction
     * don't automatically consult Resource::canCreate() / canEdit() /
     * canDelete() the way the actual Create/Edit *page* routes do —
     * those only protect direct URL access, not these buttons'
     * visibility. Without this, a Viewer would still see a clickable
     * "New document" or Edit button that then 403s. Use these
     * factories instead of the bare Filament classes wherever this
     * resource shows a create/edit/delete control.
     */
    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->visible(fn (): bool => static::canCreate());
    }

    public static function editAction(): EditAction
    {
        return EditAction::make()
            ->visible(fn (Document $record): bool => static::canEdit($record));
    }

    /**
     * The "Delete selected" button itself can't be made to hide/show
     * based on whether the current selection is actually deletable —
     * table row selection is pure client-side Alpine state between
     * Livewire round-trips, and the only PHP-level hook that could gate
     * the button (hidden()) is also what Filament's isSelectionEnabled()
     * consults to decide whether to render row checkboxes AT ALL, since
     * it isn't given the current selection to distinguish "nothing
     * selected yet" from "something selected but none of it qualifies".
     * Tying it to selection state either hides checkboxes outright
     * (before anything is selected) or, once a real round-trip happens
     * with an unauthorized-only selection, wipes out the whole bulk
     * toolbar for the rest of that page load. So the button stays
     * visible whenever anything is selected, same as Filament's
     * default — authorizeIndividualRecords() is what actually protects
     * the data: it silently excludes non-Draft records from the delete
     * and Filament shows its own "you don't have permission to delete
     * :count" notification for the ones that got skipped.
     */
    public static function deleteBulkAction(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->authorizeIndividualRecords(fn (Document $record): bool => static::canDelete($record));
    }

    /**
     * Copies the user's profile signature (see
     * App\Filament\Pages\EditProfile) into this document's own
     * signature folder at the moment of signing, rather than pointing
     * at the profile file directly — so a later change to the user's
     * saved signature can never alter what an already-signed document
     * shows or hashes.
     */
    private static function copySavedSignature(Document $document, User $user): string
    {
        $path = "documents/{$document->id}/signatures/{$user->id}-".now()->timestamp.'.png';
        Storage::disk('local')->put($path, Storage::disk('local')->get($user->defaultSignaturePath()));

        return $path;
    }
}
