-- 下列表名为默认 DB_TABLE_PREFIX=xdj_ 下的物理名；若 .env 中前缀不同请全局替换 xdj_。
-- xdj_menus 菜单（除首页外）；支持父子菜单。首页仍写死在布局中。
-- 「用户」与「用户列表」均指向 /admin/users（同一页面）。
-- 「日志管理」为一级菜单，指向 /admin/user-logs。

SET NAMES utf8mb4;

INSERT INTO xdj_menus (id, name, permission_code, path, component, parent_id, icon, sort, visible, created_at, updated_at)
VALUES
    (1, '用户管理', 'menu.admin', '/admin/users', '', NULL, 'el-icon-user', 10, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (2, '用户列表', 'menu.admin_users_list', '/admin/users', 'admin.users.index', 1, 'el-icon-user', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (11, '部门与职务', 'menu.admin_departments', '/admin/departments', '', 1, 'el-icon-office-building', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (3, '日志管理', 'menu.admin_user_logs', '/admin/user-logs', 'admin.logs.index', NULL, 'el-icon-document', 20, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (4, '菜单管理', 'menu.admin_menus', '/admin/menus', '', NULL, 'el-icon-menu', 25, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (5, '权限管理', 'menu.admin_permissions', '/admin/permissions', '', NULL, 'el-icon-key', 26, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (6, '角色管理', 'menu.admin_roles', '/admin/roles', '', NULL, 'el-icon-collection', 27, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (7, '系统配置', 'menu.admin_settings', '/admin/settings', '', NULL, 'el-icon-setting', 99, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (8, '报销管理', 'menu.admin_expense', '/admin/expense/templates', '', NULL, 'el-icon-s-order', 17, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (9, '报销模板', 'menu.admin_expense_templates', '/admin/expense/templates', '', 8, '', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (10, '报销申请', 'menu.admin_expense_apply', '/admin/expense/apply', '', 8, '', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())
   ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    path = VALUES(path),
    component = VALUES(component),
    parent_id = VALUES(parent_id),
    icon = VALUES(icon),
    sort = VALUES(sort),
    visible = VALUES(visible),
    updated_at = VALUES(updated_at);

-- 可按需追加其它顶级菜单（parent_id 为 NULL，sort 自行递增）及其子菜单（parent_id 指向父级 id）。
