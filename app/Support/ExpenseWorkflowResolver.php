<?php

namespace App\Support;

use App\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 报销审批链：仅支持 dept_leader / parent_dept_leader / position / supervisor；
 * 条件 condition_json 仅 applicant_dept_ids、applicant_position_codes。
 */
final class ExpenseWorkflowResolver
{
    private const APPROVER_TYPES = ['dept_leader', 'parent_dept_leader', 'position', 'supervisor'];

    /**
     * @return list<array<string, mixed>>
     */
    public static function resolveChainForApplicant(int $workflowId, UserModel $user): array
    {
        if (! Schema::hasTable('workflow_nodes')) {
            return [];
        }

        $org = UserModel::applicantOrgContext($user);

        $nodes = DB::table('workflow_nodes')
            ->where('workflow_id', $workflowId)
            ->orderBy('node_order')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($nodes as $n) {
            $type = trim((string) ($n->approver_type ?? ''));
            if (! self::isKnownApproverType($type)) {
                continue;
            }

            if (! self::nodeMatchesApplicant($n->condition_json ?? null, $org)) {
                continue;
            }

            $ref = trim((string) ($n->role_code ?? ''));
            $approverPreview = self::buildApproverPreview($type, $ref, $org);
            [$adIds, $apCodes] = self::parseApplicantCondition($n->condition_json ?? null);

            $out[] = [
                'node_order' => (int) $n->node_order,
                'node_name' => (string) ($n->node_name ?? ''),
                'approver_type' => $type,
                'approver_ref' => $ref !== '' ? $ref : null,
                'approver_preview' => $approverPreview,
                'applicant_dept_ids' => $adIds,
                'applicant_position_codes' => $apCodes,
            ];
        }

        return $out;
    }

    private static function isKnownApproverType(string $type): bool
    {
        return in_array($type, self::APPROVER_TYPES, true);
    }

    /**
     * @param  array{dept_ids:list<int>, primary_dept_id:?int, position_codes:list<string>}  $org
     */
    private static function buildApproverPreview(string $type, string $ref, array $org): string
    {
        if ($type === 'dept_leader') {
            $pid = $org['primary_dept_id'] ?? null;
            if ($pid === null || $pid < 1) {
                return '申请人未关联部门';
            }
            $lid = self::deptLeaderId((int) $pid);
            if ($lid === null) {
                return '该部门未设置负责人';
            }

            return self::userPreviewLabel($lid).'（部门负责人）';
        }

        if ($type === 'parent_dept_leader') {
            $pid = $org['primary_dept_id'] ?? null;
            if ($pid === null || $pid < 1) {
                return '申请人未关联部门';
            }
            $parentId = self::deptParentId((int) $pid);
            if ($parentId === null || $parentId < 1) {
                return '无上级部门';
            }
            $lid = self::deptLeaderId($parentId);
            if ($lid === null) {
                return '上级部门未设置负责人';
            }

            return self::userPreviewLabel($lid).'（上级部门负责人）';
        }

        if ($type === 'position') {
            if ($ref === '') {
                return '未配置职务标识';
            }
            $deptIds = $org['dept_ids'] ?? [];
            if ($deptIds === []) {
                return '申请人未关联部门';
            }
            $uids = self::userIdsWithPositionInDepts($ref, $deptIds);
            if ($uids === []) {
                return '未找到担任「'.$ref.'」的审批人';
            }

            return self::joinUserLabels($uids).'（职务 '.$ref.'）';
        }

        if ($type === 'supervisor') {
            $code = $ref !== '' ? $ref : 'store_supervisor';
            $pid = $org['primary_dept_id'] ?? null;
            if ($pid === null || $pid < 1) {
                return '申请人未关联部门';
            }
            $parentId = self::deptParentId((int) $pid);
            if ($parentId === null || $parentId < 1) {
                return '无法解析上级部门（督导所在部门）';
            }
            $uids = self::userIdsWithPositionInDepts($code, [$parentId]);
            if ($uids === []) {
                return '上级部门下未找到职务「'.$code.'」';
            }

            return self::joinUserLabels($uids).'（督导 / '.$code.'）';
        }

        return '—';
    }

    private static function deptParentId(int $deptId): ?int
    {
        if (! Schema::hasTable('departments')) {
            return null;
        }
        $p = DB::table('departments')->where('id', $deptId)->value('parent_id');
        if ($p === null) {
            return null;
        }
        $n = (int) $p;

        return $n > 0 ? $n : null;
    }

    private static function deptLeaderId(int $deptId): ?int
    {
        if (! Schema::hasTable('departments')) {
            return null;
        }
        $id = DB::table('departments')->where('id', $deptId)->value('leader_id');
        if ($id === null) {
            return null;
        }
        $n = (int) $id;

        return $n > 0 ? $n : null;
    }

    /**
     * @param  list<int>  $deptIds
     * @return list<int>
     */
    private static function userIdsWithPositionInDepts(string $positionCode, array $deptIds): array
    {
        if (! Schema::hasTable('user_positions') || ! Schema::hasTable('positions')) {
            return [];
        }
        $deptIds = array_values(array_unique(array_filter(array_map('intval', $deptIds), static fn (int $id) => $id > 0)));
        if ($deptIds === []) {
            return [];
        }
        $code = trim($positionCode);
        if ($code === '') {
            return [];
        }

        return DB::table('user_positions as up')
            ->join('positions as p', 'p.id', '=', 'up.position_id')
            ->where('p.code', $code)
            ->where('p.status', 1)
            ->whereIn('p.dept_id', $deptIds)
            ->pluck('up.user_id')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private static function userPreviewLabel(int $userId): string
    {
        if ($userId <= 0 || ! Schema::hasTable('users')) {
            return '#'.$userId;
        }
        $u = UserModel::query()->find($userId);
        if ($u === null) {
            return '#'.$userId;
        }
        $rn = trim((string) ($u->real_name ?? ''));
        $ac = trim((string) ($u->account ?? ''));
        if ($rn !== '' && $ac !== '') {
            return $rn.'（'.$ac.'）';
        }

        return $rn !== '' ? $rn : ($ac !== '' ? $ac : '#'.$userId);
    }

    /**
     * @param  list<int>  $userIds
     */
    private static function joinUserLabels(array $userIds): string
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id) => $id > 0)));
        if ($userIds === []) {
            return '—';
        }
        $slice = array_slice($userIds, 0, 4);
        $labels = array_map(fn (int $id) => self::userPreviewLabel($id), $slice);
        $s = implode('、', $labels);
        $more = count($userIds) - count($slice);
        if ($more > 0) {
            $s .= ' 等'.$more.'人';
        }

        return $s;
    }

    /**
     * @param  array{dept_ids:list<int>, primary_dept_id:?int, position_codes:list<string>}  $org
     */
    private static function nodeMatchesApplicant(?string $conditionJson, array $org): bool
    {
        if ($conditionJson === null || trim($conditionJson) === '') {
            return true;
        }
        $j = json_decode($conditionJson, true);
        if (! is_array($j)) {
            return true;
        }

        $deptFilter = null;
        if (isset($j['applicant_dept_ids']) && is_array($j['applicant_dept_ids'])) {
            $deptFilter = array_values(array_filter(array_map('intval', $j['applicant_dept_ids']), static fn (int $id) => $id > 0));
        }
        $posFilter = null;
        if (isset($j['applicant_position_codes']) && is_array($j['applicant_position_codes'])) {
            $posFilter = array_values(array_filter(array_map('strval', $j['applicant_position_codes']), static fn ($c) => trim($c) !== ''));
        }

        $hasDept = $deptFilter !== null && $deptFilter !== [];
        $hasPos = $posFilter !== null && $posFilter !== [];

        if (! $hasDept && ! $hasPos) {
            return true;
        }

        $okDept = ! $hasDept || count(array_intersect($deptFilter, $org['dept_ids'])) > 0;
        $okPos = ! $hasPos || count(array_intersect($posFilter, $org['position_codes'])) > 0;

        return $okDept && $okPos;
    }

    /**
     * @return array{0: list<int>, 1: list<string>}
     */
    private static function parseApplicantCondition(?string $conditionJson): array
    {
        $ad = [];
        $ap = [];
        if ($conditionJson === null || trim($conditionJson) === '') {
            return [$ad, $ap];
        }
        $j = json_decode($conditionJson, true);
        if (! is_array($j)) {
            return [$ad, $ap];
        }
        if (isset($j['applicant_dept_ids']) && is_array($j['applicant_dept_ids'])) {
            $ad = array_values(array_filter(array_map('intval', $j['applicant_dept_ids']), static fn (int $id) => $id > 0));
        }
        if (isset($j['applicant_position_codes']) && is_array($j['applicant_position_codes'])) {
            $ap = array_values(array_filter(array_map('strval', $j['applicant_position_codes']), static fn ($c) => trim($c) !== ''));
        }

        return [$ad, $ap];
    }
}
