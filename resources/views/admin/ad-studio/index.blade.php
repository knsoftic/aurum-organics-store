@extends('layouts.admin')

@section('title', 'Ad Studio')
@section('crumb', 'Marketing')

@section('content')
<div class="grid grid-main">
    <div>
        <div class="card">
            <div class="card-head"><h3>Your ad projects</h3></div>
            @forelse ($projects as $project)
                <div class="card-body flex between wrap" style="border-bottom:1px solid var(--line)">
                    <div style="flex:1;min-width:220px">
                        <a href="{{ route('admin.ad-studio.show', $project) }}" class="cell-main" style="font-size:15px">{{ $project->title }}</a>
                        <div class="cell-sub">{{ $project->product?->name ?? (($project->config['brief']['brand'] ?? 'Custom').' · custom product') }} · {{ $project->renders->count() }} {{ \Illuminate\Support\Str::plural('video', $project->renders->count()) }} · updated {{ $project->updated_at->diffForHumans() }}</div>
                    </div>
                    <div class="flex">
                        @foreach ($project->renders->take(3) as $render)
                            <video src="{{ $render->url }}#t=1" preload="metadata" muted style="height:64px;border-radius:6px;background:#000"></video>
                        @endforeach
                    </div>
                    <div class="flex">
                        <a href="{{ route('admin.ad-studio.show', $project) }}" class="btn btn-primary btn-sm">Open studio</a>
                        <form method="POST" action="{{ route('admin.ad-studio.destroy', $project) }}" class="inline-form" data-confirm="Delete this ad project and its videos?">
                            @csrf @method('DELETE')
                            <button class="icon-action danger" title="Delete"><x-icon name="trash" /></button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="empty">No ad projects yet — create your first one on the right.</div>
            @endforelse
        </div>
    </div>

    <div>
        <form method="POST" action="{{ route('admin.ad-studio.store-custom') }}" enctype="multipart/form-data" class="card">
            @csrf
            <div class="card-head"><h3>New ad — any product / company</h3></div>
            <div class="card-body" style="display:grid;gap:12px">
                <p class="muted" style="margin:0;font-size:12.5px">For products that are not in this store. You add the photos and videos on the next screen; the studio (and AI, if connected) reads them and writes the script.</p>
                <div class="form-grid" style="gap:10px 12px">
                    <div class="field"><label>Brand / company</label><input class="input" name="brand" value="{{ old('brand') }}" required maxlength="80" placeholder="e.g. Glow Naturals"></div>
                    <div class="field"><label>Product name</label><input class="input" name="name" value="{{ old('name') }}" required maxlength="120" placeholder="e.g. Vitamin C Serum"></div>
                    <div class="field"><label>Size <span class="muted">(optional)</span></label><input class="input" name="size" value="{{ old('size') }}" placeholder="30 ml"></div>
                    <div class="field"><label>Price <span class="muted">(optional)</span></label><input class="input" type="number" step="0.01" min="0" name="price" value="{{ old('price') }}" placeholder="1850"></div>
                    <div class="field"><label>Website / link</label><input class="input" name="url" value="{{ old('url') }}" placeholder="glownaturals.pk"></div>
                    <div class="field"><label>Button text</label><input class="input" name="cta" value="{{ old('cta', 'SHOP NOW') }}" maxlength="24"></div>
                    <div class="field full"><label>Product overview</label>
                        <textarea class="textarea" name="overview" style="min-height:150px" maxlength="6000" placeholder="What it is and who it's for.&#10;Benefits:&#10;- Brightens skin&#10;- Fades dark spots&#10;Ingredients: Vitamin C, Aloe Vera&#10;How to use:&#10;1. Cleanse face&#10;2. Apply 3 drops">{{ old('overview') }}</textarea>
                        <div class="hint">Write freely — "Benefits:", "Ingredients:" and "How to use:" sections with bullet points are picked up automatically.</div>
                    </div>
                    <div class="field"><label>Delivery area</label><input class="input" name="delivery_area" value="{{ old('delivery_area', 'all over Pakistan') }}"></div>
                    <div class="field"><label>Logo <span class="muted">(optional, square)</span></label><input type="file" name="logo" accept="image/*" style="font-size:12.5px"></div>
                    <div class="field full flex wrap" style="gap:18px">
                        <label class="switch"><input type="checkbox" name="free_delivery" value="1" @checked(old('free_delivery'))><span class="track"></span> Free delivery</label>
                        <label class="switch"><input type="checkbox" name="cod" value="1" @checked(old('cod', true))><span class="track"></span> Cash on delivery</label>
                    </div>
                </div>
                <button class="btn btn-gold"><x-icon name="play" /> Create &amp; add photos</button>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.ad-studio.store') }}" class="card">
            @csrf
            <div class="card-head"><h3>New ad — product from this store</h3></div>
            <div class="card-body" style="display:grid;gap:12px">
                <div class="field"><label>Product</label>
                    <select class="select" name="product_id" required>
                        @foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                    </select>
                </div>
                <div class="field"><label>Project name <span class="muted">(optional)</span></label><input class="input" name="title" placeholder="e.g. Eid campaign"></div>
                <p class="muted" style="margin:0;font-size:12.5px">The studio reads the product's photos, videos, price, benefits and ingredients, analyses the media and writes the English text and Urdu voice script for you.</p>
                <button class="btn btn-gold"><x-icon name="play" /> Create ad</button>
            </div>
        </form>

        <div class="card">
            <div class="card-head"><h3>Urdu voice</h3></div>
            <div class="card-body" style="font-size:13px;display:grid;gap:8px">
                @if ($ttsProvider)
                    <div class="alert alert-success" style="margin:0">AI voice connected: <strong>{{ \App\Services\Tts\TtsManager::PROVIDERS[$ttsProvider] }}</strong>.</div>
                @else
                    <div class="alert alert-info" style="margin:0">No voice API connected. You can still <strong>record your own voice</strong> in the studio or upload an audio file.</div>
                    <p class="muted" style="margin:0">For AI Urdu voices add a <strong>free Google Gemini key</strong> (or an OpenAI key for the openai.fm voices) in <a href="{{ route('admin.settings.edit') }}#integrations" style="text-decoration:underline">Settings → Integrations</a>.</p>
                @endif
                <p class="muted" style="margin:0">AI script writer: {{ $aiProvider ? 'on ('.ucfirst($aiProvider).')' : 'off — scripts are written from the product data' }}.</p>
            </div>
        </div>
    </div>
</div>
@endsection
