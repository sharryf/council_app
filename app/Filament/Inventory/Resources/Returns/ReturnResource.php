<?php

namespace App\Filament\Inventory\Resources\Returns;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryReturnCondition;
use App\Enums\InventoryReturnStatus;
use App\Enums\InventorySourceType;
use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\Returns\Pages\EditReturn;
use App\Filament\Inventory\Resources\Returns\Pages\ListReturns;
use App\Filament\Inventory\Resources\Returns\Pages\ViewReturn;
use App\Filament\Inventory\Resources\Returns\Schemas\ReturnForm;
use App\Filament\Inventory\Resources\Returns\Schemas\ReturnInfolist;
use App\Filament\Inventory\Resources\Returns\Tables\ReturnsTable;
use App\Models\InventoryAuditLog;
use App\Models\InventoryIssueRequestLine;
use App\Models\InventorySetting;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStockReturn;
use App\Models\User;
use App\Services\Inventory\DocumentLock;
use App\Services\Inventory\InventorySequenceService;
use App\Services\Inventory\StockMovementService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Spec 8.5 — no approval step (see InventoryStockReturn's own schema:
 * no approved_by column, and the spec's API surface has no /approve
 * endpoint for returns, unlike adjustments'). Draft -> Posted ->
 * Reversed, direct post.
 */
class ReturnResource extends Resource
{
    use HasInventoryRoleAccess;

    protected static ?string $model = InventoryStockReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?string $navigationLabel = 'Returns';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'return';

    protected static ?string $pluralModelLabel = 'returns';

    /**
     * Stock Admin or above — a plain User has no reason to see returns
     * processing (they only ever raise/track their own Issue Requests,
     * per the module's own role design).
     */
    public static function canAccess(): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    public static function canCreate(): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    public static function canEdit(Model $record): bool
    {
        /** @var InventoryStockReturn $record */
        return self::userIsStockAdminOrAbove() && $record->status === InventoryReturnStatus::Draft;
    }

    public static function canDelete(Model $record): bool
    {
        /** @var InventoryStockReturn $record */
        return self::userIsStockAdminOrAbove() && $record->status === InventoryReturnStatus::Draft;
    }

    public static function form(Schema $schema): Schema
    {
        return ReturnForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReturnInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReturnsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReturns::route('/'),
            'view' => ViewReturn::route('/{record}'),
            'edit' => EditReturn::route('/{record}/edit'),
        ];
    }

    /**
     * Reused by the list page's header action and the empty-state
     * action — same pattern as IssueRequestResource::createAction() and
     * GoodsReceiptResource::createAction(). No 'create' page route
     * exists, so CreateAction renders as a modal instead of navigating
     * away.
     */
    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->createAnother(false)
            ->mutateFormDataUsing(function (array $data): array {
                $data['return_no'] = app(InventorySequenceService::class)->next('RET');
                $data['status'] = InventoryReturnStatus::Draft;
                $data['returned_by'] = auth()->id();

                return $data;
            })
            ->successRedirectUrl(fn (InventoryStockReturn $record): string => static::getUrl('view', ['record' => $record]));
    }

    /**
     * Spec 8.5 step 3: Good lines create a RETURN movement and
     * increase on_hand; Damaged lines are recorded but create no
     * movement. Both increase issue_request_lines.returned_qty when
     * linked to a source request line.
     */
    public static function postAction(): Action
    {
        return Action::make('post')
            ->label('Post')
            ->icon(Heroicon::OutlinedCheck)
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('Posted returns cannot be edited, only reversed.')
            ->visible(fn (InventoryStockReturn $record): bool => self::userIsStockAdminOrAbove() && $record->status === InventoryReturnStatus::Draft)
            ->action(function (InventoryStockReturn $record): void {
                DocumentLock::once("return:{$record->id}", function () use ($record) {
                    $record->refresh();

                    if ($record->status !== InventoryReturnStatus::Draft) {
                        return;
                    }

                    $record->loadMissing(['lines.item', 'lines.issueLine', 'location']);

                    if ($record->lines->isEmpty()) {
                        Notification::make()->title('Add at least one line before posting.')->danger()->send();

                        return;
                    }

                    $inactiveItem = $record->lines->first(fn ($line) => ! $line->item->is_active);
                    if ($inactiveItem) {
                        Notification::make()->title("{$inactiveItem->item->code} is inactive and cannot be returned.")->danger()->send();

                        return;
                    }

                    $lockDate = InventorySetting::get('posting_lock_date');
                    if (filled($lockDate) && $record->return_date->lt(\Illuminate\Support\Carbon::parse($lockDate))) {
                        Notification::make()->title("Return date is before the posting lock date ({$lockDate}).")->danger()->send();

                        return;
                    }

                    // Re-checked server-side, not just at form pre-fill time
                    // — the source line's own outstanding balance may have
                    // moved since (e.g. another return posted meanwhile).
                    $overCapped = $record->lines->first(function ($line): bool {
                        if (! $line->issueLine) {
                            return false;
                        }

                        $remaining = bcsub((string) $line->issueLine->issued_qty, (string) $line->issueLine->returned_qty, 3);

                        return bccomp((string) $line->quantity, $remaining, 3) > 0;
                    });

                    if ($overCapped) {
                        Notification::make()->title("{$overCapped->item->code}: quantity exceeds what remains to be returned on the source request.")->danger()->send();

                        return;
                    }

                    $performer = auth()->user();
                    /** @var User $performer */
                    $movements = app(StockMovementService::class);

                    try {
                        DB::transaction(function () use ($record, $movements, $performer) {
                            foreach ($record->lines->sortBy('item_id') as $line) {
                                if ($line->condition === InventoryReturnCondition::Good) {
                                    $movements->record(
                                        item: $line->item,
                                        location: $record->location,
                                        type: InventoryMovementType::Return,
                                        quantity: (string) $line->quantity,
                                        performer: $performer,
                                        sourceType: InventorySourceType::Return,
                                        sourceId: $record->id,
                                        sourceLineId: $line->id,
                                        sourceNo: $record->return_no,
                                        remarks: $record->reason,
                                    );
                                }

                                if ($line->issue_line_id) {
                                    InventoryIssueRequestLine::query()->whereKey($line->issue_line_id)
                                        ->increment('returned_qty', $line->quantity);
                                }
                            }

                            $record->update([
                                'status' => InventoryReturnStatus::Posted,
                                'received_by' => $performer->id,
                                'posted_at' => now(),
                            ]);
                        });
                    } catch (\InvalidArgumentException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return;
                    }

                    InventoryAuditLog::write('RETURN', $record->id, 'POST', ['status' => 'DRAFT'], ['status' => 'POSTED']);

                    Notification::make()->title("{$record->return_no} posted.")->success()->send();
                });
            });
    }

    public static function reverseAction(): Action
    {
        return Action::make('reverse')
            ->label('Reverse')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (InventoryStockReturn $record): bool => self::userIsStockAdminOrAbove() && $record->status === InventoryReturnStatus::Posted)
            ->schema([
                Textarea::make('reason')->label('Reversal reason')->required(),
            ])
            ->action(function (InventoryStockReturn $record, array $data): void {
                DocumentLock::once("return:{$record->id}", function () use ($record, $data) {
                    $record->refresh();

                    if ($record->status !== InventoryReturnStatus::Posted) {
                        return;
                    }

                    $performer = auth()->user();
                    /** @var User $performer */
                    $movements = app(StockMovementService::class);

                    try {
                        DB::transaction(function () use ($record, $movements, $performer, $data) {
                            $record->loadMissing('lines');

                            foreach ($record->lines as $line) {
                                if ($line->condition === InventoryReturnCondition::Good) {
                                    $movement = InventoryStockMovement::query()
                                        ->where('source_type', InventorySourceType::Return)
                                        ->where('source_id', $record->id)
                                        ->where('source_line_id', $line->id)
                                        ->sole();

                                    $movements->reverse($movement, $performer, $data['reason']);
                                }

                                if ($line->issue_line_id) {
                                    InventoryIssueRequestLine::query()->whereKey($line->issue_line_id)
                                        ->decrement('returned_qty', $line->quantity);
                                }
                            }

                            $record->update(['status' => InventoryReturnStatus::Reversed]);
                        });
                    } catch (\InvalidArgumentException $exception) {
                        Notification::make()->title("Cannot reverse: {$exception->getMessage()}")->danger()->send();

                        return;
                    }

                    InventoryAuditLog::write('RETURN', $record->id, 'REVERSE', ['status' => 'POSTED'], ['status' => 'REVERSED', 'reason' => $data['reason']]);

                    Notification::make()->title("{$record->return_no} reversed.")->warning()->send();
                });
            });
    }
}
