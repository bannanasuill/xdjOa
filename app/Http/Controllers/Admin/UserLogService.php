<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Http\Controllers\Controller;
use App\Models\UserLogModel;

class UserLogService extends Controller
{
    public function index(Request $request): View
    {
        $request->merge([
            'tagtype' => $request->input('tagtype') === '' ? null : $request->input('tagtype'),
            'log_type' => $request->input('log_type') === '' ? null : $request->input('log_type'),
            'module' => $request->input('module') === '' ? null : $request->input('module'),
            'action' => $request->input('action') === '' ? null : $request->input('action'),
            'start_date' => $request->input('start_date') === '' ? null : $request->input('start_date'),
            'end_date' => $request->input('end_date') === '' ? null : $request->input('end_date'),
            'start_at' => $request->input('start_at') === '' ? null : $request->input('start_at'),
            'end_at' => $request->input('end_at') === '' ? null : $request->input('end_at'),
        ]);

        $dateOptions = UserLogModel::dateOptions(60);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'log_type' => ['nullable', 'string', 'max:20', Rule::in(UserLogModel::logTypeOptions())],
            'tagtype' => ['nullable', 'string', 'max:50', Rule::in(UserLogModel::targetTypeOptions())],
            'module' => ['nullable', 'string', 'max:50', Rule::in(UserLogModel::moduleOptions())],
            'action' => ['nullable', 'string', 'max:50', Rule::in(UserLogModel::actionOptions())],
            'start_date' => ['nullable', 'string', Rule::in($dateOptions)],
            'end_date' => ['nullable', 'string', Rule::in($dateOptions)],
            'start_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'end_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
        ]);

        $keyword = trim((string) ($validated['q'] ?? ''));
        $logType = $validated['log_type'] ?? null;
        $tagType = $validated['tagtype'] ?? null;
        $module = $validated['module'] ?? null;
        $action = $validated['action'] ?? null;
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $startAt = $validated['start_at'] ?? null;
        $endAt = $validated['end_at'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 20);
        $tagTypeOptions = UserLogModel::targetTypeOptions();
        $logTypeOptions = UserLogModel::logTypeOptions();
        $moduleOptions = UserLogModel::moduleOptions();
        $actionOptions = UserLogModel::actionOptions();

        $logs = UserLogModel::adminFilteredQuery([
            'q' => $keyword,
            'log_type' => $logType,
            'target_type' => $tagType,
            'module' => $module,
            'action' => $action,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ])->paginate($perPage);
        $logs->appends($request->query());

        return view('admin.logs.index', [
            'logs' => $logs,
            'searchQuery' => $keyword,
            'filters' => [
                'log_type' => $logType,
                'tagtype' => $tagType,
                'module' => $module,
                'action' => $action,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_at' => $startAt,
                'end_at' => $endAt,
            ],
            'perPage' => $perPage,
            'perPageOptions' => [10, 20, 50, 100],
            'tagTypeOptions' => $tagTypeOptions,
            'logTypeOptions' => $logTypeOptions,
            'moduleOptions' => $moduleOptions,
            'actionOptions' => $actionOptions,
            'dateOptions' => $dateOptions,
        ]);
    }

    public function apiIndex(Request $request): JsonResponse
    {
        $request->merge([
            'tagtype' => $request->input('tagtype') === '' ? null : $request->input('tagtype'),
            'log_type' => $request->input('log_type') === '' ? null : $request->input('log_type'),
            'module' => $request->input('module') === '' ? null : $request->input('module'),
            'action' => $request->input('action') === '' ? null : $request->input('action'),
            'start_at' => $request->input('start_at') === '' ? null : $request->input('start_at'),
            'end_at' => $request->input('end_at') === '' ? null : $request->input('end_at'),
        ]);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'log_type' => ['nullable', 'string', 'max:20', Rule::in(UserLogModel::logTypeOptions())],
            'tagtype' => ['nullable', 'string', 'max:50', Rule::in(UserLogModel::targetTypeOptions())],
            'module' => ['nullable', 'string', 'max:50', Rule::in(UserLogModel::moduleOptions())],
            'action' => ['nullable', 'string', 'max:50', Rule::in(UserLogModel::actionOptions())],
            'start_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'end_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $keyword = trim((string) ($validated['q'] ?? ''));
        $logType = $validated['log_type'] ?? null;
        $tagType = $validated['tagtype'] ?? null;
        $module = $validated['module'] ?? null;
        $action = $validated['action'] ?? null;
        $startAt = $validated['start_at'] ?? null;
        $endAt = $validated['end_at'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 20);

        $paginator = UserLogModel::adminFilteredQuery([
            'q' => $keyword,
            'log_type' => $logType,
            'target_type' => $tagType,
            'module' => $module,
            'action' => $action,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ])->paginate($perPage);

        $logTypeLabels = UserLogModel::logTypeLabels();
        $logTypeOptions = array_map(static function (string $v) use ($logTypeLabels) {
            return [
                'value' => $v,
                'label' => $logTypeLabels[$v] ?? $v,
            ];
        }, UserLogModel::logTypeOptions());

        $targetTypeLabels = UserLogModel::targetTypeLabels();
        $targetTypeOptions = array_map(static function (string $v) use ($targetTypeLabels) {
            return [
                'value' => $v,
                'label' => $targetTypeLabels[$v] ?? $v,
            ];
        }, UserLogModel::targetTypeOptions());

        $moduleLabels = UserLogModel::moduleLabels();
        $moduleOptions = array_map(static function (string $v) use ($moduleLabels) {
            return [
                'value' => $v,
                'label' => $moduleLabels[$v] ?? $v,
            ];
        }, UserLogModel::moduleOptions());

        $actionLabels = UserLogModel::actionLabels();
        $actionOptions = array_map(static function (string $v) use ($actionLabels) {
            return [
                'value' => $v,
                'label' => $actionLabels[$v] ?? $v,
            ];
        }, UserLogModel::actionOptions());

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'options' => [
                'target_type' => $targetTypeOptions,
                'log_type' => $logTypeOptions,
                'module' => $moduleOptions,
                'action' => $actionOptions,
                'per_page' => [10, 20, 50, 100],
            ],
            'labels' => [
                'log_type' => $logTypeLabels,
                'target_type' => $targetTypeLabels,
                'module' => $moduleLabels,
                'action' => $actionLabels,
            ],
        ]);
    }
}

