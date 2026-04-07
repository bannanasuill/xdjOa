-- 组织示例数据（默认物理表前缀 xdj_；若 .env 中 DB_TABLE_PREFIX 不同请替换表名）
-- 负责人 leader_id 一律为 NULL，可在后台「部门列表」再指定。
-- 执行前请确保已跑迁移：departments 含 status/sort/type/updated_at，positions 含 level/status/updated_at。
--
-- 推荐（自动使用 .env 前缀与库名）：在 src 目录执行
--   php artisan db:seed --class=Database\\Seeders\\OrgStructureSeeder
-- 或执行完整种子（含角色与用户）：
--   php artisan db:seed

SET NAMES utf8mb4;

-- =========================
-- 部门初始化
-- =========================
INSERT INTO xdj_departments (id, name, parent_id, leader_id, level, path, status, sort, type, created_at, updated_at) VALUES
(1, '总公司', 0, NULL, 1, '1', 1, 0, 'company', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),

(2, '财务部', 1, NULL, 2, '1/2', 1, 2, 'department', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(3, '市场部', 1, NULL, 2, '1/3', 1, 3, 'department', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),

(10, '上海分公司', 1, NULL, 2, '1/10', 1, 10, 'branch', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(11, '上海门店1', 10, NULL, 3, '1/10/11', 1, 11, 'store', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(12, '上海门店2', 10, NULL, 3, '1/10/12', 1, 12, 'store', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),

(20, '北京分公司', 1, NULL, 2, '1/20', 1, 20, 'branch', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(21, '北京门店1', 20, NULL, 3, '1/20/21', 1, 21, 'store', UNIX_TIMESTAMP(), UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    parent_id = VALUES(parent_id),
    leader_id = VALUES(leader_id),
    level = VALUES(level),
    path = VALUES(path),
    status = VALUES(status),
    sort = VALUES(sort),
    type = VALUES(type),
    updated_at = VALUES(updated_at);

-- =========================
-- 职位初始化
-- =========================
INSERT INTO xdj_positions (id, name, code, dept_id, level, status, created_at, updated_at) VALUES
(1, '总经理', 'ceo', 1, 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '财务经理', 'finance_manager', 2, 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(3, '财务助理', 'finance_assistant', 2, 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(4, '市场经理', 'marketing_manager', 3, 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),

(10, '分公司负责人', 'branch_manager', 10, 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(11, '督导', 'supervisor', 10, 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),

(20, '店长', 'store_manager', 11, 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(21, '店员', 'staff', 11, 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    dept_id = VALUES(dept_id),
    level = VALUES(level),
    status = VALUES(status),
    updated_at = VALUES(updated_at);
