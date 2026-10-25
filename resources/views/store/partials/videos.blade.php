{{-- Swipeable strip of click-to-play (vertical, reels-style) videos. Expects $urls. --}}
@php $urls = collect($urls)->values(); @endphp
<div class="video-strip" data-video-strip>
    <div class="video-grid" data-video-track>
        @foreach ($urls as $i => $url)
            <div class="video-card" data-video>
                <video src="{{ $url }}#t=0.5" preload="metadata" playsinline loop muted></video>
                <button type="button" class="play" aria-label="Play video {{ $i + 1 }}"><span><x-icon name="play" /></span></button>
            </div>
        @endforeach
    </div>
    @if ($urls->count() > 3)
        <div class="video-nav">
            <button type="button" data-video-scroll="-1" aria-label="Previous videos"><x-icon name="arrow-left" /></button>
            <button type="button" data-video-scroll="1" aria-label="Next videos"><x-icon name="arrow-right" /></button>
        </div>
    @endif
</div>
