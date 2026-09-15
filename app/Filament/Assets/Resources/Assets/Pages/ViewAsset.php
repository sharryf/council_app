<?php

namespace App\Filament\Assets\Resources\Assets\Pages;

use App\Filament\Assets\Resources\Assets\AssetResource;
use App\Models\Asset;
use App\Models\AssetAttachment;
use App\Models\AssetHistory;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printLabel')
                ->label('Print Label')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('gray')
                ->url(fn (): string => route('assets.label', $this->record))
                ->openUrlInNewTab(),
            Action::make('downloadQr')
                ->label('Download QR')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url(fn (): string => route('assets.qr', $this->record)),
            Action::make('uploadDocument')
                ->label('Upload Document')
                ->icon(Heroicon::OutlinedPaperClip)
                ->color('gray')
                ->schema([
                    FileUpload::make('documents')
                        ->label('Documents')
                        ->multiple()
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(25 * 1024)
                        ->disk('local')
                        ->directory('assets/documents')
                        ->visibility('private')
                        ->required(),
                ])
                ->visible(fn (): bool => AssetResource::canEdit($this->record))
                ->action(function (array $data): void {
                    /** @var Asset $asset */
                    $asset = $this->record;

                    foreach ($data['documents'] as $path) {
                        AssetAttachment::create([
                            'asset_id' => $asset->id,
                            'kind' => 'document',
                            'file_path' => $path,
                            'file_name' => basename($path),
                            'mime_type' => Storage::disk('local')->mimeType($path) ?: 'application/octet-stream',
                            'file_size' => Storage::disk('local')->size($path),
                            'uploaded_by' => auth()->id(),
                        ]);

                        AssetHistory::record($asset->id, 'attachment_added', basename($path));
                    }

                    Notification::make()->title('Document(s) uploaded')->success()->send();
                }),
            AssetResource::requestTransferAction(),
            AssetResource::logMaintenanceAction(),
            EditAction::make()
                ->visible(fn (): bool => AssetResource::canEdit($this->record)),
        ];
    }
}
