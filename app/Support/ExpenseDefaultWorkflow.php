<?php

namespace App\Support;

/**
 * 默认报销审批链（workflows.code = default_expense）。
 * 节点仅按部门/职务解析；workflow_nodes.role_code 存职务 positions.code。
 */
final class ExpenseDefaultWorkflow
{
    public const WORKFLOW_CODE = 'default_expense';

    /** @var list<array{node_order:int, node_name:string, approver_type:string, role_code:string}> */
    public const NODES = [
        ['node_order' => 1, 'node_name' => '店长审核', 'approver_type' => 'position', 'role_code' => 'store_manager'],
        ['node_order' => 2, 'node_name' => '督导审核', 'approver_type' => 'supervisor', 'role_code' => 'store_supervisor'],
        ['node_order' => 3, 'node_name' => '财务助理审核', 'approver_type' => 'position', 'role_code' => 'finance_assistant'],
        ['node_order' => 4, 'node_name' => '财务审核', 'approver_type' => 'position', 'role_code' => 'finance'],
        ['node_order' => 5, 'node_name' => '总经理审核', 'approver_type' => 'position', 'role_code' => 'general_manager'],
        ['node_order' => 6, 'node_name' => '财务打款', 'approver_type' => 'position', 'role_code' => 'finance_payer'],
    ];

    /** @var list<string> 历史种子曾写入的 roles.code，迁移用于清理非系统角色行 */
    public const LEGACY_EXPENSE_ROLE_CODES = [
        'store_manager',
        'store_supervisor',
        'finance_assistant',
        'finance',
        'general_manager',
        'finance_payer',
    ];
}
