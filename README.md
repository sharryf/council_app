# Council App

A single-login office/council administration app. One Laravel app, one
database, one [Filament](https://filamentphp.com) admin panel. Every
user can see every module (Document Signing, Bureau, Inventory, Assets,
Registries, Permits, HR & Payroll, Events) — what they can *do* inside
each one is controlled by a per-user, per-module Viewer/Editor/Approver
level (see [Module access levels](#module-access-levels)).

Document Signing is the first module built out (see
[Document Signing module](#document-signing-module) below); the rest
still just have the scaffolding to add them cleanly.

## Stack

- Laravel 13 (PHP 8.4)
- Filament v5 — single admin panel
- MariaDB (via `spatie/laravel-permission` for roles)
- Laravel Herd — local PHP/Composer/service manager (Windows/macOS)
- `doctrine/dbal` — only for migrations that alter an existing column
  (e.g. making `documents.title` nullable); not used at runtime.
- `pdf.js` (CDN, not a Composer/npm dependency) — client-side PDF
  rendering and text search for the Document Signing upload wizard's
  placement designer.

## Running locally

1. **Install Herd** (bundles PHP 8.4 and Composer): https://herd.laravel.com
2. **Install a database.** This project was set up against MariaDB
   (`winget install MariaDB.Server`), but any MySQL-compatible server
   works. Create a database and user matching your `.env`, e.g.:
   ```sql
   CREATE DATABASE council_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'council_app'@'127.0.0.1' IDENTIFIED BY 'your-password';
   GRANT ALL PRIVILEGES ON council_app.* TO 'council_app'@'127.0.0.1';
   ```
3. **Install dependencies and configure the app:**
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```
   Fill in `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env` to match
   step 2.
4. **Migrate and seed:**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```
   This creates the `admin` role and the council roster below (see
   [Seeded accounts](#seeded-accounts)).
5. **Serve the app.** Either park this folder's parent directory with
   Herd (`herd park`) so it's available at `http://council-app.test`, or
   run `php artisan serve --no-reload` and visit `http://localhost:8000`
   — the `--no-reload` matters on Windows: without it, file uploads
   fail (see the Document Signing module's assumptions below for why).
6. **Log in** at `/admin` (e.g. `http://council-app.test/admin`).

There is no self-registration screen — accounts are created by an admin
from the topbar **Users** menu item inside the panel (see
[Navigation & profile](#navigation--profile)), or via a seeder/tinker
for initial setup.

## Navigation & profile

The sidebar only lists actual apps (Dashboard, plus each module's own
entries) — there's no "Dashboard" page heading or welcome/sign-out card
competing with the topbar, since identity and sign-out already live in
the avatar menu at top right (`App\Filament\Pages\Dashboard::getHeading()`
returns null; `AccountWidget` was dropped from `AdminPanelProvider`).

**The topbar user menu** (click the avatar) is per-user:
- **Profile** (`App\Filament\Pages\EditProfile`) — name, email, password,
  and two **Signature** slots: each can be drawn on a canvas or uploaded
  as a PNG, with its own live preview and "remove" toggle
  (`users.signature_path` / `signature_path_2`, private `local` disk) —
  the same two-slot pattern as the module's organization stamps below.
  Once both slots hold a signature, a **Default signature** picker
  appears to choose which one is used; `User::defaultSignaturePath()`
  falls back to whichever slot is actually filled if the chosen default
  ever becomes empty. This is reused anywhere a signature is needed —
  right now that's Document Signing's Sign action, which uses the
  default signature as a one-click alternative to drawing/typing it
  fresh each time (`SignatureType::Saved`; the file is *copied* into the
  document's own folder at signing time, so a later change to your
  profile signature never alters an already-signed document).
- **Users** — admin-only, links to `UserResource`. Not in the main
  sidebar at all (`UserResource::shouldRegisterNavigation()` is always
  `false`); it's system administration, not one of the apps in
  `config/modules.php`.

## Module access + module levels

This section covers the *generic* Viewer/Editor/Approver system, still
used by every module except Document Signing, which now defines its
own bespoke role vocabulary instead — see
[Document Signing's own roles](#document-signing-module) for why and
how, and treat it as the template if another module needs roles that
don't fit a single Viewer/Editor/Approver ranking.

Two separate, independently-managed things:

1. **Module access** — which apps a user can see at all. Default: every
   module (`users.module_access` is `null`). An admin can restrict a
   user to a subset from **Users → Edit** (a checklist of modules). This
   is the *only* thing `UserResource` manages — set via
   `User::canAccessModule()`, checked by `HasModuleAccess::canAccess()`
   and `ModuleCardsWidget` (which simply omits cards the user can't
   reach).
2. **Module level** — Viewer/Editor/Approver, what a user can *do*
   inside a module they can already reach. Managed from **that module's
   own "Roles" page**, not from Users:

   | Level | Can do |
   |---|---|
   | Viewer | See the module's records (list/view pages). No create, edit, delete, or approval actions. |
   | Editor | Viewer, plus create/edit records. Delete only where a module's own rules already allow it (e.g. Document Signing still requires Draft + being the uploader). |
   | Approver | Editor, plus any approval/sign-off action in that module (Document Signing's Sign/Reject), **and** access to that module's Roles page — assigning access is itself a step above Editor. |

**How levels are stored:** `user_module_levels` (`App\Models\UserModuleLevel`) —
one row per (user, module) pair, only for Editor/Approver; no row means
Viewer. `admin` (a `spatie/laravel-permission` role,
`database/seeders/RoleSeeder.php`) is Approver in every module
automatically, ignores `module_access` entirely, and separately gates
**Users** — it's a system-owner flag, not a module level.

**Reading a level:**
```php
$user->roleFor('bureau');                                    // App\Enums\ModuleAccessLevel
$user->hasModuleLevel('bureau', ModuleAccessLevel::Approver); // bool, atLeast() under the hood
$user->canAccessModule('bureau');                             // bool — can they see it at all?
```

**Gating a Resource** — `App\Filament\Concerns\HasModuleAccess` (used by
every module Resource) wires `canCreate()` / `canEdit()` / `canDelete()`
to require Editor+, and `canAccess()` / `shouldRegisterNavigation()` to
check both that `$moduleKey` resolves to a real `config/modules.php`
entry *and* `User::canAccessModule()`. For an approval-shaped action,
gate it directly, since "what counts as approval" is specific to each
module:
```php
->visible(fn (): bool => static::currentLevel()->atLeast(ModuleAccessLevel::Approver))
```
(`currentLevel()` is `protected static` on the trait, so it's only
callable from within the Resource itself — see `DocumentResource::signAction()`
for a full example, including combining it with the module's own
business rules.)

**Each module's Roles page** — `App\Filament\Concerns\HasModuleRolesPage`
(gates the page to Approver+) plus
`App\Filament\Widgets\Concerns\HasModuleRolesTable` (an inline-editable
Viewer/Editor/Approver table, `Filament\Tables\Columns\SelectColumn`,
one row per user who currently has access to the module — `admin` rows
are excluded since their level is fixed). This is the pattern for a
module that's happy with the generic three-level ranking; **Document
Signing doesn't use it** — see
[Document Signing's own roles](#document-signing-module) for a module
whose roles don't fit a single ranked value, and are edited via a modal
instead of an inline column for that reason. Either pattern's Roles
page is registered manually in `AdminPanelProvider::pages()` (not
auto-discovered — see the comment there).

**Dashboard consequence:** `App\Filament\Widgets\NoModulesAssignedWidget`
("no modules assigned") shows for a user restricted (via `module_access`)
away from every module — an edge case, but a real one now that access
can be restricted. `App\Filament\Widgets\ModuleCardsWidget` shows one
card per module the user can reach, each labeled with their level.

## Seeded accounts

`UserSeeder` creates a system-owner admin account plus a 21-person
council roster (password `password` for all — a placeholder, tell
people to change it), matching real positions so `position` can show up
on profiles and meeting attendee lists later:

| Email | Position | Notable module levels |
|---|---|---|
| admin@council.test | *(system owner)* | Admin — Approver everywhere |
| ibrahim.waheed@councilapp.test | Council President | Approver: Bureau, Doc. Signing, Permits |
| ahmed.shiyam@councilapp.test | Vice President | Approver: Bureau, Doc. Signing, Permits |
| hussain.rasheed@councilapp.test | Councilor | Editor: Bureau, Doc. Signing |
| aishath.nazima@councilapp.test | Councilor | Editor: Bureau, Doc. Signing |
| mohamed.naeem@councilapp.test | Councilor | Editor: Bureau, Doc. Signing |
| fathimath.zuhura@councilapp.test | Secretary General | Editor: Bureau, Doc. Signing, Registries |
| ali.shareef@councilapp.test | Council Executive | Editor: Bureau, Doc. Signing, Permits, Events; Approver: Inventory, Assets, Registries, HR & Payroll |
| mariyam.saeed@councilapp.test | Asst Council Executive | Editor in all 8 modules |
| adam.rasheed@councilapp.test | Senior Council Officer | Editor: Document Signing |
| hawwa.nasheeda@councilapp.test | Senior Council Officer | Editor: Inventory |
| ismail.waheed@councilapp.test | Senior Council Officer | Editor: Assets |
| aminath.shifa@councilapp.test | Senior Council Officer | Editor: Registries |
| yoosuf.naeem@councilapp.test | Senior Council Officer | Editor: HR & Payroll |
| zeenath.hussain@councilapp.test | Council Officer | Editor: Permits |
| moosa.rasheed@councilapp.test | Council Officer | Editor: Permits |
| rugiyya.adam@councilapp.test | Council Officer | Editor: Events |
| hassan.zareer@councilapp.test | Council Officer | Editor: Events |
| shazna.ibrahim@councilapp.test | Council Officer | Editor: Registries |
| abdulla.nasheed@councilapp.test | Asst Council Officer | Editor: Inventory |
| aminath.waheeda@councilapp.test | Asst Council Officer | Editor: Bureau |

The "Doc. Signing" entries above are shorthand for the generic level
`UserSeeder` still records for every module (`levels['document-signing']`)
— for actual Document Signing behavior, that value is translated into
[this module's own roles](#document-signing-module) at seed time: old
Approver → Editor + Signee + Viewer, old Editor → Editor + Viewer. So
Ibrahim Waheed and Ahmed Shiyam (old Approver) can sign; the rest
listed above (old Editor) can upload and see their own documents but
not sign or see everyone else's. Nobody gets the new module-specific
Admin role by default (see the module section's assumptions).

Everyone not listed above for a given module is Viewer in it. Use these
to verify access control — e.g. log in as `hawwa.nasheeda@councilapp.test`
and confirm Inventory shows create/edit but Bureau doesn't, or as
`ibrahim.waheed@councilapp.test` and confirm Document Signing's Sign
action is available. Change or remove these accounts before any real
deployment.

## Adding a new module later

Each module prompt should end with these steps, and nothing else needs to
change:

1. **Add an entry to `config/modules.php`** (with a `description` for its
   dashboard card):
   ```php
   'permits' => [
       'label' => 'Permits',
       'description' => 'Issue and track permits and licenses.',
       'icon' => 'heroicon-o-document-check',
       'resource' => null,
   ],
   ```
   (Already present for all 8 planned modules — this step is only for a
   9th module beyond the original list. No seeder step needed — every
   user with access to the module already has Viewer by default; assign
   Editor/Approver from the module's own Roles page, step 4 below, once
   the Resource exists.)

2. **Generate Resources under the module's own directory** and gate each
   one with the trait:
   ```bash
   php artisan make:filament-resource Permits/Permit --generate
   ```
   ```php
   use App\Filament\Concerns\HasModuleAccess;

   class PermitResource extends Resource
   {
       use HasModuleAccess;

       protected static ?string $moduleKey = 'permits'; // matches config/modules.php key

       // ...
   }
   ```

3. **Point the registry at the new Resource** by setting `resource` in
   the `config/modules.php` entry from step 1:
   ```php
   'resource' => \App\Filament\Resources\Permits\PermitResource::class,
   ```
   The module's dashboard card switches from "Coming soon" to a live link
   as soon as this is set — no other change needed.

4. **Add a Roles page** so an Approver can assign Viewer/Editor/Approver
   for the new module without going through tinker:
   ```php
   // app/Filament/Resources/Permits/Widgets/PermitsRolesTable.php
   class PermitsRolesTable extends TableWidget
   {
       use HasModuleRolesTable;
       protected static ?string $moduleKey = 'permits';
   }

   // app/Filament/Resources/Permits/Pages/PermitsRoles.php
   class PermitsRoles extends Page
   {
       use HasModuleRolesPage;
       protected static ?string $moduleKey = 'permits';
       protected static string|UnitEnum|null $navigationGroup = 'Permits'; // matches the Resource's own group
       protected static ?string $navigationLabel = 'Roles';

       protected function getFooterWidgets(): array
       {
           return [PermitsRolesTable::class];
       }
   }
   ```
   Then register the page in `AdminPanelProvider::pages()` (it isn't
   auto-discovered, since it doesn't live under `app/Filament/Pages/`).

That's it beyond the one-line `pages()` registration in step 4 — no
changes to the panel provider's colors/middleware, or any other
module's code, are needed to add a module.

## Document Signing module

Replaces wet-ink signing with a tracked digital workflow: upload a PDF,
pick one or more required signers (sequential or parallel), each signer
draws or types their signature, and once everyone has signed the app
composites every signature/stamp onto the document's own pages and
hashes each signature for later verification.

**Flow:** the "New document" wizard (below) uploads, places signatures,
and sends for signing in one pass, so a document goes straight from
`Draft` to `Pending` (locked; signers act) the moment it's created.
Every signer signs → `Signed` (stamped copy generated, signatures
composited onto their placed positions) — or any pending signer clicks
*Reject* at any point → `Rejected` — or the uploader/an admin clicks
*Void* → `Voided` (an administrative cancellation, not a signer's own
objection — see point 7 under "Assumptions" below). (The older manual
Draft → *Submit
for signing* path — `EditDocument`, `DocumentResource::submitAction()`
— still exists underneath for anything that reaches Draft some other
way, e.g. tests or direct API use, but the wizard is the only path a
user goes through in the UI.)

**Navigation:** `Documents` (upload/sign — this module's home page,
carrying the pending-signature card below) as a flat sidebar item, plus
a `Settings` group with `Roles` and `Organization`, both gated to the
module's own Admin role (below) rather than the sidebar's normal
"every module has Viewer by default" assumption.

### The "New document" wizard

Two steps, both in `App\Filament\Resources\DocumentSigning\Documents\Pages\CreateDocument`
— a hand-built page rather than Filament's usual `CreateRecord`, since
step 2 is a client-heavy PDF designer that doesn't fit a single-form
lifecycle:

1. **Upload** — drag-and-drop or browse for a PDF, an optional title
   (falls back to the filename anywhere a title displays —
   `Document::displayTitle()`), then *Continue*. The server stores the
   file and counts its pages (`setasign/fpdi`) before moving on.
2. **Place** — the PDF renders page-by-page on a canvas (`pdf.js`, CDN)
   with page-navigation arrows. Clicking a name in the *Signees* list
   (everyone with the Signee role) adds them and drops a movable,
   resizable box onto the current page — drag its body to move it, drag
   its corner handle to resize it, across pages via the nav arrows. A
   signee isn't limited to one box either — an "+ Add a box here"
   control under their name (visible once they're added) drops another
   box on whatever page is currently in view, so e.g. initials can be
   placed on every page plus a full signature on the last; each box is
   listed under its owner with its own page number and a *Remove*, so
   any individual one can be deleted without removing the signee
   entirely (removing the signee removes all of their boxes). Every box
   belonging to a signee is stamped with the same signature at signing
   time. Each signer gets a distinct color (cycled from a fixed palette
   in signing order), shown as an initials avatar in the list, a
   tinted/bordered row once added, and the same color on all of their
   boxes' borders and name labels. The
   organization can have up to two named stamps configured (Settings →
   Organization, `App\Models\DocumentSigningOrganization`'s
   `stamp_path`/`stamp_path_2` slots) — one *Add {label}* button per
   configured stamp drops a movable box tagged with that slot
   (`document_stamps.stamp_slot`), the same way a signer box works,
   except its resize handle is locked to a physically square aspect
   ratio regardless of the page's own proportions (see
   `pageBaseWidth`/`pageBaseHeight` in the blade file). A document isn't
   limited to one stamp per slot either — the uploader can add either
   stamp as many times, on as many pages, as needed; with no stamps
   configured yet, the section just shows a hint pointing at Settings →
   Organization instead of any buttons. Signing order defaults to
   **Parallel**. *Send to Sign* creates the document, every signer row,
   and every stamp row (each remembering which slot it was) — with their
   placed page/position/size — in one transaction, then submits it
   immediately.

**Auto-detect:** when a signee is added, the wizard searches the PDF's
extracted text (`pdf.js`'s text layer, client-side — not a server-side
PDF parser) for their name; if found, their box is pre-placed near that
text instead of a generic stacked default position. Either way it's
just a starting point — still fully draggable. This is a plain
substring match against page text, so it can miss a name split across
lines/fonts or place a box slightly off from a genuinely intended
signature line; there's no separate placeholder-marker convention
(e.g. `[[SIGN]]`) — that was considered and deliberately left out of
this pass.

**Placement data:** stored as fractions of the page (0–1), not pixels
or PDF units, so they're independent of both the browser's render
scale and the PDF's actual page size:
- `document_signer_placements` (`App\Models\DocumentSignerPlacement`,
  `DocumentSigner::placements()`) — `page_number` / `position_x` /
  `position_y` / `box_width` / `box_height`, one row per spot a
  signer's signature lands. A signer can have zero, one, or several
  (e.g. initials on every page plus a full signature on the last) — the
  signature itself (`signature_type`/`signature_value`) stays on the
  one `document_signers` row, reused for every one of that signer's
  placements. Same one-to-many shape as `document_stamps` below, which
  it was modeled on.
- `document_stamps` (`App\Models\DocumentStamp`) — same placement
  columns plus `stamp_slot` (1 or 2, which organization stamp this
  placement uses — see
  `App\Models\DocumentSigningOrganization::availableStamps()`), one row
  per stamp placement, since a document can have a stamp placed more
  than once, and can mix both stamps across placements.

`DocumentStampService` converts these to the PDF's real page
dimensions (from FPDI's `getTemplateSize()`) when generating the signed
copy, compositing each signer's signature onto every one of their
placements (or the org stamp for that placement's `stamp_slot`)
directly onto its page via FPDI's `Image()`/`Cell()` — see
[Document Signing's own roles](#document-signing-module) above for the
Signee/Editor roles this depends on. `SetAutoPageBreak(false)` is set
before compositing starts — FPDF's automatic page break is on by
default and would otherwise silently insert an extra blank page the
moment a Typed signature's `Cell()` call lands close enough to a page's
bottom margin, since this service manages page flow itself one
imported template page at a time. A placement whose stamp slot has no
image configured (e.g. only one of the two stamps was ever uploaded) is
silently skipped rather than erroring. An image (drawn/saved signature
or an org stamp) is fit to the placed box's aspect ratio and centered
within it (`DocumentStampService::fitWithinBox()`), rather than
stretched to the box's exact width/height — the box is just where it
was dropped and sized in the wizard, which won't generally match the
image's own proportions.

**No certificate/audit page.** The signed PDF is exactly the original
page count — nothing gets appended. The per-signer verification hash
still exists (`document_signers.hash`) and is still shown in the app's
own document view (`DocumentInfolist`); it's just not printed into the
PDF file itself anymore. One real consequence: a signer with no
placements at all (e.g. a document created via `Document::create()`
directly, as in tests, rather than through the wizard) has nowhere left
to appear in the output at all, since there's no fallback page to list
them on. Every signer added through the actual wizard already has at
least one placement, so this only affects documents created outside the
normal UI flow.

### Previewing without downloading

`DocumentResource::previewAction()` opens the document (the signed copy
if there is one, otherwise the original) in a modal, paginated with the
same `pdf.js` viewer as the wizard, so anyone with access can look at a
document without triggering a browser download — available from both
the Documents list and the document's own view page. `signAction()`
shows the same viewer with every one of the current user's own placed
sign boxes highlighted and labeled "You sign here" (a signer can have
more than one — see [placement data](#the-new-document-wizard) above),
jumped to the first one's page, plus
an outline for every placed organization stamp (`DocumentResource::stampBoxes()`)
— both are just placeholders, since the real signature/stamp images
only get composited into the PDF's actual pixels once the document is
Signed (see `DocumentStampService`); `stampBoxes()` returns nothing for
an already-Signed document for that reason, since `previewAction()`'s
signed copy already has the real thing baked in.

Both live in
`resources/views/filament/resources/document-signing/documents/partials/document-preview.blade.php`.
It's rendered inside a Filament action modal (`modalContent()` /
action `schema()`), which means the markup only ever reaches the page
via a Livewire AJAX response — never present in the page's initial
HTML. Browsers don't execute `<script>` tags delivered that way, and a
separately `Alpine.data()`-registered component (the pattern the wizard
itself uses) has the same problem, since its own registration script
would never run either. So this component is a single self-contained
IIFE inlined directly in `x-data`, which Alpine evaluates as a plain JS
expression regardless of how the element reached the DOM — no
`<script>` tag involved at all.

**Alpine `x-show` gotcha:** any element that's both `x-show`-toggled
*and* needs `display:flex` (the page-nav row) can't set that via
inline `style` — Alpine's `x-show` reveals an element by removing only
the inline `display` property (it doesn't restore a specific prior
value), which drops back to the browser's block default and stacks
flex children vertically. A CSS class doesn't help either here, since
Filament's compiled CSS is pre-purged to only the utility classes
Filament's own views use — arbitrary Tailwind classes referenced from
this project's own blade files (like `flex`) were never generated into
that bundle. The fix used throughout this partial: `<template x-if>`
instead of `x-show` wherever the toggled element carries its own
`display` in an inline style — `x-if` adds/removes the whole node
rather than mutating a style property, so the inline style is never
touched.

**Signing is a single modal now** — `signAction()` no longer offers a
Drawn/Typed/Saved signature-type choice: it auto-picks the signee's
saved profile signature if they have one, otherwise falls back to
their account name as a typed signature. The modal's footer is Sign /
Reject / Cancel. Reject doesn't mount a second, independent action —
Filament's `extraModalFooterActions()` only genuinely supports
*variants of the same mounted action* (see `Filament\Actions\AttachAction`'s
"Attach another", which this is modeled on)
`makeModalSubmitAction('rejectFromSign', ['reject' => true])` re-runs
`signAction()`'s own `->action()` closure with `$arguments['reject'] === true`,
which branches to `DocumentSigningService::reject()` instead of
`sign()`, using this same modal's own `reason` field (present but not
schema-`required()`, since it's only needed for the reject branch —
manually validated inside the closure). That manual validation must
key its `ValidationException::withMessages()` entry by the field's full
Livewire state path (`$schema->getStatePath() . '.reason'`, injecting
`Schema $schema` into the closure) rather than the bare field name
`'reason'` — Filament's schema components look up errors by that exact
statePath (`mountedActions.0.data.reason`), and a plain key silently
attaches nowhere.

### This module's own roles

Document Signing doesn't use the generic Viewer/Editor/Approver ranking
(see [Module access + module levels](#module-access--module-levels)) —
its roles aren't a single ranked value, they're a set: a user can hold
any combination of the four, so `App\Enums\DocumentSigningRole` isn't
comparable with `atLeast()` the way `ModuleAccessLevel` is.

| Role | Grants |
|---|---|
| Viewer | See every uploaded document, not just their own. Without it, a user only sees documents they uploaded (Editor) or are a listed signer on (Signee) — see `DocumentResource::getEloquentQuery()`. |
| Editor | Create/upload documents for signing, and edit/delete/submit their own while still Draft. |
| Signee | Sign or reject documents they're listed as a signer on (also selectable in the signer picker on the create form). |
| Admin | Manage this module's roles (Settings → Roles) and its Organization settings (the two stamp slots). Doesn't by itself grant document create/sign/view — pair with the other roles as needed. |

**Storage:** `document_signing_user_roles` (`App\Models\DocumentSigningUserRole`)
— one row per (user, role) held, so a user with three roles has three
rows; no row for a role means they don't hold it. The system-wide
`admin` spatie role holds all four implicitly
(`User::hasDocumentSigningRole()`), same as it does for the generic
system.

**Managed from Settings → Roles** (Admin-only) — since a table cell
can't hold a multi-select the way `SelectColumn` holds one value (the
generic pattern above), each row has an "Edit roles" action that opens
a checklist modal instead
(`app/Filament/Resources/DocumentSigning/Documents/Widgets/DocumentSigningRolesTable.php`).

**Key files:**
- `app/Models/Document.php`, `app/Models/DocumentSigner.php`,
  `app/Models/DocumentSignerPlacement.php`, `app/Models/DocumentStamp.php`
  — `documents`, `document_signers`, `document_signer_placements` (a
  signer's one or more placed sign boxes), and `document_stamps`.
- `app/Filament/Resources/DocumentSigning/Documents/Pages/CreateDocument.php`
  + `resources/views/filament/resources/document-signing/documents/pages/create-document.blade.php`
  — the "New document" wizard (see below): PHP side handles upload,
  page-counting, and the final `sendToSign()`; the blade file is the
  entire client-side PDF/placement designer (`pdf.js` + Alpine, no
  Livewire round-trips in between).
- `app/Enums/{DocumentStatus,SigningMode,SignerStatus,SignatureType}.php` —
  `SignatureType` has three cases: `Typed` (literal text), `Drawn` (a
  fresh canvas drawing), and `Saved` (a copy of the signer's profile
  signature, see [Navigation & profile](#navigation--profile)). `Drawn`
  and `Saved` are both image files on disk;
  `SignatureType::isImageBased()` is the shared check used wherever the
  two need the same handling (the infolist preview, the stamped PDF).
- `app/Services/DocumentSigning/DocumentSigningService.php` — the state
  machine: who may sign/reject/void right now, the SHA-256 hash on
  signing (`hash('sha256', $originalFileBytes . $signerId . $timestamp)`),
  rolling the document to Signed, and the administrative Void path
  (see point 7 under "Assumptions" above).
- `app/Services/DocumentSigning/DocumentStampService.php` — builds the
  final PDF via `setasign/fpdi`: every original page, with each placed
  signature/stamp composited directly onto its page (see the wizard
  below). No certificate/audit page is appended — the output is
  exactly the original page count.
- `app/Filament/Forms/Components/SignaturePad.php` +
  `resources/views/filament/forms/components/signature-pad.blade.php` —
  canvas signature capture, reusable in any future module needing one.
- `app/Filament/Resources/DocumentSigning/Documents/` — the Resource.
  `DocumentResource::signAction()` / `rejectAction()` / `submitAction()`
  / `previewAction()` / `downloadOriginalAction()` /
  `downloadSignedAction()` are shared between the list table and the
  view page so both stay in sync.
- `.../partials/document-preview.blade.php` — the read-only `pdf.js`
  viewer used by `previewAction()` and `signAction()` (see
  "Previewing without downloading" above).
- `app/Http/Controllers/DocumentSigning/DocumentDownloadController.php`
  + routes in `routes/web.php` — files live on the private `local` disk,
  never a public URL; every download is checked against the requester
  being the uploader, a listed signer, or `admin`.
- `app/Enums/DocumentSigningRole.php` + `app/Models/DocumentSigningUserRole.php`
  — this module's own Admin/Editor/Signee/Viewer roles (see above).
- `app/Filament/Resources/DocumentSigning/Documents/Pages/DocumentSigningRoles.php`
  + `.../Widgets/DocumentSigningRolesTable.php` — Settings → Roles.
- `app/Models/DocumentSigningOrganization.php` (single-row settings,
  fetched via `::current()`) + `.../Pages/DocumentSigningOrganization.php`
  — Settings → Organization, two named stamp slots (`stamp_path`/`stamp_label`
  and `stamp_path_2`/`stamp_label_2`), each independently uploadable and
  relabelable without touching the other.
- `.../Pages/DocumentSigningCleanup.php` — Settings → Delete, the
  age-based bulk-delete page (see point 7 under "Assumptions" above).
- `.../Widgets/PendingSignaturesWidget.php` — the notification card atop
  Documents, shown to anyone with the Signee role.

**Assumptions made while building this — confirm before real use:**

1. **PDF only**, not Word. Converting Word to PDF on upload needs an
   external converter (LibreOffice headless, a paid API, …) that isn't
   available in this environment; stamping also requires a real PDF to
   merge pages into. The upload field rejects anything else. If Word
   support matters, that's a follow-up task once a converter is chosen.
2. **Eligible signers** = users with the **Signee** role
   (`$user->hasDocumentSigningRole(DocumentSigningRole::Signee)`), set
   from Settings → Roles. Signing/rejecting also requires Signee, on
   top of the existing per-document signer-assignment + turn-order
   check — being listed as a signer on a specific document doesn't by
   itself grant the ability to sign; the module-wide role is a
   prerequisite. No notion of
   "Section Head" / "Director" as a real job title exists yet (that's
   HR & Payroll module territory, and separate from a user's `position`
   field) — each signer's "role" on a document is a free-text label the
   uploader types per document (defaults to "Signer"), not tied to any
   org chart.
3. **One rejection kills the whole document** — any single pending
   signer rejecting moves the whole document to `Rejected`, full stop.
   There's no "resubmit a rejected document" flow; the uploader would
   re-upload as a new document.
4. **Any pending signer can reject at any time**, even out of turn in a
   sequential document — e.g. signer #3 can reject before #1 or #2 have
   acted, to stop a bad document early rather than waiting for it to
   reach them. Only *signing* respects the sequential order.
5. **No notifications yet**, beyond the pending-signature card atop
   Documents (`PendingSignaturesWidget`) — a signer still has to open
   the app to see it; nothing emails or pings them. That card also uses
   the same simpler definition as the *Assigned to me (pending)* table
   filter (any Pending document where their signer row is Pending), not
   the stricter "genuinely your turn right now" that
   `DocumentSigningService::canSign()` enforces for the Sign button
   itself — so in a sequential document, the count can include ones not
   literally at the front of the queue yet. The foundation's
   Notification system (database + mail channels) isn't wired up to
   this module — worth adding once real usage starts.
6. **No malformed/encrypted-PDF handling.** If an uploaded PDF is
   corrupt or password-protected, stamping will throw rather than fail
   gracefully with a friendly message.
7. **Deleting is Draft-only** — once a document leaves Draft it can't be
   edited or deleted, to keep the audit trail, including by `admin`.
   For a Pending document sent to the wrong signers or with the wrong
   file attached, there's a separate **Void** action
   (`DocumentResource::voidAction()`, `DocumentStatus::Voided`) that
   cancels it without deleting anything — signers can no longer sign or
   reject it, and it drops out of the pending-signature widget/filter,
   but every signer row (including any already signed before the void)
   stays exactly as it was. Distinct from **Reject**: Reject is always
   a specific signer's own recorded objection with a reason on their
   signer row; Void is an administrative cancellation attributed to
   whoever triggered it (`documents.voided_by`/`voided_at`), not to any
   signer. Only the document's own uploader (while they still hold the
   Editor role) or a system-wide `admin` can void it — deliberately the
   system-wide role, not this module's own `DocumentSigningRole::Admin`
   (see point 9 below on why that role stays narrow). A Signed document
   can't be voided — that's a completed record, not something this
   action is meant to unwind.

   For reclaiming disk space on old records, **Settings → Delete**
   (`DocumentSigningCleanup`) permanently deletes Signed/Rejected/Voided
   documents (never Draft or Pending) older than an adjustable
   retention period — floored at 365 days both in the field's own
   validation and again in the query itself, so it can't be set any
   shorter. Gated the same as Roles/Organization (this module's own
   `DocumentSigningRole::Admin`), unlike Void's system-wide `admin`
   check above — this is a routine housekeeping page, not something
   that needs the stricter gate. The page shows a live count and a
   preview of what currently qualifies before you click delete.
   `Document::booted()`'s `deleting` model hook removes each record's
   file(s) from disk (the original upload plus its `documents/{id}/`
   folder — signed copy, any drawn/saved signature images) for *any*
   deletion path, including the ordinary Draft-only delete — closing
   what was otherwise an orphaned-file leak there.
8. **Saved profile signatures must be PNG.** `App\Filament\Pages\EditProfile`
   restricts the "upload an image" option to PNG, matching what the
   canvas always exports, so `DocumentStampService`'s hard-coded `'PNG'`
   FPDI image call works for both. A drawn signature is unaffected.
   Applies to both of a user's two signature slots.
9. **The module-specific Admin role grants nothing document-related by
   itself** — deliberately narrow, matching the brief ("manage roles,
   edit organization"). An Admin who also needs to see or handle
   documents needs Viewer/Editor/Signee too, same as anyone else.
   `UserSeeder` doesn't grant this new role to anyone in the roster
   (the system-wide `admin` account has it implicitly) — assign it from
   Settings → Roles to whoever should administer this module day to
   day.
10. **Organization stamps are placed via the wizard's "Add {label}"
    buttons** — one per stamp the organization has configured (up to
    two, see Settings → Organization) — composited onto the document
    like a signature (see [the wizard](#document-signing-module)
    above). No stamp is added automatically; someone has to drop one on
    for it to appear on the signed copy, and a document isn't limited
    to using just one of the two — either can be placed as many times
    as needed.
11. **Auto-detect is a plain, client-side text search** — `pdf.js`'s
    text layer, matching a signee's name as a literal substring. It can
    miss a name that's split across lines or rendered with unusual
    spacing, and there's no fallback marker convention (like typing
    `[[SIGN]]` into the source document) for documents where name
    matching won't work — confirmed out of scope for this pass, not an
    oversight.
12. **`php artisan serve` needs `--no-reload`, or every file upload
    fails.** Laravel's `ServeCommand` spawns its actual PHP built-in
    server as a child process with a *filtered* environment — every env
    var not on a short passthrough allowlist (`APP_ENV`, `PATH`,
    `SYSTEMROOT`, a few Herd/Xdebug ones — see
    `Illuminate\Foundation\Console\ServeCommand::$passthroughVariables`)
    is stripped, unless `--no-reload` is passed. On Windows that
    silently drops `TEMP`/`TMP`, so PHP has nowhere to write the
    temporary file it needs to parse an incoming upload — every upload
    fails at the PHP SAPI level with "unable to create a temporary
    file", before Laravel even sees the request. This isn't specific to
    this feature — it breaks *any* file upload through `artisan serve`
    on Windows (the signature/stamp uploads elsewhere in this module
    included) until `--no-reload` is set. Fixed here by adding it to
    the `council-app` entry in `.claude/launch.json`; if you run
    `artisan serve` directly, add `--no-reload` to the command. Herd's
    normal nginx/php-fpm serving doesn't go through `ServeCommand` at
    all, so it was never affected.

    With that fixed, the full upload wizard was verified live in the
    browser end to end: upload → page count detected correctly →
    auto-detect found a signee's name in the PDF text and placed their
    box near it → dragging the box updated its stored position →
    adding a stamp → Send to Sign → redirected to the new `Pending`
    document with the signer, its dragged placement, and the stamp all
    persisted correctly. One real bug turned up and got fixed along the
    way: `pdfDoc`/the page-text cache were originally stored as
    properties on the Alpine component (`this.pdfDoc`), which Alpine's
    reactivity wraps in a Proxy — pdf.js's document/page objects use
    native JS private class fields (`#foo`), and a Proxy around an
    object breaks its own private-field access. Moved both to plain
    closure variables in the `Alpine.data('documentPlacement', …)`
    factory instead, alongside a small render-task-cancellation guard
    (`renderPage()`) since Livewire can re-render (and re-run
    `x-init`) in quick succession right after the upload settles,
    which was racing two `page.render()` calls onto the same canvas.

None of these are hard to change — they're isolated to
`DocumentSigningService`, `DocumentForm`, `EditProfile`, and the upload
fields' `acceptedFileTypes()` — but they're real behavior, not just
labels, so worth confirming with staff (per the original brief) before
this goes near real documents.

## Brand colors

"Lagoon" — set in `app/Providers/Filament/AdminPanelProvider.php`:

| Color | Hex | Filament slot | Used for |
|---|---|---|---|
| Deep turquoise | `#0E7A82` | `primary` | Buttons, links, active nav, focus rings |
| Stone (Filament built-in) | — | `gray` | Page/sidebar background, borders, chrome (both themes) |
| Weathered gold | `#B8934A` | `accent` (custom) | `admin` role badge; use `color="accent"` elsewhere for emphasis |
| Pale lagoon | `#BFE0DE` | `muted` (custom) | "Coming soon" module-card badge; use `color="muted"` for secondary/quiet elements |

`primary` and `accent` are saturated enough that Filament's own
`Color::hex()` ramp generator (`50`–`950` shades) preserves their hue at
every step. `gray` uses `Color::Stone`, one of Filament's built-in
Tailwind-based ramps — already tuned for both themes, so no custom
handling needed. `muted`'s source hex is pale — `Color::hex()` would
snap it to pure neutral gray at every shade, which is most obvious (and
most damaging) at shade `50`, the light-mode page background — so
`app/Support/ColorPalette::tintedScale()` builds it instead, holding
hue and saturation constant and varying only lightness, so the tint
survives from the lightest to the darkest shade in both light and dark
mode.

To add another brand color: call `Color::hex()` if it's reasonably
saturated, reach for one of Filament's built-in `Color::*` ramps
(`Slate`, `Zinc`, `Stone`, `Rose`, …) if a stock neutral or hue fits,
or use `ColorPalette::tintedScale()` if it's pale/muted and needs to be
custom — add it to the `->colors([...])` array under any name, then
reference it in a component via `color="that-name"`.

## Project structure notes

- `app/Enums/ModuleAccessLevel.php` — Viewer/Editor/Approver, see
  [Module access + module levels](#module-access--module-levels).
- `app/Models/UserModuleLevel.php` — the per-user, per-module level rows.
- `app/Filament/Concerns/HasModuleAccess.php` — the module-gating trait
  used by every module Resource.
- `app/Filament/Concerns/HasModuleRolesPage.php` +
  `app/Filament/Widgets/Concerns/HasModuleRolesTable.php` — the
  reusable pair behind every module's own Roles page.
- `app/Filament/Pages/Dashboard.php` — heading-less Dashboard override,
  see [Navigation & profile](#navigation--profile).
- `app/Filament/Pages/EditProfile.php` — the profile page, including the
  signature capture/reuse section.
- `app/Filament/Resources/Users/` — admin-only user management, now
  scoped to identity + module *access* only (not levels);
  `Concerns/InteractsWithModuleAccess.php` moves the Edit form's virtual
  `is_admin` field to/from the `admin` role and normalizes
  `module_access`.
- `app/Filament/Widgets/NoModulesAssignedWidget.php` — empty-state
  dashboard widget, shown when `module_access` excludes every module.
- `app/Filament/Widgets/ModuleCardsWidget.php` — the dashboard's module
  card grid.
- `app/Support/ColorPalette.php` — hue-preserving color ramp builder,
  see [Brand colors](#brand-colors).
- `app/Providers/Filament/AdminPanelProvider.php` — the single panel's
  configuration (colors, widgets, middleware, user menu, profile page).
  Module Resources under `app/Filament/Resources/**` are auto-discovered;
  standalone Pages (Dashboard, each module's Roles page) are not and are
  registered explicitly in `->pages()`.
- `database/seeders/RoleSeeder.php` / `UserSeeder.php` — roles and
  verification accounts.
