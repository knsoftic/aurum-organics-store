<?php

namespace App\Console\Commands;

use App\Services\ImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/** Makes the WebP copies of every uploaded photo ahead of time (run by deploy.sh). */
class OptimizeImages extends Command
{
    protected $signature = 'images:optimize';

    protected $description = 'Create resized WebP copies of all uploaded photos';

    public function handle(): int
    {
        $root = public_path('uploads');
        if (! is_dir($root)) {
            $this->info('No uploads folder yet.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach (File::allFiles($root) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if (! ImageOptimizer::relative($relative) || ! ($dims = ImageOptimizer::dimensions($relative))) {
                continue;
            }
            foreach ([...ImageOptimizer::WIDTHS, 120, 200] as $width) {
                ImageOptimizer::variant($relative, min($width, $dims[0]));
            }
            $count++;
        }

        $this->info("Optimised {$count} photos.");

        return self::SUCCESS;
    }
}
