<?php

namespace App\Filament\Assets\Resources\Assets\Pages;

use App\Filament\Assets\Resources\Assets\AssetResource;
use App\Models\Asset;
use App\Models\AssetAttachment;
use App\Models\AssetHistory;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    /**
     * The asset's own name already says what this page is — Filament's
     * default "View {record}" heading is redundant here.
     */
    public function getTitle(): string
    {
        return $this->record->name;
    }

    /**
     * Only Change Location and Log Maintenance stay as visible buttons
     * — everything else (label/document utilities, Post, Edit/Request
     * Edit) lives in the "More" menu, keeping the header to just the two
     * actions used day to day.
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('printLabel')
                    ->label('Print Label')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->url(fn (): string => route('assets.label', $this->record))
                    ->openUrlInNewTab(),
                Action::make('uploadDocument')
                    ->label('Upload Document')
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->schema([
                        FileUpload::make('documents')
                            ->label('Documents')
                            ->helperText('Registry, warranty card, an insurance document, a user manual, or a repair quote etc. can be uploaded.')
                            ->multiple()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->maxSize(25 * 1024)
                            ->disk('local')
                            ->directory('assets/documents')
                            ->visibility('private')
                            ->required(),
                        TextInput::make('document_name')
                            ->label('Document name')
                            ->placeholder('e.g. Warranty Card')
                            ->maxLength(160)
                            ->helperText('Optional — used as this document\'s display name. Only applies when uploading a single file.'),
                    ])
                    ->visible(fn (): bool => AssetResource::canEdit($this->record))
                    ->action(function (array $data): void {
                        /** @var Asset $asset */
                        $asset = $this->record;

                        // A typed name only makes sense paired with one
                        // file — with several files in one go, each just
                        // keeps its own file name to avoid every one of
                        // them getting the same label.
                        $documentName = count($data['documents']) === 1 ? ($data['document_name'] ?: null) : null;

                        foreach ($data['documents'] as $path) {
                            AssetAttachment::create([
                                'asset_id' => $asset->id,
                                'kind' => 'document',
                                'document_name' => $documentName,
                                'file_path' => $path,
                                'file_name' => basename($path),
                                'mime_type' => Storage::disk('local')->mimeType($path) ?: 'application/octet-stream',
                                'file_size' => Storage::disk('local')->size($path),
                                'uploaded_by' => auth()->id(),
                            ]);

                            AssetHistory::record($asset->id, 'attachment_added', $documentName ?? basename($path));
                        }

                        Notification::make()->title('Document(s) uploaded')->success()->send();
                    }),
                AssetResource::postAction(),
                EditAction::make()
                    ->label(fn (): string => $this->record->isPosted() ? 'Request Edit' : 'Edit')
                    ->visible(fn (): bool => AssetResource::canEdit($this->record)
                        && (! $this->record->isPosted() || ! $this->record->hasPendingEditRequest())),
                AssetResource::requestDeleteAction(),
            ])
                ->label('More')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray')
                ->tooltip('More actions'),
            AssetResource::requestTransferAction(),
            AssetResource::logMaintenanceAction(),
        ];
    }
}
