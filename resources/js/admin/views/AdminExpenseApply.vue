<template>
  <div class="admin-expense-apply-page">
    <el-card class="admin-mb-12">
      <div slot="header"><span>报销申请</span></div>
      <p class="admin-expense-apply-intro">
        提交后单据按下列顺序流转；审批人由「部门负责人 / 职务 / 上级部门」等解析，请在「用户管理」中维护员工的部门、职务及部门负责人。
      </p>
      <div v-loading="workflowLoading" class="admin-expense-apply-steps-wrap">
        <el-steps v-if="displaySteps.length" :active="-1" finish-status="wait" align-center class="admin-expense-apply-steps">
          <el-step
            v-for="(s, idx) in displaySteps"
            :key="idx"
            :title="s.title"
            :description="s.description"
          />
        </el-steps>
        <p v-else-if="!workflowLoading" class="admin-expense-placeholder">
          未加载到默认审批流程，请执行数据库迁移（含 <code>2026_04_10_110000</code>、<code>2026_04_18_000000</code>）。
        </p>
      </div>
    </el-card>

    <el-card>
      <div slot="header"><span>发起申请</span></div>
      <p class="admin-expense-placeholder">报销单填报与审批操作可在此后续接入（流程节点按部门与职务解析）。</p>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'AdminExpenseApply',
  data() {
    return {
      workflowLoading: false,
      workflow: null,
    };
  },
  computed: {
    displaySteps() {
      const first = { title: '用户提交', description: '填写报销单并提交' };
      const nodes = (this.workflow && this.workflow.nodes) || [];
      const rest = nodes.map((n) => ({
        title: (n.node_name && String(n.node_name)) || (n.approver_ref && String(n.approver_ref)) || `节点${n.node_order}`,
        description: (n.approver_preview && String(n.approver_preview)) || (n.approver_ref ? String(n.approver_ref) : ''),
      }));
      return [first, ...rest];
    },
  },
  created() {
    this.loadWorkflow();
  },
  methods: {
    async loadWorkflow() {
      this.workflowLoading = true;
      try {
        const { data } = await window.axios.get('/admin/api/expense-workflow/default');
        this.workflow = data.data || null;
      } catch (e) {
        this.workflow = null;
        if (e?.response?.status !== 403) {
          this.$message.error(e?.response?.data?.message || '加载审批流程失败');
        }
      } finally {
        this.workflowLoading = false;
      }
    },
  },
};
</script>

<style scoped>
.admin-expense-apply-intro {
  margin: 0 0 16px;
  font-size: 13px;
  line-height: 1.6;
  color: #64748b;
}
.admin-expense-apply-steps-wrap {
  min-height: 120px;
}
.admin-expense-apply-steps {
  max-width: 1080px;
  margin: 0 auto;
}
.admin-expense-placeholder {
  margin: 0;
  font-size: 14px;
  color: #64748b;
}
.admin-expense-placeholder code {
  font-size: 12px;
}
</style>
