<template>
  <div class="admin-departments-page">
    <el-card class="admin-mb-12 admin-page-filters">
      <div class="admin-form-row">
        <el-button size="small" :loading="refreshLoading" @click="refreshAll">刷新</el-button>
        <el-radio-group
          v-if="$canPerm('perm.admin.api.departments.index') || $canPerm('perm.admin.api.positions.index')"
          v-model="treeViewMode"
          size="small"
          class="admin-dept-view-mode"
        >
          <el-radio-button label="departments" :disabled="!$canPerm('perm.admin.api.departments.index')">
            部门
          </el-radio-button>
          <el-radio-button label="positions" :disabled="!$canPerm('perm.admin.api.positions.index')">
            职务
          </el-radio-button>
        </el-radio-group>
        <span class="admin-flex-spacer"></span>
        <el-button
          v-if="$canPerm('perm.admin.api.departments.store')"
          type="primary"
          size="small"
          :disabled="!$canPerm('perm.admin.api.departments.store')"
          title="无「接口：部门新增」权限时不可操作"
          @click="openCreate"
        >
          新增顶级部门
        </el-button>
      </div>
    </el-card>

    <el-card v-if="$canPerm('perm.admin.api.departments.index') || $canPerm('perm.admin.api.positions.index')">
      <el-table
        ref="adminDataTable"
        class="admin-data-table"
        :data="unifiedTree"
        :max-height="adminTableMaxHeight"
        row-key="rowKey"
        size="mini"
        v-loading="loading || positionLoading"
        :tree-props="{ children: 'children' }"
        default-expand-all
      >
        <el-table-column label="名称" :min-width="showNameInlineEdit ? 220 : 200" fixed="left">
          <template slot-scope="{ row }">
            <div v-if="row.rowType === 'position'" class="admin-dept-pos-name-stack">
              <span
                class="admin-dept-unified-position-name"
                :title="adminEllipsisTitle(row.name)"
              >{{ adminEllipsisDisplay(row.name) }}</span>
              <span
                v-if="row.code"
                class="admin-dept-pos-name-stack__code"
                :title="String(row.code)"
              >{{ adminEllipsisDisplay(String(row.code)) }}</span>
            </div>
            <el-input
              v-else-if="showNameInlineEdit"
              v-model="row.name"
              class="admin-dept-name-inline"
              size="mini"
              :disabled="nameBusyId === row.id"
              @focus="onDeptNameFocus(row)"
              @blur="onDeptNameBlur(row)"
              @keyup.native.enter="onDeptNameEnter"
            />
            <span
              v-else
              class="admin-menu-tree-name"
              :class="{ 'admin-menu-tree-name--expandable': row.children && row.children.length }"
              :title="adminEllipsisTitle(row.name)"
              @click.stop="onTreeNameClick(row)"
            >{{ adminEllipsisDisplay(row.name) }}</span>
          </template>
        </el-table-column>
        <el-table-column v-if="showDepartmentMetaColumns" label="类型" width="88" align="center">
          <template slot-scope="{ row }">
            <span v-if="row.rowType !== 'position'">{{ typeLabel(row.type) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="负责人" :min-width="showLeaderInlineAssign ? 200 : 138">
          <template slot-scope="{ row }">
            <template v-if="row.rowType === 'position'">
              <span :title="adminEllipsisTitle(positionDeptLabel(row))">{{ adminEllipsisDisplay(positionDeptLabel(row)) }}</span>
            </template>
            <template v-else-if="showLeaderInlineAssign">
              <el-select
                :value="leaderSelectValue(row)"
                class="admin-dept-leader-inline"
                filterable
                clearable
                placeholder="选择负责人"
                size="mini"
                :disabled="leaderBusyId === row.id"
                popper-class="admin-dept-leader-inline-dropdown"
                @change="(v) => onLeaderCommit(row, v)"
              >
                <el-option v-for="u in leaderOptions" :key="u.id" :label="u.label" :value="u.id" />
              </el-select>
            </template>
            <template v-else>
              <span :title="adminEllipsisTitle(row.leader_label)">{{ adminEllipsisDisplay(row.leader_label || '—') }}</span>
            </template>
          </template>
        </el-table-column>
        <el-table-column v-if="showDepartmentMetaColumns" label="排序" width="118" align="center">
          <template slot-scope="{ row }">
            <template v-if="row.rowType !== 'position' && $canPerm('perm.admin.api.departments.update')">
              <el-input-number
                v-model="row.sort"
                :min="0"
                :controls="false"
                size="mini"
                style="width: 96px"
                :disabled="sortBusyId === row.id"
                @change="(v) => onSortCommit(row, v)"
              />
            </template>
            <span v-else-if="row.rowType !== 'position'">{{ row.sort }}</span>
          </template>
        </el-table-column>
        <el-table-column v-if="showPositionLevelColumn" label="职级" width="72" align="center">
          <template slot-scope="{ row }">
            <span v-if="row.rowType === 'position'">{{ row.level }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center" fixed="right">
          <template slot-scope="{ row }">
            <template v-if="row.rowType === 'position'">
              <el-switch
                v-if="$canPerm('perm.admin.api.positions.status')"
                class="admin-status-switch"
                :value="row.status === 1"
                :active-color="'#13ce66'"
                :inactive-color="'#f56c6c'"
                :disabled="posStatusBusyId === row.id"
                @change="(on) => patchPosStatus(row, on ? 1 : 0)"
              />
              <span v-else>{{ row.status === 1 ? '启用' : '禁用' }}</span>
            </template>
            <template v-else>
              <el-switch
                v-if="$canPerm('perm.admin.api.departments.status')"
                class="admin-status-switch"
                :value="row.status === 1"
                :active-color="'#13ce66'"
                :inactive-color="'#f56c6c'"
                :disabled="statusBusyId === row.id"
                @change="(on) => patchStatus(row, on ? 1 : 0)"
              />
              <span v-else>{{ row.status === 1 ? '启用' : '禁用' }}</span>
            </template>
          </template>
        </el-table-column>
        <el-table-column label="操作" min-width="312" width="312" fixed="right" align="left">
          <template slot-scope="{ row }">
            <template v-if="row.rowType === 'position'">
              <div class="admin-dept-actions">
                <el-button v-if="$canPerm('perm.admin.api.positions.update')" size="mini" @click="openPosEdit(row)">
                  编辑
                </el-button>
                <el-button
                  v-if="$canPerm('perm.admin.api.positions.destroy')"
                  type="danger"
                  size="mini"
                  :disabled="posDeleteBusyId === row.id"
                  @click="confirmDeletePosition(row)"
                >删除</el-button>
                <span
                  v-if="!$canPerm('perm.admin.api.positions.update') && !$canPerm('perm.admin.api.positions.destroy')"
                >—</span>
              </div>
            </template>
            <template v-else>
              <div class="admin-dept-actions">
                <el-button
                  v-if="$canPerm('perm.admin.api.positions.store')"
                  size="mini"
                  @click="openPosCreateForDept(row)"
                >加职务</el-button>
                <el-button
                  v-if="$canPerm('perm.admin.api.departments.store')"
                  size="mini"
                  @click="openCreateChild(row)"
                >加子部门</el-button>
                <el-button v-if="$canPerm('perm.admin.api.departments.update')" size="mini" @click="openEdit(row)">
                  编辑
                </el-button>
                <el-button
                  v-if="$canPerm('perm.admin.api.departments.destroy')"
                  type="danger"
                  size="mini"
                  :disabled="deptDeleteBusyId === row.id"
                  @click="confirmDeleteDepartment(row)"
                >删除</el-button>
                <span
                  v-if="
                    !$canPerm('perm.admin.api.positions.store') &&
                    !$canPerm('perm.admin.api.departments.store') &&
                    !$canPerm('perm.admin.api.departments.update') &&
                    !$canPerm('perm.admin.api.departments.destroy')
                  "
                >—</span>
              </div>
            </template>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog :title="formMode === 'create' ? '新增部门' : '编辑部门'" :visible.sync="formVisible" width="520px" @closed="onFormClosed">
      <el-form :model="form" label-width="100px" size="small">
        <el-form-item label="部门名称" required>
          <el-input v-model="form.name" maxlength="100" placeholder="部门名称" />
        </el-form-item>
        <el-form-item label="上级部门">
          <el-select v-model="form.parent_id" filterable clearable placeholder="顶级（不选则为顶级）" style="width: 100%">
            <el-option :key="0" label="顶级" :value="0" />
            <el-option
              v-for="opt in parentSelectOptions"
              :key="opt.id"
              :label="opt.label"
              :value="opt.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="负责人">
          <el-select
            v-model="form.leader_id"
            filterable
            clearable
            placeholder="可选"
            style="width: 100%"
          >
            <el-option v-for="u in leaderOptions" :key="u.id" :label="u.label" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" :controls="false" style="width: 100%" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.statusOn" active-text="启用" inactive-text="禁用" />
        </el-form-item>
      </el-form>
      <span slot="footer" class="admin-dialog-footer">
        <el-button size="small" @click="formVisible = false">取消</el-button>
        <el-button size="small" type="primary" :loading="formSubmitting" @click="submitForm">保存</el-button>
      </span>
    </el-dialog>

    <el-dialog :title="posFormMode === 'create' ? '新增职务' : '编辑职务'" :visible.sync="posFormVisible" width="520px" @closed="onPosFormClosed">
      <el-form :model="posForm" label-width="100px" size="small">
        <el-form-item label="职务名称" required>
          <el-input v-model="posForm.name" maxlength="100" placeholder="职位名称" />
        </el-form-item>
        <el-form-item label="职位标识" required>
          <el-input
            v-model="posForm.code"
            maxlength="50"
            placeholder="唯一编码，字母数字下划线与中划线"
            :disabled="posFormMode === 'edit'"
          />
          <div v-if="posFormMode === 'edit'" class="admin-form-hint">标识创建后不可修改</div>
        </el-form-item>
        <el-form-item label="所属部门" required>
          <el-select v-model="posForm.dept_id" filterable placeholder="请选择部门" style="width: 100%">
            <el-option v-for="d in positionDeptOptions" :key="d.id" :label="d.label" :value="d.id" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="posFormMode === 'create'" label="已有职务">
          <template v-if="!posForm.dept_id">
            <span class="admin-form-hint">请选择所属部门后，展示该部门下已有职务。</span>
          </template>
          <template v-else-if="!posFormSiblingPositions.length">
            <span class="admin-form-hint">该部门下暂无职务。</span>
          </template>
          <ul v-else class="admin-pos-existing-list">
            <li v-for="p in posFormSiblingPositions" :key="p.id" class="admin-pos-existing-item">
              <span class="admin-pos-existing-name" :title="p.name">{{ adminEllipsisDisplay(p.name) }}</span>
              <code v-if="p.code" class="admin-pos-existing-code">{{ p.code }}</code>
              <span class="admin-pos-existing-meta">职级 {{ p.level != null ? p.level : '—' }}</span>
              <el-tag size="mini" :type="p.status === 1 ? 'success' : 'info'">{{
                p.status === 1 ? '启用' : '禁用'
              }}</el-tag>
            </li>
          </ul>
        </el-form-item>
        <el-form-item label="职级">
          <el-input-number v-model="posForm.level" :min="0" :controls="false" style="width: 100%" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="posForm.statusOn" active-text="启用" inactive-text="禁用" />
        </el-form-item>
      </el-form>
      <span slot="footer" class="admin-dialog-footer">
        <el-button size="small" @click="posFormVisible = false">取消</el-button>
        <el-button size="small" type="primary" :loading="posFormSubmitting" @click="submitPosForm">保存</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import adminTableFixedHeader from '../mixins/adminTableFixedHeader';
import { DEPT_TYPE_LABELS } from '../deptTypeLabels';

export default {
  name: 'AdminDepartments',
  mixins: [adminTableFixedHeader],
  data() {
    return {
      loading: false,
      rows: [],
      leaderOptions: [],
      formVisible: false,
      formSubmitting: false,
      formMode: 'create',
      editingId: null,
      form: {
        name: '',
        parent_id: 0,
        leader_id: null,
        sort: 0,
        statusOn: true,
      },
      statusBusyId: null,
      sortBusyId: null,
      sortBaseline: {},

      positionLoading: false,
      positionRowsAll: [],
      /** departments | positions */
      treeViewMode: 'departments',
      positionDeptOptions: [],
      posFormVisible: false,
      posFormSubmitting: false,
      posFormMode: 'create',
      posEditingId: null,
      posForm: {
        name: '',
        code: '',
        dept_id: null,
        level: 1,
        statusOn: true,
      },
      posStatusBusyId: null,
      leaderBusyId: null,
      nameBusyId: null,
      deptNameEditSnapshot: null,
      deptDeleteBusyId: null,
      posDeleteBusyId: null,
    };
  },
  computed: {
    refreshLoading() {
      return this.loading || this.positionLoading;
    },
    unifiedTree() {
      const canDept = this.$canPerm('perm.admin.api.departments.index');
      const canPos = this.$canPerm('perm.admin.api.positions.index');
      if (this.treeViewMode === 'departments' && canDept) {
        return this.buildDeptOnlyTree();
      }
      if (this.treeViewMode === 'positions' && canPos) {
        return this.buildPositionsFlatTree();
      }
      if (canDept) {
        return this.buildDeptOnlyTree();
      }
      if (canPos) {
        return this.buildPositionsFlatTree();
      }
      return [];
    },
    showDepartmentMetaColumns() {
      return this.treeViewMode === 'departments' && this.$canPerm('perm.admin.api.departments.index');
    },
    showPositionLevelColumn() {
      return this.treeViewMode === 'positions' && this.$canPerm('perm.admin.api.positions.index');
    },
    /** 部门列表内联分配负责人（需更新部门 + 负责人候选接口） */
    showLeaderInlineAssign() {
      return (
        this.treeViewMode === 'departments' &&
        this.$canPerm('perm.admin.api.departments.update') &&
        this.$canPerm('perm.admin.api.departments.leader_options')
      );
    },
    /** 部门列表内联改名称（仅需更新部门权限） */
    showNameInlineEdit() {
      return this.treeViewMode === 'departments' && this.$canPerm('perm.admin.api.departments.update');
    },
    /** 新增职务弹窗：当前所选部门下已有职务（按职级、id 排序） */
    posFormSiblingPositions() {
      if (this.posFormMode !== 'create') {
        return [];
      }
      const did = this.posForm.dept_id != null ? Number(this.posForm.dept_id) : null;
      if (did == null || !Number.isFinite(did) || did < 1) {
        return [];
      }
      const list = (this.positionRowsAll || []).filter((p) => Number(p.dept_id) === did);
      return [...list].sort(
        (a, b) =>
          (Number(b.level) || 0) - (Number(a.level) || 0) ||
          (Number(b.id) || 0) - (Number(a.id) || 0)
      );
    },
    parentSelectOptions() {
      const ex = new Set();
      if (this.formMode === 'edit' && this.editingId != null) {
        ex.add(this.editingId);
        const walk = (pid) => {
          (this.rows || []).filter((r) => r.parent_id === pid).forEach((r) => {
            ex.add(r.id);
            walk(r.id);
          });
        };
        walk(this.editingId);
      }
      return (this.rows || [])
        .filter((r) => !ex.has(r.id))
        .map((r) => ({
          id: r.id,
          label: `${r.name || ''}（ID ${r.id}）`,
        }));
    },
  },
  watch: {
    treeViewMode(v) {
      if (v === 'departments' && this.showLeaderInlineAssign && !(this.leaderOptions && this.leaderOptions.length)) {
        this.fetchLeaderOptions();
      }
      this.$nextTick(() => this.syncAdminTableMaxHeight());
    },
  },
  created() {
    if (!this.$canPerm('perm.admin.api.departments.index') && this.$canPerm('perm.admin.api.positions.index')) {
      this.treeViewMode = 'positions';
    }
    if (this.$canPerm('perm.admin.api.departments.index')) {
      this.fetchList();
    }
    if (this.$canPerm('perm.admin.api.departments.leader_options')) {
      this.fetchLeaderOptions();
    }
    if (this.$canPerm('perm.admin.api.positions.index')) {
      this.fetchPositions();
    }
    if (this.$canPerm('perm.admin.api.positions.dept_options')) {
      this.fetchPositionDeptOptions();
    }
  },
  methods: {
    async refreshAll() {
      const tasks = [];
      if (this.$canPerm('perm.admin.api.departments.index')) tasks.push(this.fetchList());
      if (this.$canPerm('perm.admin.api.positions.index')) tasks.push(this.fetchPositions());
      await Promise.all(tasks);
    },
    ensurePositionDeptOptions() {
      if ((this.positionDeptOptions || []).length) return;
      if (this.$canPerm('perm.admin.api.positions.dept_options')) {
        this.fetchPositionDeptOptions();
      }
    },
    /** 仅部门层级（不挂载职务行） */
    buildDeptOnlyTree() {
      const flat = this.rows || [];
      if (!flat.length) {
        return [];
      }
      const baseTree = this.buildTree(flat);
      const merge = (node) => {
        const clone = { ...node };
        clone.rowKey = `d-${node.id}`;
        clone.rowType = 'department';
        const subDepts = (node.children || []).map(merge);
        const kids = [...subDepts];
        if (kids.length) {
          clone.children = kids;
        } else {
          delete clone.children;
        }
        return clone;
      };
      return baseTree.map(merge);
    },
    /** 仅职务：扁平列表（按部门、职级排序，无树展开） */
    buildPositionsFlatTree() {
      const positions = this.positionRowsAll || [];
      const sorted = [...positions].sort(
        (a, b) =>
          (Number(a.dept_id) || 0) - (Number(b.dept_id) || 0) ||
          (Number(b.level) || 0) - (Number(a.level) || 0) ||
          (Number(b.id) || 0) - (Number(a.id) || 0)
      );
      return sorted.map((p) => ({
        rowKey: `p-${p.id}`,
        rowType: 'position',
        ...p,
      }));
    },
    typeLabel(t) {
      return DEPT_TYPE_LABELS[t] || t || '—';
    },
    leaderSelectValue(row) {
      if (!row || row.leader_id == null || row.leader_id === '') {
        return null;
      }
      const n = Number(row.leader_id);
      return Number.isFinite(n) && n > 0 ? n : null;
    },
    buildDepartmentPutPayload(row, patch = {}) {
      if (!row || row.rowType === 'position') {
        return {};
      }
      const parentId = row.parent_id != null ? Number(row.parent_id) : 0;
      const nm = row.name != null ? String(row.name).trim() : '';
      return Object.assign(
        {
          name: nm,
          parent_id: Number.isFinite(parentId) ? parentId : 0,
          leader_id: this.leaderSelectValue(row),
          sort: row.sort != null ? Number(row.sort) : 0,
          status: row.status != null ? Number(row.status) : 1,
        },
        patch
      );
    },
    onDeptNameFocus(row) {
      if (!row || row.rowType === 'position' || !this.showNameInlineEdit) return;
      this.deptNameEditSnapshot = { id: row.id, name: row.name != null ? String(row.name) : '' };
    },
    onDeptNameEnter(ev) {
      if (ev && ev.target) ev.target.blur();
    },
    async onDeptNameBlur(row) {
      if (!row || row.rowType === 'position' || !this.$canPerm('perm.admin.api.departments.update')) return;
      if (this.nameBusyId === row.id) return;
      const snap = this.deptNameEditSnapshot;
      if (!snap || snap.id !== row.id) return;
      const raw = row.name != null ? String(row.name) : '';
      const next = raw.trim();
      const prev = String(snap.name || '').trim();
      if (next === prev) {
        this.deptNameEditSnapshot = null;
        return;
      }
      if (!next) {
        this.$message.warning('部门名称不能为空');
        row.name = snap.name;
        this.deptNameEditSnapshot = null;
        return;
      }
      this.nameBusyId = row.id;
      try {
        await window.axios.put(
          `/admin/api/departments/${row.id}`,
          this.buildDepartmentPutPayload(row, { name: next })
        );
        this.$message.success('名称已更新');
        await this.fetchList();
      } catch (e) {
        row.name = snap.name;
        this.$message.error(e?.response?.data?.message || '名称更新失败');
      } finally {
        this.nameBusyId = null;
        this.deptNameEditSnapshot = null;
      }
    },
    async onLeaderCommit(row, newVal) {
      if (!row || row.rowType === 'position' || !this.$canPerm('perm.admin.api.departments.update')) {
        return;
      }
      const nextId = newVal === '' || newVal === undefined || newVal === null ? null : Number(newVal);
      const prevId = this.leaderSelectValue(row);
      if (nextId === prevId) {
        return;
      }
      this.leaderBusyId = row.id;
      try {
        await window.axios.put(
          `/admin/api/departments/${row.id}`,
          this.buildDepartmentPutPayload(row, { leader_id: nextId })
        );
        this.$message.success('负责人已更新');
        await this.fetchList();
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '负责人更新失败');
      } finally {
        this.leaderBusyId = null;
      }
    },
    /** 职务行：展示所属部门（职务不设负责人） */
    positionDeptLabel(row) {
      if (!row || row.rowType !== 'position') return '—';
      const name = row.dept_name != null ? String(row.dept_name).trim() : '';
      if (name) return name;
      const id = row.dept_id != null ? Number(row.dept_id) : NaN;
      if (Number.isFinite(id) && id > 0) return `部门 #${id}`;
      return '—';
    },
    buildTree(flat) {
      if (!flat || !flat.length) return [];
      const ids = new Set(flat.map((r) => r.id));
      const map = new Map();
      flat.forEach((r) => {
        map.set(r.id, { ...r });
      });
      const roots = [];
      flat.forEach((r) => {
        const pid = r.parent_id == null || r.parent_id === '' ? 0 : Number(r.parent_id);
        const node = map.get(r.id);
        if (pid === 0 || !ids.has(pid)) {
          roots.push(node);
        }
      });
      flat.forEach((r) => {
        const pid = r.parent_id == null || r.parent_id === '' ? 0 : Number(r.parent_id);
        const node = map.get(r.id);
        if (pid !== 0 && ids.has(pid)) {
          const parent = map.get(pid);
          if (!parent.children) parent.children = [];
          parent.children.push(node);
        }
      });
      const sortFn = (a, b) => {
        const ds = (Number(a.sort) || 0) - (Number(b.sort) || 0);
        if (ds !== 0) return ds;
        return (Number(b.id) || 0) - (Number(a.id) || 0);
      };
      const finalize = (nodes) => {
        nodes.sort(sortFn);
        nodes.forEach((n) => {
          if (n.children && n.children.length) {
            finalize(n.children);
          } else {
            delete n.children;
          }
        });
      };
      finalize(roots);
      return roots;
    },
    onTreeNameClick(row) {
      if (!row || row.rowType === 'position' || !row.children || !row.children.length) return;
      const table = this.$refs.adminDataTable;
      if (table && typeof table.toggleRowExpansion === 'function') {
        table.toggleRowExpansion(row);
      }
    },
    async onSortCommit(row, v) {
      if (!row || row.rowType === 'position' || !this.$canPerm('perm.admin.api.departments.update')) return;
      const sort = Math.max(0, Math.floor(Number(v != null ? v : row.sort) || 0));
      row.sort = sort;
      const prev = this.sortBaseline[row.id];
      if (prev !== undefined && sort === prev) return;
      this.sortBusyId = row.id;
      try {
        await window.axios.patch(`/admin/api/departments/${row.id}/sort`, { sort });
        this.$message.success('排序已更新');
        await this.fetchList();
      } catch (e) {
        if (prev !== undefined) row.sort = prev;
        this.$message.error(e?.response?.data?.message || '排序更新失败');
        await this.fetchList();
      } finally {
        this.sortBusyId = null;
      }
    },
    async fetchLeaderOptions() {
      try {
        const { data } = await window.axios.get('/admin/api/departments/leader-options');
        this.leaderOptions = data.data || [];
      } catch (e) {
        this.leaderOptions = [];
      }
    },
    async fetchList() {
      if (!this.$canPerm('perm.admin.api.departments.index')) return;
      this.loading = true;
      try {
        const { data } = await window.axios.get('/admin/api/departments');
        this.rows = data.data || [];
        const b = {};
        (this.rows || []).forEach((r) => {
          b[r.id] = Number(r.sort) || 0;
        });
        this.sortBaseline = b;
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '加载失败');
      } finally {
        this.loading = false;
        this.$nextTick(() => this.syncAdminTableMaxHeight());
      }
    },
    async fetchPositionDeptOptions() {
      try {
        const { data } = await window.axios.get('/admin/api/positions/dept-options');
        this.positionDeptOptions = data.data || [];
      } catch (e) {
        this.positionDeptOptions = [];
      }
    },
    async fetchPositions() {
      if (!this.$canPerm('perm.admin.api.positions.index')) return;
      this.positionLoading = true;
      try {
        const { data } = await window.axios.get('/admin/api/positions');
        this.positionRowsAll = data.data || [];
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '职务列表加载失败');
      } finally {
        this.positionLoading = false;
        this.$nextTick(() => this.syncAdminTableMaxHeight());
      }
    },
    openCreate() {
      if (!this.$canPerm('perm.admin.api.departments.store')) {
        this.$message.warning('无新增权限，请在角色中分配「接口：部门新增」');
        return;
      }
      this.formMode = 'create';
      this.editingId = null;
      this.form = { name: '', parent_id: 0, leader_id: null, sort: 0, statusOn: true };
      this.formVisible = true;
    },
    openCreateChild(row) {
      if (row.rowType === 'position' || !this.$canPerm('perm.admin.api.departments.store')) return;
      this.formMode = 'create';
      this.editingId = null;
      this.form = {
        name: '',
        parent_id: row && row.id != null ? row.id : 0,
        leader_id: null,
        sort: 0,
        statusOn: true,
      };
      this.formVisible = true;
    },
    openEdit(row) {
      if (row.rowType === 'position') return;
      this.formMode = 'edit';
      this.editingId = row.id;
      const pid = row.parent_id != null ? Number(row.parent_id) : 0;
      this.form = {
        name: row.name || '',
        parent_id: Number.isFinite(pid) ? pid : 0,
        leader_id: row.leader_id != null ? Number(row.leader_id) : null,
        sort: row.sort != null ? Number(row.sort) : 0,
        statusOn: row.status === 1,
      };
      this.formVisible = true;
    },
    onFormClosed() {
      this.editingId = null;
    },
    async submitForm() {
      const name = (this.form.name && String(this.form.name).trim()) || '';
      if (!name) {
        this.$message.warning('请填写部门名称');
        return;
      }
      let parentId = this.form.parent_id != null ? Number(this.form.parent_id) : 0;
      if (!Number.isFinite(parentId) || parentId < 0) parentId = 0;
      const status = this.form.statusOn ? 1 : 0;
      const sort = this.form.sort != null ? Number(this.form.sort) : 0;
      const leaderRaw = this.form.leader_id;
      const leader_id =
        leaderRaw != null && leaderRaw !== '' && Number.isFinite(Number(leaderRaw)) && Number(leaderRaw) > 0
          ? Number(leaderRaw)
          : null;

      this.formSubmitting = true;
      try {
        const payload = { name, parent_id: parentId, leader_id, sort, status };
        if (this.formMode === 'create') {
          await window.axios.post('/admin/api/departments', payload);
          this.$message.success('新增成功');
        } else {
          await window.axios.put(`/admin/api/departments/${this.editingId}`, payload);
          this.$message.success('已更新');
        }
        this.formVisible = false;
        await this.fetchList();
        await this.fetchPositionDeptOptions();
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
      if (!row || row.rowType === 'position' || !this.$canPerm('perm.admin.api.departments.status')) return;
      const prev = row.status;
      this.statusBusyId = row.id;
      row.status = status;
      try {
        await window.axios.patch(`/admin/api/departments/${row.id}/status`, { status });
        this.$message.success('状态已更新');
        await this.fetchList();
      } catch (e) {
        row.status = prev;
        this.$message.error(e?.response?.data?.message || '更新失败');
      } finally {
        this.statusBusyId = null;
      }
    },

    openPosCreate() {
      if (!this.$canPerm('perm.admin.api.positions.store')) {
        this.$message.warning('无新增权限，请在角色中分配「接口：职务新增」');
        return;
      }
      this.ensurePositionDeptOptions();
      if (this.$canPerm('perm.admin.api.positions.index')) {
        this.fetchPositions();
      }
      this.posFormMode = 'create';
      this.posEditingId = null;
      const first = this.positionDeptOptions[0];
      const deptId = first ? first.id : null;
      this.posForm = {
        name: '',
        code: '',
        dept_id: deptId,
        level: 1,
        statusOn: true,
      };
      this.posFormVisible = true;
    },
    openPosCreateForDept(row) {
      if (row.rowType === 'position' || !this.$canPerm('perm.admin.api.positions.store')) return;
      this.ensurePositionDeptOptions();
      if (this.$canPerm('perm.admin.api.positions.index')) {
        this.fetchPositions();
      }
      this.posFormMode = 'create';
      this.posEditingId = null;
      this.posForm = {
        name: '',
        code: '',
        dept_id: row.id,
        level: 1,
        statusOn: true,
      };
      this.posFormVisible = true;
    },
    openPosEdit(row) {
      if (row.rowType !== 'position') return;
      this.ensurePositionDeptOptions();
      this.posFormMode = 'edit';
      this.posEditingId = row.id;
      this.posForm = {
        name: row.name || '',
        code: row.code || '',
        dept_id: row.dept_id != null ? Number(row.dept_id) : null,
        level: row.level != null ? Number(row.level) : 1,
        statusOn: row.status === 1,
      };
      this.posFormVisible = true;
    },
    onPosFormClosed() {
      this.posEditingId = null;
    },
    async submitPosForm() {
      const name = (this.posForm.name && String(this.posForm.name).trim()) || '';
      const code = (this.posForm.code && String(this.posForm.code).trim()) || '';
      if (!name) {
        this.$message.warning('请填写职务名称');
        return;
      }
      if (this.posFormMode === 'create' && !code) {
        this.$message.warning('请填写职位标识');
        return;
      }
      if (this.posFormMode === 'create' && !/^[A-Za-z0-9_\-]+$/.test(code)) {
        this.$message.warning('职位标识仅允许字母、数字、下划线与中划线');
        return;
      }
      const deptId = this.posForm.dept_id != null ? Number(this.posForm.dept_id) : null;
      if (deptId == null || !Number.isFinite(deptId) || deptId < 1) {
        this.$message.warning('请选择所属部门');
        return;
      }
      const level = this.posForm.level != null ? Number(this.posForm.level) : 1;
      const status = this.posForm.statusOn ? 1 : 0;

      this.posFormSubmitting = true;
      try {
        if (this.posFormMode === 'create') {
          await window.axios.post('/admin/api/positions', { name, code, dept_id: deptId, level, status });
          this.$message.success('职务新增成功');
        } else {
          await window.axios.put(`/admin/api/positions/${this.posEditingId}`, { name, dept_id: deptId, level, status });
          this.$message.success('职务已更新');
        }
        this.posFormVisible = false;
        await this.fetchPositions();
      } catch (e) {
        const msg =
          e?.response?.data?.message ||
          (e?.response?.data?.errors ? Object.values(e.response.data.errors).flat().filter(Boolean).join('；') : null) ||
          '保存失败';
        this.$message.error(msg);
      } finally {
        this.posFormSubmitting = false;
      }
    },
    async patchPosStatus(row, status) {
      if (!row || row.rowType !== 'position' || !this.$canPerm('perm.admin.api.positions.status')) return;
      const prev = row.status;
      this.posStatusBusyId = row.id;
      row.status = status;
      try {
        await window.axios.patch(`/admin/api/positions/${row.id}/status`, { status });
        this.$message.success('状态已更新');
        await this.fetchPositions();
      } catch (e) {
        row.status = prev;
        this.$message.error(e?.response?.data?.message || '更新失败');
      } finally {
        this.posStatusBusyId = null;
      }
    },
    async confirmDeleteDepartment(row) {
      if (!row || row.rowType === 'position' || !this.$canPerm('perm.admin.api.departments.destroy')) return;
      const label = (row.name != null && String(row.name).trim()) || `ID ${row.id}`;
      try {
        await this.$confirm(
          `确定要永久删除部门「${label}」吗？此操作不可恢复。`,
          '删除确认',
          { type: 'warning', confirmButtonText: '继续', cancelButtonText: '取消' }
        );
        await this.$confirm(
          '再次确认：将硬删除该部门，并解除用户与该部门的归属关系（不删除用户账号）。存在子部门或部门下仍有职务时将被拒绝。',
          '二次确认',
          { type: 'error', confirmButtonText: '确认删除', cancelButtonText: '取消' }
        );
      } catch (e) {
        return;
      }
      this.deptDeleteBusyId = row.id;
      try {
        await window.axios.delete(`/admin/api/departments/${row.id}`);
        this.$message.success('部门已删除');
        await this.fetchList();
      } catch (err) {
        this.$message.error(err?.response?.data?.message || '删除失败');
      } finally {
        this.deptDeleteBusyId = null;
      }
    },
    async confirmDeletePosition(row) {
      if (!row || row.rowType !== 'position' || !this.$canPerm('perm.admin.api.positions.destroy')) return;
      const label = (row.name != null && String(row.name).trim()) || `ID ${row.id}`;
      try {
        await this.$confirm(
          `确定要永久删除职务「${label}」吗？此操作不可恢复。`,
          '删除确认',
          { type: 'warning', confirmButtonText: '继续', cancelButtonText: '取消' }
        );
        await this.$confirm(
          '再次确认：将硬删除该职务，并解除用户与该职务的关联（不删除用户账号）。',
          '二次确认',
          { type: 'error', confirmButtonText: '确认删除', cancelButtonText: '取消' }
        );
      } catch (e) {
        return;
      }
      this.posDeleteBusyId = row.id;
      try {
        await window.axios.delete(`/admin/api/positions/${row.id}`);
        this.$message.success('职务已删除');
        await this.fetchPositions();
      } catch (err) {
        this.$message.error(err?.response?.data?.message || '删除失败');
      } finally {
        this.posDeleteBusyId = null;
      }
    },
  },
};
</script>

<style scoped>
.admin-dept-view-mode {
  margin-left: 12px;
}
.admin-dept-pos-name-stack {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  line-height: 1.3;
  padding-left: 2px;
}
.admin-dept-pos-name-stack__code {
  font-size: 11px;
  color: #909399;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.admin-dept-unified-position-name {
  color: #606266;
}
.admin-dept-leader-inline {
  width: 100%;
  max-width: 240px;
}
.admin-dept-name-inline {
  width: 100%;
  max-width: 260px;
}
.admin-pos-existing-list {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 200px;
  overflow-y: auto;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  background: #fafafa;
}
.admin-pos-existing-item {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px 10px;
  padding: 8px 10px;
  font-size: 12px;
  border-bottom: 1px solid #ebeef5;
}
.admin-pos-existing-item:last-child {
  border-bottom: none;
}
.admin-pos-existing-name {
  flex: 1;
  min-width: 80px;
  font-weight: 500;
  color: #303133;
}
.admin-pos-existing-code {
  font-size: 11px;
  padding: 1px 6px;
  border-radius: 3px;
  background: #f0f2f5;
  color: #606266;
}
.admin-pos-existing-meta {
  color: #909399;
  font-size: 12px;
}
</style>
