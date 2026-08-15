<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JustCallUserMapping;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Integrations\JustCall\JustCallClient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JustCallUserMappingController extends Controller
{
    public function index(Request $request, JustCallClient $justCall)
    {
        $this->authorize('justcall.view');

        $justCallResult = session('justcall_users');
        $justCallUsers = $justCallResult['users'] ?? [];
        $users = User::with('justCallMapping')->orderBy('name')->get();

        return view('admin.justcall-settings.mappings', [
            'users' => $users,
            'mappings' => JustCallUserMapping::with(['user', 'updatedBy'])->latest()->paginate(20),
            'justCallUsers' => $justCallUsers,
            'justCallResult' => $justCallResult,
            'suggestions' => $this->suggestions($users, $justCallUsers, $justCall),
        ]);
    }

    public function fetch(Request $request, JustCallClient $justCall, AuditService $audit)
    {
        $this->authorize('justcall.manage');

        $result = $justCall->listUsers();
        $audit->log('justcall.users_fetched', null, 'JustCall users fetched for mapping.', null, [
            'ok' => $result['ok'],
            'status' => $result['status'],
            'count' => count($result['users']),
        ], $request->user(), $request);

        return back()
            ->with('justcall_users', $result)
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function store(Request $request, AuditService $audit)
    {
        $this->authorize('justcall.manage');

        $validated = $this->validatedMapping($request);
        $crmUser = User::where('is_active', true)->findOrFail($validated['user_id']);
        $this->abortIfDuplicateActive((int) $validated['user_id'], $validated['justcall_user_id']);

        $mapping = JustCallUserMapping::create($this->payload($validated, $request->user()->id, true) + [
            'created_by_id' => $request->user()->id,
        ]);
        $audit->created($mapping, 'justcall.mapping_created', 'JustCall user mapping created.', $request->user(), $request);

        return back()->with('status', 'JustCall mapping saved for '.$crmUser->name.'.');
    }

    public function update(Request $request, JustCallUserMapping $mapping, AuditService $audit)
    {
        $this->authorize('justcall.manage');

        $validated = $this->validatedMapping($request);
        User::where('is_active', true)->findOrFail($validated['user_id']);
        $this->abortIfDuplicateActive((int) $validated['user_id'], $validated['justcall_user_id'], $mapping);
        $before = $mapping->getAttributes();

        $mapping->fill($this->payload($validated, $request->user()->id, true))->save();
        $audit->updated($mapping, $before, 'justcall.mapping_updated', 'JustCall user mapping updated.', $request->user(), $request);

        return back()->with('status', 'JustCall mapping updated.');
    }

    public function disable(Request $request, JustCallUserMapping $mapping, AuditService $audit)
    {
        $this->authorize('justcall.manage');

        $before = $mapping->getAttributes();
        $mapping->forceFill([
            'is_active' => false,
            'active_user_id' => null,
            'active_justcall_user_id' => null,
            'updated_by_id' => $request->user()->id,
        ])->save();
        $audit->updated($mapping, $before, 'justcall.mapping_disabled', 'JustCall user mapping disabled.', $request->user(), $request);

        return back()->with('status', 'JustCall mapping disabled.');
    }

    public function verify(Request $request, JustCallUserMapping $mapping, JustCallClient $justCall, AuditService $audit)
    {
        $this->authorize('justcall.manage');

        $remoteUser = $justCall->findUserByExternalId($mapping->justcall_user_id);
        $before = $mapping->getAttributes();

        if ($remoteUser) {
            $mapping->fill([
                'justcall_name' => $remoteUser['name'] ?? $mapping->justcall_name,
                'justcall_email' => $remoteUser['email'] ?? $mapping->justcall_email,
                'justcall_phone' => $remoteUser['phone'] ?? $mapping->justcall_phone,
                'last_verified_at' => now(),
                'last_synced_at' => now(),
                'updated_by_id' => $request->user()->id,
            ])->save();
        } else {
            $mapping->forceFill(['last_verified_at' => now(), 'updated_by_id' => $request->user()->id])->save();
        }

        $audit->updated($mapping, $before, 'justcall.mapping_verified', $remoteUser ? 'JustCall user mapping verified.' : 'JustCall user mapping verification failed.', $request->user(), $request);

        return back()->with($remoteUser ? 'status' : 'error', $remoteUser ? 'JustCall mapping verified.' : 'JustCall user was not found.');
    }

    private function validatedMapping(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'justcall_user_id' => ['required', 'string', 'max:255'],
            'justcall_name' => ['nullable', 'string', 'max:255'],
            'justcall_email' => ['nullable', 'email', 'max:255'],
            'justcall_phone' => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function payload(array $validated, int $actorId, bool $active): array
    {
        return [
            'user_id' => $validated['user_id'],
            'justcall_user_id' => $validated['justcall_user_id'],
            'active_user_id' => $active ? $validated['user_id'] : null,
            'active_justcall_user_id' => $active ? $validated['justcall_user_id'] : null,
            'justcall_name' => $validated['justcall_name'] ?? null,
            'justcall_email' => $validated['justcall_email'] ?? null,
            'justcall_phone' => $validated['justcall_phone'] ?? null,
            'is_active' => $active,
            'last_synced_at' => now(),
            'updated_by_id' => $actorId,
        ];
    }

    private function abortIfDuplicateActive(int $userId, string $justCallUserId, ?JustCallUserMapping $ignore = null): void
    {
        $query = JustCallUserMapping::where('is_active', true)
            ->where(function ($query) use ($userId, $justCallUserId) {
                $query->where('user_id', $userId)
                    ->orWhere('justcall_user_id', $justCallUserId);
            });

        if ($ignore) {
            $query->where('id', '!=', $ignore->id);
        }

        abort_if($query->exists(), 422, 'This CRM user or JustCall user is already actively mapped.');
    }

    private function suggestions($users, array $justCallUsers, JustCallClient $justCall): array
    {
        if ($justCallUsers === []) {
            return [];
        }

        return $users
            ->filter(fn (User $user) => $user->is_active && ! $user->justCallMapping)
            ->mapWithKeys(function (User $user) use ($justCallUsers, $justCall) {
                $match = $justCall->suggestedMatch($user, $justCallUsers);

                return $match ? [$user->id => $match] : [];
            })
            ->all();
    }
}
