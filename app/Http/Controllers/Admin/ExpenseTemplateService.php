<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseTemplateModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExpenseTemplateService extends Controller
{
    public function apiShow(ExpenseTemplateModel $expenseTemplate): JsonResponse
    {
        $row = $this->serializeOne($expenseTemplate);
        $row['workflow_nodes'] = $this->loadWorkflowNodes((int) $expenseTemplate->workflow_id);

        return response()->json(['data' => $row]);
    }

    public function apiIndex(): JsonResponse
    {
        if (! Schema::hasTable('expense_templates')) {
            return response()->json(['data' => []]);
        }

        $q = ExpenseTemplateModel::query()
            ->orderByDesc('id');

        if (Schema::hasTable('users')) {
            $q->leftJoin('users as u', 'u.id', '=', 'expense_templates.created_by')
                ->select([
                    'expense_templates.id',
                    'expense_templates.name',
                    'expense_templates.code',
                    'expense_templates.workflow_id',
                    'expense_templates.status',
                    'expense_templates.created_by',
                    'expense_templates.created_at',
                    'expense_templates.updated_at',
                ])
                ->addSelect([
                    'u.real_name as creator_real_name',
                    'u.account as creator_account',
                ]);
        } else {
            $q->select([
                'expense_templates.id',
                'expense_templates.name',
                'expense_templates.code',
                'expense_templates.workflow_id',
                'expense_templates.status',
                'expense_templates.created_by',
                'expense_templates.created_at',
                'expense_templates.updated_at',
            ]);
        }

        $rows = $q->get()->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'name' => $row->name,
                'code' => $row->code,
                'workflow_id' => $row->workflow_id !== null ? (int) $row->workflow_id : null,
                'status' => (int) $row->status,
                'created_by' => $row->created_by !== null ? (int) $row->created_by : null,
                'creator_real_name' => $row->creator_real_name ?? null,
                'creator_account' => $row->creator_account ?? null,
                'created_at' => $row->created_at !== null ? (int) $row->created_at : null,
                'updated_at' => $row->updated_at !== null ? (int) $row->updated_at : null,
            ];
        })->values();

        return response()->json(['data' => $rows]);
    }

    public function apiStore(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge(
            [
                'name' => ['required', 'string', 'max:100'],
                'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_\-]+$/', Rule::unique('expense_templates', 'code')],
                'status' => ['nullable', 'integer', 'in:0,1'],
            ],
            $this->workflowNodesRules()
        ), [
            'code.regex' => '编码仅允许字母、数字、下划线与中划线。',
        ]);

        $now = time();
        $uid = $request->user()?->id;

        $tpl = new ExpenseTemplateModel;
        $tpl->name = trim((string) $validated['name']);
        $tpl->code = trim((string) $validated['code']);
        $tpl->status = isset($validated['status']) ? (int) $validated['status'] : 1;
        $tpl->created_by = $uid !== null ? (int) $uid : null;
        $tpl->created_at = $now;
        $tpl->updated_at = $now;
        $tpl->save();

        if (! empty($validated['workflow_nodes']) && is_array($validated['workflow_nodes'])) {
            $this->validateWorkflowNodeRefs($validated['workflow_nodes']);
            $this->syncWorkflowForTemplate($tpl, $validated['workflow_nodes'], $now);
            $tpl->refresh();
        }

        $fresh = $tpl->fresh();

        return response()->json([
            'message' => '模板新增成功',
            'data' => array_merge($this->serializeOne($fresh), [
                'workflow_nodes' => $this->loadWorkflowNodes((int) $fresh->workflow_id),
            ]),
        ], 201);
    }

    public function apiUpdate(Request $request, ExpenseTemplateModel $expenseTemplate): JsonResponse
    {
        $codeUnique = Rule::unique('expense_templates', 'code')->ignore($expenseTemplate->id);
        $validated = $request->validate(array_merge(
            [
                'name' => ['required', 'string', 'max:100'],
                'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_\-]+$/', $codeUnique],
                'status' => ['nullable', 'integer', 'in:0,1'],
            ],
            $this->workflowNodesRules()
        ), [
            'code.regex' => '编码仅允许字母、数字、下划线与中划线。',
        ]);

        $expenseTemplate->name = trim((string) $validated['name']);
        $expenseTemplate->code = trim((string) $validated['code']);
        if (array_key_exists('status', $validated)) {
            $expenseTemplate->status = (int) $validated['status'];
        }
        $expenseTemplate->updated_at = time();
        $expenseTemplate->save();

        if (array_key_exists('workflow_nodes', $validated) && is_array($validated['workflow_nodes'])) {
            $list = $validated['workflow_nodes'];
            if ($list === [] && $expenseTemplate->workflow_id) {
                DB::table('workflow_nodes')->where('workflow_id', (int) $expenseTemplate->workflow_id)->delete();
            } elseif ($list !== []) {
                $this->validateWorkflowNodeRefs($list);
                $this->syncWorkflowForTemplate($expenseTemplate, $list, time());
                $expenseTemplate->refresh();
            }
        }

        $fresh = $expenseTemplate->fresh();

        return response()->json([
            'message' => '模板已更新',
            'data' => array_merge($this->serializeOne($fresh), [
                'workflow_nodes' => $this->loadWorkflowNodes((int) $fresh->workflow_id),
            ]),
        ]);
    }

    public function apiPatchStatus(Request $request, ExpenseTemplateModel $expenseTemplate): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        $expenseTemplate->status = (int) $validated['status'];
        $expenseTemplate->updated_at = time();
        $expenseTemplate->save();

        return response()->json([
            'message' => '状态已更新',
            'data' => $this->serializeOne($expenseTemplate->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowNodesRules(): array
    {
        return [
            'workflow_nodes' => ['nullable', 'array'],
            'workflow_nodes.*.node_order' => ['required', 'integer', 'min:1'],
            'workflow_nodes.*.node_name' => ['required', 'string', 'max:100'],
            'workflow_nodes.*.approver_type' => ['required', 'string', Rule::in(['dept_leader', 'parent_dept_leader', 'position', 'supervisor'])],
            'workflow_nodes.*.role_code' => ['nullable', 'string', 'max:50'],
            'workflow_nodes.*.applicant_dept_ids' => ['nullable', 'array'],
            'workflow_nodes.*.applicant_dept_ids.*' => ['integer', 'min:1'],
            'workflow_nodes.*.applicant_position_codes' => ['nullable', 'array'],
            'workflow_nodes.*.applicant_position_codes.*' => ['string', 'max:50'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function validateWorkflowNodeRefs(array $nodes): void
    {
        foreach ($nodes as $i => $n) {
            if (! is_array($n)) {
                continue;
            }
            $type = trim((string) ($n['approver_type'] ?? ''));
            $ref = trim((string) ($n['role_code'] ?? ''));
            if ($type === 'position' && $ref === '') {
                throw ValidationException::withMessages([
                    "workflow_nodes.$i.role_code" => ['「指定职务」须选择职务编码（positions.code）。'],
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function syncWorkflowForTemplate(ExpenseTemplateModel $tpl, array $nodes, int $now): void
    {
        if (! Schema::hasTable('workflows') || ! Schema::hasTable('workflow_nodes')) {
            return;
        }

        usort($nodes, fn ($a, $b) => (int) $a['node_order'] <=> (int) $b['node_order']);

        $wid = $tpl->workflow_id;
        if ($wid) {
            DB::table('workflow_nodes')->where('workflow_id', (int) $wid)->delete();
        } else {
            $wid = DB::table('workflows')->insertGetId([
                'name' => $tpl->name.' — 审批',
                'template_id' => $tpl->id,
                'code' => null,
                'created_at' => $now,
            ]);
            $tpl->workflow_id = (int) $wid;
            DB::table('expense_templates')->where('id', $tpl->id)->update([
                'workflow_id' => (int) $wid,
                'updated_at' => $now,
            ]);
        }

        DB::table('workflows')->where('id', (int) $wid)->update([
            'template_id' => $tpl->id,
            'name' => $tpl->name.' — 审批',
        ]);

        foreach ($nodes as $n) {
            $ad = isset($n['applicant_dept_ids']) && is_array($n['applicant_dept_ids']) ? array_values(array_filter(array_map('intval', $n['applicant_dept_ids']), static fn (int $id) => $id > 0)) : [];
            $ap = isset($n['applicant_position_codes']) && is_array($n['applicant_position_codes']) ? array_values(array_filter(array_map('strval', $n['applicant_position_codes']), static fn ($c) => trim($c) !== '')) : [];

            $condObj = [];
            if ($ad !== []) {
                $condObj['applicant_dept_ids'] = $ad;
            }
            if ($ap !== []) {
                $condObj['applicant_position_codes'] = $ap;
            }
            $cond = $condObj !== [] ? json_encode($condObj, JSON_UNESCAPED_UNICODE) : null;

            $type = trim((string) ($n['approver_type'] ?? 'dept_leader'));
            if ($type === '') {
                $type = 'dept_leader';
            }
            $ref = trim((string) ($n['role_code'] ?? ''));
            if (in_array($type, ['dept_leader', 'parent_dept_leader'], true)) {
                $ref = '';
            }

            DB::table('workflow_nodes')->insert([
                'workflow_id' => (int) $wid,
                'node_order' => (int) $n['node_order'],
                'node_name' => (string) $n['node_name'],
                'role_code' => $ref,
                'approver_type' => $type,
                'approver_id' => null,
                'condition_json' => $cond,
                'created_at' => $now,
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadWorkflowNodes(int $workflowId): array
    {
        if ($workflowId <= 0 || ! Schema::hasTable('workflow_nodes')) {
            return [];
        }

        $nodes = DB::table('workflow_nodes')
            ->where('workflow_id', $workflowId)
            ->orderBy('node_order')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($nodes as $n) {
            $ad = [];
            $ap = [];
            if ($n->condition_json !== null && trim((string) $n->condition_json) !== '') {
                $j = json_decode((string) $n->condition_json, true);
                if (is_array($j)) {
                    if (isset($j['applicant_dept_ids']) && is_array($j['applicant_dept_ids'])) {
                        $ad = array_values(array_filter(array_map('intval', $j['applicant_dept_ids']), static fn (int $id) => $id > 0));
                    }
                    if (isset($j['applicant_position_codes']) && is_array($j['applicant_position_codes'])) {
                        $ap = array_values(array_filter(array_map('strval', $j['applicant_position_codes']), static fn ($c) => trim($c) !== ''));
                    }
                }
            }
            $atype = trim((string) ($n->approver_type ?? ''));
            if ($atype === '') {
                $atype = 'dept_leader';
            }
            $out[] = [
                'node_order' => (int) $n->node_order,
                'node_name' => (string) ($n->node_name ?? ''),
                'approver_type' => $atype,
                'role_code' => (string) ($n->role_code ?? ''),
                'applicant_dept_ids' => $ad,
                'applicant_position_codes' => $ap,
            ];
        }

        return $out;
    }

    /**
     * 配置流程节点时：部门列表 + 职务列表（含 code），用于「适用申请人部门/职务」与按职务指定审批人。
     */
    public function apiWorkflowOrgOptions(): JsonResponse
    {
        $departments = [];
        if (Schema::hasTable('departments')) {
            $departments = DB::table('departments')
                ->where('status', 1)
                ->orderBy('sort')
                ->orderByDesc('id')
                ->get(['id', 'name'])
                ->map(static function ($d) {
                    return [
                        'id' => (int) ($d->id ?? 0),
                        'name' => trim((string) ($d->name ?? '')),
                    ];
                })
                ->filter(static fn ($row) => $row['id'] > 0 && $row['name'] !== '')
                ->values()
                ->all();
        }

        $positions = [];
        if (Schema::hasTable('positions')) {
            $q = DB::table('positions')->where('status', 1)->orderBy('dept_id')->orderByDesc('level')->orderByDesc('id');
            $plist = $q->get(['id', 'name', 'code', 'dept_id']);
            $deptIds = $plist->pluck('dept_id')->unique()->filter()->map(static fn ($id) => (int) $id)->all();
            $deptNames = [];
            if ($deptIds !== [] && Schema::hasTable('departments')) {
                $deptNames = DB::table('departments')->whereIn('id', $deptIds)->pluck('name', 'id')->all();
            }
            foreach ($plist as $p) {
                $did = (int) ($p->dept_id ?? 0);
                $code = trim((string) ($p->code ?? ''));
                if ($code === '') {
                    continue;
                }
                $positions[] = [
                    'id' => (int) ($p->id ?? 0),
                    'name' => trim((string) ($p->name ?? '')),
                    'code' => $code,
                    'dept_id' => $did,
                    'dept_name' => $did > 0 ? trim((string) ($deptNames[$did] ?? '')) : '',
                ];
            }
        }

        return response()->json([
            'data' => [
                'departments' => $departments,
                'positions' => $positions,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOne(ExpenseTemplateModel $tpl): array
    {
        return [
            'id' => (int) $tpl->id,
            'name' => $tpl->name,
            'code' => $tpl->code,
            'workflow_id' => $tpl->workflow_id !== null ? (int) $tpl->workflow_id : null,
            'status' => (int) $tpl->status,
            'created_by' => $tpl->created_by !== null ? (int) $tpl->created_by : null,
            'created_at' => $tpl->created_at !== null ? (int) $tpl->created_at : null,
            'updated_at' => $tpl->updated_at !== null ? (int) $tpl->updated_at : null,
        ];
    }
}
