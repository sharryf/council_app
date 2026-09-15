<?php

namespace App\Filament\Assets\Resources\Audits;

use App\Enums\AssetAuditSessionStatus;
use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Filament\Assets\Resources\Audits\Pages\CreateAssetAuditSession;
use App\Filament\Assets\Resources\Audits\Pages\ListAssetAuditSessions;
use App\Filament\Assets\Resources\Audits\Pages\ViewAssetAuditSession;
use App\Filament\Assets\Resources\Audits\Schemas\AssetAuditSessionForm;
use App\Filament\Assets\Resources\Audits\Tables\AssetAuditSessionsTable;
use App\Models\AssetAuditSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Spec section 11 / implementation plan section 3.8. Sessions are only
 * ever created via CreateAssetAuditSession's scope-snapshot flow and
 * decided (verify/close/review) via ViewAssetAuditSession's live
 * workstation — no generic edit form.
 */
class AssetAuditSessionResource extends Resource
{
    use HasAssetRoleAccess;

    protected static ?string $model = AssetAuditSession::class;

    protected static ?string $slug = 'asset-audits';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Audits';

    protected static ?string $modelLabel = 'audit session';

    protected static ?string $pluralModelLabel = 'audit sessions';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return self::userHasAnyAssetRole();
    }

    /**
     * Admin and Manager may start a session (spec section 4: both
     * checked on "Start/close audit session").
     */
    public static function canCreate(): bool
    {
        return self::userIsAssetAdminOrManager();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        /** @var AssetAuditSession $record */
        return self::userIsAssetAdminOrManager() && $record->status !== AssetAuditSessionStatus::InProgress;
    }

    public static function form(Schema $schema): Schema
    {
        return AssetAuditSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetAuditSessionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetAuditSessions::route('/'),
            'create' => CreateAssetAuditSession::route('/create'),
            'view' => ViewAssetAuditSession::route('/{record}'),
        ];
    }
}
