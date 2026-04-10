<template>
  <div>
    <el-card class="admin-mb-12 admin-page-filters">
      <div class="admin-form-row">
        <el-button size="small" :loading="loading" @click="fetchList">刷新</el-button>
        <span class="admin-flex-spacer"></span>
        <el-button v-if="$canPerm('perm.admin.api.roles.store')" type="primary" size="small" @click="openCreate">新增角色</el-button>
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
        <el-table-column prop="id" label="ID" width="70" fixed="left" />
        <el-table-column label="角色名称" min-width="140" fixed="left">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(row.name)">{{ adminEllipsisDisplay(row.name) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="角色标识" min-width="140">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(row.code)">{{ adminEllipsisDisplay(row.code) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="数据范围" width="110" align="center">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(dataScopeLabel(row.data_scope))">{{ adminEllipsisDisplay(dataScopeLabel(row.data_scope)) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="110" align="center">
          <template slot-scope="{ row }">
            <el-tag v-if="row.is_system === 1" size="mini" type="danger">系统</el-tag>
            <el-tag v-else size="mini" type="info">自定义</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="更新时间" min-width="170">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(formatTs(row.updated_at))">{{ adminEllipsisDisplay(formatTs(row.updated_at)) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right">
          <template slot-scope="{ row }">
            <template v-if="row.code === 'super_admin'">
              <span class="admin-role-system-hint" title="系统内置超级管理员：拥有全部权限，不可分配">拥有全部权限</span>
            </template>
            <template v-else>
              <el-button
                v-if="canEditRolePermissions(row)"
                size="mini"
                class="admin-mr-6"
                @click="openAssignPermissions(row)"
              >分配权限</el-button>
              <el-button
                v-else-if="$canPerm('perm.admin.api.roles.permissions.index')"
                size="mini"
                class="admin-mr-6"
                @click="openAssignPermissions(row)"
              >查看权限</el-button>
              <el-button v-if="$canPerm('perm.admin.api.roles.update')" size="mini" @click="openEdit(row)">编辑</el-button>
              <span
                v-if="
                  !canEditRolePermissions(row) &&
                  !$canPerm('perm.admin.api.roles.permissions.index') &&
                  !$canPerm('perm.admin.api.roles.update')
                "
              >—</span>
            </template>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog :title="formMode === 'create' ? '新增角色' : '编辑角色'" :visible.sync="formVisible" width="480px" @closed="onFormClosed">
      <el-form :model="form" label-width="100px" size="small">
        <el-form-item label="角色名称" required>
          <el-input v-model="form.name" maxlength="50" />
        </el-form-item>
        <el-form-item label="角色标识" required>
          <el-input
            v-model="form.code"
            maxlength="50"
            placeholder="唯一，如 editor"
            :disabled="formMode === 'edit' && editingIsSystem"
          />
          <div v-if="formMode === 'edit' && editingIsSystem" class="admin-form-hint">系统角色的标识不可修改</div>
        </el-form-item>
        <el-form-item label="数据范围" required>
          <el-select v-model="form.data_scope" placeholder="请选择" class="admin-w-full">
            <el-option v-for="o in dataScopeOptions" :key="o.value" :label="o.label" :value="o.value" />
          </el-select>
        </el-form-item>
      </el-form>
      <span slot="footer" class="admin-dialog-footer">
        <el-button size="small" @click="formVisible = false">取消</el-button>
        <el-button size="small" type="primary" :loading="formSubmitting" @click="submitForm">保存</el-button>
      </span>
    </el-dialog>

    <el-dialog
      :title="assignTitle"
      :visible.sync="assignVisible"
      width="800px"
      top="4vh"
      custom-class="admin-role-perm-dialog"
      @closed="onAssignClosed"
    >
      <p v-if="assignReadOnly" class="admin-role-perm-readonly-hint">当前为只读：系统角色或您仅有查看权限时不可修改勾选。</p>
      <div v-loading="assignLoading" class="admin-role-perm-dialog__body">
        <el-tree
          v-if="!assignLoading && permTreeData.length"
          ref="permTree"
          class="admin-role-perm-tree"
          :data="permTreeData"
          :props="permTreeProps"
          node-key="id"
          show-checkbox
          default-expand-all
          :expand-on-click-node="false"
        >
          <span slot-scope="{ node, data }" class="admin-role-perm-node">
            <span class="admin-role-perm-node__main">
              <span class="admin-role-perm-node__name" :title="data.name">{{ data.name }}</span>
              <code v-if="data.code" class="admin-role-perm-node__code" :title="data.code">{{ data.code }}</code>
            </span>
            <span v-if="data.type" class="admin-role-perm-node__meta">
              <el-tag size="mini" class="admin-role-perm-node__tag">{{ permTypeLabel(data.type) }}</el-tag>
            </span>
          </span>
        </el-tree>
        <div v-else-if="!assignLoading && !permTreeData.length" class="admin-role-perm-empty">暂无权限数据，请先在权限管理中维护。</div>
      </div>
      <span slot="footer" class="admin-dialog-footer">
        <el-button size="small" @click="assignVisible = false">{{ assignReadOnly ? '关闭' : '取消' }}</el-button>
        <el-button
          v-if="!assignReadOnly"
          size="small"
          type="primary"
          :loading="assignSaving"
          @click="submitAssignPermissions"
        >保存</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import adminTableFixedHeader from '../mixins/adminTableFixedHeader';

const DATA_SCOPE_LABELS = {
  self: '仅本人',
  all: '全部',
  dept: '本部门',
};

/**
 * 父子联动 el-tree 下还原勾选：接口常同时返回「父菜单 id + 部分子 id」（如合并菜单祖先），
 * 若直接把父 id 交给 setCheckedKeys，会表现为整棵子树全选。
 * 规则：非叶节点在 assigned 中且其下无一叶子在 assigned → 视为勾选整棵子树（展开为全部叶子 id）；
 * 非叶节点在 assigned 且仅有部分叶子在 assigned → 只传这些叶子，父级由组件显示半选；
 * 非叶节点在 assigned 且全部叶子在 assigned → 传全部叶子即可，父级联动为全选。
 *
 * @param {Array<number|string>} assignedIds
 * @param {Array<object>} nodes
 * @returns {number[]}
 */
function permAssignedKeysForLinkedTree(assignedIds, nodes) {
  const assigned = new Set(
    (assignedIds || []).map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0),
  );
  const out = new Set();

  function collectLeafIds(node) {
    const ch = node.children;
    if (!Array.isArray(ch) || ch.length === 0) {
      return [Number(node.id)];
    }
    return ch.flatMap(collectLeafIds);
  }

  function walk(node) {
    const id = Number(node.id);
    if (!Number.isFinite(id) || id <= 0) {
      return;
    }
    const children = Array.isArray(node.children) ? node.children : [];

    if (children.length === 0) {
      if (assigned.has(id)) {
        out.add(id);
      }
      return;
    }

    const leafIds = collectLeafIds(node);
    const leavesInAssigned = leafIds.filter((lid) => assigned.has(lid));
    const hasNode = assigned.has(id);

    if (hasNode) {
      if (leavesInAssigned.length === 0) {
        leafIds.forEach((lid) => out.add(lid));
      } else if (leavesInAssigned.length === leafIds.length) {
        leafIds.forEach((lid) => out.add(lid));
      } else {
        leavesInAssigned.forEach((lid) => out.add(lid));
      }
      return;
    }

    children.forEach(walk);
  }

  (nodes || []).forEach(walk);
  return [...out];
}

export default {
  mixins: [adminTableFixedHeader],
  data() {
    return {
      loading: false,
      rows: [],
      formVisible: false,
      formSubmitting: false,
      formMode: 'create',
      editingId: null,
      editingIsSystem: false,
      form: {
        name: '',
        code: '',
        data_scope: 'self',
      },
      dataScopeOptions: [
        { value: 'self', label: '仅本人' },
        { value: 'all', label: '全部' },
        { value: 'dept', label: '本部门' },
      ],
      assignVisible: false,
      assignLoading: false,
      assignSaving: false,
      assignRoleId: null,
      assignRoleName: '',
      permTreeData: [],
      permTreeProps: { children: 'children', label: 'name', disabled: 'disabled' },
      assignReadOnly: false,
    };
  },
  computed: {
    assignTitle() {
      const n = (this.assignRoleName || '').trim();
      const t = this.assignReadOnly ? '查看权限' : '分配权限';
      return n ? `${t} — ${n}` : t;
    },
  },
  created() {
    this.fetchList();
  },
  methods: {
    permTypeLabel(t) {
      if (t === 'menu') return '菜单';
      if (t === 'api') return '接口';
      if (t === 'button') return '按钮';
      return t || '';
    },
    dataScopeLabel(v) {
      return DATA_SCOPE_LABELS[v] || v || '—';
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
      this.loading = true;
      try {
        const { data } = await window.axios.get('/admin/api/roles');
        this.rows = data.data || [];
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '加载失败');
      } finally {
        this.loading = false;
        this.$nextTick(() => this.syncAdminTableMaxHeight());
      }
    },
    resetForm() {
      this.form = { name: '', code: '', data_scope: 'self' };
      this.editingId = null;
      this.editingIsSystem = false;
    },
    openCreate() {
      this.formMode = 'create';
      this.resetForm();
      this.formVisible = true;
    },
    openEdit(row) {
      this.formMode = 'edit';
      this.editingId = row.id;
      this.editingIsSystem = row.is_system === 1;
      this.form = {
        name: row.name || '',
        code: row.code || '',
        data_scope: row.data_scope && DATA_SCOPE_LABELS[row.data_scope] ? row.data_scope : 'self',
      };
      this.formVisible = true;
    },
    onFormClosed() {
      this.resetForm();
    },
    onAssignClosed() {
      this.assignRoleId = null;
      this.assignRoleName = '';
      this.permTreeData = [];
      this.assignLoading = false;
      this.assignSaving = false;
      this.assignReadOnly = false;
    },
    canEditRolePermissions(row) {
      if (!row || row.code === 'super_admin') {
        return false;
      }
      return this.$canPerm('perm.admin.api.roles.permissions.sync');
    },
    applyPermTreeDisabledState() {
      const ro = this.assignReadOnly;
      const walk = (nodes) => {
        if (!Array.isArray(nodes)) {
          return;
        }
        nodes.forEach((n) => {
          if (ro) {
            this.$set(n, 'disabled', true);
          } else if (Object.prototype.hasOwnProperty.call(n, 'disabled')) {
            this.$delete(n, 'disabled');
          }
          walk(n.children);
        });
      };
      walk(this.permTreeData);
    },
    async openAssignPermissions(row) {
      this.assignRoleId = row.id;
      this.assignRoleName = row.name || '';
      this.assignReadOnly = row.code === 'super_admin' || !this.$canPerm('perm.admin.api.roles.permissions.sync');
      this.assignVisible = true;
      this.assignLoading = true;
      this.permTreeData = [];
      let keys = [];
      try {
        const [treeRes, idsRes] = await Promise.all([
          window.axios.get('/admin/api/permissions'),
          window.axios.get(`/admin/api/roles/${row.id}/permissions`),
        ]);
        this.permTreeData = treeRes.data?.data || [];
        this.applyPermTreeDisabledState();
        keys = idsRes.data?.permission_ids || [];
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '加载权限失败');
        this.assignVisible = false;
        this.assignLoading = false;
        return;
      }
      this.assignLoading = false;
      await this.$nextTick();
      const tree = this.$refs.permTree;
      if (tree && typeof tree.setCheckedKeys === 'function') {
        const forTree = permAssignedKeysForLinkedTree(keys, this.permTreeData);
        tree.setCheckedKeys(forTree);
      }
    },
    async submitAssignPermissions() {
      if (this.assignReadOnly) {
        return;
      }
      const tree = this.$refs.permTree;
      if (!tree || typeof tree.getCheckedKeys !== 'function') {
        this.$message.warning('权限树未就绪');
        return;
      }
      const checked = tree.getCheckedKeys();
      const permission_ids = [...new Set(checked.map((id) => Number(id)))];
      this.assignSaving = true;
      try {
        await window.axios.put(`/admin/api/roles/${this.assignRoleId}/permissions`, { permission_ids });
        this.$message.success('角色权限已保存');
        this.assignVisible = false;
        await this.fetchList();
      } catch (e) {
        const msg =
          e?.response?.data?.message ||
          (e?.response?.data?.errors ? Object.values(e.response.data.errors)[0]?.[0] : null) ||
          '保存失败';
        this.$message.error(msg);
      } finally {
        this.assignSaving = false;
      }
    },
    async submitForm() {
      const name = (this.form.name || '').trim();
      const code = (this.form.code || '').trim();
      if (!name) {
        this.$message.warning('请填写角色名称');
        return;
      }
      if (this.formMode === 'create' && !code) {
        this.$message.warning('请填写角色标识');
        return;
      }
      if (!this.form.data_scope) {
        this.$message.warning('请选择数据范围');
        return;
      }
      this.formSubmitting = true;
      try {
        if (this.formMode === 'create') {
          await window.axios.post('/admin/api/roles', {
            name,
            code,
            data_scope: this.form.data_scope,
          });
          this.$message.success('角色新增成功');
        } else {
          const payload = this.editingIsSystem
            ? { name, data_scope: this.form.data_scope }
            : { name, code, data_scope: this.form.data_scope };
          await window.axios.put(`/admin/api/roles/${this.editingId}`, payload);
          this.$message.success('角色已更新');
        }
        this.formVisible = false;
        await this.fetchList();
      } catch (e) {
        const msg =
          e?.response?.data?.message ||
          (e?.response?.data?.errors ? Object.values(e.response.data.errors)[0]?.[0] : null) ||
          '保存失败';
        this.$message.error(msg);
      } finally {
        this.formSubmitting = false;
      }
    },
  },
};
</script>
