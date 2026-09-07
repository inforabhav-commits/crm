<?php

namespace App\Services\Integrations\JustCall;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\JustCallUserMapping;
use App\Models\Lead;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PhonePrivacyService;
use App\Services\PhoneNumberNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class JustCallClickToCallService
{
    public function __construct(
        private JustCallClient $client,
        private PhoneNumberNormalizer $phoneNormalizer,
        private PhonePrivacyService $phonePrivacy,
        private AuditService $audit
    ) {
    }

    public function launch(User $user, Model $record, Request $request): array
    {
        if (! config('justcall.enabled')) {
            return $this->failed('JustCall integration is disabled.');
        }

        if (! $user->is_active) {
            return $this->failed('Your CRM account is inactive.');
        }

        $mapping = JustCallUserMapping::where('user_id', $user->id)->where('is_active', true)->first();
        if (! $mapping) {
            return $this->failed('Your CRM user is not mapped to an active JustCall user.');
        }

        $phone = $this->recordPhone($record);
        $normalized = $this->phoneNormalizer->normalize($phone);
        if (! $this->isCallable($normalized['normalized'], $normalized['comparison'])) {
            return $this->failed('This record does not have a valid phone number to call.');
        }

        // The installed CTI SDK requires the destination in the browser. Never
        // fall back to it for users whose phone access is restricted.
        if (! $this->phonePrivacy->canViewFullPhone($user)) {
            return $this->failed('Secure calling is unavailable: this integration has no verified server-side API to call through your mapped JustCall agent. Contact your administrator. No call was placed.') + [
                'code' => 'secure_calling_not_supported',
                'masked_number' => $this->phonePrivacy->mask($phone),
                'record' => ['type' => class_basename($record), 'id' => $record->getKey(), 'name' => $this->phonePrivacy->maskedText($this->recordName($record))],
                'direction' => 'outbound',
                'status' => 'failed',
            ];
        }

        $metadata = [
            'crm_entity_type' => class_basename($record),
            'crm_entity_id' => $record->getKey(),
            'crm_user_id' => $user->id,
            'justcall_user_id' => $mapping->justcall_user_id,
        ];

        $this->audit->log(
            'justcall.click_to_call_requested',
            $record,
            'JustCall click-to-call requested.',
            null,
            [
                'entity_type' => class_basename($record),
                'entity_id' => $record->getKey(),
                'target_phone' => $this->maskedPhone($normalized['comparison']),
                'justcall_user_id' => $mapping->justcall_user_id,
            ],
            $user,
            $request
        );

        return [
            'ok' => true,
            'url' => $this->client->dialerUrl($normalized['normalized'], $metadata),
            'number' => $normalized['normalized'],
            'display_phone' => $this->phonePrivacy->display($phone, $user),
            'record' => [
                'type' => class_basename($record),
                'id' => $record->getKey(),
                'name' => $this->recordName($record),
            ],
        ];
    }

    public function canShowFor(Model $record): bool
    {
        $normalized = $this->phoneNormalizer->normalize($this->recordPhone($record));

        return $this->isCallable($normalized['normalized'], $normalized['comparison']);
    }

    private function recordPhone(Model $record): ?string
    {
        if ($record instanceof Contact) {
            return $record->phone ?: $record->mobile;
        }

        if ($record instanceof Lead || $record instanceof Customer) {
            return $record->phone;
        }

        return null;
    }

    private function isCallable(?string $normalized, ?string $comparison): bool
    {
        if (! $normalized || ! $comparison) {
            return false;
        }

        return strlen($comparison) >= 7 && strlen($comparison) <= 15;
    }

    private function recordName(Model $record): string
    {
        if ($record instanceof Contact) {
            return $record->name;
        }

        return (string) ($record->name ?? class_basename($record).' #'.$record->getKey());
    }

    private function maskedPhone(?string $comparison): ?string
    {
        if (! $comparison) {
            return null;
        }

        return str_repeat('*', max(0, strlen($comparison) - 4)).substr($comparison, -4);
    }

    private function failed(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
