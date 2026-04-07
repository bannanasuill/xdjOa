<template>
  <div class="admin-expense-templates-page">
    <el-card class="admin-mb-12 admin-page-filters">
      <div class="admin-form-row">
        <el-button size="small" :loading="loading" @click="fetchList">刷新</el-button>
        <span class="admin-flex-spacer"></span>
        <el-button
          type="primary"
          size="small"
          :disabled="!$canPerm('perm.admin.api.expense_templates.store')"
          title="无「接口：报销模板新增」权限时不可操作"
          @click="openCreate"
        >
          新增
        </el-button>
      </div>
    </el-card>

    <el-card>
      <el-table
        ref="adminDataTable"
        class="admin-data-table"
        :data="rows"
        :max-height="adminTableMaxHeight"
        size="mini"
        v-loading="loading"
      >
        <el-table-column prop="id" label="ID" width="72" fixed="left" />
        <el-table-column label="模板名称" min-width="140" fixed="left">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(row.name)">{{ adminEllipsisDisplay(row.name) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="编码" min-width="120">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(row.code)">{{ adminEllipsisDisplay(row.code) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="创建人" min-width="120">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(creatorLabel(row))">{{ adminEllipsisDisplay(creatorLabel(row)) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="更新时间" width="158">
          <template slot-scope="{ row }">{{ formatTs(row.updated_at) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center" fixed="right">
          <template slot-scope="{ row }">
            <el-switch
              v-if="$canPerm('perm.admin.api.expense_templates.status')"
              class="admin-status-switch"
              :value="row.status === 1"
              :active-color="'#13ce66'"
              :inactive-color="'#f56c6c'"
              :disabled="statusBusyId === row.id"
              @change="(on) => patchStatus(row, on ? 1 : 0)"
            />
            <span v-else>{{ row.status === 1 ? '启用' : '禁用' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="100" fixed="right">
          <template slot-scope="{ row }">
            <el-button v-if="$canPerm('perm.admin.api.expense_templates.update')" size="mini" @click="openEdit(row)">
              编辑
            </el-button>
            <span v-else>—</span>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog
      :title="formMode === 'create' ? '新增报销模板' : '编辑报销模板'"
      :visible.sync="formVisible"
      width="720px"
      top="5vh"
      custom-class="admin-expense-template-dialog"
      @closed="onFormClosed"
    >
      <el-form :model="form" label-width="100px" size="small">
        <el-form-item label="模板名称" required>
          <el-input v-model="form.name" maxlength="100" placeholder="显示名称" />
        </el-form-item>
        <el-form-item label="编码" required>
          <el-input
            v-model="form.code"
            maxlength="50"
            placeholder="唯一，字母数字下划线与中划线"
            :disabled="formMode === 'edit'"
          />
          <div v-if="formMode === 'edit'" class="admin-form-hint">编码新增后不可修改</div>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.statusOn" active-text="启用" inactive-text="禁用" />
        </el-form-item>

        <el-divider content-position="left">审批节点</el-divider>
        <p class="admin-expense-template-nodes-hint">
          自上而下为审批顺序。「审批人类型」按部门负责人、职务等解析实际审批人（请在「用户管理」中维护部门与职务）。「适用申请人部门 / 职务」用于限定哪些员工会经过该节点；不选则不限。
        </p>
        <div class="admin-expense-template-nodes-toolbar">
          <el-button
            size="mini"
            type="primary"
            plain
            icon="el-icon-plus"
            :disabled="formSubmitting"
            @click="addWorkflowNode"
          >添加节点</el-button>
        </div>
        <div v-if="!formWorkflowNodes.length" class="admin-expense-template-nodes-empty">暂无节点；可不配置（后续再编辑添加），或点击「添加节点」。</div>
        <div
          v-for="(node, idx) in formWorkflowNodes"
          :key="'wf-node-' + idx"
          class="admin-expense-template-node-card"
        >
          <div class="admin-expense-template-node-card__head">
            <span class="admin-expense-template-node-card__step">第 {{ idx + 1 }} 步</span>
            <span class="admin-expense-template-node-card__actions">
              <el-button type="text" size="mini" :disabled="idx === 0 || formSubmitting" @click="moveWorkflowNode(idx, -1)">上移</el-button>
              <el-button
                type="text"
                size="mini"
                :disabled="idx >= formWorkflowNodes.length - 1 || formSubmitting"
                @click="moveWorkflowNode(idx, 1)"
              >下移</el-button>
              <el-button type="text" size="mini" class="admin-text-danger" :disabled="formSubmitting" @click="removeWorkflowNode(idx)">删除</el-button>
            </span>
          </div>
          <el-form
            :model="node"
            label-width="138px"
            size="small"
            label-position="right"
            class="admin-expense-template-node-inner-form"
          >
            <el-form-item label="节点名称">
              <el-input v-model="node.node_name" maxlength="100" placeholder="如：店长审核" />
            </el-form-item>
            <el-form-item label="审批人类型">
              <el-select v-model="node.approver_type" class="admin-w-full" @change="onApproverTypeChange(node)">
                <el-option
                  v-for="opt in approverTypeOptions"
                  :key="opt.value"
                  :label="opt.label"
                  :value="opt.value"
                />
              </el-select>
            </el-form-item>
            <el-form-item v-if="nodeNeedsPositionPicker(node)" :label="positionPickerLabel(node)" :required="nodeRequiresPositionCode(node)">
              <el-select
                v-model="node.role_code"
                filterable
                clearable
                :placeholder="positionPickerPlaceholder(node)"
                class="admin-w-full"
              >
                <el-option
                  v-for="p in workflowOrgOptions.positions"«
                  :key="p.code + '-' + p.id"
                  :label="workflowPositionOptionLabel(p)"
                  :value="p.code"
                />
              </el-select>
            </el-form-item>
            <el-form-item label="适用申请人部门">
              <el-select
                v-model="node.applicant_dept_ids"
                multiple
                filterable
                placeholder="不选则不限；申请人主部门或附属部门命中即可"
                class="admin-w-full admin-expense-template-applicant-select"
              >
                <el-option
                  v-for="d in workflowOrgOptions.departments"
                  :key="'ad-' + d.id"
                  :label="d.name"
                  :value="d.id"
                />
              </el-select>
            </el-form-item>
            <el-form-item label="适用申请人职务">
              <el-select
                v-model="node.applicant_position_codes"
                multiple
                filterable
                placeholder="不选则不限；按职务 code（positions.code）匹配"
                class="admin-w-full admin-expense-template-applicant-select"
              >
                <el-option
                  v-for="p in workflowOrgOptions.positions"
                  :key="'apc-' + p.code + '-' + p.id"
                  :label="workflowPositionOptionLabel(p)"
                  :value="p.code"
                />
              </el-select>
            </el-form-item>
          </el-form>
        </div>
      </el-form>
      <span slot="footer" class="admin-dialog-footer">
        <el-button size="small" @click="formVisible = false">取消</el-button>
        <el-button size="small" type="primary" :loading="formSubmitting" @click="submitForm">保存</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import adminTableFixedHeader from '../mixins/adminTableFixedHeader';

export default {
  name: 'AdminExpenseTemplates',
  mixins: [adminTableFixedHeader],
  data() {
    return {
      loading: false,
      rows: [],
      formVisible: false,
      formSubmitting: false,
      formMode: 'create',
      editingId: null,
      form: {
        name: '',
        code: '',
        statusOn: true,
      },
      /** 弹窗内编辑的审批链（顺序即 node_order） */
      formWorkflowNodes: [],
      workflowOrgOptions: { departments: [], positions: [] },
      approverTypeOptions: [
        { value: 'dept_leader', label: '部门负责人（申请人主部门）' },
        { value: 'parent_dept_leader', label: '上级部门负责人' },
        { value: 'position', label: '指定职务（申请人所属部门内，按 positions.code）' },
        { value: 'supervisor', label: '督导（上级部门内指定职务；职务码可空=store_supervisor）' },
      ],
      statusBusyId: null,
    };
  },
  created() {
    if (this.$canPerm('perm.admin.api.expense_templates.index')) {
      this.fetchList();
    }
  },
  methods: {
    creatorLabel(row) {
      if (!row) return '—';
      const n = (row.creator_real_name || '').trim();
      if (n) return n;
      const a = (row.creator_account || '').trim();
      if (a) return a;
      return '—';
    },
    formatTs(ts) {
      if (ts == null || ts === '') return '—';
      const n = Number(ts);
      if (!Number.isFinite(n) || n <= 0) return '—';
      const d = new Date(n * 1000);
      if (Number.isNaN(d.getTime())) return '—';
      const p = (x) => String(x).padStart(2, '0');
      return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
    },
    async fetchList() {
      if (!this.$canPerm('perm.admin.api.expense_templates.index')) return;
      this.loading = true;
      try {
        const { data } = await window.axios.get('/admin/api/expense-templates');
        this.rows = data.data || [];
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '加载失败');
      } finally {
        this.loading = false;
        this.$nextTick(() => this.syncAdminTableMaxHeight());
      }
    },
    workflowPositionOptionLabel(p) {
      if (!p) return '';
      const n = (p.name || '').trim();
      const c = (p.code || '').trim();
      const dn = (p.dept_name || '').trim();
      const bits = [];
      if (n) bits.push(n);
      if (c) bits.push(c);
      let s = bits.length ? bits.join(' / ') : c || n || '—';
      if (dn) s += ` · ${dn}`;
      return s;
    },
    nodeNeedsPositionPicker(node) {
      const t = String(node.approver_type || '');
      return t === 'position' || t === 'supervisor';
    },
    nodeRequiresPositionCode(node) {
      return String(node.approver_type || '') === 'position';
    },
    positionPickerLabel(node) {
      return String(node.approver_type || '') === 'supervisor' ? '督导职务（可选）' : '审批职务';
    },
    positionPickerPlaceholder(node) {
      return String(node.approver_type || '') === 'supervisor'
        ? '不选则使用 store_supervisor'
        : '选择职务（positions.code）';
    },
    onApproverTypeChange(node) {
      const t = String(node.approver_type || '');
      if (t === 'dept_leader' || t === 'parent_dept_leader') {
        node.role_code = '';
      }
      if ((t === 'position' || t === 'supervisor') && node.role_code) {
        const codes = new Set((this.workflowOrgOptions.positions || []).map((p) => p.code));
        if (!codes.has(node.role_code)) {
          node.role_code = '';
        }
      }
    },
    async ensureWorkflowOrgOptions() {
      const has =
        Array.isArray(this.workflowOrgOptions.departments) &&
        Array.isArray(this.workflowOrgOptions.positions) &&
        (this.workflowOrgOptions.departments.length || this.workflowOrgOptions.positions.length);
      if (has || !this.$canPerm('perm.admin.api.expense_templates.index')) {
        return;
      }
      try {
        const { data } = await window.axios.get('/admin/api/expense-templates/workflow-org-options');
        const d = data.data || {};
        this.workflowOrgOptions = {
          departments: Array.isArray(d.departments) ? d.departments : [],
          positions: Array.isArray(d.positions) ? d.positions : [],
        };
      } catch (e) {
        this.workflowOrgOptions = { departments: [], positions: [] };
        if (e?.response?.status !== 403) {
          this.$message.warning(e?.response?.data?.message || '加载部门职务列表失败');
        }
      }
    },
    emptyWorkflowNode() {
      return {
        node_name: '',
        approver_type: 'dept_leader',
        role_code: '',
        applicant_dept_ids: [],
        applicant_position_codes: [],
      };
    },
    addWorkflowNode() {
      this.formWorkflowNodes.push(this.emptyWorkflowNode());
    },
    removeWorkflowNode(idx) {
      if (idx < 0 || idx >= this.formWorkflowNodes.length) return;
      this.formWorkflowNodes.splice(idx, 1);
    },
    moveWorkflowNode(idx, delta) {
      const j = idx + delta;
      if (j < 0 || j >= this.formWorkflowNodes.length) return;
      const arr = this.formWorkflowNodes.slice();
      const t = arr[idx];
      arr[idx] = arr[j];
      arr[j] = t;
      this.formWorkflowNodes = arr;
    },
    buildWorkflowNodesPayload() {
      const raw = this.formWorkflowNodes || [];
      const hasPartial = raw.some((n) => {
        const nn = String(n.node_name || '').trim();
        const at = String(n.approver_type || 'dept_leader').trim();
        const rc = String(n.role_code || '').trim();
        if (at === 'position') {
          return (nn && !rc) || (!nn && rc);
        }
        return !nn && rc !== '';
      });
      if (hasPartial) {
        return {
          error:
            '请填写完整的节点：选择「指定职务」时必须同时填写节点名称与职务编码，或删除未填完整的行。',
        };
      }
      const complete = raw.filter((n) => {
        const node_name = String(n.node_name || '').trim();
        if (!node_name) {
          return false;
        }
        const at = String(n.approver_type || 'dept_leader').trim();
        const rc = String(n.role_code || '').trim();
        if (at === 'position') {
          return !!rc;
        }
        return true;
      });
      const workflow_nodes = complete.map((n, idx) => {
        const node_name = String(n.node_name || '').trim();
        let approver_type = String(n.approver_type || 'dept_leader').trim();
        if (!approver_type) {
          approver_type = 'dept_leader';
        }
        let role_code = String(n.role_code || '').trim();
        if (approver_type === 'dept_leader' || approver_type === 'parent_dept_leader') {
          role_code = '';
        }
        const ad = Array.isArray(n.applicant_dept_ids)
          ? n.applicant_dept_ids.filter((id) => id != null && Number(id) > 0)
          : [];
        const ap = Array.isArray(n.applicant_position_codes)
          ? n.applicant_position_codes.filter((c) => c != null && String(c).trim() !== '')
          : [];
        const item = { node_order: idx + 1, node_name, approver_type, role_code };
        if (ad.length) {
          item.applicant_dept_ids = ad;
        }
        if (ap.length) {
          item.applicant_position_codes = ap;
        }
        return item;
      });
      return { workflow_nodes };
    },
    async openCreate() {
      if (!this.$canPerm('perm.admin.api.expense_templates.store')) {
        this.$message.warning('无新增权限，请在角色中分配「接口：报销模板新增」');
        return;
      }
      this.formMode = 'create';
      this.editingId = null;
      this.form = { name: '', code: '', statusOn: true };
      this.formWorkflowNodes = [];
      await this.ensureWorkflowOrgOptions();
      this.formVisible = true;
    },
    async openEdit(row) {
      if (!this.$canPerm('perm.admin.api.expense_templates.update')) {
        return;
      }
      this.formMode = 'edit';
      this.editingId = row.id;
      await this.ensureWorkflowOrgOptions();
      try {
        const { data } = await window.axios.get(`/admin/api/expense-templates/${row.id}`);
        const d = data.data || {};
        this.form = {
          name: d.name || '',
          code: d.code || '',
          statusOn: d.status === 1,
        };
        const knownTypes = ['dept_leader', 'parent_dept_leader', 'position', 'supervisor'];
        const nodes = d.workflow_nodes || [];
        this.formWorkflowNodes = nodes.map((n) => {
          const at = n.approver_type != null ? String(n.approver_type).trim() : '';
          return {
            node_name: n.node_name != null ? String(n.node_name) : '',
            approver_type: knownTypes.includes(at) ? at : 'dept_leader',
            role_code: n.role_code != null ? String(n.role_code) : '',
            applicant_dept_ids: Array.isArray(n.applicant_dept_ids) ? n.applicant_dept_ids.map((id) => Number(id)) : [],
            applicant_position_codes: Array.isArray(n.applicant_position_codes)
              ? n.applicant_position_codes.map((c) => String(c))
              : [],
          };
        });
        this.formVisible = true;
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '加载模板详情失败');
      }
    },
    onFormClosed() {
      this.editingId = null;
      this.formWorkflowNodes = [];
    },
    async submitForm() {
      const name = (this.form.name && String(this.form.name).trim()) || '';
      const code = (this.form.code && String(this.form.code).trim()) || '';
      if (!name) {
        this.$message.warning('请填写模板名称');
        return;
      }
      if (!code) {
        this.$message.warning('请填写编码');
        return;
      }
      const built = this.buildWorkflowNodesPayload();
      if (built.error) {
        this.$message.warning(built.error);
        return;
      }
      const { workflow_nodes } = built;

      this.formSubmitting = true;
      try {
        const status = this.form.statusOn ? 1 : 0;
        if (this.formMode === 'create') {
          const body = { name, code, status };
          if (workflow_nodes.length) {
            body.workflow_nodes = workflow_nodes;
          }
          await window.axios.post('/admin/api/expense-templates', body);
          this.$message.success('新增成功');
        } else {
          await window.axios.put(`/admin/api/expense-templates/${this.editingId}`, {
            name,
            code,
            status,
            workflow_nodes,
          });
          this.$message.success('已更新');
        }
        this.formVisible = false;
        await this.fetchList();
      } catch (e) {
        const msg =
          e?.response?.data?.message ||
          (e?.response?.data?.errors ? Object.values(e.response.data.errors).flat().filter(Boolean).join('；') : null) ||
          '保存失败';
        this.$message.error(msg);
      } finally {
        this.formSubmitting = false;
      }
    },
    async patchStatus(row, status) {
      if (!row || !this.$canPerm('perm.admin.api.expense_templates.status')) return;
      const prev = row.status;
      this.statusBusyId = row.id;
      row.status = status;
      try {
        await window.axios.patch(`/admin/api/expense-templates/${row.id}/status`, { status });
        this.$message.success('状态已更新');
      } catch (e) {
        row.status = prev;
        this.$message.error(e?.response?.data?.message || '更新失败');
      } finally {
        this.statusBusyId = null;
      }
    },
  },
};
</script>

<style scoped>
.admin-expense-template-nodes-hint {
  margin: 0 0 10px;
  font-size: 12px;
  color: #909399;
  line-height: 1.5;
}
.admin-expense-template-nodes-toolbar {
  margin-bottom: 10px;
}
.admin-expense-template-nodes-empty {
  font-size: 12px;
  color: #c0c4cc;
  padding: 12px;
  text-align: center;
  border: 1px dashed #dcdfe6;
  border-radius: 4px;
  margin-bottom: 12px;
}
.admin-expense-template-node-card {
  border: 1px solid #ebeef5;
  border-radius: 6px;
  padding: 10px 12px 4px;
  margin-bottom: 12px;
  background: #fafafa;
}
.admin-expense-template-node-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}
.admin-expense-template-node-card__step {
  font-size: 13px;
  font-weight: 600;
  color: #303133;
}
.admin-expense-template-node-card__actions .el-button {
  padding: 0 6px;
}
.admin-expense-template-node-inner-form.admin-expense-template-node-inner-form {
  margin: 0;
}
.admin-expense-template-node-inner-form >>> .el-form-item {
  margin-bottom: 10px;
}
.admin-expense-template-node-inner-form >>> .el-form-item:last-child {
  margin-bottom: 0;
}
/* 节点内标签统一 138px 宽且不换行，各步展示一致 */
.admin-expense-template-node-inner-form >>> .el-form-item__label {
  white-space: nowrap;
  line-height: 32px;
}
/* 不使用 collapse-tags：多选角色全部以标签展示，避免 Element 折叠成「+1」 */
.admin-expense-template-applicant-select >>> .el-select__tags {
  flex-wrap: wrap;
  align-items: flex-start;
  max-height: 140px;
  overflow-y: auto;
  width: 100%;
}
.admin-expense-template-applicant-select >>> .el-select__tags .el-tag {
  margin: 2px 6px 2px 0;
}
.admin-expense-template-applicant-select >>> .el-select .el-input__inner {
  height: auto !important;
  min-height: 32px;
}
.admin-w-full {
  width: 100%;
}
</style>
