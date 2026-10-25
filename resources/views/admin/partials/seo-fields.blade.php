{{-- Meta title / description / focus keyword with a live Google result preview.
     Expects: $model (product or page), $url (public link), $fallbackTitle, $fallbackDescription --}}
<div class="seo-fields" data-seo-fields>
    <div class="field full">
        <label>Focus keyword <span class="muted">(main search phrase for this page)</span></label>
        <input class="input" name="focus_keyword" value="{{ old('focus_keyword', $model->focus_keyword) }}" maxlength="120" placeholder="e.g. organic hair oil in Pakistan" data-seo-keyword>
    </div>
    <div class="field full">
        <label>Meta title <span class="muted" data-count-for="title"></span></label>
        <input class="input" name="meta_title" value="{{ old('meta_title', $model->meta_title) }}" maxlength="160" placeholder="{{ $fallbackTitle }}" data-seo-title>
        <div class="hint">Best 30–60 characters. Put the focus keyword near the start.</div>
    </div>
    <div class="field full">
        <label>Meta description <span class="muted" data-count-for="description"></span></label>
        <textarea class="textarea" name="meta_description" style="min-height:70px" maxlength="500" placeholder="{{ $fallbackDescription }}" data-seo-description>{{ old('meta_description', $model->meta_description) }}</textarea>
        <div class="hint">Best 70–160 characters: what it is, the main benefit, and a reason to click (price, COD, free delivery).</div>
    </div>
    <div class="field full">
        <label>Google preview</label>
        <div class="serp">
            <div class="serp-url">{{ preg_replace('#^https?://#', '', $url) }}</div>
            <div class="serp-title" data-serp-title>{{ $model->meta_title ?: $fallbackTitle }}</div>
            <div class="serp-desc" data-serp-desc>{{ $model->meta_description ?: $fallbackDescription }}</div>
        </div>
    </div>
</div>
