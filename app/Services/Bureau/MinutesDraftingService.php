<?php

namespace App\Services\Bureau;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Expands the Bureau Admin's raw bullet points into formal Dhivehi
 * minutes prose via Google's Gemini API. The audio recording (see
 * BureauMeetingMinutes::audio_path) is archive-only and is never sent
 * here — only the typed bullet points are.
 *
 * Optional: GEMINI_API_KEY is unset by default. When it's missing,
 * isConfigured() is false and the recording page simply leaves the
 * drafted-text field for the Bureau Admin to type by hand instead of
 * showing a broken "Draft with AI" button. Gemini (rather than a paid
 * provider) was chosen specifically for its free tier via Google AI
 * Studio — https://aistudio.google.com/apikey.
 */
class MinutesDraftingService
{
    private const MODEL = 'gemini-3.6-flash';

    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/'.self::MODEL.':generateContent';

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    public function draft(string $agendaItemDetails, ?string $speakerName, string $bulletPoints): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('AI drafting is not configured (GEMINI_API_KEY is unset).');
        }

        $system = <<<'SYSTEM'
            You are drafting official Maldivian council-meeting minutes in
            formal, third-person Dhivehi (Thaana script). Given a speaker's
            raw bullet points, expand them into 2-4 sentences of formal
            minutes prose in Dhivehi. Do not invent facts not present in the
            bullet points. Do not add a preamble, heading, or translation —
            output only the drafted Dhivehi paragraph.
            SYSTEM;

        $userContent = "Agenda item: {$agendaItemDetails}\n"
            .'Speaker: '.($speakerName ?? '—')."\n"
            ."Bullet points:\n{$bulletPoints}";

        $response = Http::withHeaders([
            'x-goog-api-key' => config('services.gemini.key'),
        ])->post(self::API_URL, [
            'system_instruction' => [
                'parts' => [['text' => $system]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userContent]]],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('AI drafting request failed: '.$response->body());
        }

        $text = collect($response->json('candidates.0.content.parts'))
            ->pluck('text')
            ->implode('');

        if (blank($text)) {
            throw new RuntimeException('AI drafting returned no text.');
        }

        return trim($text);
    }
}
