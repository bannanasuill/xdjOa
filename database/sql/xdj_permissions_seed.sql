-- 表名含默认前缀 xdj_；与 DB_TABLE_PREFIX 不一致时请替换。
-- xdj_permissions：与当前后台路由对齐的可选种子数据
-- 需已存在 xdj_permissions 表（见 需求文档 / migrations）。
--
-- 执行策略：先删除本脚本管理范围内的角色-权限关联与权限行，再插入（避免 ON DUPLICATE 残留）。
--
-- 固定 id 约定：
--   1 = 后台管理（perm.admin）
--   2 = 前台管理（perm.frontend，预留）
--   3 = 后台登录（perm.admin.login）
--   4 起：后台菜单与接口节点连续递增（至 29，含系统配置）
--   报销管理相关权限与菜单见迁移 2026_04_08_100000_create_expense_workflow_tables_and_menu.php（perm.admin.expense.*）
--
-- 路由来源：
--   - 页面：resources/js/admin/router.js（Vue base: /admin）
--   - 接口：routes/web.php Route::prefix('admin/api')
--   - 菜单 path 与 xdj_menus_seed.sql 一致（日志为 /admin/user-logs，SPA 内映射为 /logs）
--
-- code 约定：perm.admin.<模块>.<动作>；菜单类 type=menu，接口类 type=api。
--
-- 以下接口仅要求已登录（routes/web.php 中 admin/api 未挂 admin.perm），不在本表维护：
--   GET /admin/api/me、GET /admin/api/menus、POST /admin/api/logout

SET NAMES utf8mb4;

-- 历史上若曾为「公共接口」建过权限节点，先移除角色关联与节点本身（按 code 匹配）
DELETE rp FROM xdj_role_permissions rp
INNER JOIN xdj_permissions p ON p.id = rp.permission_id
WHERE p.code IN ('perm.admin.api.me', 'perm.admin.api.menus', 'perm.admin.api.logout');

DELETE FROM xdj_permissions
WHERE code IN ('perm.admin.api.me', 'perm.admin.api.menus', 'perm.admin.api.logout');

-- 本种子固定维护的 permission id：1–29（先清关联再删权限）
DELETE FROM xdj_role_permissions WHERE permission_id >= 1 AND permission_id <= 29;

DELETE FROM xdj_permissions WHERE id >= 1 AND id <= 29;

INSERT INTO xdj_permissions (id, name, code, type, parent_id, path, created_at, updated_at)
VALUES
    (1, '后台管理', 'perm.admin', 'menu', NULL, NULL, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (2, '前台管理', 'perm.frontend', 'menu', NULL, NULL, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (3, '后台登录', 'perm.admin.login', 'api', 1, NULL, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),

    (4, '控制台', 'perm.admin.dashboard', 'menu', 1, '/admin', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (5, '用户管理', 'perm.admin.users', 'menu', 1, '/admin/users', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (6, '操作日志', 'perm.admin.logs', 'menu', 1, '/admin/user-logs', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (7, '菜单管理', 'perm.admin.menus', 'menu', 1, '/admin/menus', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (8, '权限管理', 'perm.admin.permissions', 'menu', 1, '/admin/permissions', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (9, '角色管理', 'perm.admin.roles', 'menu', 1, '/admin/roles', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),

    (10, '接口：用户列表', 'perm.admin.api.users.index', 'api', 5, 'GET /admin/api/users', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (11, '接口：新增用户', 'perm.admin.api.users.store', 'api', 5, 'POST /admin/api/users', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (12, '接口：更新用户', 'perm.admin.api.users.update', 'api', 5, 'PUT /admin/api/users/{adminUser}', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (13, '接口：用户状态', 'perm.admin.api.users.status', 'api', 5, 'PATCH /admin/api/users/{adminUser}/status', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),

    (14, '接口：日志列表', 'perm.admin.api.logs.index', 'api', 6, 'GET /admin/api/user-logs', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),

    (15, '接口：菜单树', 'perm.admin.api.menu_items.index', 'api', 7, 'GET /admin/api/menu-items', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (16, '接口：新增菜单', 'perm.admin.api.menu_items.store', 'api', 7, 'POST /admin/api/menu-items', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (17, '接口：更新菜单', 'perm.admin.api.menu_items.update', 'api', 7, 'PUT /admin/api/menu-items/{menu}', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (18, '接口：菜单内联', 'perm.admin.api.menu_items.patch', 'api', 7, 'PATCH /admin/api/menu-items/{menu}', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),

    (19, '接口：权限树', 'perm.admin.api.permissions.index', 'api', 8, 'GET /admin/api/permissions', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (20, '接口：新增权限', 'perm.admin.api.permissions.store', 'api', 8, 'POST /admin/api/permissions', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (21, '接口：更新权限', 'perm.admin.api.permissions.update', 'api', 8, 'PUT /admin/api/permissions/{permission}', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),

    (22, '接口：角色列表', 'perm.admin.api.roles.index', 'api', 9, 'GET /admin/api/roles', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (23, '接口：新增角色', 'perm.admin.api.roles.store', 'api', 9, 'POST /admin/api/roles', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (24, '接口：更新角色', 'perm.admin.api.roles.update', 'api', 9, 'PUT /admin/api/roles/{role}', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (25, '接口：角色已选权限', 'perm.admin.api.roles.permissions.index', 'api', 9, 'GET /admin/api/roles/{role}/permissions', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (26, '接口：角色分配权限', 'perm.admin.api.roles.permissions.sync', 'api', 9, 'PUT /admin/api/roles/{role}/permissions', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),

    (27, '系统配置', 'perm.admin.settings', 'menu', 1, '/admin/settings', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (28, '接口：系统配置读取', 'perm.admin.api.settings.index', 'api', 27, 'GET /admin/api/system-config', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (29, '接口：系统配置保存', 'perm.admin.api.settings.update', 'api', 27, 'PUT /admin/api/system-config', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- 预置角色 id=2 用户（code=admin）、id=3 员工：默认具备「后台登录」（permission id=3；上文已删掉 1–29 的旧关联）
INSERT INTO xdj_role_permissions (role_id, permission_id) VALUES (2, 3), (3, 3);
-- 与迁移一致：id=2 管理员角色默认可看系统配置菜单并可调接口
INSERT INTO xdj_role_permissions (role_id, permission_id) VALUES (2, 27), (2, 28), (2, 29);
