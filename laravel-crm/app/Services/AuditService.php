<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AuditService
{
    private const HIDDEN_KEYS = [
        'password',
        'password_confirmation',
        'remember_token',
        'api_key',
        'api_secret',
        'secret',
        'token',
        'access_token',
        'refresh_token',
    ];

    private const NOISY_KEYS = [
        'created_at',
        'updated_at',
        'email_verified_at',
    ];

    public function log(string $action, ?Model $entity = null, ?string $description = null, ?array $oldValues = null, ?array $newValues = null, ?User $user = null, ?Request $request = null): AuditLog
    {
        $request ??= request();
        $user ??= $request?->user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entity ? $entity::class : null,
            'entity_id' => $entity?->getKey(),
            'description' => $description,
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    public function created(Model $entity, string $action, ?string $description = null, ?User $user = null, ?Request $request = null): AuditLog
    {
        return $this->log($action, $entity, $description, null, $entity->getAttributes(), $user, $request);
    }

    public function updated(Model $entity, array $before, string $action, ?string $description = null, ?User $user = null, ?Request $request = null): ?AuditLog
    {
        $changes = $this->changedValues($before, $entity->getAttributes());

        if ($changes['old'] === [] && $changes['new'] === []) {
            return null;
        }

        return $this->log($action, $entity, $description, $changes['old'], $changes['new'], $user, $request);
    }

    public function relationChanged(Model $entity, string $action, string $field, array $before, array $after, ?string $description = null, ?User $user = null, ?Request $request = null): ?AuditLog
    {
        $before = array_values(array_map('intval', $before));
        $after = array_values(array_map('intval', $after));
        sort($before);
        sort($after);

        if ($before === $after) {
            return null;
        }

        return $this->log($action, $entity, $description, [$field => $before], [$field => $after], $user, $request);
    }

    private function changedValues(array $before, array $after): array
    {
        $old = [];
        $new = [];

        foreach ($after as $key => $value) {
            if ($this->skipKey($key) || ! array_key_exists($key, $before)) {
                continue;
            }

            if ((string) $before[$key] !== (string) $value) {
                $old[$key] = $before[$key];
                $new[$key] = $value;
            }
        }

        return ['old' => $this->sanitize($old) ?? [], 'new' => $this->sanitize($new) ?? []];
    }

    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return collect($values)
            ->reject(fn ($value, $key) => $this->skipKey((string) $key))
            ->map(fn ($value) => is_array($value) ? $this->sanitize($value) : $value)
            ->all();
    }

    private function skipKey(string $key): bool
    {
        $normalized = strtolower($key);

        if (in_array($normalized, self::NOISY_KEYS, true)) {
            return true;
        }

        if (in_array($normalized, self::HIDDEN_KEYS, true)) {
            return true;
        }

        return Arr::first(self::HIDDEN_KEYS, fn ($hidden) => str_contains($normalized, $hidden)) !== null;
    }
}
