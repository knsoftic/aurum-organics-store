<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HandlesUploads
{
    protected function storeUpload(UploadedFile $file, string $folder): string
    {
        return $file->store($folder, 'uploads');
    }

    /** Replace an existing file with a new upload (or keep it when nothing was uploaded). */
    protected function replaceUpload(?UploadedFile $file, ?string $current, string $folder, bool $remove = false): ?string
    {
        if ($file) {
            $this->deleteUpload($current);

            return $this->storeUpload($file, $folder);
        }

        if ($remove) {
            $this->deleteUpload($current);

            return null;
        }

        return $current;
    }

    protected function deleteUpload(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http')) {
            Storage::disk('uploads')->delete($path);
        }
    }
}
