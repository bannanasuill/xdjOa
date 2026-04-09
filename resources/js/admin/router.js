import Vue from 'vue';
import Router from 'vue-router';

import AdminLayout from './views/AdminLayout.vue';
import AdminHome from './views/AdminHome.vue';
import AdminUsers from './views/AdminUsers.vue';
import AdminLogs from './views/AdminLogs.vue';
import AdminMenus from './views/AdminMenus.vue';
import AdminPermissions from './views/AdminPermissions.vue';
import AdminRoles from './views/AdminRoles.vue';
import AdminSettings from './views/AdminSettings.vue';
import AdminExpenseTemplates from './views/AdminExpenseTemplates.vue';
import AdminExpenseApply from './views/AdminExpenseApply.vue';
import AdminDepartments from './views/AdminDepartments.vue';
import AdminStores from './views/AdminStores.vue';
import AdminAttendanceRules from './views/AdminAttendanceRules.vue';
import { Message } from 'element-ui';
import { ensureAdminPermissions, canAdminPermission } from './permissions';

Vue.use(Router);

const router = new Router({
    mode: 'history',
    base: '/admin',
    routes: [
        {
            path: '/',
            component: AdminLayout,
            children: [
                { path: '', name: 'admin.home', component: AdminHome },
                { path: 'users', name: 'admin.users', meta: { perm: 'perm.admin.users' }, component: AdminUsers },
                {
                    path: 'departments',
                    name: 'admin.departments',
                    meta: { permAny: ['perm.admin.departments', 'perm.admin.positions'] },
                    component: AdminDepartments,
                },
                { path: 'positions', redirect: { name: 'admin.departments' } },
                {
                  path: 'stores',
                  name: 'admin.stores',
                  meta: { perm: 'perm.admin.stores' },
                  component: AdminStores,
                },
                {
                  path: 'attendance-rules',
                  name: 'admin.attendance_rules',
                  meta: { perm: 'perm.admin.attendance_rules' },
                  component: AdminAttendanceRules,
                },
                { path: 'logs', name: 'admin.logs', meta: { perm: 'perm.admin.logs' }, component: AdminLogs },
                { path: 'menus', name: 'admin.menus', meta: { perm: 'perm.admin.menus' }, component: AdminMenus },
                { path: 'permissions', name: 'admin.permissions', meta: { perm: 'perm.admin.permissions' }, component: AdminPermissions },
                { path: 'roles', name: 'admin.roles', meta: { perm: 'perm.admin.roles' }, component: AdminRoles },
                { path: 'settings', name: 'admin.settings', meta: { perm: 'perm.admin.settings' }, component: AdminSettings },
                {
                    path: 'expense/templates',
                    name: 'admin.expense.templates',
                    meta: { perm: 'perm.admin.expense.templates' },
                    component: AdminExpenseTemplates,
                },
                {
                    path: 'expense/apply',
                    name: 'admin.expense.apply',
                    meta: { perm: 'perm.admin.expense.apply' },
                    component: AdminExpenseApply,
                },
            ],
        },
    ],
});

router.beforeEach(async (to, from, next) => {
  // 先拉取权限：避免首页无 meta.perm 时 codeSet 未就绪、子页误判；会话失效时勿 next(false)（会白屏）。
  try {
    await ensureAdminPermissions();
  } catch (e) {
    window.location.href = '/login';
    return;
  }

  const rec = to.matched.find((r) => r.meta && (r.meta.perm || r.meta.permAny));
  if (rec && rec.meta.permAny && Array.isArray(rec.meta.permAny)) {
    const ok = rec.meta.permAny.some((c) => canAdminPermission(c));
    if (!ok) {
      Message.warning('无权访问该页面');
      next({ name: 'admin.home', replace: true });
      return;
    }
    next();
    return;
  }
  const need = rec && rec.meta.perm;
  if (!need) {
    next();
    return;
  }
  if (!canAdminPermission(need)) {
    Message.warning('无权访问该页面');
    next({ name: 'admin.home', replace: true });
    return;
  }
  next();
});

export default router;

