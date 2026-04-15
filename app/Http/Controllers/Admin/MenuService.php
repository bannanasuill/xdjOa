<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class MenuService extends Controller
{
    public function apiIndex(): JsonResponse
    {
        $all = MenuModel::query()
            ->orderByDesc('visible')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
        $roots = $all->filter(function (MenuModel $m) {
            return $m->parent_id === null || (int) $m->parent_id === 0;
        });

        $data = $roots->map(fn (MenuModel $m) => $this->menuNode($m, $all))->values();

        return response()->json(['data' => $data]);
    }

    /**
     * @param  Collection<int, MenuModel>  $all
     * @return array<string, mixed>
     */
    private function menuNode(MenuModel $item, Collection $all): array
    {
        $children = $all
            ->filter(fn (MenuModel $m) => (int) ($m->parent_id ?? 0) === (int) $item->id)
            ->values()
            ->map(fn (MenuModel $m) => $this->menuNode($m, $all))
            ->all();

        return [
            'id' => (int) $item->id,
            'name' => $item->name,
            'permission_code' => $item->permission_code,
            'path' => $item->path,
            'component' => $item->component,
            'parent_id' => $item->parent_id !== null ? (int) $item->parent_id : null,
            'icon' => $item->icon,
            'sort' => (int) $item->sort,
            'visible' => (int) $item->visible,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
            'children' => $children,
        ];
    }

    public function apiStore(Request $request): JsonResponse
    {
        $validated = $this->validatedMenuPayload($request, null);
        $now = time();
        $menu = new MenuModel;
        $menu->fill($validated);
        $menu->created_at = $now;
        $menu->updated_at = $now;
        $menu->save();

        return response()->json([
            'message' => '菜单新增成功',
            'data' => ['id' => (int) $menu->id],
        ], 201);
    }

    public function apiUpdate(Request $request, MenuModel $menu): JsonResponse
    {
        $validated = $this->validatedMenuPayload($request, $menu);

        $newParent = $validated['parent_id'] ?? null;
        if ($this->wouldCreateCycle($menu, $newParent)) {
            return response()->json(['message' => '不能将父级设为自身或子菜单'], 422);
        }

        $validated['updated_at'] = time();
        $menu->fill($validated);
        $menu->save();

        return response()->json(['message' => '菜单已更新']);
    }

    /**
     * 列表内联更新：仅允许修改状态（visible）与排序（sort）。
     */
    public function apiPatch(Request $request, MenuModel $menu): JsonResponse
    {
        $validated = $request->validate([
            'visible' => ['sometimes', 'integer', Rule::in([0, 1])],
            'sort' => ['sometimes', 'integer', 'min:0', 'max:999999'],
        ]);

        if (! array_key_exists('visible', $validated) && ! array_key_exists('sort', $validated)) {
            return response()->json(['message' => '请提供 visible 或 sort'], 422);
        }

        if (array_key_exists('visible', $validated)) {
            $menu->visible = $validated['visible'];
        }
        if (array_key_exists('sort', $validated)) {
            $menu->sort = $validated['sort'];
        }

        $menu->updated_at = time();
        $menu->save();

        return response()->json(['message' => '已更新']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedMenuPayload(Request $request, ?MenuModel $existing): array
    {
        $permissionUnique = Rule::unique('menus', 'permission_code');
        if ($existing) {
            $permissionUnique->ignore($existing->id);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'permission_code' => ['required', 'string', 'max:100', $permissionUnique],
            'path' => ['nullable', 'string', 'max:255'],
            'component' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:menus,id'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'visible' => ['nullable', 'integer', Rule::in([0, 1])],
        ]);

        if (array_key_exists('parent_id', $validated) && ($validated['parent_id'] === 0 || $validated['parent_id'] === '0')) {
            $validated['parent_id'] = null;
        }

        if (! array_key_exists('sort', $validated) || $validated['sort'] === null) {
            $validated['sort'] = 0;
        }

        if (! array_key_exists('visible', $validated) || $validated['visible'] === null) {
            $validated['visible'] = 1;
        }

        return $validated;
    }

    private function wouldCreateCycle(MenuModel $menu, ?int $newParentId): bool
    {
        if ($newParentId === null || $newParentId === 0) {
            return false;
        }

        if ((int) $newParentId === (int) $menu->id) {
            return true;
        }

        $current = MenuModel::query()->find($newParentId);
        $guard = 0;

        while ($current && $guard++ < 100) {
            if ((int) $current->id === (int) $menu->id) {
                return true;
            }
            $pid = $current->parent_id;
            if ($pid === null || (int) $pid === 0) {
                break;
            }
            $current = MenuModel::query()->find((int) $pid);
        }

        return false;
    }
}
