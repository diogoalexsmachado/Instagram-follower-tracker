<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ImageProxyController
{
    private const ALLOWED_HOSTS = [
        'cdninstagram.com',
        'fbcdn.net',
    ];

    private const CACHE_TTL_SECONDS = 86400;
    private const CACHE_DIR = 'img-cache';

    public function __invoke(Request $request)
    {
        $url = (string) $request->query('u', '');
        if ($url === '') {
            abort(400, 'missing u');
        }

        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $scheme = parse_url($url, PHP_URL_SCHEME) ?: '';
        if ($scheme !== 'https' || ! $this->hostAllowed($host)) {
            abort(403, 'host not allowed');
        }

        $key = self::CACHE_DIR . '/' . sha1($url);
        $disk = Storage::disk('local');

        if ($disk->exists($key) && (time() - $disk->lastModified($key)) < self::CACHE_TTL_SECONDS) {
            return $this->respond($disk->get($key), $disk->mimeType($key) ?: 'image/jpeg');
        }

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; IGFollowerTracker/1.0)',
            'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            'Referer' => 'https://www.instagram.com/',
        ])->timeout(15)->get($url);

        if (! $response->successful()) {
            abort(502, 'upstream ' . $response->status());
        }

        $body = $response->body();
        $contentType = $response->header('Content-Type') ?: 'image/jpeg';

        $disk->put($key, $body);

        return $this->respond($body, $contentType);
    }

    private function hostAllowed(string $host): bool
    {
        foreach (self::ALLOWED_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }
        return false;
    }

    private function respond(string $body, string $contentType): Response
    {
        return response($body, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=86400, immutable',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }
}
