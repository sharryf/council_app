<?php

use App\Http\Controllers\Assets\AssetAttachmentController;
use App\Http\Controllers\Assets\AssetExportController;
use App\Http\Controllers\Assets\AssetLabelController;
use App\Http\Controllers\Assets\AssetQrCodeController;
use App\Http\Controllers\Assets\PublicAssetController;
use App\Http\Controllers\Bureau\AgendaAttachmentController;
use App\Http\Controllers\Bureau\MeetingPdfDownloadController;
use App\Http\Controllers\Bureau\MinutesAudioController;
use App\Http\Controllers\DocumentSigning\DocumentDownloadController;
use App\Http\Controllers\Inventory\AdjustmentSlipController;
use App\Http\Controllers\Inventory\InventoryAttachmentController;
use App\Http\Controllers\Inventory\IssueSlipController;
use App\Http\Controllers\Inventory\ReorderBuyingListController;
use App\Http\Controllers\Inventory\ReportExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('documents/{document}/download/original', [DocumentDownloadController::class, 'original'])
        ->name('documents.download.original');
    Route::get('documents/{document}/download/signed', [DocumentDownloadController::class, 'signed'])
        ->name('documents.download.signed');

    Route::get('bureau/agenda-items/{agendaItem}/attachment', [AgendaAttachmentController::class, 'show'])
        ->name('bureau.agenda-items.attachment');

    Route::get('bureau/meetings/{meeting}/pdf/{type}', [MeetingPdfDownloadController::class, 'show'])
        ->name('bureau.meetings.pdf');

    Route::post('bureau/minutes/{minutes}/audio', [MinutesAudioController::class, 'store'])
        ->name('bureau.minutes.audio.store');

    Route::get('inventory/attachments/{attachment}', [InventoryAttachmentController::class, 'show'])
        ->name('inventory.attachments.show');

    Route::get('inventory/issue-requests/{issueRequest}/slip', [IssueSlipController::class, 'show'])
        ->name('inventory.issue-requests.slip');

    Route::get('inventory/adjustments/{adjustment}/slip', [AdjustmentSlipController::class, 'show'])
        ->name('inventory.adjustments.slip');

    Route::get('inventory/reorder/buying-list', [ReorderBuyingListController::class, 'show'])
        ->name('inventory.reorder.buying-list');

    Route::get('inventory/reports/{report}/export', [ReportExportController::class, 'show'])
        ->name('inventory.reports.export');

    Route::get('inventory/reports/{report}/export-pdf', [ReportExportController::class, 'pdf'])
        ->name('inventory.reports.export-pdf');

    Route::get('assets/attachments/{attachment}', [AssetAttachmentController::class, 'show'])
        ->name('assets.attachments.show');

    Route::get('assets/{asset}/qr', [AssetQrCodeController::class, 'show'])
        ->name('assets.qr');

    Route::get('assets/{asset}/label', [AssetLabelController::class, 'show'])
        ->name('assets.label');

    Route::get('assets/labels/bulk', [AssetLabelController::class, 'bulk'])
        ->name('assets.labels.bulk');

    Route::get('assets/export/assets', [AssetExportController::class, 'assets'])
        ->name('assets.export.assets');

    Route::get('assets/export/transfers', [AssetExportController::class, 'transfers'])
        ->name('assets.export.transfers');

    Route::get('assets/export/maintenance', [AssetExportController::class, 'maintenance'])
        ->name('assets.export.maintenance');

    Route::get('assets/export/audits/{session}', [AssetExportController::class, 'auditItems'])
        ->name('assets.export.audit-items');
});

// Public, no-login QR landing (spec section 9/6.7) — deliberately
// outside the auth group and rate limited, since anyone with a printed
// label can reach these. Looked up by the unguessable public_token,
// never by id (implementation plan section 2.3).
Route::middleware('throttle:30,1')->group(function () {
    Route::get('a/{token}', [PublicAssetController::class, 'show'])
        ->name('assets.public.show');

    Route::get('a/{token}/photo', [PublicAssetController::class, 'photo'])
        ->name('assets.public.photo');

    Route::get('api/public/assets/{token}', [PublicAssetController::class, 'json'])
        ->name('assets.public.json');
});
