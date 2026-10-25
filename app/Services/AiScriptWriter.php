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
 * The model also looks at the product photos / video frames to read labels and details.
 * Only whitelisted keys are taken from the reply; everything else keeps the draft.
 */
class AiScriptWriter
{
    protected const ANGLES = [
        'the power of natural ingredients and tradition',
        'confidence and feeling beautiful every day',
        'a simple daily self-care ritual',
        'trusted by families, gentle for everyone',
        'great value with fast delivery',
        'the visible result after regular use',
        'premium quality at an honest price',
        'a perfect gift for someone you love',
    ];

    public static function provider(): ?string
    {
        return match (setting('tts_provider')) {
            'gemini' => TtsManager::secret('gemini_api_key') ? 'gemini' : null,
            'openai' => TtsManager::secret('openai_api_key') ? 'openai' : null,
            default => TtsManager::secret('gemini_api_key') ? 'gemini' : (TtsManager::secret('openai_api_key') ? 'openai' : null),
        };
    }

    /**
     * @param  array<int, array{mime: string, data: string}>  $images  base64 photos / video frames
     * @return array The project's config with new copy merged in.
     */
    public function write(AdProject $project, array $images = []): array
    {
        $provider = self::provider() ?? throw new RuntimeException('Add a Gemini or OpenAI API key in Settings → Integrations to write scripts with AI.');
        $config = $project->config;

        $system = 'You are a senior performance-marketing copywriter for Pakistani e-commerce brands. You write short, catchy, trustworthy ad copy for TikTok and Meta video ads: English on-screen text and natural spoken Urdu (Urdu script, the way a Pakistani TV presenter speaks). Reply with JSON only.';
        $user = $this->prompt($project, $config, count($images));

        $raw = $provider === 'gemini' ? $this->gemini($system, $user, $images) : $this->openai($system, $user, $images);
        $data = json_decode(trim(preg_replace('/^```(?:json)?|```$/m', '', trim($raw))), true);
        if (! is_array($data)) {
            throw new RuntimeException('The AI reply was not valid JSON — please try again.');
        }

        return $this->merge($config, $data);
    }

    protected function facts(AdProject $project, array $config): array
    {
        $b = $config['brief'] ?? [];
        $product = $project->product;

        return array_filter([
            'brand' => $b['brand'] ?? setting('store_name'),
            'website' => $config['text']['url'] ?? ($b['url'] ?? ''),
            'product' => $b['name'] ?? $product?->name,
            'size' => $b['size'] ?? $product?->size_label,
            'price' => ($config['text']['price'] ?? '') !== '' ? ($config['text']['currency'] ?? 'Rs.').' '.$config['text']['price'] : null,
            'overview' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($b['overview'] ?? $product?->short_description)))), 2000),
            'highlights' => $b['highlights'] ?? $product?->highlights,
            'description' => $product ? Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $product->description))), 1500) : null,
            'free_delivery' => (bool) ($b['freeDelivery'] ?? ((float) setting('shipping_fee', 0) <= 0)),
            'cash_on_delivery' => (bool) ($b['cod'] ?? PaymentMethod::active()->where('type', PaymentMethod::TYPE_COD)->exists()),
            'delivery_area' => $b['deliveryArea'] ?? 'all over Pakistan',
            'ingredients_found' => $config['text']['tags'] ?? [],
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    protected function prompt(AdProject $project, array $config, int $imageCount): string
    {
        $schema = [
            'text' => [
                'productLine1' => 'first part of the product name (shown in elegant italics)',
                'productLine2' => 'rest of the product name in UPPERCASE, max 16 characters',
                'hook' => ['3 short punchy UPPERCASE lines, max 14 characters each'],
                'hookAccent' => 'elegant lowercase phrase that completes the hook, max 14 characters, ends with a full stop',
                'hookUrdu' => 'one short Urdu line in Urdu script (max 45 characters) shown under the hook',
                'hookVariants' => [['lines' => ['3 lines as above'], 'accent' => 'as above', 'urdu' => 'as above']],
                'introChips' => ['up to 3 tiny tags, max 16 characters, e.g. size or who it is for'],
                'benefitsTitle' => 'max 22 characters',
                'benefits' => ['exactly 4 benefits, max 24 characters each'],
                'countNumber' => 'integer — number of natural ingredients/herbs if stated, else 0',
                'countLabel' => 'UPPERCASE label for that counter, max 22 characters',
                'tags' => ['up to 12 key ingredient or feature words, max 16 characters each'],
                'freeFromLine' => 'UPPERCASE line like NO PARABENS · NO SULFATES, or empty',
                'offerSub' => 'max 22 characters, e.g. all over Pakistan',
                'offerChips' => ['up to 3 tags, max 16 characters'],
                'meetBig' => 'one word ending with a full stop, e.g. Magic.',
                'inside' => [['ingredient or feature (max 14 chars)', 'its benefit (max 20 chars)']],
                'nothing' => ['up to 4 things it is free from, max 14 characters, or empty'],
                'steps' => [['step title (max 16 chars)', 'detail (max 34 chars)']],
                'howNote' => 'max 28 characters',
            ],
            'voice' => array_fill_keys(array_keys(AdProject::VOICE_KEYS), 'spoken Urdu in Urdu script, 6–22 words'),
        ];

        $previous = ['text' => Arr::only($config['text'] ?? [], ['hook', 'hookAccent', 'benefits']), 'voice' => $config['voice']['lines'] ?? []];

        return "Write a NEW, fresh script for a video ad. Creative angle for this version: ".Arr::random(self::ANGLES).".\n\n"
            ."PRODUCT FACTS (use only these and what you can clearly see in the images — never invent claims):\n"
            .json_encode($this->facts($project, $config), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n\n"
            .($imageCount ? "{$imageCount} product photo(s) / video frame(s) are attached. Read the label and packaging: product type, name, size, ingredients, claims, colours and who it is for. Use what is clearly visible.\n\n" : '')
            ."PREVIOUS VERSION (write something clearly different):\n".json_encode($previous, JSON_UNESCAPED_UNICODE)."\n\n"
            ."RULES:\n"
            ."- Return ONLY a JSON object with exactly this shape:\n".json_encode($schema, JSON_UNESCAPED_UNICODE)."\n"
            ."- Voice keys: hook, reveal (introduce brand + product), benefits, ingredients, meet (opener of the real-footage reel), inside (ingredients/features), nothing (what it is free from, or a quality promise), howto (how to use), offer (price, delivery, call to action with the website).\n"
            ."- In the Urdu voice lines write numbers and prices in Urdu words (e.g. بائیس سو روپے), and write the brand, product name and website in Urdu letters so they are pronounced correctly.\n"
            ."- Leave arrays empty when the facts/images do not support them (e.g. no ingredients listed). Do not mention delivery offers that are not in the facts.\n"
            ."- No medical or guaranteed-result claims, no before/after, and never address the viewer's personal condition (e.g. do not ask \"is your hair falling?\") — this breaks Meta and TikTok ad policies.\n"
            ."- Keep every length limit. hookVariants must contain 3 different hooks.";
    }

    protected function merge(array $config, array $data): array
    {
        $str = fn ($v, $max) => is_string($v) && trim($v) !== '' ? Str::limit(trim($v), $max, '') : null;
        $list = fn ($v, $count, $max) => is_array($v) ? array_values(array_filter(array_map(fn ($i) => $str($i, $max), array_slice($v, 0, $count)))) : [];
        $pairs = fn ($v, $count, $a, $b) => is_array($v) ? collect($v)->take($count)
            ->map(fn ($r) => is_array($r) ? [$str($r[0] ?? null, $a), (string) $str($r[1] ?? null, $b)] : null)
            ->filter(fn ($r) => $r && $r[0])->values()->all() : [];
        $text = $data['text'] ?? [];

        $strings = [
            'productLine1' => 22, 'productLine2' => 18, 'hookAccent' => 18, 'hookUrdu' => 60, 'benefitsTitle' => 26,
            'countLabel' => 26, 'freeFromLine' => 60, 'offerSub' => 26, 'meetBig' => 14, 'howNote' => 32,
        ];
        foreach ($strings as $key => $max) {
            if ($value = $str($text[$key] ?? null, $max)) {
                $config['text'][$key] = $key === 'productLine2' ? mb_strtoupper($value) : $value;
            }
        }
        if (count($hook = $list($text['hook'] ?? null, 3, 16)) >= 2) {
            $config['text']['hook'] = array_map('mb_strtoupper', $hook);
        }
        foreach (['benefits' => [4, 28, 3], 'introChips' => [3, 18, 1], 'tags' => [12, 18, 3], 'offerChips' => [3, 18, 1], 'nothing' => [4, 16, 2]] as $key => [$count, $max, $min]) {
            if (count($items = $list($text[$key] ?? null, $count, $max)) >= $min) {
                $config['text'][$key] = $items;
            }
        }
        if (count($inside = $pairs($text['inside'] ?? null, 6, 16, 22)) >= 2) {
            $config['text']['inside'] = $inside;
        }
        if (count($steps = $pairs($text['steps'] ?? null, 3, 18, 38)) >= 2) {
            $config['text']['steps'] = $steps;
        }
        if (isset($text['countNumber']) && is_numeric($text['countNumber'])) {
            $config['text']['countNumber'] = max(0, min(99, (int) $text['countNumber']));
        }
        $config['text']['hookVariants'] = collect($text['hookVariants'] ?? [])->take(3)->map(fn ($v) => [
            'lines' => array_map('mb_strtoupper', $list($v['lines'] ?? null, 3, 16)),
            'accent' => $str($v['accent'] ?? null, 18),
            'urdu' => $str($v['urdu'] ?? null, 60),
        ])->filter(fn ($v) => count($v['lines']) >= 2 && $v['accent'])->values()->all() ?: ($config['text']['hookVariants'] ?? []);

        foreach (array_keys(AdProject::VOICE_KEYS) as $key) {
            if ($line = $str($data['voice'][$key] ?? null, 400)) {
                $config['voice']['lines'][$key] = $line;
            }
        }
        $config['meta']['aiWrittenAt'] = now()->toIso8601String();

        return $config;
    }

    /** Product photo from the uploads disk → small JPEG for the AI (keeps requests light). */
    public static function imageForAi(string $path, int $max = 768): ?array
    {
        $file = public_path('uploads/'.ltrim($path, '/'));
        if (! is_file($file) || ! ($info = @getimagesize($file))) {
            return null;
        }
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG => @imagecreatefrompng($file),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file),
            default => null,
        };
        if (! $src) {
            return null;
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, $max / max($w, $h));
        $dst = imagecreatetruecolor((int) round($w * $scale), (int) round($h * $scale));
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
        ob_start();
        imagejpeg($dst, null, 82);

        return ['mime' => 'image/jpeg', 'data' => base64_encode(ob_get_clean())];
    }

    protected function gemini(string $system, string $user, array $images): string
    {
        $input = $images
            ? array_merge([['type' => 'text', 'text' => $user]], array_map(fn ($i) => ['type' => 'image', 'data' => $i['data'], 'mime_type' => $i['mime']], $images))
            : $user;

        $response = Http::timeout(120)
            ->withHeaders(['x-goog-api-key' => TtsManager::secret('gemini_api_key')])
            ->post('https://generativelanguage.googleapis.com/v1beta/interactions', [
                'model' => setting('gemini_text_model') ?: 'gemini-3.8-flash',
                'system_instruction' => $system,
                'input' => $input,
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

    protected function openai(string $system, string $user, array $images): string
    {
        $content = $images
            ? array_merge([['type' => 'text', 'text' => $user]], array_map(fn ($i) => ['type' => 'image_url', 'image_url' => ['url' => "data:{$i['mime']};base64,{$i['data']}", 'detail' => 'auto']], $images))
            : $user;

        $response = Http::timeout(120)
            ->withToken(TtsManager::secret('openai_api_key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => setting('openai_text_model') ?: 'gpt-4o-mini',
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.9,
                'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $content]],
            ]);
        if (! $response->successful()) {
            $message = $response->json('error.message') ?: mb_substr($response->body(), 0, 200);
            throw new RuntimeException(match ($response->status()) {
                401, 403 => 'OpenAI rejected the API key — check it in Settings → Integrations.',
                429 => 'OpenAI quota reached or no billing credit on the account: '.$message,
                default => "OpenAI error {$response->status()}: {$message}",
            });
        }

        return (string) $response->json('choices.0.message.content');
    }
}
