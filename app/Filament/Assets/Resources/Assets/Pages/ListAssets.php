<?php

namespace App\Filament\Assets\Resources\Assets\Pages;

use App\Filament\Assets\Resources\Assets\AssetResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Attributes\Url;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    /**
     * Lets the dashboard's per-status stat cards deep-link precisely
     * (?status=in_use) — ?tableFilters[status][values][]=... does NOT
     * reliably apply on load (confirmed empirically elsewhere in this
     * app), so this mirrors the same #[Url]-bound-property workaround
     * used for Inventory's dashboard deep-links.
     */
    #[Url(as: 'status')]
    public ?string $dashboardStatusFilter = null;

    protected function getHeaderActions(): array
    {
        return [
            // Mirrors whatever filters are currently active on this
            // table (see the controller's own doc comment) so the
            // export matches what's on screen (spec section 6.2/8).
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url(fn (): string => route('assets.export.assets', ['filters' => $this->tableFilters])),
            CreateAction::make()
                ->visible(fn (): bool => AssetResource::canCreate()),
        ];
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        return parent::getTableQuery()
            ?->when($this->dashboardStatusFilter, fn (Builder $query, string $status) => $query->where('status', $status));
    }
}
