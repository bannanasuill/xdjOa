<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseTemplateModel;
use App\Models\UserModel;
use App\Support\ExpenseDefaultWorkflow;
use App\Support\ExpenseWorkflowResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExpenseWorkflowService extends Controller
{
    /**
     * 系统内置「标准报销审批」流程 + 当前登录人的部门/职务，预览将经历的节点。
     */
    public function apiDefault(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => '未登录'], 401);
        }

        if (! Schema::hasTable('workflows')) {
            return response()->json([
                'data' => [
                    'workflow_code' => ExpenseDefaultWorkflow::WORKFLOW_CODE,
                    'workflow_id' => null,
                    'applicant_org' => UserModel::applicantOrgContext($user),
                    'nodes' => [],
                    'hint' => '数据表未就绪。',
                ],
            ]);
        }

        $wid = DB::table('workflows')->where('code', ExpenseDefaultWorkflow::WORKFLOW_CODE)->value('id');
        if (! $wid) {
            return response()->json([
                'data' => [
                    'workflow_code' => ExpenseDefaultWorkflow::WORKFLOW_CODE,
                    'workflow_id' => null,
                    'applicant_org' => UserModel::applicantOrgContext($user),
                    'nodes' => [],
                    'hint' => '未找到默认报销流程，请执行迁移（含 2026_04_10_110000、2026_04_18_000000）。',
                ],
            ]);
        }

        $wid = (int) $wid;
        $nodes = ExpenseWorkflowResolver::resolveChainForApplicant($wid, $user);

        return response()->json([
            'data' => [
                'workflow_code' => ExpenseDefaultWorkflow::WORKFLOW_CODE,
                'workflow_id' => $wid,
                'applicant_org' => UserModel::applicantOrgContext($user),
                'nodes' => $nodes,
            ],
        ]);
    }

    /**
     * 按所选报销模板 + 当前用户的部门/职务，解析其将经历的审批节点。
     */
    public function apiPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => ['required', 'integer', 'min:1'],
        ]);

        if (! Schema::hasTable('expense_templates')) {
            return response()->json(['message' => '数据表未就绪'], 400);
        }

        $tpl = ExpenseTemplateModel::query()->whereKey($validated['template_id'])->first();
        if ($tpl === null) {
            return response()->json(['message' => '模板不存在'], 404);
        }

        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => '未登录'], 401);
        }

        $org = UserModel::applicantOrgContext($user);

        if (! $tpl->workflow_id) {
            return response()->json([
                'data' => [
                    'template_id' => (int) $tpl->id,
                    'template_name' => $tpl->name,
                    'applicant_org' => $org,
                    'nodes' => [],
                    'hint' => '该模板尚未配置审批节点，请在「报销模板」中维护流程。',
                ],
            ]);
        }

        $nodes = ExpenseWorkflowResolver::resolveChainForApplicant((int) $tpl->workflow_id, $user);

        return response()->json([
            'data' => [
                'template_id' => (int) $tpl->id,
                'template_name' => $tpl->name,
                'workflow_id' => (int) $tpl->workflow_id,
                'applicant_org' => $org,
                'nodes' => $nodes,
            ],
        ]);
    }
}
