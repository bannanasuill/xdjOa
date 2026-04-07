<template>
  <div class="admin-permissions-page">
    <el-card class="admin-mb-12 admin-page-filters">
      <div class="admin-form-row">
        <el-button size="small" :loading="loading" @click="fetchTree">刷新</el-button>
        <span class="admin-flex-spacer"></span>
        <el-button
          type="primary"
          size="small"
          :disabled="!$canPerm('perm.admin.api.permissions.store')"
          title="无「接口：权限新增」权限时不可操作"
          @click="openCreateRoot"
        >
          新增顶级
        </el-button>
      </div>
    </el-card>

    <el-card>
      <el-table
        ref="adminDataTable"
        class="admin-data-table"
        :data="treeData"
        :max-height="adminTableMaxHeight"
        row-key="id"
        size="mini"
        v-loading="loading"
        :tree-props="{ children: 'children' }"
        default-expand-all
      >
        <el-table-column label="名称" min-width="200" fixed="left">
          <template slot-scope="{ row }">
            <span
              class="admin-menu-tree-name"
              :class="{ 'admin-menu-tree-name--expandable': row.children && row.children.length }"
              :title="adminEllipsisTitle(row.name)"
              @click.stop="onTreeNameClick(row)"
            >{{ adminEllipsisDisplay(row.name) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="权限标识" min-width="160">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(row.code)">{{ adminEllipsisDisplay(row.code || '—') }}</span>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="88" align="center">
          <template slot-scope="{ row }">{{ typeLabel(row.type) }}</template>
        </el-table-column>
        <el-table-column label="路径" min-width="140">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(row.path)">{{ adminEllipsisDisplay(row.path || '—') }}</span>
          </template>
        </el-table-column>
        <el-table-column label="更新时间" width="158">
          <template slot-scope="{ row }">{{ formatTs(row.updated_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template slot-scope="{ row }">
            <el-button v-if="$canPerm('perm.admin.api.permissions.store')" size="mini" class="admin-mr-6" @click="openCreateChild(row)">子权限</el-button>
            <el-button v-if="$canPerm('perm.admin.api.permissions.update')" size="mini" @click="openEdit(row)">编辑</el-button>
            <span v-if="!$canPerm('perm.admin.api.permissions.store') && !$canPerm('perm.admin.api.permissions.update')">—</span>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog :title="formTitle" :visible.sync="formVisible" width="520px" @closed="onFormClosed">
      <el-form :model="form" label-width="100px" size="small">
        <el-form-item label="上级权限">
          <el-select v-model="form.parent_id" clearable placeholder="无（顶级）" class="admin-w-full">
            <el-option
              v-for="opt in parentOptions"
              :key="opt.id"
              :label="opt.label"
              :value="opt.id"
              :disabled="opt.disabled"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="名称" required>
          <el-input v-model="form.name" maxlength="100" />
        </el-form-item>
        <el-form-item label="权限标识" required>
          <el-input v-model="form.code" maxlength="100" placeholder="唯一，如 perm.user.list" />
        </el-form-item>
        <el-form-item label="类型" required>
          <el-select v-model="form.type" placeholder="请选择" class="admin-w-full">
            <el-option label="菜单" value="menu" />
            <el-option label="按钮" value="button" />
            <el-option label="接口" value="api" />
          </el-select>
        </el-form-item>
        <el-form-item label="路径">
          <el-input v-model="form.path" maxlength="255" placeholder="路由或接口路径，可为空" />
        </el-form-item>
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

const TYPE_LABELS = { menu: '菜单', button: '按钮', api: '接口' };

export default {
  mixins: [adminTableFixedHeader],
  data() {
    return {
      loading: false,
      treeData: [],
      formVisible: false,
      formSubmitting: false,
      formMode: 'create',
      editingId: null,
      excludedParentIds: [],
      form: {
        parent_id: '',
        name: '',
        code: '',
        type: 'menu',
        path: '',
      },
    };
  },
  computed: {
    formTitle() {
      return this.formMode === 'create' ? '新增权限' : '编辑权限';
    },
    parentOptions() {
      const base = [{ id: '', label: '无（顶级）', disabled: false }];
      const flat = this.flattenForParent(this.treeData, 0, []);
      const ex = new Set(this.excludedParentIds || []);
      flat.forEach((item) => {
        base.push({
          id: item.id,
          label: item.label,
          disabled: ex.has(item.id),
        });
      });
      return base;
    },
  },
  created() {
    this.fetchTree();
  },
  methods: {
    typeLabel(t) {
      return TYPE_LABELS[t] || t || '—';
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
    async fetchTree(silent) {
      if (!silent) this.loading = true;
      try {
        const { data } = await window.axios.get('/admin/api/permissions');
        this.treeData = data.data || [];
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '加载失败');
      } finally {
        if (!silent) this.loading = false;
        this.$nextTick(() => this.syncAdminTableMaxHeight());
      }
    },
    onTreeNameClick(row) {
      if (!row || !row.children || !row.children.length) return;
      const table = this.$refs.adminDataTable;
      if (table && typeof table.toggleRowExpansion === 'function') {
        table.toggleRowExpansion(row);
      }
    },
    flattenForParent(nodes, depth, acc) {
      const pad = '　'.repeat(depth);
      (nodes || []).forEach((n) => {
        acc.push({ id: n.id, label: `${pad}${n.name}` });
        if (n.children && n.children.length) {
          this.flattenForParent(n.children, depth + 1, acc);
        }
      });
      return acc;
    },
    collectDescendantIds(node) {
      let ids = [node.id];
      (node.children || []).forEach((c) => {
        ids = ids.concat(this.collectDescendantIds(c));
      });
      return ids;
    },
    findNodeById(nodes, id) {
      for (const n of nodes || []) {
        if (n.id === id) return n;
        const found = this.findNodeById(n.children, id);
        if (found) return found;
      }
      return null;
    },
    resetForm() {
      this.form = {
        parent_id: '',
        name: '',
        code: '',
        type: 'menu',
        path: '',
      };
      this.excludedParentIds = [];
      this.editingId = null;
    },
    openCreateRoot() {
      if (!this.$canPerm('perm.admin.api.permissions.store')) {
        this.$message.warning('无新增权限，请在角色中分配「接口：权限新增」');
        return;
      }
      this.formMode = 'create';
      this.resetForm();
      this.formVisible = true;
    },
    openCreateChild(row) {
      if (!this.$canPerm('perm.admin.api.permissions.store')) {
        this.$message.warning('无新增权限，请在角色中分配「接口：权限新增」');
        return;
      }
      this.formMode = 'create';
      this.resetForm();
      this.form.parent_id = row.id;
      this.formVisible = true;
    },
    openEdit(row) {
      const node = this.findNodeById(this.treeData, row.id) || row;
      this.formMode = 'edit';
      this.editingId = row.id;
      this.excludedParentIds = this.collectDescendantIds(node);
      this.form = {
        parent_id: node.parent_id != null && node.parent_id !== 0 ? node.parent_id : '',
        name: node.name || '',
        code: node.code || '',
        type: node.type && TYPE_LABELS[node.type] ? node.type : 'menu',
        path: node.path || '',
      };
      this.formVisible = true;
    },
    onFormClosed() {
      this.resetForm();
    },
    async submitForm() {
      const name = (this.form.name || '').trim();
      const code = (this.form.code || '').trim();
      if (!name || !code) {
        this.$message.warning('请填写名称与权限标识');
        return;
      }
      if (!this.form.type) {
        this.$message.warning('请选择类型');
        return;
      }
      const payload = {
        name,
        code,
        type: this.form.type,
        path: (this.form.path || '').trim() || null,
        parent_id: this.form.parent_id === '' || this.form.parent_id == null ? null : this.form.parent_id,
      };
      this.formSubmitting = true;
      try {
        if (this.formMode === 'create') {
          await window.axios.post('/admin/api/permissions', payload);
          this.$message.success('权限新增成功');
        } else {
          await window.axios.put(`/admin/api/permissions/${this.editingId}`, payload);
          this.$message.success('权限已更新');
        }
        this.formVisible = false;
        await this.fetchTree();
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
