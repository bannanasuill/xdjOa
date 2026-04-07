-- 表名含默认前缀 xdj_；与 DB_TABLE_PREFIX 不一致时请替换。
-- 预置角色（与 DatabaseSeeder / 迁移一致）：
--   id=1 超级管理员 super_admin（is_system=1，全权限，一般不在 xdj_role_permissions 存行）
--   id=2 管理员 admin（is_system=0，权限在 xdj_role_permissions 维护）
--   id=3 员工 employee（is_system=0）
--
-- 清理历史上若曾给 role_id=1 插过关联，可执行下面 DELETE（无数据则不影响）：

SET NAMES utf8mb4;

DELETE FROM xdj_role_permissions WHERE role_id = 1;

INSERT INTO xdj_roles (id, name, code, data_scope, is_system, created_at, updated_at)
VALUES
    (1, '超级管理员', 'super_admin', 'all', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
    (2, '后台管理员', 'admin', 'self', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    code = VALUES(code),
    data_scope = VALUES(data_scope),
    is_system = VALUES(is_system),
    updated_at = VALUES(updated_at);
