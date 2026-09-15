<?php

namespace Database\Seeders;

use App\Enums\DocumentSigningRole;
use App\Enums\ModuleAccessLevel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * The old sample/test accounts this roster replaces (one admin +
     * one per module-role + a no-role account, from before the
     * Viewer/Editor/Approver level system existed). Deleted up front so
     * this seeder is safe to re-run against a database that still has
     * them.
     */
    private const RETIRED_SAMPLE_EMAILS = [
        'document-signing@council.test', 'bureau@council.test', 'inventory@council.test',
        'assets@council.test', 'registries@council.test', 'permits@council.test',
        'hr-payroll@council.test', 'events@council.test', 'norole@council.test',
    ];

    /**
     * name, position, module levels (module key => level; anything
     * omitted defaults to Viewer — see User::roleFor()).
     *
     * @var array<int, array{name: string, position: string, levels: array<string, string>}>
     */
    private const ROSTER = [
        // Leadership and management.
        ['name' => 'Ibrahim Waheed', 'position' => 'Council President', 'levels' => [
            'bureau' => 'approver', 'document-signing' => 'approver', 'permits' => 'approver',
        ]],
        ['name' => 'Ahmed Shiyam', 'position' => 'Vice President', 'levels' => [
            'bureau' => 'approver', 'document-signing' => 'approver', 'permits' => 'approver',
        ]],
        ['name' => 'Hussain Rasheed', 'position' => 'Councilor', 'levels' => [
            'bureau' => 'editor', 'document-signing' => 'editor',
        ]],
        ['name' => 'Aishath Nazima', 'position' => 'Councilor', 'levels' => [
            'bureau' => 'editor', 'document-signing' => 'editor',
        ]],
        ['name' => 'Mohamed Naeem', 'position' => 'Councilor', 'levels' => [
            'bureau' => 'editor', 'document-signing' => 'editor',
        ]],
        ['name' => 'Fathimath Zuhura', 'position' => 'Secretary General', 'levels' => [
            'bureau' => 'editor', 'document-signing' => 'editor', 'registries' => 'editor',
        ]],
        ['name' => 'Ali Shareef', 'position' => 'Council Executive', 'levels' => [
            'bureau' => 'editor', 'document-signing' => 'editor', 'inventory' => 'approver',
            'assets' => 'approver', 'registries' => 'approver', 'permits' => 'editor',
            'hr-payroll' => 'approver', 'events' => 'editor',
        ]],
        ['name' => 'Mariyam Saeed', 'position' => 'Asst Council Executive', 'levels' => [
            'bureau' => 'editor', 'document-signing' => 'editor', 'inventory' => 'editor',
            'assets' => 'editor', 'registries' => 'editor', 'permits' => 'editor',
            'hr-payroll' => 'editor', 'events' => 'editor',
        ]],

        // Officers — Editor in their one listed module, Viewer elsewhere.
        ['name' => 'Adam Rasheed', 'position' => 'Senior Council Officer', 'levels' => ['document-signing' => 'editor']],
        ['name' => 'Hawwa Nasheeda', 'position' => 'Senior Council Officer', 'levels' => ['inventory' => 'editor']],
        ['name' => 'Ismail Waheed', 'position' => 'Senior Council Officer', 'levels' => ['assets' => 'editor']],
        ['name' => 'Aminath Shifa', 'position' => 'Senior Council Officer', 'levels' => ['registries' => 'editor']],
        ['name' => 'Yoosuf Naeem', 'position' => 'Senior Council Officer', 'levels' => ['hr-payroll' => 'editor']],
        ['name' => 'Zeenath Hussain', 'position' => 'Council Officer', 'levels' => ['permits' => 'editor']],
        ['name' => 'Moosa Rasheed', 'position' => 'Council Officer', 'levels' => ['permits' => 'editor']],
        ['name' => 'Rugiyya Adam', 'position' => 'Council Officer', 'levels' => ['events' => 'editor']],
        ['name' => 'Hassan Zareer', 'position' => 'Council Officer', 'levels' => ['events' => 'editor']],
        ['name' => 'Shazna Ibrahim', 'position' => 'Council Officer', 'levels' => ['registries' => 'editor']],
        ['name' => 'Abdulla Nasheed', 'position' => 'Asst Council Officer', 'levels' => ['inventory' => 'editor']],
        ['name' => 'Aminath Waheeda', 'position' => 'Asst Council Officer', 'levels' => ['bureau' => 'editor']],
    ];

    public function run(): void
    {
        User::whereIn('email', self::RETIRED_SAMPLE_EMAILS)->delete();

        $password = Hash::make('password');

        // Separate from the roster below — none of the 20 positions is
        // a system-administration role, so a dedicated technical owner
        // account keeps Users/roles manageable. See README.
        $admin = User::updateOrCreate(
            ['email' => 'admin@council.test'],
            ['name' => 'System Admin', 'password' => $password],
        );
        $admin->syncRoles(['admin']);

        foreach (self::ROSTER as $person) {
            $email = static::emailFor($person['name']);

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $person['name'],
                    'position' => $person['position'],
                    'password' => $password,
                ],
            );

            foreach ($person['levels'] as $module => $level) {
                $user->moduleLevels()->updateOrCreate(
                    ['module' => $module],
                    ['level' => ModuleAccessLevel::from($level)],
                );
            }

            foreach (static::documentSigningRolesFor($person['levels']['document-signing'] ?? null) as $role) {
                $user->documentSigningRoles()->firstOrCreate(['role' => $role]);
            }
        }
    }

    /**
     * Document Signing has its own Admin/Editor/Signee/Viewer roles
     * (see App\Enums\DocumentSigningRole), unrelated to the generic
     * Viewer/Editor/Approver `levels` above — this roughly carries
     * forward each person's old document-signing capability into the
     * new vocabulary: old Approver (could sign off) becomes Editor +
     * Signee + Viewer; old Editor becomes Editor + Viewer. Nobody in
     * the roster gets the new module-specific Admin role by default —
     * assign it to whoever should manage this module's Roles/Organization
     * settings from Settings > Roles once seeded.
     *
     * @return array<int, DocumentSigningRole>
     */
    private static function documentSigningRolesFor(?string $oldLevel): array
    {
        return match ($oldLevel) {
            'approver' => [DocumentSigningRole::Editor, DocumentSigningRole::Signee, DocumentSigningRole::Viewer],
            'editor' => [DocumentSigningRole::Editor, DocumentSigningRole::Viewer],
            default => [],
        };
    }

    private static function emailFor(string $name): string
    {
        return Str::slug($name, '.').'@councilapp.test';
    }
}
