<?php

namespace App\Support;

/** 报销单状态（与 expense_forms.status 字符串一致，供提交/审批接口扩展使用）。 */
final class ExpenseFormStatus
{
    public const DRAFT = 'draft';

    /** 已提交，按 current_node 停在某一审批节点 */
    public const PENDING = 'pending';

    public const REJECTED = 'rejected';

    /** 全流程通过且已打款 */
    public const PAID = 'paid';
}
