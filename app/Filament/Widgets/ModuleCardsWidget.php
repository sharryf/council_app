<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Dashboard grid of module cards, one per config/modules.php entry the
 * logged in user's roles grant access to. A module with no `resource`
 * class yet (or one whose class no longer exists), and no registered
 * `panel` either, renders as a disabled "Coming soon" card instead of a
 * link — see App\Filament\Concerns\HasModuleAccess for the access rule
 * itself, and config/modules.php's own doc comment for the
 * resource-vs-panel distinction.
 */
class ModuleCardsWidget extends Widget
{
    protected string $view = 'filament.widgets.module-cards';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasAnyModuleAccess();
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, icon: string, url: ?string, isBuilt: bool, level: \App\Enums\ModuleAccessLevel}>
     */
    public function getCards(): array
    {
        $user = auth()->user();
        $cards = [];

        foreach (config('modules') as $key => $module) {
            if (! $user->canAccessModule($key)) {
                continue;
            }

            $resourceClass = $module['resource'] ?? null;
            $panelId = $module['panel'] ?? null;
            $panel = $panelId ? Filament::getPanel($panelId, isStrict: false) : null;

            $isBuilt = (filled($resourceClass) && class_exists($resourceClass)) || $panel !== null;

            $url = match (true) {
                filled($resourceClass) && class_exists($resourceClass) => $resourceClass::getUrl('index'),
                $panel !== null => $panel->getUrl(),
                default => null,
            };

            $cards[] = [
                'key' => $key,
                'label' => $module['label'],
                'description' => $module['description'] ?? '',
                'icon' => $module['icon'],
                'url' => $url,
                'isBuilt' => $isBuilt,
                'level' => $user->roleFor($key),
            ];
        }

        return $cards;
    }
}
