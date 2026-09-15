<?php

namespace App\Http\Controllers\Bureau;

use App\Enums\BureauRole;
use App\Http\Controllers\Controller;
use App\Models\BureauMeetingMinutes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Receives the live-meeting audio recording (see the MediaRecorder
 * script in the RecordMinutes Blade view) as a plain multipart upload
 * rather than through Livewire's own file-upload plumbing — a
 * multi-minute recording is too large a payload for a Livewire
 * component round-trip. Archive-only: this file is never read back by
 * MinutesDraftingService, only stored for the record.
 */
class MinutesAudioController extends Controller
{
    public function store(Request $request, BureauMeetingMinutes $minutes): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user && $user->hasBureauRole(BureauRole::BureauAdmin), 403);

        $request->validate([
            'audio' => ['required', 'file', 'max:512000'], // 500MB ceiling for a full meeting recording
        ]);

        $directory = "bureau/minutes/{$minutes->id}";
        Storage::disk('local')->makeDirectory($directory);

        $extension = $request->file('audio')->extension() ?: 'webm';
        $path = $request->file('audio')->storeAs($directory, 'recording.'.$extension, 'local');

        $minutes->update(['audio_path' => $path]);

        return response()->json(['path' => $path]);
    }
}
