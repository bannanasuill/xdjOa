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
        <el-select
          v-model="query.presence_today"
          clearable
          placeholder="当下状态"
          size="small"
          class="admin-w-160"
          @change="fetchUsers(1)"
        >
          <el-option
            v-for="o in presenceFilterOptions"
            :key="o.value"
            :label="o.label"
            :value="o.value"
          />
        </el-select>
        <el-select
          v-model="query.position_id"
          clearable
          filterable
          placeholder="按职务筛选"
          size="small"
          class="admin-w-220"
          @change="fetchUsers(1)"
        >
          <el-option
            v-for="p in positionFilterOptions"
            :key="p.id"
            :label="positionOptionLabel(p)"
            :value="p.id"
          />
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
        <el-table-column type="index" label="序号" width="56" fixed="left" :index="userRowIndex" />
        <el-table-column label="姓名" min-width="100" fixed="left">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(row.real_name)">{{ adminEllipsisDisplay(row.real_name) }}</span>
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
        <el-table-column label="门店" min-width="168" class-name="admin-users-col-stores">
          <template slot-scope="{ row }">
            <div v-if="row.stores && row.stores.length" class="admin-user-org-line">
              <el-tag
                v-for="s in row.stores"
                :key="'us-' + s.id"
                size="mini"
                :type="s.is_main ? 'warning' : 'info'"
                class="admin-user-org-tag"
              >
                {{ s.store_name || s.store_code || '#' + s.store_id }}{{ s.is_main ? '·主店' : '' }}
              </el-tag>
            </div>
            <span v-else class="admin-users-op-empty">—</span>
          </template>
        </el-table-column>
        <el-table-column label="当下状态" min-width="100" align="center">
          <template slot-scope="{ row }">
            <span
              :class="row.presence_today_class || 'admin-presence-pill admin-presence-pill--muted'"
              :title="row.presence_today_title || null"
            >{{ row.presence_today }}</span>
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
        <el-table-column label="操作" width="520" fixed="right" class-name="admin-users-col-actions">
          <template slot-scope="{ row }">
            <el-button
              v-if="$canPerm('perm.admin.api.users.index')"
              size="mini"
              class="admin-mr-6"
              @click="openPresenceViewer(row)"
            >查看出勤</el-button>
            <template v-if="!row.is_super_admin && $canPerm('perm.admin.api.users.update')">
              <el-button size="mini" class="admin-mr-6" @click="openRoleAssign(row)">分配角色</el-button>
              <el-button size="mini" class="admin-mr-6" @click="openOrgAssign(row)">分配职务</el-button>
              <el-button size="mini" class="admin-mr-6" @click="openStoreAssign(row)">分配店铺</el-button>
              <el-button size="mini" @click="openEdit(row)">编辑</el-button>
            </template>
            <span
              v-if="!$canPerm('perm.admin.api.users.index') && (row.is_super_admin || !$canPerm('perm.admin.api.users.update'))"
              class="admin-users-op-empty"
            >—</span>
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

    <el-dialog
      :title="storeAssignTitle"
      :visible.sync="storeAssignVisible"
      width="760px"
      top="6vh"
      :close-on-click-modal="false"
      custom-class="admin-users-store-dialog"
      @closed="onStoreAssignClosed"
    >
      <p class="admin-users-role-hint">
        可分配多个门店；每人须恰好一个「主门店」，其余为支援。每行选择门店与在该门店担任的职务，并设置任职起止日期。
      </p>
      <div class="admin-users-store-toolbar">
        <el-button type="primary" plain size="small" @click="addStoreAssignRow">添加门店</el-button>
      </div>
      <el-table :data="storeAssignRows" size="mini" border class="admin-users-store-table" empty-text="点击下方添加门店">
        <el-table-column label="门店" min-width="200">
          <template slot-scope="{ row }">
            <el-select v-model="row.store_id" filterable clearable placeholder="选择门店" class="admin-w-full">
              <el-option v-for="s in storeOptions" :key="s.id" :label="storeOptionLabel(s)" :value="s.id" />
            </el-select>
          </template>
        </el-table-column>
        <el-table-column label="职务" min-width="200">
          <template slot-scope="{ row }">
            <el-select v-model="row.position_id" filterable clearable placeholder="选择职务" class="admin-w-full">
              <el-option
                v-for="p in storePositionOptions"
                :key="p.id"
                :label="orgPositionOptionLabel(p)"
                :value="p.id"
              />
            </el-select>
          </template>
        </el-table-column>
        <el-table-column label="主门店" width="88" align="center">
          <template slot-scope="{ row }">
            <el-switch
              :value="row.is_main === 1"
              @change="(on) => onStoreMainChange(row, on)"
            />
          </template>
        </el-table-column>
        <el-table-column label="生效" width="128">
          <template slot-scope="{ row }">
            <el-date-picker v-model="row.start_date" type="date" value-format="yyyy-MM-dd" placeholder="生效日" size="mini" class="admin-w-full" />
          </template>
        </el-table-column>
        <el-table-column label="失效" width="128">
          <template slot-scope="{ row }">
            <el-date-picker v-model="row.end_date" type="date" value-format="yyyy-MM-dd" placeholder="失效日" size="mini" class="admin-w-full" />
          </template>
        </el-table-column>
        <el-table-column label="" width="56" align="center">
          <template slot-scope="{ $index }">
            <el-button type="danger" plain icon="el-icon-delete" circle size="mini" @click="removeStoreAssignRow($index)" />
          </template>
        </el-table-column>
      </el-table>
      <span slot="footer" class="admin-dialog-footer">
        <el-button size="small" @click="storeAssignVisible = false">取消</el-button>
        <el-button size="small" type="primary" :loading="storeAssignSaving" @click="submitStoreAssign">保存</el-button>
      </span>
    </el-dialog>

    <el-dialog
      :title="presenceDialogTitle"
      :visible.sync="presenceVisible"
      width="900px"
      top="5vh"
      :close-on-click-modal="false"
      custom-class="admin-users-presence-dialog"
      @closed="onPresenceClosed"
    >
      <div class="admin-form-row admin-mb-12">
        <el-date-picker
          v-model="presenceDateRange"
          type="daterange"
          range-separator="至"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          value-format="yyyy-MM-dd"
          size="small"
          clearable
          unlink-panels
        />
        <el-button size="small" type="primary" @click="onPresenceQuery">查询</el-button>
        <el-button size="small" @click="presenceDateRange = null; onPresenceQuery()">重置日期</el-button>
      </div>
      <el-table
        :data="presenceRows"
        v-loading="presenceLoading"
        size="mini"
        class="admin-data-table"
        max-height="440"
        empty-text="暂无出勤记录"
      >
        <el-table-column prop="work_date" label="业务日" width="108" />
        <el-table-column prop="record_type_label" label="类型" width="72" align="center" />
        <el-table-column label="开始时间" width="158">
          <template slot-scope="{ row }">{{ formatTs(row.start_at) }}</template>
        </el-table-column>
        <el-table-column label="结束时间" width="158">
          <template slot-scope="{ row }">{{ row.end_at != null ? formatTs(row.end_at) : '—' }}</template>
        </el-table-column>
        <el-table-column label="时长" width="88" align="center">
          <template slot-scope="{ row }">{{ presenceDurationText(row) }}</template>
        </el-table-column>
        <el-table-column label="原因/说明" min-width="120" show-overflow-tooltip>
          <template slot-scope="{ row }">{{ row.reason || '—' }}</template>
        </el-table-column>
        <el-table-column label="地址" min-width="140" show-overflow-tooltip>
          <template slot-scope="{ row }">{{ row.address || '—' }}</template>
        </el-table-column>
      </el-table>
      <div class="admin-pager-row" style="margin-top: 12px; padding-bottom: 0;">
        <el-select v-model="presenceQuery.per_page" size="small" class="admin-w-110" @change="fetchPresenceRecords(1)">
          <el-option v-for="n in presencePerPageOptions" :key="n" :label="`${n}/页`" :value="n" />
        </el-select>
        <el-pagination
          background
          layout="prev, pager, next, total"
          :page-size="presenceMeta.per_page"
          :current-page="presenceMeta.current_page"
          :total="presenceMeta.total"
          @current-change="fetchPresenceRecords"
        />
      </div>
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
      query: { q: '', role_id: null, presence_today: null, position_id: null, per_page: 20 },
      roleFilterOptions: [],
      positionFilterOptions: [],
      presenceFilterOptions: [
        { value: 'not_arrived', label: '未到岗' },
        { value: 'present', label: '到岗' },
        { value: 'outing', label: '外出' },
        { value: 'off_work', label: '下班' },
        { value: 'unknown', label: '其他' },
      ],
      perPageOptions: [10, 20, 50, 100],

      // create/edit
      formVisible: false,
      formSubmitting: false,
      formMode: 'create',
      editingId: null,
      roleOptions: [],
      form: { account: '', real_name: '', phone: '', password: '' },

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

      storeAssignVisible: false,
      storeAssignTarget: null,
      storeAssignRows: [],
      storeOptions: [],
      storePositionOptions: [],
      storeAssignSaving: false,

      presenceVisible: false,
      presenceTarget: null,
      presenceLoading: false,
      presenceRows: [],
      presenceDateRange: null,
      presenceQuery: { page: 1, per_page: 20 },
      presenceMeta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
      presencePerPageOptions: [10, 20, 50],
    };
  },
  computed: {
    presenceDialogTitle() {
      const t = this.presenceTarget;
      if (!t) return '出勤记录';
      const name = (t.real_name && String(t.real_name).trim()) || t.account || '';

      return name ? `出勤记录 — ${name}` : '出勤记录';
    },
    orgAssignTitle() {
      const a = this.orgAssignTarget && this.orgAssignTarget.real_name;
      return a ? `分配职务 — ${a}` : '分配职务';
    },
    roleAssignTitle() {
      const a = this.roleAssignTarget && this.roleAssignTarget.real_name;
      return a ? `分配角色 — ${a}` : '分配角色';
    },
    storeAssignTitle() {
      const a = this.storeAssignTarget && this.storeAssignTarget.real_name;
      return a ? `分配店铺 — ${a}` : '分配店铺';
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
    this.loadPositionFilterOptions();
    this.fetchUsers(1);
  },
  methods: {
    positionOptionLabel(p) {
      const name = (p && p.name && String(p.name).trim()) || '';
      const dn = p && p.dept_name && String(p.dept_name).trim() ? String(p.dept_name).trim() : '';
      return dn ? `${name}（${dn}）` : name || String(p.id);
    },
    userRowIndex(index) {
      const page = this.meta.current_page || 1;
      const per = this.meta.per_page || 20;

      return (page - 1) * per + index + 1;
    },
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
        if (this.query.presence_today != null && this.query.presence_today !== '') {
          params.presence_today = this.query.presence_today;
        }
        if (this.query.position_id != null && this.query.position_id !== '') {
          params.position_id = this.query.position_id;
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
      this.query.presence_today = null;
      this.query.position_id = null;
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
    async loadPositionFilterOptions() {
      if (!this.$canPerm('perm.admin.api.users.index')) {
        this.positionFilterOptions = [];
        return;
      }
      try {
        const { data } = await window.axios.get('/admin/api/users/position-filter-options');
        this.positionFilterOptions = data.data || [];
      } catch (e) {
        this.positionFilterOptions = [];
      }
    },
    openPresenceViewer(row) {
      if (!row || !row.id) return;
      this.presenceTarget = row;
      this.presenceDateRange = null;
      this.presenceQuery = { page: 1, per_page: 20 };
      this.presenceMeta = { current_page: 1, per_page: 20, total: 0, last_page: 1 };
      this.presenceRows = [];
      this.presenceVisible = true;
      this.fetchPresenceRecords(1);
    },
    onPresenceClosed() {
      this.presenceTarget = null;
      this.presenceRows = [];
    },
    onPresenceQuery() {
      this.fetchPresenceRecords(1);
    },
    presenceDurationText(row) {
      if (!row || row.duration_minutes == null) return '—';
      const m = Number(row.duration_minutes);
      if (Number.isNaN(m) || m <= 0) return '—';

      return `${m} 分钟`;
    },
    async fetchPresenceRecords(page) {
      if (!this.presenceTarget || !this.presenceTarget.id) return;
      const p = page != null ? page : this.presenceMeta.current_page || 1;
      this.presenceLoading = true;
      this.presenceQuery.page = p;
      try {
        const params = {
          page: this.presenceQuery.page,
          per_page: this.presenceQuery.per_page,
        };
        if (this.presenceDateRange && this.presenceDateRange.length === 2) {
          params.date_from = this.presenceDateRange[0];
          params.date_to = this.presenceDateRange[1];
        }
        const { data } = await window.axios.get(`/admin/api/users/${this.presenceTarget.id}/presence-records`, {
          params,
        });
        this.presenceRows = data.data || [];
        this.presenceMeta = data.meta || this.presenceMeta;
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '加载出勤记录失败');
      } finally {
        this.presenceLoading = false;
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
      this.form = { account: '', real_name: '', phone: '', password: '' };
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
    assignDateToday() {
      const d = new Date();
      const p = (n) => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
    },
    storeOptionLabel(s) {
      if (!s) return '';
      const name = (s.name || '').trim();
      const code = (s.code || '').trim();
      if (code && name) return `${name}（${code}）`;
      return name || code || `#${s.id}`;
    },
    async loadStoreAssignmentOptions() {
      try {
        const { data } = await window.axios.get('/admin/api/users/store-assignment-options');
        const pack = data.data || {};
        this.storeOptions = pack.stores || [];
        this.storePositionOptions = pack.positions || [];
      } catch (e) {
        this.storeOptions = [];
        this.storePositionOptions = [];
        if (e?.response?.status !== 403) {
          this.$message.error(e?.response?.data?.message || '加载门店/职务失败');
        }
      }
    },
    addStoreAssignRow() {
      const isFirst = !this.storeAssignRows.length;
      this.storeAssignRows.push({
        store_id: null,
        position_id: null,
        is_main: isFirst ? 1 : 0,
        start_date: this.assignDateToday(),
        end_date: '9999-12-31',
      });
    },
    removeStoreAssignRow(index) {
      this.storeAssignRows.splice(index, 1);
      if (this.storeAssignRows.length === 1) {
        this.storeAssignRows[0].is_main = 1;
      } else if (this.storeAssignRows.length && !this.storeAssignRows.some((r) => r.is_main === 1)) {
        this.storeAssignRows[0].is_main = 1;
      }
    },
    onStoreMainChange(row, on) {
      if (on) {
        this.storeAssignRows.forEach((r) => {
          r.is_main = r === row ? 1 : 0;
        });
        return;
      }
      const hadOthers = this.storeAssignRows.some((r) => r !== row && r.is_main === 1);
      if (!hadOthers && this.storeAssignRows.length > 0) {
        this.$message.warning('须保留一个主门店');
        row.is_main = 1;
      } else {
        row.is_main = 0;
      }
    },
    openStoreAssign(row) {
      if (!row || row.is_super_admin) return;
      if (!this.$canPerm('perm.admin.api.users.update')) return;
      this.storeAssignTarget = row;
      this.storeAssignRows = [];
      this.storeAssignVisible = true;
      this.loadStoreAssignmentOptions();
      this.loadUserStoreAssignRows(row.id);
    },
    async loadUserStoreAssignRows(userId) {
      try {
        const { data } = await window.axios.get(`/admin/api/users/${userId}/stores`);
        const list = data.data || [];
        this.storeAssignRows = list.map((s) => ({
          store_id: s.store_id != null ? Number(s.store_id) : null,
          position_id: s.position_id != null ? Number(s.position_id) : null,
          is_main: s.is_main ? 1 : 0,
          start_date: s.start_date || this.assignDateToday(),
          end_date: s.end_date || '9999-12-31',
        }));
        if (!this.storeAssignRows.length) {
          this.addStoreAssignRow();
        }
      } catch (e) {
        this.storeAssignRows = [];
        this.addStoreAssignRow();
        if (e?.response?.status !== 403) {
          this.$message.error(e?.response?.data?.message || '加载门店分配失败');
        }
      }
    },
    onStoreAssignClosed() {
      this.storeAssignTarget = null;
      this.storeAssignRows = [];
    },
    async submitStoreAssign() {
      if (!this.storeAssignTarget) return;
      const rows = this.storeAssignRows.filter((r) => r.store_id != null && r.position_id != null);
      if (rows.length !== this.storeAssignRows.length) {
        this.$message.warning('请完整选择每行的门店与职务，或删除空行');
        return;
      }
      if (rows.length === 0) {
        this.storeAssignSaving = true;
        try {
          await window.axios.put(`/admin/api/users/${this.storeAssignTarget.id}/stores`, { assignments: [] });
          this.$message.success('已清空门店分配');
          this.storeAssignVisible = false;
          this.fetchUsers(this.meta.current_page);
        } catch (e) {
          const msg =
            e?.response?.data?.message ||
            (e?.response?.data?.errors ? Object.values(e.response.data.errors).flat().filter(Boolean).join('；') : null) ||
            '保存失败';
          this.$message.error(msg);
        } finally {
          this.storeAssignSaving = false;
        }
        return;
      }
      const mainCnt = rows.filter((r) => r.is_main === 1).length;
      if (mainCnt !== 1) {
        this.$message.warning('须且仅能指定一个主门店');
        return;
      }
      const assignments = rows.map((r) => ({
        store_id: Number(r.store_id),
        position_id: Number(r.position_id),
        is_main: r.is_main === 1 ? 1 : 0,
        start_date: r.start_date,
        end_date: r.end_date || '9999-12-31',
      }));
      this.storeAssignSaving = true;
      try {
        await window.axios.put(`/admin/api/users/${this.storeAssignTarget.id}/stores`, { assignments });
        this.$message.success('门店分配已更新');
        this.storeAssignVisible = false;
        this.fetchUsers(this.meta.current_page);
      } catch (e) {
        const msg =
          e?.response?.data?.message ||
          (e?.response?.data?.errors ? Object.values(e.response.data.errors).flat().filter(Boolean).join('；') : null) ||
          '保存失败';
        this.$message.error(msg);
      } finally {
        this.storeAssignSaving = false;
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

