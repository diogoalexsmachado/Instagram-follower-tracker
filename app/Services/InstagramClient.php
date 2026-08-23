<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class InstagramClient
{
    private const BASE = 'https://www.instagram.com';

    public function __construct(private TrackerConfig $config)
    {
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(self::BASE)
            ->withHeaders([
                'User-Agent' => $this->config->userAgent(),
                'X-IG-App-ID' => $this->config->appId(),
                'X-CSRFToken' => $this->config->csrfToken(),
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => '*/*',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Referer' => self::BASE . '/',
            ])
            ->withCookies([
                'sessionid' => $this->config->sessionId(),
                'csrftoken' => $this->config->csrfToken(),
                'ds_user_id' => $this->config->dsUserId(),
            ], 'www.instagram.com')
            ->timeout(30)
            ->connectTimeout(10)
            ->retry(3, 2000, throw: false);
    }

    /**
     * Devolve dados do perfil web público (nome, id, contadores).
     * Endpoint: /api/v1/users/web_profile_info/?username=X
     */
    public function fetchProfile(string $username): array
    {
        $response = $this->http()->get('/api/v1/users/web_profile_info/', [
            'username' => $username,
        ]);

        $this->ensureOk($response, "fetchProfile({$username})");

        $user = data_get($response->json(), 'data.user');
        if (! is_array($user)) {
            throw new RuntimeException("Resposta inesperada para {$username}: user vazio.");
        }

        return [
            'ig_user_id' => (string) $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'] ?? null,
            'profile_pic_url' => $user['profile_pic_url_hd'] ?? $user['profile_pic_url'] ?? null,
            'biography' => $user['biography'] ?? null,
            'is_verified' => (bool) ($user['is_verified'] ?? false),
            'is_private' => (bool) ($user['is_private'] ?? false),
            'followers_count' => (int) data_get($user, 'edge_followed_by.count', 0),
            'following_count' => (int) data_get($user, 'edge_follow.count', 0),
            'media_count' => (int) data_get($user, 'edge_owner_to_timeline_media.count', 0),
        ];
    }

    /**
     * Itera todas as páginas de followers e devolve um generator com
     * arrays no formato ['ig_user_id','username','full_name','profile_pic_url','is_verified','is_private'].
     *
     * @return \Generator<array>
     */
    public function iterateFollowers(string $igUserId): \Generator
    {
        $maxId = null;
        $pageSize = $this->config->pageSize();
        $delayMs = $this->config->delayMsBetweenPages();

        do {
            $query = ['count' => $pageSize];
            if ($maxId !== null) {
                $query['max_id'] = $maxId;
            }

            $response = $this->http()->get("/api/v1/friendships/{$igUserId}/followers/", $query);
            $this->ensureOk($response, "followers({$igUserId}) max_id={$maxId}");

            $json = $response->json();
            $users = $json['users'] ?? [];

            foreach ($users as $u) {
                yield [
                    'ig_user_id' => (string) ($u['pk'] ?? $u['id'] ?? ''),
                    'username' => (string) ($u['username'] ?? ''),
                    'full_name' => $u['full_name'] ?? null,
                    'profile_pic_url' => $u['profile_pic_url'] ?? null,
                    'is_verified' => (bool) ($u['is_verified'] ?? false),
                    'is_private' => (bool) ($u['is_private'] ?? false),
                ];
            }

            $maxId = $json['next_max_id'] ?? null;

            if ($maxId !== null && $delayMs > 0) {
                usleep($delayMs * 1000);
            }
        } while (! empty($maxId));
    }

    private function ensureOk(Response $response, string $ctx): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $body = mb_substr((string) $response->body(), 0, 300);

        if ($status === 401 || $status === 403) {
            throw new RuntimeException(
                "IG rejeitou o pedido ({$ctx}): HTTP {$status}. Sessão expirada ou bloqueada. Refresca session_id/csrf_token no config.json. Corpo: {$body}"
            );
        }

        if ($status === 429) {
            throw new RuntimeException(
                "IG rate limit ({$ctx}): HTTP 429. Aumenta delay_ms_between_pages ou espera. Corpo: {$body}"
            );
        }

        throw new RuntimeException("Erro IG ({$ctx}): HTTP {$status}. Corpo: {$body}");
    }
}
