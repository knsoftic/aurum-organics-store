<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Builds a ready-to-render ad configuration from a product's own data — no AI/API needed.
 * English on-screen text comes from the product fields; the Urdu voice script is assembled
 * from templates and small dictionaries. Everything stays editable in the studio.
 */
class AdScriptGenerator
{
    protected const INGREDIENT_BENEFITS = [
        'coconut' => 'Deep nourishment', 'amla' => 'Stronger roots', 'bhringraj' => 'Healthy growth',
        'hibiscus' => 'Softness & shine', 'rosemary' => 'Scalp care', 'argan' => 'Smooth & frizz-free',
        'castor' => 'Thicker-looking hair', 'almond' => 'Soft & smooth', 'jojoba' => 'Balanced scalp',
        'olive' => 'Deep conditioning', 'fenugreek' => 'Strength', 'brahmi' => 'Calming care',
        'tulsi' => 'Fresh scalp', 'lavender' => 'Soothing aroma', 'onion' => 'Hair strength',
        'aloe' => 'Hydration', 'neem' => 'Clean scalp', 'honey' => 'Natural moisture',
        'black seed' => 'Root care', 'kalonji' => 'Root care', 'vitamin e' => 'Antioxidant care',
    ];

    protected const URDU_WORDS = [
        'coconut' => 'ناریل', 'amla' => 'آملہ', 'bhringraj' => 'بھرنگ راج', 'hibiscus' => 'گڑہل', 'rosemary' => 'روزمیری',
        'argan' => 'آرگن', 'castor' => 'کیسٹر', 'almond' => 'بادام', 'jojoba' => 'جوجوبا', 'olive' => 'زیتون',
        'fenugreek' => 'میتھی', 'brahmi' => 'برہمی', 'tulsi' => 'تلسی', 'lavender' => 'لیونڈر', 'onion' => 'پیاز',
        'aloe' => 'ایلوویرا', 'neem' => 'نیم', 'honey' => 'شہد', 'kalonji' => 'کلونجی', 'black seed' => 'کلونجی',
        'parabens' => 'پیرابین', 'sulfates' => 'سلفیٹ', 'silicones' => 'سلیکون', 'mineral oil' => 'منرل آئل',
        'chemicals' => 'کیمیکلز', 'alcohol' => 'الکحل', 'fragrance' => 'مصنوعی خوشبو',
        // Transliteration for product / brand names
        'magical' => 'میجیکل', 'hair' => 'ہیئر', 'oil' => 'آئل', 'organic' => 'آرگینک', 'serum' => 'سیرم',
        'shampoo' => 'شیمپو', 'cream' => 'کریم', 'herbal' => 'ہربل', 'natural' => 'نیچرل', 'growth' => 'گروتھ',
        'beauty' => 'بیوٹی', 'skin' => 'اسکن', 'care' => 'کیئر', 'pure' => 'پیور', 'gold' => 'گولڈ', 'royal' => 'رائل',
        'site' => 'سائٹ', 'com' => 'کام', 'pk' => 'پی کے', 'store' => 'اسٹور', 'shop' => 'شاپ', 'online' => 'آن لائن',
    ];

    protected const URDU_LETTERS = [
        'A' => 'اے', 'B' => 'بی', 'C' => 'سی', 'D' => 'ڈی', 'E' => 'ای', 'F' => 'ایف', 'G' => 'جی', 'H' => 'ایچ', 'I' => 'آئی',
        'J' => 'جے', 'K' => 'کے', 'L' => 'ایل', 'M' => 'ایم', 'N' => 'این', 'O' => 'او', 'P' => 'پی', 'Q' => 'کیو', 'R' => 'آر',
        'S' => 'ایس', 'T' => 'ٹی', 'U' => 'یو', 'V' => 'وی', 'W' => 'ڈبلیو', 'X' => 'ایکس', 'Y' => 'وائی', 'Z' => 'زیڈ',
    ];

    public function make(Product $product): array
    {
        $product->loadMissing(['images', 'category']);

        $html = (string) $product->description;
        $text = Str::lower(strip_tags($product->name.'. '.$product->short_description.'. '.implode('. ', $product->highlights ?? []).'. '.$html.' '.$product->category?->name));
        $isHair = Str::contains($text, ['hair', 'scalp']);

        $brand = setting('store_name', config('app.name'));
        $domain = parse_url(config('app.url'), PHP_URL_HOST) ?: request()->getHost();
        $domain = preg_replace('/^www\./', '', (string) $domain);
        $free = (float) setting('shipping_fee', 0) <= 0;
        $cod = PaymentMethod::active()->where('type', PaymentMethod::TYPE_COD)->exists();
        $price = number_format((float) $product->price, fmod((float) $product->price, 1.0) ? 2 : 0);

        $ingredients = $this->ingredients($html);
        $count = $this->herbCount($text, count($ingredients));
        $freeFrom = $this->freeFrom($text);
        $benefits = $this->benefits($product, $html);
        $steps = $this->steps($html, $isHair);
        [$line1, $line2] = $this->splitName($product->name);

        $brandUr = $this->urdu($brand);
        $productUr = $this->urdu($product->name);
        $domainUr = $this->urduDomain($domain, $brand, $brandUr);
        $ingredientsUr = array_values(array_filter(array_map(fn ($i) => self::URDU_WORDS[Str::lower($i)] ?? null, $ingredients)));

        $offerChips = array_values(array_filter([$cod ? 'Cash on Delivery' : null, Str::contains($text, ['100% natural', '100% organic', 'organic']) ? '100% Natural' : null, $product->size_label]));

        return [
            'palette' => ['mode' => 'auto', 'primary' => null],
            'formats' => ['motion', 'footage', 'landscape'],
            'music' => ['enabled' => true, 'volume' => 0.8],
            'media' => [
                'images' => collect([$product->image])->merge($product->images->pluck('path'))->filter()->unique()
                    ->map(fn ($p) => ['path' => $p, 'enabled' => true])->values()->all(),
                'videos' => collect($product->videos ?? [])->map(fn ($p) => ['path' => $p, 'enabled' => true])->values()->all(),
            ],
            'analysis' => null,
            'text' => [
                'brand' => Str::upper($brand),
                'url' => $domain,
                'currency' => setting('currency_symbol', 'Rs.'),
                'price' => $price,
                'size' => (string) $product->size_label,
                'productLine1' => $line1,
                'productLine2' => $line2,
                'hook' => $isHair ? ['STRONGER,', 'SHINIER HAIR', 'STARTS AT'] : ['PURE.', 'NATURAL.', 'MADE WITH'],
                'hookAccent' => $isHair ? 'the roots.' : 'care.',
                'hookUrdu' => $isHair ? 'مضبوط بالوں کا راز — صحت مند جڑیں' : 'قدرتی اجزاء، بہترین معیار',
                // Picked at random per render so every video opens differently.
                'hookVariants' => $isHair ? [
                    ['lines' => ['STRONGER,', 'SHINIER HAIR', 'STARTS AT'], 'accent' => 'the roots.', 'urdu' => 'مضبوط بالوں کا راز — صحت مند جڑیں'],
                    ['lines' => ['NOURISH.', 'STRENGTHEN.', 'SHINE.'], 'accent' => 'naturally.', 'urdu' => 'قدرتی جڑی بوٹیوں کی طاقت'],
                    ['lines' => ['YOUR DAILY', 'DOSE OF', 'NATURE'], 'accent' => 'for hair.', 'urdu' => 'روزانہ کی قدرتی دیکھ بھال'],
                ] : [
                    ['lines' => ['PURE.', 'NATURAL.', 'MADE WITH'], 'accent' => 'care.', 'urdu' => 'قدرتی اجزاء، بہترین معیار'],
                    ['lines' => ['NATURE’S', 'BEST, IN', 'EVERY DROP'], 'accent' => 'for you.', 'urdu' => 'ہر قطرے میں قدرت'],
                    ['lines' => ['FEEL THE', 'DIFFERENCE', 'EVERY DAY'], 'accent' => 'naturally.', 'urdu' => 'ہر دن، قدرتی احساس'],
                ],
                'intro' => 'INTRODUCING',
                'introChips' => array_values(array_filter([$product->size_label, $isHair ? 'Men & Women' : null, $isHair ? 'All hair types' : null])),
                'benefitsTitle' => 'Why you’ll love it',
                'benefits' => $benefits,
                'countNumber' => $count,
                'countLabel' => $isHair ? 'NATURAL HERBS & OILS' : 'NATURAL INGREDIENTS',
                'countUrdu' => $count ? $count.' سے زائد قدرتی جڑی بوٹیاں اور تیل' : '',
                'tags' => array_slice($ingredients, 0, 12),
                'freeFromLine' => $freeFrom ? Str::upper('No '.implode('  ·  No ', array_slice($freeFrom, 0, 3))) : '',
                'offerTitle' => $free ? 'FREE DELIVERY' : 'CASH ON DELIVERY',
                'offerSub' => 'all over Pakistan',
                'offerUrdu' => $free ? 'پورے پاکستان میں مفت ڈیلیوری' : 'پورے پاکستان میں کیش آن ڈیلیوری',
                'offerChips' => array_slice($offerChips, 0, 3),
                'cta' => 'SHOP NOW',
                'meetTop' => 'MEET THE',
                'meetBig' => $isHair ? 'Magic.' : 'Difference.',
                'meetSub' => trim($brand.'  ·  '.$product->name.($product->size_label ? '  ·  '.$product->size_label : '')),
                'insideTitle1' => 'WHAT’S',
                'insideTitle2' => 'inside?',
                'inside' => collect($ingredients)->map(fn ($i) => [$i, self::INGREDIENT_BENEFITS[Str::lower($i)] ?? 'Natural goodness'])->take(6)->values()->all(),
                'insideMore' => count($ingredients) > 6 ? '+ '.(max($count, count($ingredients)) - 6).' more herbs & oils' : '',
                'insideMoreList' => implode(' · ', array_slice($ingredients, 6, 8)),
                'nothingTitle1' => 'NOTHING',
                'nothingTitle2' => 'you don’t need.',
                'nothing' => array_slice($freeFrom, 0, 4),
                'stamp1' => '100%',
                'stamp2' => 'NATURAL',
                'howTitle' => 'How to use',
                'steps' => $steps,
                'howNote' => $isHair ? 'Use 2–3 times a week' : '',
            ],
            'voice' => [
                'mode' => 'none',
                'voice' => null,
                'rate' => 0,
                'lines' => [
                    'hook' => $isHair ? 'مضبوط اور چمکدار بالوں کا راز… صحت مند جڑیں۔' : 'قدرتی اجزاء، اور بہترین معیار۔',
                    'reveal' => "پیش ہے {$brandUr} کا {$productUr}۔",
                    'benefits' => $isHair
                        ? 'یہ بالوں کا گرنا کم کرتا ہے، نشوونما بڑھاتا ہے، سر کی جلد کو غذائیت دیتا ہے، اور بالوں میں قدرتی چمک لاتا ہے۔'
                        : "{$productUr} — روزمرہ استعمال کے لیے بہترین انتخاب۔",
                    'ingredients' => $count
                        ? "{$count} سے زائد قدرتی جڑی بوٹیوں اور تیلوں سے بھرپور"
                            .($ingredientsUr ? ' — '.$this->urduList(array_slice($ingredientsUr, 0, 5)) : '').'۔'
                        : 'قدرتی اجزاء سے تیار۔',
                    'meet' => "ملیے {$productUr} سے۔",
                    'inside' => $ingredientsUr ? 'اس میں ہے '.$this->urduList(array_slice($ingredientsUr, 0, 6)).'۔' : 'اس میں صرف قدرتی اجزاء ہیں۔',
                    'nothing' => $freeFrom
                        ? $this->urduList(array_map(fn ($f) => self::URDU_WORDS[Str::lower($f)] ?? $f, array_slice($freeFrom, 0, 4))).' سے پاک — سو فیصد قدرتی۔'
                        : 'سو فیصد قدرتی۔',
                    'howto' => $isHair
                        ? 'تھوڑا سا تیل سر کی جلد پر لگائیں، تین سے پانچ منٹ مساج کریں، اور ایک گھنٹہ یا رات بھر لگا رہنے دیں۔'
                        : 'استعمال کا طریقہ نہایت آسان ہے۔',
                    'offer' => "صرف {$price} روپے میں"
                        .($free ? '، پورے پاکستان میں مفت ڈیلیوری' : '')
                        .($cod ? ($free ? ' اور' : '،').' کیش آن ڈیلیوری کے ساتھ' : '')
                        ."۔ ابھی آرڈر کریں — {$domainUr}۔",
                ],
                'clips' => [],
            ],
        ];
    }

    /** "Magical Hair Oil" → ["Magical", "HAIR OIL"]. */
    protected function splitName(string $name): array
    {
        $words = preg_split('/\s+/', trim($name));
        if (count($words) < 2) {
            return [$name, ''];
        }

        return [array_shift($words), Str::upper(implode(' ', $words))];
    }

    protected function headingBlock(string $html, array $keywords, string $tag): ?string
    {
        foreach ($keywords as $word) {
            if (preg_match('/<h[2-4][^>]*>[^<]*'.preg_quote($word, '/').'[^<]*<\/h[2-4]>\s*<'.$tag.'[^>]*>(.*?)<\/'.$tag.'>/is', $html, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    protected function listItems(?string $block): array
    {
        if (! $block) {
            return [];
        }
        preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $block, $m);

        return array_values(array_filter(array_map(fn ($i) => trim(html_entity_decode(strip_tags($i))), $m[1])));
    }

    protected function ingredients(string $html): array
    {
        $block = $this->headingBlock($html, ['ingredient'], 'p');
        if (! $block) {
            return [];
        }

        $text = html_entity_decode(strip_tags($block));
        $text = preg_replace('/(&|and)\s+more\.?$/i', '', trim($text));

        return collect(preg_split('/,|·|\band\b|&/i', $text))
            ->map(fn ($i) => trim(preg_replace('/\b(oil|extract|seed oil)\b/i', '', trim($i, " .\t\n"))))
            ->filter(fn ($i) => $i !== '' && mb_strlen($i) <= 18)
            ->map(fn ($i) => Str::title($i))
            ->unique()->values()->all();
    }

    protected function herbCount(string $text, int $fallback): int
    {
        if (preg_match('/(\d{1,2})\s*\+?\s*(natural\s+)?(herbs|ingredients|oils|extracts)/i', $text, $m)) {
            return (int) $m[1];
        }

        return $fallback >= 5 ? $fallback : 0;
    }

    protected function freeFrom(string $text): array
    {
        if (! preg_match('/\bno ([a-z ,]+?)(?:\.|$|\n| ·)/i', $text, $m)) {
            return [];
        }

        return collect(preg_split('/,|\bor\b|\band\b/', $m[1]))
            ->map(fn ($i) => Str::ucfirst(trim($i)))
            ->filter(fn ($i) => $i !== '' && mb_strlen($i) <= 16)
            ->values()->all();
    }

    protected function benefits(Product $product, string $html): array
    {
        $short = array_filter($this->listItems($this->headingBlock($html, ['love', 'benefit'], 'ul')), fn ($b) => mb_strlen($b) <= 28);
        if (count($short) >= 3) {
            return array_slice(array_values($short), 0, 4);
        }

        return collect($product->highlights ?? [])
            ->map(fn ($h) => Str::ucfirst(trim(preg_split('/,| and | · /', $h)[0])))
            ->map(fn ($h) => Str::limit($h, 28, ''))
            ->take(4)->values()->all();
    }

    protected function steps(string $html, bool $isHair): array
    {
        $items = $this->listItems($this->headingBlock($html, ['how to use', 'direction', 'usage'], 'ol'));

        if ($isHair && $items && Str::contains(Str::lower(implode(' ', $items)), 'massage')) {
            return [
                ['Apply to scalp', 'A few pumps on scalp & hair'],
                ['Massage gently', '3–5 minutes with your fingertips'],
                ['Leave & wash', '1 hour or overnight, then mild shampoo'],
            ];
        }

        return collect($items)->take(3)->map(function ($item) {
            $words = preg_split('/\s+/', $item);

            return [Str::ucfirst(implode(' ', array_slice($words, 0, 2))), Str::limit(implode(' ', array_slice($words, 2)), 42)];
        })->all();
    }

    /** Word-by-word Urdu transliteration; all-caps acronyms are spelled with Urdu letter names. */
    public function urdu(string $text): string
    {
        return collect(preg_split('/\s+/', trim($text)))->map(function ($word) {
            $key = Str::lower($word);
            if (isset(self::URDU_WORDS[$key])) {
                return self::URDU_WORDS[$key];
            }
            if (preg_match('/^[A-Z]{2,4}$/', $word)) {
                return implode(' ', array_map(fn ($c) => self::URDU_LETTERS[$c], str_split($word)));
            }

            return $word;
        })->implode(' ');
    }

    protected function urduDomain(string $domain, string $brand, string $brandUr): string
    {
        if (! str_contains($domain, '.')) {
            return $this->urdu($domain);
        }
        $parts = explode('.', $domain);
        $tld = array_pop($parts);
        $name = implode('.', $parts);
        $nameUr = Str::lower(str_replace(' ', '', $brand)) === Str::lower($name) ? $brandUr : $this->urdu($name);

        return $nameUr.' ڈاٹ '.(self::URDU_WORDS[Str::lower($tld)] ?? $tld);
    }

    protected function urduList(array $items): string
    {
        if (count($items) < 2) {
            return implode('', $items);
        }
        $last = array_pop($items);

        return implode('، ', $items).' اور '.$last;
    }
}
