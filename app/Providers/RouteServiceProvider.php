<?php

namespace App\Providers;

use App\Models\AttendanceRuleModel;
use App\Models\ExpenseTemplateModel;
use App\Models\DepartmentModel;
use App\Models\PositionModel;
use App\Models\StoreModel;
use App\Models\MenuModel;
use App\Models\PermissionModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/admin';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        Route::bind('adminUser', function (string $value) {
            return UserModel::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('menu', function (string $value) {
            return MenuModel::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('permission', function (string $value) {
            return PermissionModel::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('role', function (string $value) {
            return RoleModel::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('expenseTemplate', function (string $value) {
            return ExpenseTemplateModel::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('department', function (string $value) {
            return DepartmentModel::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('position', function (string $value) {
            return PositionModel::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('store', function (string $value) {
            return StoreModel::query()->whereKey($value)->firstOrFail();
        });

        Route::bind('attendanceRule', function (string $value) {
            return AttendanceRuleModel::query()->whereKey($value)->firstOrFail();
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
