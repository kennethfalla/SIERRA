<?php
/** Resolve the current uploaded photo and invalidate cached replacements. */
final class ProfilePicture
{
    public static function url(?string $path): string
    {
        if (!$path) return '';
        if (preg_match('#^https?://#i', $path)) return $path;
        $relative = ltrim(str_replace('\\', '/', $path), '/');
        $file = BASE_PATH . $relative;
        $version = is_file($file) ? '?v=' . filemtime($file) : '';
        return BASE_URL . $relative . $version;
    }
}
