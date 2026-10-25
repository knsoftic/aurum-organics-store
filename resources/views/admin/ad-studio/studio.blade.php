@extends('layouts.admin')

@section('title', 'Ad Studio')
@section('crumb')<a href="{{ route('admin.ad-studio.index') }}">Ad Studio</a> / {{ $project->product?->name ?? ($project->config['brief']['name'] ?? '') }}@endsection

@push('head')
    {{-- Font pairs the ad looks rotate through (see FONT_PAIRS in templates.js). --}}
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@1,600&family=Jost:wght@500;600&family=Montserrat:wght@700;800;900&family=Poppins:wght@500;600;700;800;900&family=Playfair+Display:ital,wght@1,600&family=Raleway:wght@500;600;700;800;900&family=Lora:ital,wght@1,600&family=Outfit:wght@500;600;700;800;900&family=DM+Serif+Display:ital@1&family=Noto+Nastaliq+Urdu:wght@600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/ad-studio.css') }}?v={{ filemtime(public_path('css/ad-studio.css')) }}">
@endpush

@section('actions')
    <span class="studio-save" id="saveState">Saved</span>
@endsection

@section('content')
@php
    $themeColors = ['emerald' => '#2b6b4f', 'noir' => '#3a3a3a', 'ivory' => '#8a7340', 'rose' => '#8a3d4a', 'lavender' => '#5a2ea6'];
    $boot = [
        'title' => $project->title,
        'config' => $project->config,
        'uploads' => rtrim(asset('uploads'), '/'),
        // Custom (other company) projects use their own logo; store products use the store's icon.
        'brandMark' => ! empty($project->config['brief']['logo'])
            ? media_url($project->config['brief']['logo'])
            : ($project->product_id && setting('brand_mark') ? media_url(setting('brand_mark')) : null),
        'isCustom' => ! $project->product_id,
        'storeColor' => $themeColors[setting('theme', 'emerald')] ?? '#5a2ea6',
        'ttsReady' => (bool) $ttsProvider,
        'ttsProvider' => $ttsProvider,
        'ttsLabel' => $ttsProvider ? \App\Services\Tts\TtsManager::PROVIDERS[$ttsProvider] : null,
        'aiProvider' => $aiProvider,
        'voices' => \App\Services\Tts\TtsManager::voices($ttsProvider),
        'voiceKeys' => \App\Models\AdProject::VOICE_KEYS,
        'formats' => \App\Models\AdProject::FORMATS,
        'settingsUrl' => route('admin.settings.edit').'#integrations',
        'routes' => [
            'update' => route('admin.ad-studio.update', $project),
            'regenerate' => route('admin.ad-studio.regenerate', $project),
            'aiScript' => route('admin.ad-studio.ai-script', $project),
            'media' => route('admin.ad-studio.media', $project),
            'tts' => route('admin.ad-studio.tts', $project),
            'voice' => route('admin.ad-studio.voice', $project),
            'voiceDelete' => route('admin.ad-studio.voice.delete', $project),
            'chunk' => route('admin.ad-studio.renders.chunk', $project),
        ],
        'renders' => $project->renders->map(fn ($r) => [
            'id' => $r->id, 'url' => $r->url, 'label' => $r->format_label, 'download' => $r->download_name,
            'size' => $r->size, 'duration' => (float) $r->duration, 'voice' => $r->has_voice,
            'date' => $r->created_at->format('d M, h:i A'), 'delete' => route('admin.ad-studio.renders.destroy', $r),
        ])->values(),
    ];
@endphp

<div class="studio">
    <div class="studio-main">
        <div class="card">
            <div class="card-body" style="padding-bottom:0">
                <input class="input studio-title" id="projectTitle" value="{{ $project->title }}" aria-label="Project name">
            </div>
            <div class="tabs studio-tabs" style="padding:0 22px">
                <button type="button" data-step="media" class="active">1 · Media &amp; analysis</button>
                <button type="button" data-step="script">2 · Script</button>
                <button type="button" data-step="voice">3 · Urdu voice</button>
                <button type="button" data-step="render">4 · Render</button>
            </div>
            <div class="card-body">
                <section data-panel="media"></section>
                <section data-panel="script" hidden></section>
                <section data-panel="voice" hidden></section>
                <section data-panel="render" hidden></section>
            </div>
        </div>
    </div>

    <aside class="studio-side">
        <div class="card">
            <div class="card-head">
                <h3>Preview</h3>
                <select class="select" id="previewFormat" style="width:auto;padding:6px 30px 6px 10px"></select>
            </div>
            <div class="card-body">
                <div class="preview-stage"><canvas id="previewCanvas"></canvas><div class="preview-busy" id="previewBusy">Preparing…</div></div>
                <div class="flex" style="margin-top:12px">
                    <button type="button" class="btn btn-light btn-sm" id="previewPlay"><x-icon name="play" /> Play</button>
                    <input type="range" id="previewTime" min="0" max="10" step="0.033" value="1.5" style="flex:1;accent-color:var(--gold)">
                    <span class="muted" id="previewClock" style="font-size:12px;min-width:70px;text-align:right">0.0s</span>
                </div>
                <div class="flex between wrap" style="margin-top:10px;gap:8px">
                    <span class="muted" id="previewLook" style="font-size:12px;flex:1"></span>
                    <button type="button" class="btn btn-light btn-sm" id="previewShuffle">🎲 Shuffle look</button>
                </div>
                <p class="muted" style="font-size:12px;margin:8px 0 0">Preview is silent — the voice and music are mixed in when you render.</p>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h3>Rendered videos</h3></div>
            <div class="card-body" id="renderList"></div>
        </div>
    </aside>
</div>

<canvas id="renderCanvas" hidden></canvas>
<input type="file" id="voiceFile" accept="audio/*" hidden>
@endsection

@push('scripts')
<script>window.STUDIO = @json($boot);</script>
<script src="https://cdn.jsdelivr.net/npm/mp4-muxer@5.2.1/build/mp4-muxer.min.js"></script>
@foreach (['engine', 'audio', 'analyze', 'templates', 'studio'] as $file)
    <script src="{{ asset("js/ad-studio/{$file}.js") }}?v={{ filemtime(public_path("js/ad-studio/{$file}.js")) }}"></script>
@endforeach
@endpush
