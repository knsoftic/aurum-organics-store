<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Analytics\Geo;
use App\Services\Seo\SearchConsole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PharData;
use RecursiveIteratorIterator;
use Throwable;

/** Admin → Marketing → Tracking & Pixels: verification tags, Google tags, ad pixels, custom code. */
class TrackingController extends Controller
{
    protected const FIELDS = [
        'verify_google' => 'nullable|string|max:300',
        'verify_bing' => 'nullable|string|max:300',
        'verify_yandex' => 'nullable|string|max:300',
        'verify_facebook' => 'nullable|string|max:300',
        'verify_pinterest' => 'nullable|string|max:300',
        'ga4_id' => ['nullable', 'regex:/^G-[A-Z0-9]{4,20}$/i'],
        'gtm_id' => ['nullable', 'regex:/^GTM-[A-Z0-9]{4,12}$/i'],
        'gads_id' => ['nullable', 'regex:/^AW-\d{6,14}$/i'],
        'gads_purchase_label' => ['nullable', 'regex:/^[A-Za-z0-9_\-]{4,60}$/'],
        'adsense_client' => ['nullable', 'regex:/^(ca-)?pub-\d{10,20}$/i'],
        'ads_txt' => 'nullable|string|max:5000',
        'meta_pixel_id' => ['nullable', 'regex:/^\d{8,20}$/'],
        'tiktok_pixel_id' => ['nullable', 'regex:/^[A-Z0-9]{10,30}$/i'],
        'snap_pixel_id' => ['nullable', 'regex:/^[a-f0-9\-]{20,40}$/i'],
        'pinterest_tag_id' => ['nullable', 'regex:/^\d{8,20}$/'],
        'x_pixel_id' => ['nullable', 'regex:/^[a-z0-9]{4,12}$/i'],
        'currency_code' => ['required', 'regex:/^[A-Z]{3}$/'],
        'head_code' => 'nullable|string|max:20000',
        'body_code' => 'nullable|string|max:20000',
        'analytics_retention' => 'required|integer|min:30|max:1095',
        'gsc_site' => 'nullable|string|max:200',
        'maxmind_account' => 'nullable|digits_between:3,12',
    ];

    protected const BOOLEANS = ['adsense_enabled', 'adsense_skip_checkout', 'analytics_enabled'];

    protected const SECRETS = ['gsc_credentials', 'maxmind_key'];

    public function edit()
    {
        return view('admin.marketing.tracking', [
            'geoip' => Geo::hasDatabase() ? ['size' => filesize(storage_path(Geo::DB_PATH)), 'date' => filemtime(storage_path(Geo::DB_PATH))] : null,
            'gscEmail' => SearchConsole::serviceEmail(),
        ]);
    }

    public function update(Request $request)
    {
        $rules = self::FIELDS + [
            'gsc_credentials' => 'nullable|string|max:10000',
            'maxmind_key' => 'nullable|string|max:100',
            'geoip_file' => 'nullable|file|max:204800',
        ];
        $data = $request->validate($rules, [
            'ga4_id.regex' => 'The GA4 ID looks like G-XXXXXXXXXX.',
            'gtm_id.regex' => 'The Tag Manager ID looks like GTM-XXXXXXX.',
            'gads_id.regex' => 'The Google Ads ID looks like AW-123456789.',
            'adsense_client.regex' => 'The AdSense publisher ID looks like ca-pub-1234567890123456.',
            'meta_pixel_id.regex' => 'The Meta Pixel ID is a number (15–16 digits).',
            'tiktok_pixel_id.regex' => 'The TikTok Pixel ID looks like CABC123DEF456GHI789.',
        ]);

        $values = collect($data)->only(array_keys(self::FIELDS))->map(fn ($v) => is_string($v) ? trim($v) : $v)->all();
        foreach (['verify_google', 'verify_bing', 'verify_yandex', 'verify_facebook', 'verify_pinterest'] as $key) {
            $values[$key] = $this->verificationCode($values[$key] ?? '');
        }
        foreach (['ga4_id', 'gtm_id', 'gads_id', 'tiktok_pixel_id'] as $key) {
            $values[$key] = strtoupper($values[$key] ?? '');
        }
        if (! empty($values['adsense_client'])) {
            $values['adsense_client'] = 'ca-'.preg_replace('/^ca-/i', '', strtolower($values['adsense_client']));
        }
        foreach (self::BOOLEANS as $flag) {
            $values[$flag] = $request->boolean($flag) ? '1' : '0';
        }

        if (filled($data['gsc_credentials'] ?? null)) {
            $json = json_decode(trim($data['gsc_credentials']), true);
            if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
                return back()->withErrors(['gsc_credentials' => 'Paste the whole service-account JSON key file (it contains "client_email" and "private_key").'])->withInput()->withFragment('gsc');
            }
            $values['gsc_credentials'] = Crypt::encryptString(json_encode($json));
        } elseif ($request->boolean('remove_gsc_credentials')) {
            $values['gsc_credentials'] = '';
        }
        if (filled($data['maxmind_key'] ?? null)) {
            $values['maxmind_key'] = Crypt::encryptString(trim($data['maxmind_key']));
        }

        Setting::setMany($values);

        $message = 'Tracking settings saved.';
        if ($file = $request->file('geoip_file')) {
            if (strtolower($file->getClientOriginalExtension()) !== 'mmdb') {
                return back()->withErrors(['geoip_file' => 'Upload the .mmdb file from the GeoLite2 download.'])->withFragment('geo');
            }
            File::ensureDirectoryExists(dirname(storage_path(Geo::DB_PATH)));
            $file->move(dirname(storage_path(Geo::DB_PATH)), basename(Geo::DB_PATH));
            $message .= ' Location database uploaded.';
        }

        return back()->with('success', $message);
    }

    /** Download the free GeoLite2 City database with the store's MaxMind account. */
    public function downloadGeoip()
    {
        $account = setting('maxmind_account');
        $key = $this->secret('maxmind_key');
        if (! $account || ! $key) {
            return back()->with('error', 'Add your MaxMind account ID and licence key first, then save.')->withFragment('geo');
        }

        $dir = storage_path('app/geoip');
        File::ensureDirectoryExists($dir);
        $archive = $dir.'/download.tar.gz';

        try {
            $response = Http::withBasicAuth($account, $key)->timeout(180)->sink($archive)
                ->get('https://download.maxmind.com/geoip/databases/GeoLite2-City/download', ['suffix' => 'tar.gz']);
            if (! $response->successful()) {
                throw new \RuntimeException($response->status() === 401 ? 'MaxMind rejected the account ID / licence key.' : 'MaxMind download failed (HTTP '.$response->status().').');
            }

            @unlink($dir.'/download.tar');
            (new PharData($archive))->decompress();
            $found = false;
            foreach (new RecursiveIteratorIterator(new PharData($dir.'/download.tar')) as $entry) {
                if (str_ends_with($entry->getFilename(), '.mmdb')) {
                    copy($entry->getPathname(), storage_path(Geo::DB_PATH));
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                throw new \RuntimeException('The download did not contain a .mmdb database.');
            }
        } catch (Throwable $e) {
            return back()->with('error', 'Could not download the location database: '.$e->getMessage())->withFragment('geo');
        } finally {
            @unlink($archive);
            @unlink($dir.'/download.tar');
        }

        return back()->with('success', 'Location database downloaded — new visits now show country and city.')->withFragment('geo');
    }

    /** Accepts either the bare code or the whole <meta ... content="..."> tag the platform shows. */
    protected function verificationCode(string $value): string
    {
        if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $value, $m)) {
            return trim($m[1]);
        }

        return trim(strip_tags($value));
    }

    protected function secret(string $key): ?string
    {
        try {
            return setting($key) ? Crypt::decryptString(setting($key)) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
