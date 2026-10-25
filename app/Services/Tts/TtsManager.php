<?php

namespace App\Services\Tts;

use Illuminate\Support\Facades\Crypt;

/**
 * Picks the voice provider configured in Admin → Settings → Integrations.
 */
class TtsManager
{
    public const PROVIDERS = [
        'gemini' => 'Google Gemini — free tier',
        'openai' => 'OpenAI — openai.fm voices (paid, very cheap)',
        'azure' => 'Microsoft Azure AI Speech',
    ];

    public const DEFAULT_STYLE = 'Speak in natural, fluent Pakistani Urdu with a warm, confident and persuasive TV-commercial tone. Clear pronunciation, lively but relaxed pace.';

    protected const VOICES = [
        'openai' => [
            'marin' => 'Marin — natural (recommended)', 'cedar' => 'Cedar — natural (recommended)', 'coral' => 'Coral — warm',
            'nova' => 'Nova — bright', 'shimmer' => 'Shimmer — soft', 'sage' => 'Sage — calm', 'ash' => 'Ash — confident',
            'ballad' => 'Ballad — smooth', 'verse' => 'Verse — expressive', 'onyx' => 'Onyx — deep', 'echo' => 'Echo — clear',
            'alloy' => 'Alloy — neutral', 'fable' => 'Fable — storyteller',
        ],
        'gemini' => [
            'Sulafat' => 'Sulafat — warm', 'Kore' => 'Kore — firm', 'Aoede' => 'Aoede — breezy', 'Leda' => 'Leda — youthful',
            'Achernar' => 'Achernar — soft', 'Vindemiatrix' => 'Vindemiatrix — gentle', 'Despina' => 'Despina — smooth',
            'Autonoe' => 'Autonoe — bright', 'Laomedeia' => 'Laomedeia — upbeat', 'Pulcherrima' => 'Pulcherrima — forward',
            'Puck' => 'Puck — upbeat', 'Charon' => 'Charon — informative', 'Fenrir' => 'Fenrir — excitable', 'Orus' => 'Orus — firm',
            'Algieba' => 'Algieba — smooth', 'Achird' => 'Achird — friendly', 'Sadachbia' => 'Sadachbia — lively',
            'Gacrux' => 'Gacrux — mature', 'Iapetus' => 'Iapetus — clear', 'Schedar' => 'Schedar — even', 'Zephyr' => 'Zephyr — bright',
        ],
        'azure' => [
            'ur-PK-UzmaNeural' => 'Uzma — Urdu (Pakistan), female', 'ur-PK-AsadNeural' => 'Asad — Urdu (Pakistan), male',
            'ur-IN-GulNeural' => 'Gul — Urdu (India), female', 'ur-IN-SalmanNeural' => 'Salman — Urdu (India), male',
        ],
    ];

    /** The configured provider, or null when none is set up. */
    public static function provider(): ?string
    {
        $provider = setting('tts_provider');

        return match ($provider) {
            'gemini' => self::secret('gemini_api_key') ? 'gemini' : null,
            'openai' => self::secret('openai_api_key') ? 'openai' : null,
            'azure' => self::secret('azure_speech_key') && setting('azure_speech_region') ? 'azure' : null,
            default => null,
        };
    }

    public static function voices(?string $provider): array
    {
        return self::VOICES[$provider] ?? [];
    }

    public static function style(): string
    {
        return trim((string) setting('voice_style')) ?: self::DEFAULT_STYLE;
    }

    public static function make(): ?TtsProvider
    {
        return match (self::provider()) {
            'gemini' => new GeminiTts(self::secret('gemini_api_key'), setting('gemini_tts_model') ?: GeminiTts::MODEL),
            'openai' => new OpenAiTts(self::secret('openai_api_key'), setting('openai_tts_model') ?: OpenAiTts::MODEL),
            'azure' => new AzureTts(self::secret('azure_speech_key'), trim((string) setting('azure_speech_region'))),
            default => null,
        };
    }

    public static function secret(string $key): ?string
    {
        $value = setting($key);
        if (! $value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
