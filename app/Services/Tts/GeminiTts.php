<?php

namespace App\Services\Tts;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Gemini speech generation (Interactions API). Has a free tier with an
 * API key from aistudio.google.com. The model detects the language automatically.
 */
class GeminiTts implements TtsProvider
{
    public const MODEL = 'gemini-3.8-flash-tts';

    public function __construct(protected string $key, protected string $model = self::MODEL)
    {
    }

    public function synthesize(string $text, string $voice, string $style, int $rate = 0): array
    {
        $pace = $rate > 0 ? ', slightly faster pace' : ($rate < 0 ? ', slightly slower pace' : '');

        $response = Http::timeout(90)
            ->withHeaders(['x-goog-api-key' => $this->key])
            ->post('https://generativelanguage.googleapis.com/v1beta/interactions', [
                'model' => $this->model,
                'input' => [[
                    'type' => 'user_input',
                    'content' => [[
                        'type' => 'text',
                        'text' => $text,
                        'annotations' => [['type' => 'speech_metadata', 'style' => $style.$pace]],
                    ]],
                ]],
                'response_format' => ['type' => 'audio'],
                'generation_config' => ['speech_config' => [['voice' => $voice]]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(self::friendlyError($response, 'Gemini voice'));
        }

        $data = null;
        foreach ((array) $response->json('steps', []) as $step) {
            foreach ((array) ($step['content'] ?? []) as $part) {
                if (($part['type'] ?? null) === 'audio' && ! empty($part['data'])) {
                    $data = $part['data'];
                }
            }
        }
        $audio = $data ? base64_decode($data, true) : false;
        if (! $audio) {
            throw new RuntimeException('Gemini returned no audio for this line.');
        }

        return [str_starts_with($audio, 'RIFF') ? $audio : self::wav($audio), 'wav'];
    }

    /** Readable message for a failed Gemini call (Google wraps errors in an object or an array). */
    public static function friendlyError($response, string $what = 'Gemini'): string
    {
        $message = (string) ($response->json('error.message') ?? $response->json('0.error.message') ?? mb_substr($response->body(), 0, 200));

        return match (true) {
            str_contains($message, 'API key not valid'), in_array($response->status(), [401, 403], true)
                => 'Gemini rejected the API key — check it in Settings → Integrations.',
            $response->status() === 429 => 'Gemini free-tier limit reached for now — wait a minute and try again.',
            default => "{$what} error {$response->status()}: {$message}",
        };
    }

    /** Wraps raw 24 kHz mono 16-bit PCM in a WAV header. */
    protected static function wav(string $pcm, int $rate = 24000): string
    {
        return 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVE'
            .'fmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16)
            .'data'.pack('V', strlen($pcm)).$pcm;
    }
}
