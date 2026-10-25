<?php

namespace App\Services;

use App\Models\AdProject;
use App\Models\PaymentMethod;
use App\Services\Tts\GeminiTts;
use App\Services\Tts\TtsManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Writes a fresh ad script (English on-screen text + spoken Urdu voice lines) with the
 * AI provider chosen in Settings → Integrations (Gemini free tier or OpenAI).
 * Only whitelisted keys are taken from the model; everything else keeps the draft.
 */
class AiScriptWriter
{
    protected const ANGLES = [
        'the power of natural ingredients and tradition',
        'confidence and feeling beautiful every day',
        'a simple daily self-care ritual',
        'trusted by families, gentle for everyone',
        'great value with free delivery and cash on delivery',
        'visible shine and softness after regular use',
    ];

    public static function provider(): ?string
    {
        return match (setting('tts_provider')) {
            'gemini' => TtsManager::secret('gemini_api_key') ? 'gemini' : null,
            'openai' => TtsManager::secret('openai_api_key') ? 'openai' : null,
            default => TtsManager::secret('gemini_api_key') ? 'gemini' : (TtsManager::secret('openai_api_key') ? 'openai' : null),
        };
    }

    /** Returns the project's config with new copy merged in. */
    public function write(AdProject $project): array
    {
        $provider = self::provider() ?? throw new RuntimeException('Add a Gemini or OpenAI API key in Settings → Integrations to write scripts with AI.');
        $config = $project->config;
        $product = $project->product;

        $facts = [
            'brand' => setting('store_name'),
            'website' => $config['text']['url'] ?? '',
            'product' => $product?->name,
            'size' => $product?->size_label,
            'price' => ($config['text']['currency'] ?? 'Rs.').' '.($config['text']['price'] ?? ''),
            'short_description' => $product?->short_description,
            'highlights' => $product?->highlights,
            'description' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $product?->description))), 1500),
            'free_delivery' => (float) setting('shipping_fee', 0) <= 0,
            'cash_on_delivery' => PaymentMethod::active()->where('type', PaymentMethod::TYPE_COD)->exists(),
            'ingredients' => $config['text']['tags'] ?? [],
        ];

        $schema = [
            'text' => [
                'hook' => ['3 short punchy UPPERCASE lines, max 14 characters each'],
                'hookAccent' => 'elegant lowercase phrase that completes the hook, max 14 characters, ends with a full stop',
                'hookUrdu' => 'one short Urdu line in Urdu script (max 45 characters) shown under the hook',
                'hookVariants' => [['lines' => ['3 lines as above'], 'accent' => 'as above', 'urdu' => 'as above']],
                'benefitsTitle' => 'max 22 characters',
                'benefits' => ['exactly 4 benefits, max 24 characters each, only from the facts'],
                'countLabel' => 'UPPERCASE label for the ingredient counter, max 22 characters',
                'offerSub' => 'max 22 characters, e.g. all over Pakistan',
                'meetBig' => 'one word ending with a full stop, e.g. Magic.',
                'insideTitle2' => 'max 12 characters, e.g. inside?',
                'nothingTitle2' => 'max 18 characters',
                'howNote' => 'max 28 characters',
            ],
            'voice' => array_fill_keys(array_keys(AdProject::VOICE_KEYS), 'spoken Urdu in Urdu script, 6–22 words'),
        ];

        $system = 'You are a senior performance-marketing copywriter for Pakistani e-commerce brands. You write short, catchy, trustworthy ad copy for TikTok and Meta video ads: English on-screen text and natural spoken Urdu (Urdu script, the way a Pakistani TV presenter speaks). Reply with JSON only.';
        $user = "Write a NEW, fresh script for a video ad. Creative angle for this version: ".Arr::random(self::ANGLES).".\n\n"
            ."PRODUCT FACTS (use only these — never invent claims):\n".json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n\n"
            ."PREVIOUS VERSION (write something clearly different):\n".json_encode(['text' => Arr::only($config['text'] ?? [], array_keys($schema['text'])), 'voice' => $config['voice']['lines'] ?? []], JSON_UNESCAPED_UNICODE)."\n\n"
            ."RULES:\n"
            ."- Return ONLY a JSON object with exactly this shape:\n".json_encode($schema, JSON_UNESCAPED_UNICODE)."\n"
            ."- Voice keys: hook, reveal (introduce brand + product), benefits, ingredients, meet (opener of the real-footage reel), inside (ingredients), nothing (what it is free from), howto (how to use), offer (price, delivery, call to action with the website).\n"
            ."- In the Urdu voice lines write numbers and prices in Urdu words (e.g. بائیس سو روپے), and write the brand, product name and website in Urdu letters so they are pronounced correctly.\n"
            ."- No medical or guaranteed-result claims, no before/after, and never address the viewer's personal condition (e.g. do not ask \"is your hair falling?\") — this breaks Meta and TikTok ad policies.\n"
            ."- Keep every length limit. hookVariants must contain 3 different hooks.";

        $raw = $provider === 'gemini' ? $this->gemini($system, $user) : $this->openai($system, $user);
        $data = json_decode(trim(preg_replace('/^```(?:json)?|```$/m', '', trim($raw))), true);
        if (! is_array($data)) {
            throw new RuntimeException('The AI reply was not valid JSON — please try again.');
        }

        return $this->merge($config, $data);
    }

    protected function merge(array $config, array $data): array
    {
        $str = fn ($v, $max) => is_string($v) && trim($v) !== '' ? Str::limit(trim($v), $max, '') : null;
        $list = fn ($v, $count, $max) => is_array($v) ? array_values(array_filter(array_map(fn ($i) => $str($i, $max), array_slice($v, 0, $count)))) : [];
        $text = $data['text'] ?? [];

        $set = [
            'hookAccent' => $str($text['hookAccent'] ?? null, 18), 'hookUrdu' => $str($text['hookUrdu'] ?? null, 60),
            'benefitsTitle' => $str($text['benefitsTitle'] ?? null, 26), 'countLabel' => $str($text['countLabel'] ?? null, 26),
            'offerSub' => $str($text['offerSub'] ?? null, 26), 'meetBig' => $str($text['meetBig'] ?? null, 14),
            'insideTitle2' => $str($text['insideTitle2'] ?? null, 14), 'nothingTitle2' => $str($text['nothingTitle2'] ?? null, 22),
            'howNote' => $str($text['howNote'] ?? null, 32),
        ];
        foreach (array_filter($set) as $key => $value) {
            $config['text'][$key] = $value;
        }
        if (count($hook = $list($text['hook'] ?? null, 3, 16)) >= 2) {
            $config['text']['hook'] = array_map('mb_strtoupper', $hook);
        }
        if (count($benefits = $list($text['benefits'] ?? null, 4, 28)) >= 3) {
            $config['text']['benefits'] = $benefits;
        }
        $config['text']['hookVariants'] = collect($text['hookVariants'] ?? [])->take(3)->map(fn ($v) => [
            'lines' => array_map('mb_strtoupper', $list($v['lines'] ?? null, 3, 16)),
            'accent' => $str($v['accent'] ?? null, 18),
            'urdu' => $str($v['urdu'] ?? null, 60),
        ])->filter(fn ($v) => count($v['lines']) >= 2 && $v['accent'])->values()->all();

        foreach (array_keys(AdProject::VOICE_KEYS) as $key) {
            if ($line = $str($data['voice'][$key] ?? null, 400)) {
                $config['voice']['lines'][$key] = $line;
            }
        }
        $config['meta']['aiWrittenAt'] = now()->toIso8601String();

        return $config;
    }

    protected function gemini(string $system, string $user): string
    {
        $response = Http::timeout(90)
            ->withHeaders(['x-goog-api-key' => TtsManager::secret('gemini_api_key')])
            ->post('https://generativelanguage.googleapis.com/v1beta/interactions', [
                'model' => setting('gemini_text_model') ?: 'gemini-3.8-flash',
                'system_instruction' => $system,
                'input' => $user,
            ]);
        if (! $response->successful()) {
            throw new RuntimeException(GeminiTts::friendlyError($response, 'Gemini script'));
        }

        $text = '';
        foreach ((array) $response->json('steps', []) as $step) {
            foreach ((array) ($step['content'] ?? []) as $part) {
                if (($part['type'] ?? null) === 'text' && isset($part['text'])) {
                    $text = $part['text'];
                }
            }
        }

        return $text ?: throw new RuntimeException('Gemini returned an empty reply.');
    }

    protected function openai(string $system, string $user): string
    {
        $response = Http::timeout(90)
            ->withToken(TtsManager::secret('openai_api_key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => setting('openai_text_model') ?: 'gpt-4o-mini',
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.9,
                'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
            ]);
        $this->check($response, 'OpenAI');

        return (string) $response->json('choices.0.message.content');
    }

    protected function check($response, string $name): void
    {
        if ($response->successful()) {
            return;
        }
        $message = $response->json('error.message') ?: mb_substr($response->body(), 0, 200);
        throw new RuntimeException(match ($response->status()) {
            401, 403 => "{$name} rejected the API key — check it in Settings → Integrations.",
            429 => "{$name} limit reached for now — wait a minute and try again. ({$message})",
            default => "{$name} error {$response->status()}: {$message}",
        });
    }
}
