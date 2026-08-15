<?php

namespace App\Services\Integrations\JustCall;

class JustCallConfig
{
    public function __construct(private array $config)
    {
    }

    public static function fromConfig(): self
    {
        return new self(config('justcall'));
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    public function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://api.justcall.io'), '/');
    }

    public function dialerUrl(): string
    {
        return rtrim((string) ($this->config['dialer_url'] ?? 'https://app.justcall.io/dialer'), '?');
    }

    public function authMode(): string
    {
        return (string) ($this->config['auth_mode'] ?? 'basic');
    }

    public function apiKey(): string
    {
        return trim((string) ($this->config['api_key'] ?? ''));
    }

    public function apiSecret(): string
    {
        return trim((string) ($this->config['api_secret'] ?? ''));
    }

    public function webhookSecret(): string
    {
        return trim((string) ($this->config['webhook_secret'] ?? ''));
    }

    public function testEndpoint(): string
    {
        $endpoint = trim((string) ($this->config['test_endpoint'] ?? '/v2.1/users'));

        return str_starts_with($endpoint, '/') ? $endpoint : '/'.$endpoint;
    }

    public function credentialsConfigured(): bool
    {
        return $this->apiKey() !== '' && $this->apiSecret() !== '';
    }

    public function webhookConfigured(): bool
    {
        return $this->webhookSecret() !== '';
    }

    public function status(): array
    {
        return [
            'enabled' => $this->enabled(),
            'base_url' => $this->baseUrl(),
            'dialer_url' => $this->dialerUrl(),
            'auth_mode' => $this->authMode(),
            'api_key_configured' => $this->apiKey() !== '',
            'api_secret_configured' => $this->apiSecret() !== '',
            'webhook_secret_configured' => $this->webhookConfigured(),
            'credentials_configured' => $this->credentialsConfigured(),
            'test_endpoint' => $this->testEndpoint(),
        ];
    }
}
