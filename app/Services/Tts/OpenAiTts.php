<?php

namespace App\Services\Tts;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI text-to-speech (gpt-4o-mini-tts) — the model behind openai.fm. Supports Urdu.
 * Pay-as-you-go; roughly 1–2 US cents per minute of generated audio.
 */
class OpenAiTts implements TtsProvider
{
    public const MODEL = 'gpt-4o-mini-tts';

    public function __construct(protected string $key, protected string $model = self::MODEL)
    {
    }

    public function synthesize(string $text, string $voice, string $style, int $rate = 0): array
    {
        $pace = $rate > 0 ? ' Speak a little faster than normal.' : ($rate < 0 ? ' Speak a little slower than normal.' : '');

        $response = Http::timeout(60)
            ->withToken($this->key)
            ->post('https://api.openai.com/v1/audio/speech', [
                'model' => $this->model,
                'input' => $text,
                'voice' => $voice,
                'instructions' => $style.$pace,
                'response_format' => 'mp3',
            ]);

        if (! $response->successful()) {
            $message = $response->json('error.message') ?: mb_substr($response->body(), 0, 200);
            throw new RuntimeException(match ($response->status()) {
                401 => 'OpenAI rejected the API key — check it in Settings → Integrations.',
                429 => 'OpenAI quota reached or no billing credit on the account: '.$message,
                default => 'OpenAI voice error '.$response->status().': '.$message,
            });
        }

        return [$response->body(), 'mp3'];
    }
}
