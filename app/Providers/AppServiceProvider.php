<?php

namespace App\Providers;

use App\Models\MenuModel;
use App\Models\SystemSettingModel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.admin', 'admin.app', 'admin.auth.login'], function ($view) {
            $view->with([
                'adminFaviconHref' => SystemSettingModel::resolvedFaviconHref(),
                'adminSiteName' => SystemSettingModel::resolvedSiteName(),
            ]);
        });

        View::composer('layouts.admin', function ($view) {
            $menus = collect();

            $pathIsActive = static function (?string $path): bool {
                $raw = trim((string) $path);
                if ($raw === '' || $raw === '#') {
                    return false;
                }
                $p = trim($raw, '/');
                if ($p === '') {
                    return false;
                }

                return request()->is($p) || request()->is($p.'/*');
            };

            $adminMenuUrl = static function (?string $path): string {
                $path = trim((string) $path);
                if ($path === '' || $path === '#') {
                    return '#';
                }
                if (Str::startsWith($path, ['http://', 'https://', '//'])) {
                    return $path;
                }

                return url('/'.ltrim($path, '/'));
            };

            if (Schema::hasTable('menus')) {
                $menus = MenuModel::query()
                    ->where('visible', 1)
                    ->where(function ($q) {
                        $q->whereNull('parent_id')->orWhere('parent_id', 0);
                    })
                    ->with([
                        'children' => static function ($q) {
                            $q->where('visible', 1)->orderBy('sort')->orderBy('id');
                        },
                    ])
                    ->orderBy('sort')
                    ->orderBy('id')
                    ->get();
            }

            $branchIsActive = static function (MenuModel $menu) use ($pathIsActive): bool {
                if ($pathIsActive($menu->path)) {
                    return true;
                }
                foreach ($menu->children as $child) {
                    if ($pathIsActive($child->path)) {
                        return true;
                    }
                }

                return false;
            };

            $adminBreadcrumbs = self::buildAdminBreadcrumbs($menus, $pathIsActive, $adminMenuUrl);

            $view->with([
                'adminNavMenus' => $menus,
                'adminMenuUrl' => $adminMenuUrl,
                'adminMenuPathIsActive' => $pathIsActive,
                'adminMenuBranchIsActive' => $branchIsActive,
                'adminBreadcrumbs' => $adminBreadcrumbs,
            ]);
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MenuModel>  $menus
     * @return array<int, array{label: string, url: ?string}>
     */
    private static function buildAdminBreadcrumbs($menus, callable $pathIsActive, callable $menuUrl): array
    {
        if (request()->routeIs('admin.dashboard')) {
            return [['label' => '首页', 'url' => null]];
        }

        $usersCrumb = static function () use ($menus, $menuUrl): array {
            foreach ($menus as $menu) {
                foreach ($menu->children as $child) {
                    if (trim((string) $child->path, '/') === 'admin/users') {
                        return [
                            ['label' => $menu->name, 'url' => $menuUrl($menu->path)],
                            ['label' => $child->name, 'url' => route('admin.users.index')],
                        ];
                    }
                }
            }

            return [
                ['label' => '用户', 'url' => route('admin.users.index')],
            ];
        };

        if (request()->routeIs('admin.users.create')) {
            $mid = $usersCrumb();

            return array_merge($mid, [['label' => '新增用户', 'url' => null]]);
        }

        if (request()->routeIs('admin.users.edit')) {
            $mid = $usersCrumb();

            return array_merge($mid, [['label' => '编辑用户', 'url' => null]]);
        }

        foreach ($menus as $menu) {
            if ($menu->children->isEmpty()) {
                if ($pathIsActive($menu->path)) {
                    return [['label' => $menu->name, 'url' => null]];
                }

                continue;
            }

            $activeChild = null;
            foreach ($menu->children as $child) {
                if ($pathIsActive($child->path)) {
                    $activeChild = $child;
                    break;
                }
            }

            if ($activeChild !== null) {
                return [
                    ['label' => $menu->name, 'url' => $menuUrl($menu->path)],
                    ['label' => $activeChild->name, 'url' => null],
                ];
            }

            if ($pathIsActive($menu->path)) {
                $first = $menu->children->first();
                if ($first === null) {
                    return [['label' => $menu->name, 'url' => null]];
                }

                return [
                    ['label' => $menu->name, 'url' => $menuUrl($menu->path)],
                    ['label' => $first->name, 'url' => null],
                ];
            }
        }

        if (request()->routeIs('admin.users.index')) {
            return [['label' => '用户', 'url' => null]];
        }

        return [['label' => '后台', 'url' => null]];
    }
}
