@props(['rating' => 0])
<span class="stars" aria-label="{{ $rating }} out of 5 stars">
    @for ($i = 1; $i <= 5; $i++)
        <x-icon name="star" class="{{ $i <= round($rating) ? '' : 'off' }}" />
    @endfor
</span>
