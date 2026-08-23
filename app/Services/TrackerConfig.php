<?php

namespace App\Services;

use RuntimeException;

class TrackerConfig
{
    private array $data;

    public function __construct(?string $path = null)
    {
        $path ??= base_path('config.json');

        if (! is_file($path)) {
            throw new RuntimeException(
                "config.json não encontrado em {$path}. Copia config.example.json para config.json e preenche."
            );
        }

        $raw = file_get_contents($path);
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('config.json inválido: ' . json_last_error_msg());
        }

        $this->data = $decoded;
        $this->validate();
    }

    private function validate(): void
    {
        foreach (['instagram', 'profiles'] as $key) {
            if (! array_key_exists($key, $this->data)) {
                throw new RuntimeException("config.json: falta a chave '{$key}'.");
            }
        }

        foreach (['session_id', 'csrf_token', 'ds_user_id', 'user_agent', 'app_id'] as $key) {
            if (empty($this->data['instagram'][$key])) {
                throw new RuntimeException("config.json: instagram.{$key} vazio.");
            }
        }

        if (! is_array($this->data['profiles']) || empty($this->data['profiles'])) {
            throw new RuntimeException("config.json: 'profiles' tem de ser um array não vazio de usernames.");
        }
    }

    public function sessionId(): string
    {
        return $this->data['instagram']['session_id'];
    }

    public function csrfToken(): string
    {
        return $this->data['instagram']['csrf_token'];
    }

    public function dsUserId(): string
    {
        return (string) $this->data['instagram']['ds_user_id'];
    }

    public function userAgent(): string
    {
        return $this->data['instagram']['user_agent'];
    }

    public function appId(): string
    {
        return (string) $this->data['instagram']['app_id'];
    }

    public function pageSize(): int
    {
        return (int) ($this->data['instagram']['page_size'] ?? 50);
    }

    public function delayMsBetweenPages(): int
    {
        return (int) ($this->data['instagram']['delay_ms_between_pages'] ?? 1500);
    }

    /** @return string[] */
    public function profiles(): array
    {
        return array_values(array_map(
            fn ($u) => ltrim(trim((string) $u), '@'),
            $this->data['profiles']
        ));
    }
}
