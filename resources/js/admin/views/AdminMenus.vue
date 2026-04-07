<template>
  <div class="admin-menus-page">
    <el-card class="admin-mb-12 admin-page-filters">
      <div class="admin-form-row">
        <el-button size="small" :loading="loading" @click="fetchTree">刷新</el-button>
        <span class="admin-flex-spacer"></span>
        <el-button
          type="primary"
          size="small"
          :disabled="!$canPerm('perm.admin.api.menu_items.store')"
          title="无「接口：新增菜单」权限时不可操作"
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
              @click.stop="onMenuTreeNameClick(row)"
            >{{ adminEllipsisDisplay(row.name) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="权限标识" min-width="160">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(row.permission_code)">{{ adminEllipsisDisplay(row.permission_code || '—') }}</span>
          </template>
        </el-table-column>
        <el-table-column label="路径" min-width="140">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(row.path)">{{ adminEllipsisDisplay(row.path || '—') }}</span>
          </template>
        </el-table-column>
        <el-table-column label="图标" width="88" align="center">
          <template slot-scope="{ row }">
            <span class="admin-menu-icon-cell">
              <el-tooltip v-if="menuIconRaw(row.icon)" :content="menuIconRaw(row.icon)" placement="top">
                <i :class="menuIconClass(row.icon)" />
              </el-tooltip>
              <span v-else class="admin-menu-icon-empty">—</span>
            </span>
          </template>
        </el-table-column>
        <el-table-column label="排序" width="118" align="center">
          <template slot-scope="{ row }"><span class="admin-menu-sort-cell"><el-input v-model="row._sortInput" :id="'admin-menu-sort-' + row.id" :name="'menu_sort_' + row.id" size="mini" class="admin-menu-sort-input" :disabled="!!row._inlineSaving || !$canPerm('perm.admin.api.menu_items.patch')" @blur="commitSortRow(row)" @keyup.enter.native="($event) => $event.target.blur()" /></span></template>
        </el-table-column>
        <el-table-column label="组件" min-width="140">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(row.component)">{{ adminEllipsisDisplay(row.component || '—') }}</span>
          </template>
        </el-table-column>
        <el-table-column label="更新时间" width="158">
          <template slot-scope="{ row }">{{ formatTs(row.updated_at) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center" fixed="right">
          <template slot-scope="{ row }">
            <el-switch
              v-if="$canPerm('perm.admin.api.menu_items.patch')"
              class="admin-status-switch"
              :value="row.visible === 1"
              :disabled="!!row._inlineSaving"
              :active-color="'#13ce66'"
              :inactive-color="'#f56c6c'"
              @change="(on) => patchVisible(row, on ? 1 : 0)"
            />
            <span v-else>{{ row.visible === 1 ? '显示' : '隐藏' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template slot-scope="{ row }">
            <el-button v-if="$canPerm('perm.admin.api.menu_items.store')" size="mini" class="admin-mr-6" @click="openCreateChild(row)">子菜单</el-button>
            <el-button v-if="$canPerm('perm.admin.api.menu_items.update')" size="mini" @click="openEdit(row)">编辑</el-button>
            <span v-if="!$canPerm('perm.admin.api.menu_items.store') && !$canPerm('perm.admin.api.menu_items.update')">—</span>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog :title="formTitle" :visible.sync="formVisible" width="560px" @closed="onFormClosed">
      <el-form :model="form" label-width="100px" size="small">
        <el-form-item label="上级菜单">
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
          <el-input v-model="form.permission_code" maxlength="100" />
        </el-form-item>
        <el-form-item label="路径">
          <el-input v-model="form.path" maxlength="255" placeholder="如 /admin/users" />
        </el-form-item>
        <el-form-item label="图标">
          <el-input v-model="form.icon" maxlength="100" placeholder="如 el-icon-menu" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input id="admin-menu-form-sort" name="menu_sort" v-model="form.sort" class="admin-w-160" placeholder="0–999999" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.visible" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="组件">
          <el-input v-model="form.component" maxlength="255" placeholder="Blade 路由名，可为空" />
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
        permission_code: '',
        path: '',
        component: '',
        icon: '',
        sort: '0',
        visible: 1,
      },
    };
  },
  computed: {
    formTitle() {
      if (this.formMode === 'create') return '新增菜单';
      return '编辑菜单';
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
    async fetchTree(silent) {
      if (!silent) this.loading = true;
      try {
        const { data } = await window.axios.get('/admin/api/menu-items');
        this.treeData = data.data || [];
        this.syncSortInputs(this.treeData);
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '加载失败');
      } finally {
        if (!silent) this.loading = false;
        this.$nextTick(() => this.syncAdminTableMaxHeight());
      }
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
    onMenuTreeNameClick(row) {
      if (!row || !row.children || !row.children.length) return;
      const table = this.$refs.adminDataTable;
      if (table && typeof table.toggleRowExpansion === 'function') {
        table.toggleRowExpansion(row);
      }
    },
    menuIconRaw(icon) {
      return icon != null && String(icon).trim() ? String(icon).trim() : '';
    },
    menuIconClass(icon) {
      const s = this.menuIconRaw(icon);
      if (!s) return 'el-icon-menu';
      if (s.startsWith('el-icon-')) return s;
      return `el-icon-${s}`;
    },
    syncSortInputs(nodes) {
      (nodes || []).forEach((n) => {
        this.$set(n, '_sortInput', String(n.sort != null ? n.sort : 0));
        this.syncSortInputs(n.children);
      });
    },
    async commitSortRow(row) {
      const t = (row._sortInput != null ? String(row._sortInput) : '').trim();
      if (t === '') {
        this.$set(row, '_sortInput', String(row.sort != null ? row.sort : 0));
        return;
      }
      const n = parseInt(t, 10);
      if (Number.isNaN(n)) {
        this.$message.warning('请输入有效数字');
        this.$set(row, '_sortInput', String(row.sort != null ? row.sort : 0));
        return;
      }
      const clamped = Math.max(0, Math.min(999999, n));
      this.$set(row, '_sortInput', String(clamped));
      if (clamped === row.sort) return;
      await this.patchSort(row, clamped);
    },
    normalizeSortValue(v) {
      const n = parseInt(String(v != null ? v : '').trim(), 10);
      if (Number.isNaN(n)) return 0;
      return Math.max(0, Math.min(999999, n));
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
        permission_code: '',
        path: '',
        component: '',
        icon: '',
        sort: '0',
        visible: 1,
      };
      this.excludedParentIds = [];
      this.editingId = null;
    },
    openCreateRoot() {
      if (!this.$canPerm('perm.admin.api.menu_items.store')) {
        this.$message.warning('无新增权限，请在角色中分配「接口：新增菜单」');
        return;
      }
      this.formMode = 'create';
      this.resetForm();
      this.formVisible = true;
    },
    openCreateChild(row) {
      if (!this.$canPerm('perm.admin.api.menu_items.store')) {
        this.$message.warning('无新增权限，请在角色中分配「接口：新增菜单」');
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
        permission_code: node.permission_code || '',
        path: node.path || '',
        component: node.component || '',
        icon: node.icon || '',
        sort: String(node.sort != null ? node.sort : 0),
        visible: node.visible === 0 ? 0 : 1,
      };
      this.formVisible = true;
    },
    onFormClosed() {
      this.resetForm();
    },
    notifyMenusReload() {
      try {
        window.dispatchEvent(new CustomEvent('admin-reload-menus'));
      } catch (e) {}
    },
    async patchVisible(row, next) {
      const prev = row.visible;
      if (prev === next) return;
      this.$set(row, '_inlineSaving', true);
      try {
        await window.axios.patch(`/admin/api/menu-items/${row.id}`, { visible: next });
        row.visible = next;
        this.$message.success('状态已更新');
        this.notifyMenusReload();
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '更新失败');
      } finally {
        this.$set(row, '_inlineSaving', false);
      }
    },
    async patchSort(row, val) {
      if (val === null || val === undefined) return;
      const num = Number(val);
      if (Number.isNaN(num) || num === row.sort) return;
      const prev = row.sort;
      this.$set(row, '_inlineSaving', true);
      try {
        await window.axios.patch(`/admin/api/menu-items/${row.id}`, { sort: num });
        this.$message.success('排序已更新');
        this.notifyMenusReload();
        await this.fetchTree(true);
      } catch (e) {
        row.sort = prev;
        this.$set(row, '_sortInput', String(prev));
        this.$message.error(e?.response?.data?.message || '更新失败');
      } finally {
        this.$set(row, '_inlineSaving', false);
      }
    },
    async submitForm() {
      const name = (this.form.name || '').trim();
      const code = (this.form.permission_code || '').trim();
      if (!name || !code) {
        this.$message.warning('请填写名称与权限标识');
        return;
      }
      const payload = {
        name,
        permission_code: code,
        path: (this.form.path || '').trim() || null,
        component: (this.form.component || '').trim() || null,
        parent_id: this.form.parent_id === '' || this.form.parent_id == null ? null : this.form.parent_id,
        icon: (this.form.icon || '').trim() || null,
        sort: this.normalizeSortValue(this.form.sort),
        visible: this.form.visible === 0 ? 0 : 1,
      };
      this.formSubmitting = true;
      try {
        if (this.formMode === 'create') {
          await window.axios.post('/admin/api/menu-items', payload);
          this.$message.success('菜单新增成功');
        } else {
          await window.axios.put(`/admin/api/menu-items/${this.editingId}`, payload);
          this.$message.success('菜单已更新');
        }
        this.formVisible = false;
        await this.fetchTree();
        this.notifyMenusReload();
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
