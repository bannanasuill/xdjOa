<template>
  <div class="admin-users-page">
    <el-card class="admin-mb-12 admin-page-filters">
      <div class="admin-form-row">
        <el-input
          v-model="query.q"
          placeholder="搜索账号/姓名"
          clearable
          size="small"
          class="admin-w-240"
          @keyup.enter.native="fetchUsers(1)"
        />
        <el-select
          v-model="query.role_id"
          clearable
          filterable
          placeholder="按角色筛选"
          size="small"
          class="admin-w-200"
          @change="fetchUsers(1)"
        >
          <el-option v-for="r in roleFilterOptions" :key="r.id" :label="roleOptionLabel(r)" :value="r.id" />
        </el-select>
        <el-button size="small" type="primary" @click="fetchUsers(1)">查询</el-button>
        <el-button size="small" @click="reset">重置</el-button>
        <span class="admin-flex-spacer"></span>
        <el-button v-if="$canPerm('perm.admin.api.users.store')" type="primary" size="small" @click="openCreate">新增用户</el-button>
      </div>
    </el-card>

    <el-card>
      <el-table
        ref="adminDataTable"
        class="admin-data-table admin-users-table"
        :data="rows"
        :max-height="adminTableMaxHeight"
        size="mini"
        v-loading="loading"
      >
        <el-table-column prop="id" label="ID" width="64" fixed="left" />
        <el-table-column label="姓名" min-width="100" fixed="left">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(row.real_name)">{{ adminEllipsisDisplay(row.real_name) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="账号" min-width="108" fixed="left">
          <template slot-scope="{ row }">
            <span class="admin-user-account-cell" :title="adminEllipsisTitle(row.account)">{{ adminEllipsisDisplay(row.account) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="角色" min-width="168" class-name="admin-users-col-roles">
          <template slot-scope="{ row }">
            <div class="admin-user-roles-cell">
              <template v-if="row.roles && row.roles.length">
                <el-tag v-for="r in row.roles" :key="r.id" size="mini" :type="r.is_system ? 'danger' : 'success'" class="admin-user-role-tag">
                  {{ r.name }}
                </el-tag>
              </template>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="职务(部门)" min-width="200" class-name="admin-users-col-org">
          <template slot-scope="{ row }">
            <div class="admin-user-org-cell">
              <div v-if="row.positions && row.positions.length" class="admin-user-org-line">
                <el-tag v-for="p in row.positions" :key="'p-' + p.id" size="mini" class="admin-user-org-tag">
                  {{ p.name }}{{ p.dept_name ? '（' + p.dept_name + '）' : '' }}
                </el-tag>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="158">
          <template slot-scope="{ row }">
            {{ formatTs(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="状态" width="108" align="center" fixed="right">
          <template slot-scope="{ row }">
            <el-switch
              v-if="$canPerm('perm.admin.api.users.status') && !row.is_super_admin"
              class="admin-status-switch"
              :value="row.status === 1"
              :active-color="'#13ce66'"
              :inactive-color="'#f56c6c'"
              @change="(val) => openStatusRemark(row, val ? 1 : 0)"
            />
            <span v-else-if="!$canPerm('perm.admin.api.users.status')">{{ row.status === 1 ? '启用' : '禁用' }}</span>
            <span v-else>{{ row.status === 1 ? '启用' : '禁用' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="300" fixed="right" class-name="admin-users-col-actions">
          <template slot-scope="{ row }">
            <template v-if="!row.is_super_admin && $canPerm('perm.admin.api.users.update')">
              <el-button size="mini" class="admin-mr-6" @click="openRoleAssign(row)">分配角色</el-button>
              <el-button size="mini" class="admin-mr-6" @click="openOrgAssign(row)">分配职务</el-button>
              <el-button size="mini" @click="openEdit(row)">编辑</el-button>
            </template>
            <span v-else class="admin-users-op-empty">—</span>
          </template>
        </el-table-column>
      </el-table>

      <div class="admin-pager-row admin-main-dock">
        <el-select v-model="query.per_page" size="small" class="admin-w-110" @change="fetchUsers(1)">
          <el-option v-for="n in perPageOptions" :key="n" :label="`${n}/页`" :value="n" />
        </el-select>
        <el-pagination
          background
          layout="prev, pager, next, jumper, total"
          :page-size="meta.per_page"
          :current-page="meta.current_page"
          :total="meta.total"
          @current-change="fetchUsers"
        />
      </div>
    </el-card>

    <el-dialog :title="formMode === 'create' ? '新增用户' : '编辑用户'" :visible.sync="formVisible" width="520px">
      <el-form :model="form" label-width="90px" size="small">
        <el-form-item label="姓名" required>
          <el-input v-model="form.real_name" autocomplete="off" />
        </el-form-item>
        <el-form-item :label="formMode === 'create' ? '账号' : '账号'" :required="formMode !== 'create'">
          <el-input
            v-model="form.account"
            autocomplete="off"
            :placeholder="formMode === 'create' ? '留空则自动生成：日期+当日序号，如 202604031' : ''"
          />
        </el-form-item>
        <el-form-item :label="formMode === 'create' ? '密码' : '新密码'" :required="false">
          <el-input
            v-model="form.password"
            autocomplete="off"
            :placeholder="formMode === 'create' ? '留空则用后台默认密码' : '不修改请留空'"
          />
        </el-form-item>
        <el-form-item label="手机号">
          <el-input v-model="form.phone" autocomplete="off" />
        </el-form-item>
        <el-form-item label="邮箱">
          <el-input v-model="form.email" autocomplete="off" />
        </el-form-item>
      </el-form>
      <span slot="footer" class="admin-dialog-footer">
        <el-button size="small" @click="formVisible=false">取消</el-button>
        <el-button size="small" type="primary" :loading="formSubmitting" @click="submitForm">保存</el-button>
      </span>
    </el-dialog>

    <el-dialog
      :title="roleAssignTitle"
      :visible.sync="roleAssignVisible"
      width="480px"
      :close-on-click-modal="false"
      custom-class="admin-users-role-dialog"
      @closed="onRoleAssignClosed"
    >
      <p class="admin-users-role-hint">可多选：保存后立即生效。</p>
      <el-form label-width="72px" size="small" class="admin-users-role-form">
        <el-form-item label="角色" class="admin-users-role-form-item">
          <el-select
            v-model="roleAssignIds"
            multiple
            filterable
            placeholder="请选择角色"
            popper-class="admin-users-role-select-dropdown"
            class="admin-w-full admin-users-role-select"
          >
            <el-option v-for="r in roleOptions" :key="r.id" :label="roleOptionLabel(r)" :value="r.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <span slot="footer" class="admin-dialog-footer">
        <el-button size="small" @click="roleAssignVisible = false">取消</el-button>
        <el-button size="small" type="primary" :loading="roleAssignSaving" @click="submitRoleAssign">保存</el-button>
      </span>
    </el-dialog>

    <el-dialog
      :title="orgAssignTitle"
      :visible.sync="orgAssignVisible"
      width="520px"
      :close-on-click-modal="false"
      custom-class="admin-users-role-dialog admin-users-org-dialog"
      @closed="onOrgAssignClosed"
    >
      <p class="admin-users-role-hint">职务多选；每个职务归属固定部门，保存时会自动同步对应部门（全量覆盖）。</p>
      <el-form label-width="72px" size="small" class="admin-users-role-form">
        <el-form-item label="职务" class="admin-users-role-form-item">
          <el-select
            v-model="orgAssignPositionIds"
            multiple
            filterable
            collapse-tags
            placeholder="选择职务（可多选）"
            popper-class="admin-users-role-select-dropdown"
            class="admin-w-full admin-users-role-select"
          >
            <el-option
              v-for="p in orgPositionOptions"
              :key="p.id"
              :label="orgPositionOptionLabel(p)"
              :value="p.id"
            />
          </el-select>
        </el-form-item>
      </el-form>
      <span slot="footer" class="admin-dialog-footer">
        <el-button size="small" @click="orgAssignVisible = false">取消</el-button>
        <el-button size="small" type="primary" :loading="orgAssignSaving" @click="submitOrgAssign">保存</el-button>
      </span>
    </el-dialog>

    <el-dialog :visible.sync="remarkVisible" width="420px">
      <div style="font-weight:600; margin-bottom:10px;">{{ remarkTitle }}</div>
      <el-input
        v-model="remark"
        type="textarea"
        :rows="4"
        :placeholder="remarkPlaceholder"
      />
      <span slot="footer" class="admin-dialog-footer">
        <el-button size="small" @click="closeRemark">取消</el-button>
        <el-button size="small" type="primary" :loading="remarkSubmitting" @click="submitRemark">确定</el-button>
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
      rows: [],
      meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
      query: { q: '', role_id: null, per_page: 20 },
      roleFilterOptions: [],
      perPageOptions: [10, 20, 50, 100],

      // create/edit
      formVisible: false,
      formSubmitting: false,
      formMode: 'create',
      editingId: null,
      roleOptions: [],
      form: { account: '', real_name: '', phone: '', email: '', password: '' },

      // status remark
      remarkVisible: false,
      remarkSubmitting: false,
      remark: '',
      remarkTarget: null,
      remarkNextStatus: 1,

      roleAssignVisible: false,
      roleAssignTarget: null,
      roleAssignIds: [],
      roleAssignSaving: false,

      orgAssignVisible: false,
      orgAssignTarget: null,
      orgAssignPositionIds: [],
      orgPositionOptions: [],
      orgAssignSaving: false,
    };
  },
  computed: {
    orgAssignTitle() {
      const a = this.orgAssignTarget && this.orgAssignTarget.real_name;
      return a ? `分配职务 — ${a}` : '分配职务';
    },
    roleAssignTitle() {
      const a = this.roleAssignTarget && this.roleAssignTarget.real_name;
      return a ? `分配角色 — ${a}` : '分配角色';
    },
    remarkTitle() {
      return this.remarkNextStatus === 0 ? '禁用备注' : '启用备注';
    },
    remarkPlaceholder() {
      return this.remarkNextStatus === 0 ? '请输入禁用原因' : '请输入启用备注';
    },
  },
  created() {
    this.loadRoleFilterOptions();
    this.fetchUsers(1);
  },
  methods: {
    formatTs(ts) {
      if (!ts) return '';
      const d = new Date(ts * 1000);
      const pad = (n) => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(
        d.getMinutes()
      )}:${pad(d.getSeconds())}`;
    },
    async fetchUsers(page) {
      this.loading = true;
      try {
        const params = { q: this.query.q, per_page: this.query.per_page, page };
        if (this.query.role_id != null && this.query.role_id !== '') {
          params.role_id = this.query.role_id;
        }
        const { data } = await window.axios.get('/admin/api/users', { params });
        this.rows = (data.data || []).map((u) => ({
          ...u,
          is_super_admin: !!u.is_super_admin,
        }));
        this.meta = data.meta || this.meta;
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '加载失败');
      } finally {
        this.loading = false;
        this.$nextTick(() => this.syncAdminTableMaxHeight());
      }
    },
    reset() {
      this.query.q = '';
      this.query.role_id = null;
      this.query.per_page = 20;
      this.fetchUsers(1);
    },
    async loadRoleFilterOptions() {
      try {
        const { data } = await window.axios.get('/admin/api/role-options');
        this.roleFilterOptions = data.data || [];
      } catch (e) {
        this.roleFilterOptions = [];
      }
    },
    roleOptionLabel(r) {
      if (!r) return '';
      return r.is_system === 1 ? `${r.name}（系统）` : r.name;
    },
    async loadRoleOptions() {
      try {
        const { data } = await window.axios.get('/admin/api/role-options');
        this.roleOptions = data.data || [];
      } catch (e) {
        this.roleOptions = [];
        if (e?.response?.status !== 403) {
          this.$message.error(e?.response?.data?.message || '加载角色列表失败');
        }
      }
    },
    openCreate() {
      this.formMode = 'create';
      this.editingId = null;
      this.form = { account: '', real_name: '', phone: '', email: '', password: '' };
      this.formVisible = true;
    },
    openEdit(row) {
      if (row.is_super_admin) {
        this.$message.warning('超级管理员不可编辑');
        return;
      }
      this.formMode = 'edit';
      this.editingId = row.id;
      this.form = {
        account: row.account || '',
        real_name: row.real_name || '',
        phone: row.phone || '',
        email: row.email || '',
        password: '',
      };
      this.formVisible = true;
    },
    openRoleAssign(row) {
      if (!row || row.is_super_admin) {
        return;
      }
      if (!this.$canPerm('perm.admin.api.users.update')) {
        return;
      }
      this.roleAssignTarget = row;
      this.roleAssignIds = (row.roles || []).map((r) => r.id).filter((id) => id != null);
      this.roleAssignVisible = true;
      this.loadRoleOptions();
    },
    onRoleAssignClosed() {
      this.roleAssignTarget = null;
      this.roleAssignIds = [];
    },
    async submitRoleAssign() {
      if (!this.roleAssignTarget) return;
      this.roleAssignSaving = true;
      try {
        await window.axios.patch(`/admin/api/users/${this.roleAssignTarget.id}/roles`, {
          role_ids: Array.isArray(this.roleAssignIds) ? this.roleAssignIds : [],
        });
        this.$message.success('角色已更新');
        this.roleAssignVisible = false;
        this.fetchUsers(this.meta.current_page);
      } catch (e) {
        const msg =
          e?.response?.data?.message ||
          (e?.response?.data?.errors ? Object.values(e.response.data.errors)[0]?.[0] : null) ||
          '保存失败';
        this.$message.error(msg);
      } finally {
        this.roleAssignSaving = false;
      }
    },
    orgPositionOptionLabel(p) {
      if (!p) return '';
      const name = (p.name || '').trim();
      const dn = (p.dept_name || '').trim();
      if (dn) {
        return `${name} — ${dn}`;
      }
      return name || `#${p.id}`;
    },
    async loadOrgOptions() {
      try {
        const { data } = await window.axios.get('/admin/api/users/org-options');
        const pack = data.data || {};
        this.orgPositionOptions = pack.positions || [];
      } catch (e) {
        this.orgPositionOptions = [];
        if (e?.response?.status !== 403) {
          this.$message.error(e?.response?.data?.message || '加载部门/职务失败');
        }
      }
    },
    openOrgAssign(row) {
      if (!row || row.is_super_admin) {
        return;
      }
      if (!this.$canPerm('perm.admin.api.users.update')) {
        return;
      }
      this.orgAssignTarget = row;
      this.orgAssignPositionIds = (row.positions || []).map((p) => p.id).filter((id) => id != null);
      this.orgAssignVisible = true;
      this.loadOrgOptions();
    },
    onOrgAssignClosed() {
      this.orgAssignTarget = null;
      this.orgAssignPositionIds = [];
    },
    async submitOrgAssign() {
      if (!this.orgAssignTarget) return;
      this.orgAssignSaving = true;
      try {
        await window.axios.patch(`/admin/api/users/${this.orgAssignTarget.id}/org`, {
          dept_ids: [],
          position_ids: Array.isArray(this.orgAssignPositionIds) ? this.orgAssignPositionIds : [],
        });
        this.$message.success('职务与关联部门已更新');
        this.orgAssignVisible = false;
        this.fetchUsers(this.meta.current_page);
      } catch (e) {
        const msg =
          e?.response?.data?.message ||
          (e?.response?.data?.errors ? Object.values(e.response.data.errors)[0]?.[0] : null) ||
          '保存失败';
        this.$message.error(msg);
      } finally {
        this.orgAssignSaving = false;
      }
    },
    async submitForm() {
      if (this.formMode === 'create') {
        if (!this.form.real_name) {
          this.$message.warning('请填写姓名');
          return;
        }
      } else if (!this.form.account || !this.form.real_name) {
        this.$message.warning('请填写账号和姓名');
        return;
      }
      this.formSubmitting = true;
      try {
        const accTrim = (this.form.account && String(this.form.account).trim()) || '';
        const payload = {
          real_name: this.form.real_name,
          phone: this.form.phone,
          email: this.form.email,
        };
        if (this.formMode === 'create') {
          if (accTrim) payload.account = accTrim;
          const pwTrim = (this.form.password && String(this.form.password).trim()) || '';
          if (pwTrim) payload.password = pwTrim;
        } else {
          payload.account = accTrim;
          payload.password = this.form.password;
        }
        if (this.formMode === 'create') {
          await window.axios.post('/admin/api/users', payload);
          this.$message.success('新增用户成功');
        } else {
          await window.axios.put(`/admin/api/users/${this.editingId}`, payload);
          this.$message.success('用户信息已更新');
        }
        this.formVisible = false;
        this.fetchUsers(this.meta.current_page);
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
    openStatusRemark(row, nextStatus) {
      if (row && row.is_super_admin) {
        this.$message.warning('超级管理员不可修改状态');
        return;
      }
      this.remarkTarget = row;
      this.remarkNextStatus = nextStatus;
      this.remark = '';
      this.remarkVisible = true;
    },
    closeRemark() {
      this.remarkVisible = false;
      this.remarkTarget = null;
      this.remark = '';
    },
    async submitRemark() {
      const r = (this.remark || '').trim();
      if (!r) {
        this.$message.warning('请输入备注');
        return;
      }
      this.remarkSubmitting = true;
      try {
        await window.axios.patch(`/admin/api/users/${this.remarkTarget.id}/status`, {
          status: this.remarkNextStatus,
          status_remark: r,
        });
        this.$message.success('状态已更新');
        this.closeRemark();
        this.fetchUsers(this.meta.current_page);
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '更新失败');
      } finally {
        this.remarkSubmitting = false;
      }
    },
  },
};
</script>

<style scoped>
/* 多选角色时逐项展示名称，标签可换行避免撑破弹窗 */
.admin-users-role-select >>> .el-select__tags {
  flex-wrap: wrap;
}
.admin-users-role-select >>> .el-select__tags-text {
  max-width: none;
}

.admin-user-org-cell {
  line-height: 1.45;
}
.admin-user-org-line {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 6px;
  margin-bottom: 4px;
}
.admin-user-org-line:last-child {
  margin-bottom: 0;
}
.admin-user-org-k {
  font-size: 11px;
  color: var(--admin-text-muted, #909399);
  flex: 0 0 auto;
  margin-right: 2px;
}
.admin-user-org-tag {
  margin-right: 0;
}
</style>

