<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmMasterValue;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CrmSettingsController extends Controller
{
    public const TYPES = [
        'lead-statuses' => ['label' => 'Lead Statuses', 'type' => 'lead_status'],
        'lead-sources' => ['label' => 'Lead Sources', 'type' => 'lead_source'],
        'opportunity-stages' => ['label' => 'Opportunity Stages', 'type' => 'opportunity_stage'],
        'activity-types' => ['label' => 'Activity Types', 'type' => 'activity_type'],
        'loss-reasons' => ['label' => 'Loss Reasons', 'type' => 'loss_reason'],
    ];

    public function index()
    {
        $this->authorize('crm_settings.view');

        $counts = CrmMasterValue::selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return view('admin.crm-settings.index', [
            'types' => self::TYPES,
            'counts' => $counts,
        ]);
    }

    public function show(string $typeKey)
    {
        $this->authorize('crm_settings.view');

        $type = $this->resolveType($typeKey);

        return view('admin.crm-settings.show', [
            'typeKey' => $typeKey,
            'type' => $type,
            'values' => CrmMasterValue::where('type', $type['type'])->orderBy('sort_order')->orderBy('name')->paginate(20),
            'value' => new CrmMasterValue(['is_active' => true, 'sort_order' => 0]),
        ]);
    }

    public function store(Request $request, string $typeKey, AuditService $audit)
    {
        $this->authorize('crm_settings.manage');

        $type = $this->resolveType($typeKey);
        $validated = $this->validatedValue($request, $type['type']);

        $value = CrmMasterValue::create($this->attributes($validated, $type['type'], $request));
        $this->enforceSingleDefault($value);
        $audit->created($value, 'crm_setting.created', 'CRM setting value created.', $request->user(), $request);

        return redirect()->route('admin.crm-settings.show', $typeKey)->with('status', 'Setting value created.');
    }

    public function edit(string $typeKey, CrmMasterValue $value)
    {
        $this->authorize('crm_settings.manage');

        $type = $this->resolveType($typeKey);
        abort_unless($value->type === $type['type'], 404);

        return view('admin.crm-settings.edit', [
            'typeKey' => $typeKey,
            'type' => $type,
            'value' => $value,
        ]);
    }

    public function update(Request $request, string $typeKey, CrmMasterValue $value, AuditService $audit)
    {
        $this->authorize('crm_settings.manage');

        $type = $this->resolveType($typeKey);
        abort_unless($value->type === $type['type'], 404);

        $validated = $this->validatedValue($request, $type['type'], $value);
        $before = $value->getAttributes();
        $value->fill($this->attributes($validated, $type['type'], $request))->save();
        $this->enforceSingleDefault($value);
        $audit->updated($value, $before, 'crm_setting.updated', 'CRM setting value updated.', $request->user(), $request);

        return redirect()->route('admin.crm-settings.show', $typeKey)->with('status', 'Setting value updated.');
    }

    public function toggleStatus(Request $request, string $typeKey, CrmMasterValue $value, AuditService $audit)
    {
        $this->authorize('crm_settings.manage');

        $type = $this->resolveType($typeKey);
        abort_unless($value->type === $type['type'], 404);

        $before = $value->getAttributes();
        $value->forceFill(['is_active' => ! $value->is_active])->save();
        $audit->updated($value, $before, $value->is_active ? 'crm_setting.activated' : 'crm_setting.deactivated', $value->is_active ? 'CRM setting value activated.' : 'CRM setting value deactivated.', $request->user(), $request);

        return back()->with('status', $value->is_active ? 'Setting value activated.' : 'Setting value deactivated.');
    }

    private function resolveType(string $typeKey): array
    {
        abort_unless(isset(self::TYPES[$typeKey]), 404);

        return self::TYPES[$typeKey];
    }

    private function validatedValue(Request $request, string $type, ?CrmMasterValue $value = null): array
    {
        if ($request->filled('name') && ! $request->filled('slug')) {
            $request->merge(['slug' => CrmMasterValue::makeSlug($request->input('name'))]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('crm_master_values', 'slug')->where('type', $type)->ignore($value),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function attributes(array $validated, string $type, Request $request): array
    {
        $name = trim($validated['name']);

        return [
            'type' => $type,
            'name' => $name,
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function enforceSingleDefault(CrmMasterValue $value): void
    {
        if (! $value->is_default) {
            return;
        }

        CrmMasterValue::where('type', $value->type)
            ->where('id', '!=', $value->id)
            ->update(['is_default' => false]);
    }
}
