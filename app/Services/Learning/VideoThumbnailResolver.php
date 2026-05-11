<?php

declare(strict_types=1);

namespace App\Services\Learning;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Derives a preview image URL from common video hosting links (no API keys).
 */
final class VideoThumbnailResolver
{
    public static function resolve(?string $videoUrl): ?string
    {
        if ($videoUrl === null || trim($videoUrl) === '') {
            return null;
        }

        $normalized = trim($videoUrl);
        $youtubeId = self::extractYouTubeId($normalized);
        if ($youtubeId !== null) {
            return 'https://img.youtube.com/vi/' . $youtubeId . '/mqdefault.jpg';
        }

        $vimeoThumb = self::fetchVimeoThumbnail($normalized);
        if ($vimeoThumb !== null) {
            return $vimeoThumb;
        }

        return null;
    }

    private static function extractYouTubeId(string $url): ?string
    {
        if (! Str::contains($url, ['youtube.com', 'youtu.be', 'youtube-nocookie.com'], true)) {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        if (str_contains($host, 'youtu.be')) {
            $id = trim($path, '/');

            return self::normalizeYouTubeId($id);
        }

        if (! empty($query['v'])) {
            return self::normalizeYouTubeId((string) $query['v']);
        }

        if (preg_match('#/(embed|shorts|live)/([a-zA-Z0-9_-]{6,})#', $path, $m)) {
            return self::normalizeYouTubeId($m[2]);
        }

        if (preg_match('#/v/([a-zA-Z0-9_-]{6,})#', $path, $m)) {
            return self::normalizeYouTubeId($m[1]);
        }

        return null;
    }

    private static function normalizeYouTubeId(string $id): ?string
    {
        $id = trim($id);
        if ($id === '' || ! preg_match('/^[a-zA-Z0-9_-]{6,}$/', $id)) {
            return null;
        }

        return $id;
    }

    private static function fetchVimeoThumbnail(string $url): ?string
    {
        if (! str_contains(strtolower($url), 'vimeo.com')) {
            return null;
        }

        try {
            $response = Http::timeout(6)
                ->acceptJson()
                ->get('https://vimeo.com/api/oembed.json', ['url' => $url]);

            if (! $response->successful()) {
                return null;
            }

            $thumb = $response->json('thumbnail_url');

            return is_string($thumb) && filter_var($thumb, FILTER_VALIDATE_URL) ? $thumb : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
