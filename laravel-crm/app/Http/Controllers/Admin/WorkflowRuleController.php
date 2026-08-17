<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\CrmMasterValue;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use App\Models\WorkflowRule;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkflowRuleController extends Controller
{
    public function index()
    {
        $this->authorize('workflows.view');
        return view('admin.workflows.index', [
            'rules' => WorkflowRule::with('executions')->orderBy('priority')->orderBy('id')->paginate(20),
        ]);
    }

    public function create()
    {
        $this->authorize('workflows.manage');
        return view('admin.workflows.form', $this->formData(new WorkflowRule(['is_active' => true])));
    }

    public function store(Request $request, AuditService $audit)
    {
        $this->authorize('workflows.manage');
        $validated = $this->validated($request);
        $rule = WorkflowRule::create($this->payload($validated, $request->user()->id));
        $audit->created($rule, 'workflow_rule.created', 'Workflow rule created.', $request->user(), $request);
        return redirect()->route('admin.workflows.index')->with('status', 'Workflow rule created.');
    }

    public function edit(WorkflowRule $workflowRule)
    {
        $this->authorize('workflows.manage');
        return view('admin.workflows.form', $this->formData($workflowRule));
    }

    public function update(Request $request, WorkflowRule $workflowRule, AuditService $audit)
    {
        $this->authorize('workflows.manage');
        $validated = $this->validated($request);
        $before = $workflowRule->getAttributes();
        $workflowRule->fill($this->payload($validated, $request->user()->id))->save();
        $audit->updated($workflowRule, $before, 'workflow_rule.updated', 'Workflow rule updated.', $request->user(), $request);
        return redirect()->route('admin.workflows.index')->with('status', 'Workflow rule updated.');
    }

    public function toggle(WorkflowRule $workflowRule, Request $request, AuditService $audit)
    {
        $this->authorize('workflows.manage');
        $before = $workflowRule->getAttributes();
        $workflowRule->forceFill(['is_active' => ! $workflowRule->is_active, 'updated_by_id' => $request->user()->id])->save();
        $audit->updated($workflowRule, $before, $workflowRule->is_active ? 'workflow_rule.activated' : 'workflow_rule.deactivated', 'Workflow rule status changed.', $request->user(), $request);
        return back()->with('status', $workflowRule->is_active ? 'Workflow rule activated.' : 'Workflow rule deactivated.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'entity_type' => ['required', Rule::in([Lead::class, Opportunity::class, Activity::class])],
            'trigger' => ['required', Rule::in(['record_created', 'record_updated', 'lead_assigned', 'lead_status_changed', 'opportunity_stage_changed', 'activity_overdue'])],
            'conditions_json' => ['nullable', 'json', 'max:10000'],
            'action_type' => ['required', Rule::in(['create_activity', 'notify', 'update_field', 'assign'])],
            'action_json' => ['nullable', 'json', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);
        $conditions = $validated['conditions_json'] ? json_decode($validated['conditions_json'], true, 512, JSON_THROW_ON_ERROR) : [];
        $action = array_merge(['type' => $validated['action_type']], $validated['action_json'] ? json_decode($validated['action_json'], true, 512, JSON_THROW_ON_ERROR) : []);
        if (! is_array($conditions) || ! is_array($action)) abort(422, 'Conditions and action must be JSON objects.');
        return [
            'name' => $validated['name'],
            'entity_type' => $validated['entity_type'],
            'trigger' => $validated['trigger'],
            'conditions' => $conditions,
            'action' => $action,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'priority' => (int) $validated['priority'],
        ];
    }

    private function payload(array $validated, int $userId): array
    {
        return [
            'name' => $validated['name'],
            'entity_type' => $validated['entity_type'],
            'trigger' => $validated['trigger'],
            'conditions' => $validated['conditions'],
            'action' => $validated['action'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'priority' => $validated['priority'],
            'updated_by_id' => $userId,
            'created_by_id' => $userId,
        ];
    }

    private function formData(WorkflowRule $rule): array
    {
        return [
            'rule' => $rule,
            'entities' => [Lead::class => 'Lead', Opportunity::class => 'Opportunity', Activity::class => 'Activity'],
            'triggers' => ['record_created', 'record_updated', 'lead_assigned', 'lead_status_changed', 'opportunity_stage_changed', 'activity_overdue'],
            'actions' => ['create_activity' => 'Create follow-up activity', 'notify' => 'Send notification', 'update_field' => 'Update permitted field', 'assign' => 'Assign lead'],
        ];
    }
}
