<?php

namespace App\Services\Tts;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Microsoft Azure AI Speech — neural text-to-speech (realistic Urdu voices such as
 * ur-PK-UzmaNeural / ur-PK-AsadNeural). Free tier: 0.5M characters per month.
 * Key + region are set in Admin → Settings → Integrations.
 */
class AzureTts
{
    public function __construct(protected string $key, protected string $region)
    {
    }

    public static function fromSettings(): ?self
    {
        $encrypted = setting('azure_speech_key');
        $region = trim((string) setting('azure_speech_region'));
        if (setting('tts_provider') !== 'azure' || ! $encrypted || $region === '') {
            return null;
        }

        try {
            return new self(Crypt::decryptString($encrypted), $region);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Returns MP3 bytes. $rate is a percentage, e.g. -10 (slower) … +20 (faster). */
    public function synthesize(string $text, string $voice, int $rate = 0): string
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

        return $response->body();
    }
}
