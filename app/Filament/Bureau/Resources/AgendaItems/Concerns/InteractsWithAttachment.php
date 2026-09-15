<?php

namespace App\Filament\Bureau\Resources\AgendaItems\Concerns;

/**
 * Extracts the `attachment`/`attachment_names` form fields (see
 * AgendaItemForm's storeFileNamesIn() comment) into the model's actual
 * attachment_path/attachment_original_name columns. Shared by
 * CreateAgendaItem and EditAgendaItem.
 */
trait InteractsWithAttachment
{
    /**
     * On Edit, an untouched FileUpload field still resubmits its
     * current stored path but WITHOUT re-populating `attachment_names`
     * (that only gets filled when a file is freshly uploaded in this
     * request) — so attachment_original_name is only touched when the
     * attachment actually changed (new file, or removed entirely),
     * otherwise it's left alone to keep its existing value.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractAttachmentFields(array $data): array
    {
        if (array_key_exists('attachment', $data)) {
            $data['attachment_path'] = $data['attachment'];

            if (filled($data['attachment_names'] ?? null)) {
                $data['attachment_original_name'] = collect($data['attachment_names'])->first();
            } elseif (blank($data['attachment'])) {
                $data['attachment_original_name'] = null;
            }
        }

        unset($data['attachment'], $data['attachment_names']);

        return $data;
    }
}
