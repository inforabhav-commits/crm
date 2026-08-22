<?php

namespace App\Services\Integrations\JustCall;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class JustCallClient
{
    private const CACHE_KEY = 'justcall.last_connection_test';

    public function __construct(private ?JustCallConfig $config = null)
    {
        $this->config ??= JustCallConfig::fromConfig();
    }

    /**
     * Integration status combined with the real connectivity state from the
     * last testConnection() run. Never reports "connected" merely because
     * credentials are configured.
     */
    public function status(): array
    {
        return $this->config->status() + $this->connectionState();
    }

    private function connectionState(): array
    {
        if (! $this->config->enabled()) {
            return ['connection_state' => 'disabled', 'last_tested_at' => null];
        }

        if (! $this->config->credentialsConfigured()) {
            return ['connection_state' => 'not_configured', 'last_tested_at' => null];
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (! $cached) {
            return ['connection_state' => 'configured', 'last_tested_at' => null];
        }

        return [
            'connection_state' => $cached['ok'] ? 'connected' : 'connection_failed',
            'last_tested_at' => $cached['tested_at'] ?? null,
        ];
    }

    public function testConnection(): array
    {
        if (! $this->config->enabled()) {
            return ['ok' => false, 'status' => null, 'message' => 'JustCall integration is disabled.'];
        }

        if (! $this->config->credentialsConfigured()) {
            return ['ok' => false, 'status' => null, 'message' => 'JustCall API credentials are not configured.'];
        }

        try {
            $request = Http::acceptJson()->timeout(10);

            if ($this->config->authMode() === 'raw') {
                $request = $request->withHeaders([
                    'Authorization' => $this->config->apiKey().':'.$this->config->apiSecret(),
                ]);
            } else {
                $request = $request->withBasicAuth($this->config->apiKey(), $this->config->apiSecret());
            }

            $response = $request->get($this->config->baseUrl().$this->config->testEndpoint());

            if ($response->successful()) {
                return $this->rememberTest(['ok' => true, 'status' => $response->status(), 'message' => 'JustCall connection successful.']);
            }

            return $this->rememberTest([
                'ok' => false,
                'status' => $response->status(),
                'message' => 'JustCall returned HTTP '.$response->status().'.',
            ]);
        } catch (Throwable $exception) {
            return $this->rememberTest([
                'ok' => false,
                'status' => null,
                'message' => 'JustCall connection failed: '.$this->safeError($exception->getMessage()),
            ]);
        }
    }

    private function rememberTest(array $result): array
    {
        Cache::forever(self::CACHE_KEY, [
            'ok' => $result['ok'],
            'status' => $result['status'],
            'tested_at' => now()->toIso8601String(),
        ]);

        return $result;
    }

    public function listUsers(): array
    {
        if (! $this->config->enabled()) {
            return ['ok' => false, 'status' => null, 'message' => 'JustCall integration is disabled.', 'users' => []];
        }

        if (! $this->config->credentialsConfigured()) {
            return ['ok' => false, 'status' => null, 'message' => 'JustCall API credentials are not configured.', 'users' => []];
        }

        try {
            $response = $this->request()->get($this->config->baseUrl().'/v2.1/users');

            if (! $response->successful()) {
                return ['ok' => false, 'status' => $response->status(), 'message' => 'JustCall returned HTTP '.$response->status().'.', 'users' => []];
            }

            return [
                'ok' => true,
                'status' => $response->status(),
                'message' => 'JustCall users fetched.',
                'users' => $this->normalizeUsers($response->json()),
            ];
        } catch (Throwable $exception) {
            return ['ok' => false, 'status' => null, 'message' => 'JustCall user fetch failed: '.$this->safeError($exception->getMessage()), 'users' => []];
        }
    }

    public function suggestedMatch(User $user, array $justCallUsers): ?array
    {
        $email = Str::lower(trim((string) $user->email));
        $emailMatches = collect($justCallUsers)->filter(fn ($candidate) => $email !== '' && Str::lower((string) ($candidate['email'] ?? '')) === $email)->values();

        if ($emailMatches->count() === 1) {
            return $emailMatches->first() + ['match_reason' => 'email'];
        }

        if ($emailMatches->count() > 1) {
            return null;
        }

        $name = $this->normalizeName($user->name);
        $nameMatches = collect($justCallUsers)->filter(fn ($candidate) => $name !== '' && $this->normalizeName((string) ($candidate['name'] ?? '')) === $name)->values();

        if ($nameMatches->count() === 1) {
            return $nameMatches->first() + ['match_reason' => 'name'];
        }

        return null;
    }

    public function findUserByExternalId(string $externalId): ?array
    {
        $result = $this->listUsers();

        if (! $result['ok']) {
            return null;
        }

        return collect($result['users'])->firstWhere('id', $externalId);
    }

    public function dialerUrl(string $phoneNumber, array $metadata = []): string
    {
        $query = [
            'numbers' => $phoneNumber,
        ];

        if ($metadata !== []) {
            $query['medium'] = 'custom';
            $query['metadata'] = json_encode($metadata);
            $query['metadata_type'] = 'json';
        }

        return $this->config->dialerUrl().'?'.http_build_query($query);
    }

    public function recordingAccessUrl(string $recordingUrl): ?string
    {
        $recordingUrl = trim($recordingUrl);

        if (! filter_var($recordingUrl, FILTER_VALIDATE_URL) || ! hash_equals('https', strtolower((string) parse_url($recordingUrl, PHP_URL_SCHEME)))) {
            return null;
        }

        return $recordingUrl;
    }

    private function request()
    {
        $request = Http::acceptJson()->timeout(10);

        if ($this->config->authMode() === 'raw') {
            return $request->withHeaders([
                'Authorization' => $this->config->apiKey().':'.$this->config->apiSecret(),
            ]);
        }

        return $request->withBasicAuth($this->config->apiKey(), $this->config->apiSecret());
    }

    private function normalizeUsers($body): array
    {
        $items = $this->extractEntries($body);

        return collect($items)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item) {
                $id = $item['id'] ?? $item['user_id'] ?? $item['agent_id'] ?? null;

                if ($id === null || trim((string) $id) === '') {
                    return null;
                }

                return [
                    'id' => (string) $id,
                    'name' => $item['name'] ?? $item['full_name'] ?? $item['agent_name'] ?? null,
                    'email' => $item['email'] ?? $item['user_email'] ?? $item['username'] ?? null,
                    'phone' => $this->extractPhone($item),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function extractEntries($body): array
    {
        if (! is_array($body)) {
            return [];
        }

        foreach (['data', 'users', 'result', 'items'] as $key) {
            if (isset($body[$key]) && is_array($body[$key])) {
                return $body[$key];
            }
        }

        return array_values(array_filter($body, 'is_array'));
    }

    private function extractPhone(array $item): ?string
    {
        $phone = $item['phone'] ?? $item['phone_number'] ?? $item['number'] ?? $item['extension'] ?? null;
        if ($phone) {
            return (string) $phone;
        }

        foreach (['owned_numbers', 'shared_numbers'] as $key) {
            if (! empty($item[$key]) && is_array($item[$key])) {
                return (string) collect($item[$key])->filter()->first();
            }
        }

        return null;
    }

    private function normalizeName(string $name): string
    {
        return preg_replace('/\s+/', ' ', Str::lower(trim($name)));
    }

    private function safeError(string $message): string
    {
        $message = preg_replace('/\s+/', ' ', strip_tags($message));

        return mb_substr($message ?: 'No response received.', 0, 180);
    }
}
