<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class InstagramClient
{
    private const BASE = 'https://www.instagram.com';

    public function __construct(private TrackerConfig $config)
    {
    }

    private function http(): PendingRequest
    {
        $http = Http::baseUrl(self::BASE)
            ->withHeaders([
                'User-Agent' => $this->config->userAgent(),
                'X-IG-App-ID' => $this->config->appId(),
                'X-CSRFToken' => $this->config->csrfToken(),
                'X-ASBD-ID' => '129477',
                'X-IG-WWW-Claim' => '0',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => '*/*',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Sec-Fetch-Site' => 'same-origin',
                'Sec-Fetch-Mode' => 'cors',
                'Sec-Fetch-Dest' => 'empty',
                'Sec-Ch-Ua' => '"Chromium";v="131", "Not_A Brand";v="24"',
                'Sec-Ch-Ua-Mobile' => '?0',
                'Sec-Ch-Ua-Platform' => '"Windows"',
                'Referer' => self::BASE . '/',
                'Origin' => self::BASE,
            ])
            ->withCookies([
                'sessionid' => $this->config->sessionId(),
                'csrftoken' => $this->config->csrfToken(),
                'ds_user_id' => $this->config->dsUserId(),
            ], 'www.instagram.com')
            ->timeout(30)
            ->connectTimeout(10);

        $proxy = $this->config->proxy();
        if ($proxy !== null) {
            $http = $http->withOptions(['proxy' => $proxy]);
        }

        return $http;
    }

    public function fetchProfile(string $username): array
    {
        $response = $this->requestWithBackoff(
            fn () => $this->http()->get('/api/v1/users/web_profile_info/', ['username' => $username]),
            "fetchProfile({$username})"
        );

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
     * @return \Generator<array>
     */
    public function iterateFollowers(string $igUserId): \Generator
    {
        $maxId = null;
        $pageSize = $this->config->pageSize();
        $baseDelayMs = $this->config->delayMsBetweenPages();

        do {
            $query = ['count' => $pageSize];
            if ($maxId !== null) {
                $query['max_id'] = $maxId;
            }

            $response = $this->requestWithBackoff(
                fn () => $this->http()->get("/api/v1/friendships/{$igUserId}/followers/", $query),
                "followers({$igUserId}) max_id={$maxId}"
            );

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

            if ($maxId !== null && $baseDelayMs > 0) {
                // jitter: ±30% para não parecer polling mecânico
                $jittered = (int) ($baseDelayMs * (0.7 + mt_rand() / mt_getrandmax() * 0.6));
                usleep($jittered * 1000);
            }
        } while (! empty($maxId));
    }

    /**
     * Executa um closure de HTTP, com backoff exponencial e honra do Retry-After em 429.
     */
    private function requestWithBackoff(\Closure $call, string $ctx): Response
    {
        $maxAttempts = 4;
        $attempt = 0;

        while (true) {
            $attempt++;
            $response = $call();

            if ($response->successful()) {
                return $response;
            }

            $status = $response->status();

            if ($status === 429 && $attempt < $maxAttempts) {
                $retryAfter = (int) ($response->header('Retry-After') ?: 0);
                $wait = $retryAfter > 0
                    ? min($retryAfter, 120)
                    : min(60, (int) pow(2, $attempt) * 5 + random_int(0, 5));

                Log::warning("IG 429 em {$ctx}, tentativa {$attempt}/{$maxAttempts}, a esperar {$wait}s");
                sleep($wait);
                continue;
            }

            if (in_array($status, [500, 502, 503, 504], true) && $attempt < $maxAttempts) {
                $wait = min(30, (int) pow(2, $attempt) * 2);
                Log::warning("IG {$status} em {$ctx}, tentativa {$attempt}/{$maxAttempts}, a esperar {$wait}s");
                sleep($wait);
                continue;
            }

            $this->throwFor($response, $ctx);
        }
    }

    private function throwFor(Response $response, string $ctx): never
    {
        $status = $response->status();
        $body = mb_substr((string) $response->body(), 0, 300);

        if ($status === 401 || $status === 403) {
            throw new RuntimeException(
                "IG rejeitou o pedido ({$ctx}): HTTP {$status}. Sessão expirada, bloqueada, ou IP do servidor flaggado. Refresca session_id/csrf_token no config.json ou configura um proxy residencial. Corpo: {$body}"
            );
        }

        if ($status === 429) {
            throw new RuntimeException(
                "IG rate limit persistente ({$ctx}): HTTP 429 mesmo após retries. Provavelmente o IP do servidor está flaggado pelo IG. Configura um proxy residencial em instagram.proxy no config.json, ou muda para uma API paga (HikerAPI/RapidAPI). Corpo: {$body}"
            );
        }

        throw new RuntimeException("Erro IG ({$ctx}): HTTP {$status}. Corpo: {$body}");
    }
}
