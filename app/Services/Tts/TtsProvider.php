<?php

namespace App\Services\Tts;

interface TtsProvider
{
    /**
     * @param  string  $style  Delivery instructions (tone, accent, pace).
     * @return array{0: string, 1: string} [audio bytes, file extension]
     */
    public function synthesize(string $text, string $voice, string $style, int $rate = 0): array;
}
