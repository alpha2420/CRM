<?php

namespace App\Support;

/**
 * URLs for our own CSS and JS files, with the file's version attached.
 * Browsers (and the service worker) keep these files for 30 days; a new
 * version number after an update makes them fetch the new file at once.
 */
final class Asset
{
    public static function url(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}
