<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Controller;
use App\Models\AdProject;
use App\Models\AdRender;
use App\Models\Product;
use App\Services\AdScriptGenerator;
use App\Services\AiScriptWriter;
use App\Services\Tts\TtsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdStudioController extends Controller
{
    use HandlesUploads;

    public function index()
    {
        return view('admin.ad-studio.index', [
            'projects' => AdProject::with(['product', 'renders'])->latest()->get(),
            'products' => Product::orderByDesc('is_active')->orderBy('name')->get(['id', 'name']),
            'ttsProvider' => TtsManager::provider(),
            'aiProvider' => AiScriptWriter::provider(),
        ]);
    }

    public function store(Request $request, AdScriptGenerator $generator)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'title' => 'nullable|string|max:120',
        ]);

        $product = Product::findOrFail($data['product_id']);
        $project = AdProject::create([
            'title' => $data['title'] ?: $product->name.' — '.now()->format('d M'),
            'product_id' => $product->id,
            'config' => $generator->make($product),
        ]);

        return redirect()->route('admin.ad-studio.show', $project);
    }

    public function show(AdProject $adProject)
    {
        return view('admin.ad-studio.studio', [
            'project' => $adProject->load(['product', 'renders']),
            'ttsProvider' => TtsManager::provider(),
            'aiProvider' => AiScriptWriter::provider(),
        ]);
    }

    /** Autosave from the studio (title + whole config). */
    public function update(Request $request, AdProject $adProject)
    {
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'config' => 'required|array',
            'config.text' => 'required|array',
            'config.voice' => 'required|array',
            'config.media' => 'required|array',
            'config.formats' => 'nullable|array',
            'config.formats.*' => Rule::in(array_keys(AdProject::FORMATS)),
        ]);

        $adProject->update($data);

        return response()->json(['saved' => true]);
    }

    /** Rebuild text + voice lines from the latest product data (media and voice clips are kept). */
    public function regenerate(AdProject $adProject, AdScriptGenerator $generator)
    {
        abort_unless($adProject->product, 422, 'This project has no product.');

        $fresh = $generator->make($adProject->product);
        $config = $adProject->config;
        $config['text'] = $fresh['text'];
        $config['voice']['lines'] = $fresh['voice']['lines'];
        $adProject->update(['config' => $config]);

        return response()->json(['config' => $config]);
    }

    public function uploadMedia(Request $request, AdProject $adProject)
    {
        $request->validate([
            'files' => 'required|array|max:20',
            'files.*' => 'file|mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime|max:102400',
        ]);

        $added = ['images' => [], 'videos' => []];
        foreach ($request->file('files') as $file) {
            $type = str_starts_with($file->getMimeType(), 'video/') ? 'videos' : 'images';
            $added[$type][] = ['path' => $this->storeUpload($file, 'ads/media'), 'enabled' => true];
        }

        $config = $adProject->config;
        foreach ($added as $type => $items) {
            $config['media'][$type] = array_merge($config['media'][$type] ?? [], $items);
        }
        $adProject->update(['config' => $config]);

        return response()->json(['added' => $added, 'media' => $config['media']]);
    }

    /** AI Urdu voice for one scene (Gemini, OpenAI or Azure — Settings → Integrations). */
    public function tts(Request $request, AdProject $adProject)
    {
        $provider = TtsManager::provider();
        $data = $request->validate([
            'key' => ['required', Rule::in(array_keys(AdProject::VOICE_KEYS))],
            'text' => 'required|string|max:1000',
            'voice' => ['required', Rule::in(array_keys(TtsManager::voices($provider)))],
            'rate' => 'nullable|integer|min:-30|max:30',
        ]);

        $tts = TtsManager::make();
        if (! $tts) {
            return response()->json(['message' => 'Choose a voice provider and add its API key in Settings → Integrations first.'], 422);
        }

        try {
            [$audio, $ext] = $tts->synthesize($data['text'], $data['voice'], TtsManager::style(), (int) ($data['rate'] ?? 0));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $path = "ads/voice/{$adProject->id}/{$data['key']}-".Str::random(8).'.'.$ext;
        Storage::disk('uploads')->put($path, $audio);

        return response()->json($this->saveClip($adProject, $data['key'], $path, 'tts'));
    }

    /** Fresh English text + Urdu voice script written by AI (Gemini / OpenAI). */
    public function aiScript(AdProject $adProject, AiScriptWriter $writer)
    {
        try {
            $config = $writer->write($adProject->load('product'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $adProject->update(['config' => $config]);

        return response()->json(['config' => $config]);
    }

    /** Microphone recording or an uploaded audio file for one scene. */
    public function voiceUpload(Request $request, AdProject $adProject)
    {
        $data = $request->validate([
            'key' => ['required', Rule::in(array_keys(AdProject::VOICE_KEYS))],
            'source' => 'required|in:record,upload',
            'audio' => 'required|file|max:20480|mimetypes:audio/webm,video/webm,audio/ogg,audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/wave,audio/mp4,audio/x-m4a,audio/aac,application/octet-stream',
        ]);

        $path = $this->storeUpload($request->file('audio'), "ads/voice/{$adProject->id}");

        return response()->json($this->saveClip($adProject, $data['key'], $path, $data['source']));
    }

    public function voiceDelete(Request $request, AdProject $adProject)
    {
        $key = $request->validate(['key' => ['required', Rule::in(array_keys(AdProject::VOICE_KEYS))]])['key'];

        $config = $adProject->config;
        $this->deleteUpload($config['voice']['clips'][$key]['path'] ?? null);
        unset($config['voice']['clips'][$key]);
        $adProject->update(['config' => $config]);

        return response()->json(['deleted' => true]);
    }

    protected function saveClip(AdProject $project, string $key, string $path, string $source): array
    {
        $config = $project->config;
        $this->deleteUpload($config['voice']['clips'][$key]['path'] ?? null);
        $config['voice']['clips'][$key] = ['path' => $path, 'source' => $source];
        $project->update(['config' => $config]);

        return ['key' => $key, 'path' => $path, 'url' => media_url($path), 'source' => $source];
    }

    /**
     * Rendered MP4s arrive in 4 MB chunks so they pass small PHP/Nginx upload limits.
     */
    public function renderChunk(Request $request, AdProject $adProject)
    {
        $data = $request->validate([
            'upload_id' => 'required|alpha_num|size:32',
            'index' => 'required|integer|min:0',
            'total' => 'required|integer|min:1|max:200',
            'format' => ['required', Rule::in(array_keys(AdProject::FORMATS))],
            'duration' => 'required|numeric|min:1|max:600',
            'has_voice' => 'required|boolean',
            'chunk' => 'required|file|max:6144',
        ]);

        $dir = storage_path("app/private/ad-chunks/{$data['upload_id']}");
        File::ensureDirectoryExists($dir);
        $request->file('chunk')->move($dir, sprintf('%05d', $data['index']));

        if (count(File::files($dir)) < $data['total']) {
            return response()->json(['received' => $data['index']]);
        }

        $path = "ads/renders/{$adProject->id}-{$data['format']}-".Str::random(10).'.mp4';
        $target = public_path('uploads/'.$path);
        File::ensureDirectoryExists(dirname($target));

        $out = fopen($target, 'wb');
        foreach (collect(File::files($dir))->sortBy(fn ($f) => $f->getFilename()) as $part) {
            fwrite($out, File::get($part->getPathname()));
        }
        fclose($out);
        File::deleteDirectory($dir);

        $head = file_get_contents($target, false, null, 0, 12);
        if (substr($head, 4, 4) !== 'ftyp') {
            File::delete($target);

            return response()->json(['message' => 'Upload was not a valid MP4.'], 422);
        }

        $format = AdProject::FORMATS[$data['format']];
        $render = $adProject->renders()->create([
            'format' => $data['format'],
            'path' => $path,
            'width' => $format['w'],
            'height' => $format['h'],
            'duration' => $data['duration'],
            'size' => filesize($target),
            'has_voice' => $data['has_voice'],
        ]);

        return response()->json(['done' => true, 'render' => [
            'id' => $render->id, 'url' => $render->url, 'label' => $render->format_label,
            'download' => $render->download_name, 'size' => $render->size, 'duration' => $render->duration,
            'delete' => route('admin.ad-studio.renders.destroy', $render),
        ]]);
    }

    public function destroyRender(AdRender $adRender)
    {
        $this->deleteUpload($adRender->path);
        $adRender->delete();

        return request()->wantsJson() ? response()->json(['deleted' => true]) : back()->with('success', 'Video deleted.');
    }

    public function destroy(AdProject $adProject)
    {
        foreach ($adProject->renders as $render) {
            $this->deleteUpload($render->path);
        }
        Storage::disk('uploads')->deleteDirectory("ads/voice/{$adProject->id}");
        $adProject->delete();

        return redirect()->route('admin.ad-studio.index')->with('success', 'Ad project deleted.');
    }
}
