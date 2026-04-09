<?php

use App\Http\Controllers\Admin\ApiService;
use App\Http\Controllers\Admin\AttendanceRuleService;
use App\Http\Controllers\Admin\AuthService;
use App\Http\Controllers\Admin\DashboardService;
use App\Http\Controllers\Admin\ExpenseTemplateService;
use App\Http\Controllers\Admin\ExpenseWorkflowService;
use App\Http\Controllers\Admin\MenuService;
use App\Http\Controllers\Admin\PermissionService;
use App\Http\Controllers\Admin\DepartmentService;
use App\Http\Controllers\Admin\StoreService;
use App\Http\Controllers\Admin\PositionService;
use App\Http\Controllers\Admin\RoleService;
use App\Http\Controllers\Admin\SystemConfigService;
use App\Http\Controllers\Admin\UserLogService;
use App\Http\Controllers\Admin\UserService;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

Route::get('/favicon.ico', static function (): BinaryFileResponse {
    $path = public_path('images/logo.png');
    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Content-Type' => 'image/png',
        'Cache-Control' => 'public, max-age=604800',
    ]);
})->name('favicon');

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('admin.dashboard');
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthService::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthService::class, 'login'])
        ->middleware('throttle:30,1');
});

Route::middleware(['auth', 'admin.panel'])->group(function () {
    Route::get('/admin', [DashboardService::class, 'index'])->name('admin.dashboard');

    // SPA JSON APIs (keep these ABOVE the /admin/{any} fallback)
    Route::prefix('admin/api')->group(function () {
        Route::get('/me', [ApiService::class, 'me'])->name('admin.api.me');
        Route::post('/logout', [ApiService::class, 'logout'])->name('admin.api.logout');
        Route::get('/menus', [ApiService::class, 'menus'])->name('admin.api.menus');

        Route::get('/role-options', [UserService::class, 'apiRoleOptions'])->name('admin.api.role-options');
        Route::get('/users/org-options', [UserService::class, 'apiOrgOptions'])
            ->middleware('admin.perm:perm.admin.api.users.update')
            ->name('admin.api.users.org-options');
        Route::get('/users/store-assignment-options', [UserService::class, 'apiStoreAssignmentOptions'])
            ->middleware('admin.perm:perm.admin.api.users.update')
            ->name('admin.api.users.store-assignment-options');

        Route::get('/users', [UserService::class, 'apiIndex'])
            ->middleware('admin.perm:perm.admin.api.users.index')
            ->name('admin.api.users.index');
        Route::get('/users/{adminUser}/presence-records', [UserService::class, 'apiUserPresenceRecords'])
            ->middleware('admin.perm:perm.admin.api.users.index')
            ->name('admin.api.users.presence-records');
        Route::post('/users', [UserService::class, 'apiStore'])
            ->middleware('admin.perm:perm.admin.api.users.store')
            ->name('admin.api.users.store');
        Route::put('/users/{adminUser}', [UserService::class, 'apiUpdate'])
            ->middleware('admin.perm:perm.admin.api.users.update')
            ->name('admin.api.users.update');
        Route::patch('/users/{adminUser}/roles', [UserService::class, 'apiSyncRoles'])
            ->middleware('admin.perm:perm.admin.api.users.update')
            ->name('admin.api.users.roles');
        Route::patch('/users/{adminUser}/org', [UserService::class, 'apiSyncOrg'])
            ->middleware('admin.perm:perm.admin.api.users.update')
            ->name('admin.api.users.org');
        Route::get('/users/{adminUser}/stores', [UserService::class, 'apiUserStores'])
            ->middleware('admin.perm:perm.admin.api.users.update')
            ->name('admin.api.users.stores');
        Route::put('/users/{adminUser}/stores', [UserService::class, 'apiUserStoresSync'])
            ->middleware('admin.perm:perm.admin.api.users.update')
            ->name('admin.api.users.stores.sync');
        Route::patch('/users/{adminUser}/status', [UserService::class, 'apiUpdateStatus'])
            ->middleware('admin.perm:perm.admin.api.users.status')
            ->name('admin.api.users.status');

        Route::get('/user-logs', [UserLogService::class, 'apiIndex'])
            ->middleware('admin.perm:perm.admin.api.logs.index')
            ->name('admin.api.logs.index');

        Route::get('/menu-items', [MenuService::class, 'apiIndex'])
            ->middleware('admin.perm:perm.admin.api.menu_items.index')
            ->name('admin.api.menu-items.index');
        Route::post('/menu-items', [MenuService::class, 'apiStore'])
            ->middleware('admin.perm:perm.admin.api.menu_items.store')
            ->name('admin.api.menu-items.store');
        Route::put('/menu-items/{menu}', [MenuService::class, 'apiUpdate'])
            ->middleware('admin.perm:perm.admin.api.menu_items.update')
            ->name('admin.api.menu-items.update');
        Route::patch('/menu-items/{menu}', [MenuService::class, 'apiPatch'])
            ->middleware('admin.perm:perm.admin.api.menu_items.patch')
            ->name('admin.api.menu-items.patch');

        Route::get('/permissions', [PermissionService::class, 'apiIndex'])
            ->middleware('admin.perm:perm.admin.api.permissions.index')
            ->name('admin.api.permissions.index');
        Route::post('/permissions', [PermissionService::class, 'apiStore'])
            ->middleware('admin.perm:perm.admin.api.permissions.store')
            ->name('admin.api.permissions.store');
        Route::put('/permissions/{permission}', [PermissionService::class, 'apiUpdate'])
            ->middleware('admin.perm:perm.admin.api.permissions.update')
            ->name('admin.api.permissions.update');

        Route::get('/roles/{role}/permissions', [RoleService::class, 'apiRolePermissionsIndex'])
            ->middleware('admin.perm:perm.admin.api.roles.permissions.index')
            ->name('admin.api.roles.permissions.index');
        Route::put('/roles/{role}/permissions', [RoleService::class, 'apiRolePermissionsSync'])
            ->middleware('admin.perm:perm.admin.api.roles.permissions.sync')
            ->name('admin.api.roles.permissions.sync');
        Route::get('/roles', [RoleService::class, 'apiIndex'])
            ->middleware('admin.perm:perm.admin.api.roles.index')
            ->name('admin.api.roles.index');
        Route::post('/roles', [RoleService::class, 'apiStore'])
            ->middleware('admin.perm:perm.admin.api.roles.store')
            ->name('admin.api.roles.store');
        Route::put('/roles/{role}', [RoleService::class, 'apiUpdate'])
            ->middleware('admin.perm:perm.admin.api.roles.update')
            ->name('admin.api.roles.update');

        Route::get('/system-config', [SystemConfigService::class, 'apiShow'])
            ->middleware('admin.perm:perm.admin.api.settings.index')
            ->name('admin.api.system-config.show');
        Route::put('/system-config', [SystemConfigService::class, 'apiUpdate'])
            ->middleware('admin.perm:perm.admin.api.settings.update')
            ->name('admin.api.system-config.update');

        Route::get('/expense-templates', [ExpenseTemplateService::class, 'apiIndex'])
            ->middleware('admin.perm:perm.admin.api.expense_templates.index')
            ->name('admin.api.expense-templates.index');
        Route::get('/expense-templates/workflow-org-options', [ExpenseTemplateService::class, 'apiWorkflowOrgOptions'])
            ->middleware('admin.perm:perm.admin.api.expense_templates.index')
            ->name('admin.api.expense-templates.workflow-org-options');
        Route::get('/expense-templates/{expenseTemplate}', [ExpenseTemplateService::class, 'apiShow'])
            ->middleware('admin.perm:perm.admin.api.expense_templates.index')
            ->name('admin.api.expense-templates.show');
        Route::post('/expense-templates', [ExpenseTemplateService::class, 'apiStore'])
            ->middleware('admin.perm:perm.admin.api.expense_templates.store')
            ->name('admin.api.expense-templates.store');
        Route::put('/expense-templates/{expenseTemplate}', [ExpenseTemplateService::class, 'apiUpdate'])
            ->middleware('admin.perm:perm.admin.api.expense_templates.update')
            ->name('admin.api.expense-templates.update');
        Route::patch('/expense-templates/{expenseTemplate}/status', [ExpenseTemplateService::class, 'apiPatchStatus'])
            ->middleware('admin.perm:perm.admin.api.expense_templates.status')
            ->name('admin.api.expense-templates.status');

        Route::get('/departments/leader-options', [DepartmentService::class, 'apiLeaderOptions'])
            ->middleware('admin.perm:perm.admin.api.departments.leader_options')
            ->name('admin.api.departments.leader-options');
        Route::get('/departments', [DepartmentService::class, 'apiIndex'])
            ->middleware('admin.perm:perm.admin.api.departments.index')
            ->name('admin.api.departments.index');
        Route::post('/departments', [DepartmentService::class, 'apiStore'])
            ->middleware('admin.perm:perm.admin.api.departments.store')
            ->name('admin.api.departments.store');
        Route::put('/departments/{department}', [DepartmentService::class, 'apiUpdate'])
            ->middleware('admin.perm:perm.admin.api.departments.update')
            ->name('admin.api.departments.update');
        Route::patch('/departments/{department}/status', [DepartmentService::class, 'apiPatchStatus'])
            ->middleware('admin.perm:perm.admin.api.departments.status')
            ->name('admin.api.departments.status');
        Route::patch('/departments/{department}/sort', [DepartmentService::class, 'apiPatchSort'])
            ->middleware('admin.perm:perm.admin.api.departments.update')
            ->name('admin.api.departments.sort');
        Route::delete('/departments/{department}', [DepartmentService::class, 'apiDestroy'])
            ->middleware('admin.perm:perm.admin.api.departments.destroy')
            ->name('admin.api.departments.destroy');

        Route::get('/positions/dept-options', [PositionService::class, 'apiDeptOptions'])
            ->middleware('admin.perm:perm.admin.api.positions.dept_options')
            ->name('admin.api.positions.dept-options');
        Route::get('/positions', [PositionService::class, 'apiIndex'])
            ->middleware('admin.perm:perm.admin.api.positions.index')
            ->name('admin.api.positions.index');
        Route::post('/positions', [PositionService::class, 'apiStore'])
            ->middleware('admin.perm:perm.admin.api.positions.store')
            ->name('admin.api.positions.store');
        Route::put('/positions/{position}', [PositionService::class, 'apiUpdate'])
            ->middleware('admin.perm:perm.admin.api.positions.update')
            ->name('admin.api.positions.update');
        Route::patch('/positions/{position}/status', [PositionService::class, 'apiPatchStatus'])
            ->middleware('admin.perm:perm.admin.api.positions.status')
            ->name('admin.api.positions.status');
        Route::delete('/positions/{position}', [PositionService::class, 'apiDestroy'])
            ->middleware('admin.perm:perm.admin.api.positions.destroy')
            ->name('admin.api.positions.destroy');

        Route::get('/stores/dept-options', [StoreService::class, 'apiDeptOptions'])
            ->middleware('admin.perm:perm.admin.api.stores.dept_options')
            ->name('admin.api.stores.dept-options');
        Route::get('/stores', [StoreService::class, 'apiIndex'])
            ->middleware('admin.perm:perm.admin.api.stores.index')
            ->name('admin.api.stores.index');
        Route::post('/stores/geocode', [StoreService::class, 'apiGeocode'])
            ->name('admin.api.stores.geocode');
        Route::post('/stores', [StoreService::class, 'apiStore'])
            ->middleware('admin.perm:perm.admin.api.stores.store')
            ->name('admin.api.stores.store');
        Route::put('/stores/{store}', [StoreService::class, 'apiUpdate'])
            ->middleware('admin.perm:perm.admin.api.stores.update')
            ->name('admin.api.stores.update');
        Route::patch('/stores/{store}/status', [StoreService::class, 'apiPatchStatus'])
            ->middleware('admin.perm:perm.admin.api.stores.status')
            ->name('admin.api.stores.status');
        Route::delete('/stores/{store}', [StoreService::class, 'apiDestroy'])
            ->middleware('admin.perm:perm.admin.api.stores.destroy')
            ->name('admin.api.stores.destroy');

        Route::get('/attendance-rules/form-options', [AttendanceRuleService::class, 'apiFormOptions'])
            ->middleware('admin.perm:perm.admin.api.attendance_rules.form_options')
            ->name('admin.api.attendance-rules.form-options');
        Route::get('/attendance-rules', [AttendanceRuleService::class, 'apiIndex'])
            ->middleware('admin.perm:perm.admin.api.attendance_rules.index')
            ->name('admin.api.attendance-rules.index');
        Route::post('/attendance-rules', [AttendanceRuleService::class, 'apiStore'])
            ->middleware('admin.perm:perm.admin.api.attendance_rules.store')
            ->name('admin.api.attendance-rules.store');
        Route::put('/attendance-rules/{attendanceRule}', [AttendanceRuleService::class, 'apiUpdate'])
            ->middleware('admin.perm:perm.admin.api.attendance_rules.update')
            ->name('admin.api.attendance-rules.update');
        Route::patch('/attendance-rules/{attendanceRule}/status', [AttendanceRuleService::class, 'apiPatchStatus'])
            ->middleware('admin.perm:perm.admin.api.attendance_rules.status')
            ->name('admin.api.attendance-rules.status');
        Route::delete('/attendance-rules/{attendanceRule}', [AttendanceRuleService::class, 'apiDestroy'])
            ->middleware('admin.perm:perm.admin.api.attendance_rules.destroy')
            ->name('admin.api.attendance-rules.destroy');

        Route::get('/expense-workflow/default', [ExpenseWorkflowService::class, 'apiDefault'])
            ->middleware('admin.perm:perm.admin.expense.apply')
            ->name('admin.api.expense-workflow.default');
        Route::get('/expense-workflow/preview', [ExpenseWorkflowService::class, 'apiPreview'])
            ->middleware('admin.perm:perm.admin.expense.apply')
            ->name('admin.api.expense-workflow.preview');
    });

    // SPA fallback (exclude /admin/api/*)
    Route::get('/admin/{any}', [DashboardService::class, 'index'])
        ->where('any', '^(?!api).*$')
        ->name('admin.spa');
    Route::get('/admin/users', [UserService::class, 'index'])->name('admin.users.index');
    Route::get('/admin/user-logs', [UserLogService::class, 'index'])->name('admin.logs.index');
    Route::get('/admin/users/create', [UserService::class, 'create'])->name('admin.users.create');
    Route::post('/admin/users', [UserService::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users/{adminUser}/edit', [UserService::class, 'edit'])->name('admin.users.edit');
    Route::put('/admin/users/{adminUser}', [UserService::class, 'update'])->name('admin.users.update');
    Route::patch('/admin/users/{adminUser}/status', [UserService::class, 'updateStatus'])->name('admin.users.status');
    Route::post('/logout', [AuthService::class, 'logout'])->name('logout');
});
