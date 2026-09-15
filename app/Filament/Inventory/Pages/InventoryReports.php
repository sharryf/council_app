<?php

namespace App\Filament\Inventory\Pages;

use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\Reports\Tables\IssueSummaryReportTable;
use App\Filament\Inventory\Resources\Reports\Tables\ReorderReportTable;
use App\Filament\Inventory\Resources\Reports\Tables\StockBalanceReportTable;
use App\Filament\Inventory\Resources\Reports\Tables\StockMovementReportTable;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\Url;

/**
 * Spec 14 — 4 of the 10 listed reports (Stock Balance, Stock Movement/
 * Item Ledger, Reorder, Issue Summary — see the Phase 8 plan's own
 * "explicitly not built this phase" list for the other 6 and why),
 * CSV export only. One page switching between four read-only tables
 * rather than four separate resources, since none of them create/
 * edit/delete anything.
 */
class InventoryReports extends Page implements HasTable
{
    use HasInventoryRoleAccess;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 8;

    protected Width|string|null $maxWidth = Width::Full;

    /**
     * URL-bound so the dashboard's Low Stock card can deep-link straight
     * into the Reorder Report tab (?activeReport=reorder) instead of
     * always landing on Stock Balance.
     */
    #[Url]
    public string $activeReport = 'stock-balance';

    /**
     * @var array<string, string>
     */
    private const REPORTS = [
        'stock-balance' => 'Stock Balance',
        'stock-movement' => 'Stock Movement',
        'reorder' => 'Reorder Report',
        'issue-summary' => 'Issue Summary',
    ];

    public static function canAccess(): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return 'Reports';
    }

    public function table(Table $table): Table
    {
        return match ($this->activeReport) {
            'stock-movement' => StockMovementReportTable::configure($table),
            'reorder' => ReorderReportTable::configure($table),
            'issue-summary' => IssueSummaryReportTable::configure($table),
            default => StockBalanceReportTable::configure($table),
        };
    }

    public function setActiveReport(string $report): void
    {
        $this->activeReport = array_key_exists($report, self::REPORTS) ? $report : 'stock-balance';

        // table() builds a fresh Table on every call, but
        // InteractsWithTable caches the result per component lifetime
        // — without this, switching tabs updates $activeReport but the
        // rendered table (and its filters/columns) stays whichever
        // report was active on first load.
        $this->resetTable();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Actions::make(collect(self::REPORTS)->map(fn (string $label, string $key): Action => Action::make($key)
                ->label($label)
                ->color(fn (): string => $this->activeReport === $key ? 'primary' : 'gray')
                ->action(fn () => $this->setActiveReport($key)))->values()->all()),
            Actions::make([
                Action::make('exportCsv')
                    ->label('Export CSV')
                    ->color('success')
                    ->outlined()
                    ->url(fn (): string => route('inventory.reports.export', [
                        'report' => $this->activeReport,
                        'filters' => $this->tableFilters,
                    ])),
                Action::make('exportPdf')
                    ->label('Export PDF')
                    ->color('danger')
                    ->outlined()
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(fn (): string => route('inventory.reports.export-pdf', [
                        'report' => $this->activeReport,
                        'filters' => $this->tableFilters,
                    ]))
                    ->openUrlInNewTab(),
            ])->alignEnd(),
            EmbeddedTable::make(),
        ]);
    }
}
