<?php

namespace App\Filament\Inventory\Pages;

use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Models\InventoryAuditLog;
use App\Models\InventorySetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * Spec 10.10's "Settings → System": a grouped form over the
 * inventory_settings key/value table, admin-only. Not a fixed-column
 * singleton page like Bureau's BureauMeetingSettings (that one binds
 * named model attributes) — the field set here is built dynamically
 * from whatever rows/categories exist, since inventory_settings is a
 * real key/value table rather than a handful of known columns.
 *
 * Saving here is deliberately the only place this module writes to
 * inventory_settings after the initial seed — InventorySetting's own
 * saved() hook clears the settings cache automatically, so nothing
 * here has to remember to invalidate it by hand.
 */
class InventorySettingsPage extends Page
{
    use HasInventoryRoleAccess;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'System';

    protected static ?int $navigationSort = 20;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return self::userIsAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return 'System Settings';
    }

    /**
     * Boolean settings must be filled with a real PHP boolean, not the
     * stored 'true'/'false' string — PHP casts any non-empty string
     * (including the literal 'false') to true, so filling a Toggle with
     * the raw string left every boolean setting displaying as ON
     * regardless of its actual stored value.
     */
    public function mount(): void
    {
        $this->form->fill(
            InventorySetting::all()
                ->mapWithKeys(fn (InventorySetting $setting): array => [
                    $setting->key => $setting->data_type === 'boolean' ? $setting->value === 'true' : $setting->value,
                ])
                ->all(),
        );
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    /**
     * Seeded from the spec's full settings list (section 15), but not
     * every one of those ever got wired to real behaviour in this build
     * — these read from nowhere in the app, so showing them as live
     * toggles/fields would mislead whoever's changing settings. Left in
     * the inventory_settings table (harmless, and cheap to wire up
     * later) — just not rendered here.
     */
    private const UNUSED_KEYS = [
        'default_approver_id',
        'allowed_attachment_types',
        'date_format',
        'require_purpose_on_request',
        'item_code_prefix_by_category',
        'low_stock_critical_ratio',
        'reorder_alert_enabled',
        'reorder_basis',
        'reorder_digest_time',
        'slow_moving_days',
        // Adjustments now always require approval (approving posts
        // immediately, in one step) — these two no longer gate
        // anything.
        'stock_take_requires_approval',
        'require_approval_for_adjustments',
    ];

    /**
     * Settings render in DB key order by default (alphabetical, since
     * inventory_settings has no natural sequence column) — fine for
     * every category except 'general', where that split the header/
     * footer PDF image uploads onto separate rows of the 2-column
     * grid. Pins just that category's order so the pair lands
     * side-by-side.
     */
    private const GENERAL_FIELD_ORDER = ['company_name', 'timezone', 'pdf_header_image', 'pdf_footer_image'];

    public function form(Schema $schema): Schema
    {
        $sections = InventorySetting::all()
            ->reject(fn (InventorySetting $setting): bool => in_array($setting->key, self::UNUSED_KEYS, true))
            ->groupBy('category')
            ->map(fn ($settings, string $category): Component => Section::make(Str::of($category)->replace('_', ' ')->title()->toString())
                ->columns(2)
                ->schema($settings
                    ->when($category === 'general', fn ($settings) => $settings->sortBy(function (InventorySetting $setting): int {
                        $position = array_search($setting->key, self::GENERAL_FIELD_ORDER, true);

                        return $position === false ? PHP_INT_MAX : $position;
                    }))
                    ->map(fn (InventorySetting $setting): Component => $this->fieldFor($setting))
                    ->values()
                    ->all()))
            ->values()
            ->all();

        return $schema->components($sections);
    }

    private function fieldFor(InventorySetting $setting): Component
    {
        $label = Str::of($setting->key)->replace('_', ' ')->title()->toString();

        $field = match (true) {
            // A free-text timezone field is one typo away from breaking
            // every displayed date/time across the module — a searchable
            // Select of real IANA identifiers can't produce an invalid
            // value in the first place.
            $setting->key === 'timezone' => Select::make($setting->key)
                ->label($label)
                ->searchable()
                ->required()
                ->options(array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers())),
            $setting->data_type === 'image' => FileUpload::make($setting->key)
                ->label($label)
                ->image()
                ->acceptedFileTypes(['image/png'])
                ->disk('local')
                ->directory('inventory/settings')
                ->visibility('private')
                ->imagePreviewHeight('60'),
            $setting->data_type === 'boolean' => Toggle::make($setting->key)->label($label)->inline(false),
            $setting->data_type === 'integer' => TextInput::make($setting->key)->label($label)->numeric(),
            $setting->data_type === 'decimal' => TextInput::make($setting->key)->label($label)->numeric()->step('0.01'),
            $setting->data_type === 'json' => Textarea::make($setting->key)->label($label)->rows(2)->rules(['nullable', 'json'])->columnSpanFull(),
            default => TextInput::make($setting->key)->label($label),
        };

        return filled($setting->description) ? $field->helperText($setting->description) : $field;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save')
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    /**
     * Boolean fields dehydrate as real PHP booleans (Toggle's native
     * state) — normalized back to the 'true'/'false' string convention
     * every setting is stored and read as (see
     * InventorySetting::getBool()) before comparing/saving, so a
     * boolean round-trip never shows up as a spurious diff.
     */
    public function save(): void
    {
        $state = $this->form->getState();
        $settings = InventorySetting::all()->keyBy('key');

        $old = [];
        $new = [];

        foreach ($state as $key => $value) {
            $setting = $settings->get($key);

            if (! $setting) {
                continue;
            }

            $normalized = $setting->data_type === 'boolean' ? ($value ? 'true' : 'false') : (string) ($value ?? '');

            if ($setting->value !== $normalized) {
                $old[$key] = $setting->value;
                $new[$key] = $normalized;
                $setting->update(['value' => $normalized, 'updated_by' => auth()->id()]);
            }
        }

        if ($new !== []) {
            InventoryAuditLog::write('SETTINGS', 0, 'UPDATE', $old, $new); // no single numeric row id for a batch settings save
        }

        Notification::make()->title('Settings updated')->success()->send();
    }
}
