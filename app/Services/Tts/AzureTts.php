<?php

namespace App\Services\Tts;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Microsoft Azure AI Speech — neural Urdu voices (ur-PK-UzmaNeural / ur-PK-AsadNeural).
 */
class AzureTts implements TtsProvider
{
    public function __construct(protected string $key, protected string $region)
    {
    }

    public function synthesize(string $text, string $voice, string $style, int $rate = 0): array
    {
        $lang = substr($voice, 0, 5);
        $ssml = sprintf(
            '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="%s"><voice name="%s"><prosody rate="%+d%%">%s</prosody></voice></speak>',
            htmlspecialchars($lang, ENT_XML1), htmlspecialchars($voice, ENT_XML1), $rate, htmlspecialchars($text, ENT_XML1)
        );

        $response = Http::timeout(30)
            ->withHeaders([
                'Ocp-Apim-Subscription-Key' => $this->key,
                'X-Microsoft-OutputFormat' => 'audio-48khz-192kbitrate-mono-mp3',
                'User-Agent' => 'KNOrganicAdStudio',
            ])
            ->withBody($ssml, 'application/ssml+xml')
            ->post("https://{$this->region}.tts.speech.microsoft.com/cognitiveservices/v1");

        if (! $response->successful()) {
            throw new RuntimeException(match ($response->status()) {
                401, 403 => 'Azure rejected the key — check the Speech key and region in Settings → Integrations.',
                429 => 'Azure free-tier limit reached for now. Try again in a minute.',
                default => 'Azure TTS error '.$response->status().': '.mb_substr($response->body(), 0, 200),
            });
        }

        return [$response->body(), 'mp3'];
    }
}
