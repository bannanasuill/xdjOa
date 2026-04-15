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
          v-model="query.employment_scope"
          clearable
          placeholder="离职范围"
          size="small"
          class="admin-w-140"
          @change="fetchUsers(1)"
        >
          <el-option
            v-for="o in employmentScopeOptions"
            :key="o.value"
            :label="o.label"
            :value="o.value"
          />
        </el-select>
        <el-select
          v-model="query.status"
          clearable
          placeholder="用户状态"
          size="small"
          class="admin-w-140"
          @change="fetchUsers(1)"
        >
          <el-option
            v-for="o in userStatusOptions"
            :key="'st-' + o.value"
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
        <el-button v-if="$canPerm('perm.admin.api.users.store')" type="primary" size="small" @click="openCreate">生成邀请码</el-button>
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
        @selection-change="handleUserSelectionChange"
      >
        <el-table-column
          type="selection"
          width="46"
          fixed="left"
          :selectable="userRowSelectable"
        />
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
        <el-table-column label="状态" width="100" align="center" fixed="right">
          <template slot-scope="{ row }">
            <el-tag size="mini" :type="userStatusTagType(row.status)">{{ row.status_label || '未知' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right" class-name="admin-users-col-actions">
          <template slot-scope="{ row }">
            <el-button
              v-if="$canPerm('perm.admin.api.users.index')"
              size="mini"
              class="admin-mr-6"
              @click="openPresenceViewer(row)"
            >查看出勤</el-button>
            <template v-if="canEditUser(row)">
              <el-button size="mini" @click="openEdit(row)">编辑</el-button>
            </template>
            <span
              v-if="!$canPerm('perm.admin.api.users.index') && (row.is_super_admin || !$canPerm('perm.admin.api.users.update'))"
              class="admin-users-op-empty"
            >—</span>
          </template>
        </el-table-column>
      </el-table>

      <div class="admin-users-table-footer admin-main-dock">
        <div class="admin-users-bulk-actions">
          <template v-if="$canPerm('perm.admin.api.users.update')">
            <el-button
              type="primary"
              plain
              size="small"
              :disabled="bulkAssignableSelectedCount === 0"
              @click="openBulkRoleAssign"
            >
              批量分配角色
            </el-button>
            <el-button
              type="primary"
              plain
              size="small"
              :disabled="bulkAssignableSelectedCount === 0"
              @click="openBulkOrgAssign"
            >
              批量分配职务
            </el-button>
            <el-button
              type="primary"
              plain
              size="small"
              :disabled="bulkAssignableSelectedCount === 0"
              @click="openBulkStoreAssign"
            >
              批量分配店铺
            </el-button>
            <el-button
              v-if="$canPerm('perm.admin.api.users.status')"
              type="primary"
              plain
              size="small"
              :disabled="bulkStatusSelectedCount === 0"
              @click="openBulkStatusDialog"
            >
              批量修改状态
            </el-button>
          </template>
          <el-button
            v-if="$canPerm('perm.admin.api.users.destroy')"
            type="danger"
            plain
            size="small"
            :disabled="bulkDeletableSelectedCount === 0"
            :loading="bulkDeleteSubmitting"
            @click="confirmBulkDeleteUsers"
          >
            批量删除
          </el-button>
        </div>
        <div class="admin-users-table-footer__pager">
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
      </div>
    </el-card>

    <el-dialog :title="formMode === 'create' ? '生成注册邀请码' : '编辑用户'" :visible.sync="formVisible" width="520px">
      <el-form :model="form" label-width="90px" size="small">
        <template v-if="formMode === 'create'">
          <el-form-item label="部门" required>
            <el-select v-model="form.dept_id" filterable placeholder="请选择部门" class="admin-w-full">
              <el-option v-for="d in inviteDeptOptions" :key="d.id" :label="d.name" :value="d.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="职务" required>
            <el-select v-model="form.position_id" filterable placeholder="请选择职务" class="admin-w-full">
              <el-option
                v-for="p in invitePositionOptions"
                :key="p.id"
                :label="orgPositionOptionLabel(p)"
                :value="p.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="店铺" required>
            <el-select v-model="form.store_id" filterable placeholder="请选择店铺" class="admin-w-full">
              <el-option v-for="s in inviteStoreOptions" :key="s.id" :label="storeOptionLabel(s)" :value="s.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="生成数量" required>
            <el-input-number v-model="form.invite_count" :min="1" :max="100" :step="1" />
            <span class="admin-text-muted admin-ml-8">条（1 ~ 100，同批配置与过期时间相同）</span>
          </el-form-item>
          <el-form-item label="注册码时效" required>
            <el-input-number v-model="form.valid_hours" :min="1" :max="720" :step="1" />
            <span class="admin-text-muted admin-ml-8">小时（1 ~ 720）</span>
          </el-form-item>
          <el-form-item label="状态" required>
            <el-radio-group v-model="form.status">
              <el-radio v-for="s in inviteStatusOptions" :key="s.value" :label="s.value">{{ s.label }}</el-radio>
            </el-radio-group>
          </el-form-item>
        </template>
        <template v-else>
          <el-form-item label="姓名" required>
            <el-input v-model="form.real_name" autocomplete="off" />
          </el-form-item>
          <el-form-item label="账号" required>
            <el-input v-model="form.account" autocomplete="off" />
          </el-form-item>
          <el-form-item label="新密码">
            <el-input v-model="form.password" autocomplete="off" placeholder="不修改请留空" />
          </el-form-item>
          <el-form-item label="手机号">
            <el-input v-model="form.phone" autocomplete="off" />
          </el-form-item>
        </template>
      </el-form>
      <span slot="footer" class="admin-dialog-footer">
        <el-button size="small" @click="formVisible=false">取消</el-button>
        <el-button size="small" type="primary" :loading="formSubmitting" @click="submitForm">{{ formMode === 'create' ? '生成' : '保存' }}</el-button>
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
      <p v-if="!roleAssignBulk" class="admin-users-role-hint">可多选：保存后立即生效。</p>
      <p v-else class="admin-users-role-hint">
        将把所选角色<strong>统一写入</strong>每个已勾选用户（覆盖原角色）。已选 {{ bulkAssignableSelectedCount }} 人。
      </p>
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
      <p v-if="!orgAssignBulk" class="admin-users-role-hint">
        职务多选；每个职务归属固定部门，保存时会自动同步对应部门（全量覆盖）。
      </p>
      <p v-else class="admin-users-role-hint">
        将把所选职务<strong>统一写入</strong>每个已勾选用户（全量覆盖）。已选 {{ bulkAssignableSelectedCount }} 人。
      </p>
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
      <p v-if="!storeAssignBulk" class="admin-users-role-hint">
        可分配多个门店；每人须恰好一个「主门店」，其余为支援。每行选择门店与在该门店担任的职务，并设置任职起止日期。
      </p>
      <p v-else class="admin-users-role-hint">
        下方门店配置将<strong>统一套用</strong>到每个已勾选用户（全量覆盖原门店分配）。已选 {{ bulkAssignableSelectedCount }} 人。
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

    <el-dialog
      title="批量修改状态"
      :visible.sync="bulkStatusVisible"
      width="480px"
      :close-on-click-modal="false"
      @closed="onBulkStatusDialogClosed"
    >
      <p class="admin-text-muted" style="margin: 0 0 12px;">
        将把所选 <strong>{{ bulkStatusSelectedCount }}</strong> 个用户的状态统一修改为下方选项。
      </p>
      <el-form label-width="88px" size="small">
        <el-form-item label="目标状态" required>
          <el-select v-model="bulkStatusTarget" filterable placeholder="请选择状态" class="admin-w-full">
            <el-option v-for="o in userStatusOptions" :key="o.value" :label="o.label" :value="o.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="备注" required>
          <el-input v-model="bulkStatusRemark" type="textarea" :rows="4" placeholder="请输入变更原因或备注（必填）" />
        </el-form-item>
      </el-form>
      <span slot="footer" class="admin-dialog-footer">
        <el-button size="small" @click="bulkStatusVisible = false">取消</el-button>
        <el-button size="small" type="primary" :loading="bulkStatusSubmitting" @click="submitBulkStatus">确定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import adminTableFixedHeader from '../mixins/adminTableFixedHeader';
import { ensureAdminPermissions } from '../permissions';

export default {
  mixins: [adminTableFixedHeader],
  data() {
    return {
      currentUserId: null,
      currentUserIsSuperAdmin: false,
      loading: false,
      rows: [],
      meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
      query: { q: '', role_id: null, presence_today: null, position_id: null, status: null, employment_scope: null, per_page: 20 },
      roleFilterOptions: [],
      positionFilterOptions: [],
      presenceFilterOptions: [
        { value: 'not_arrived', label: '未到岗' },
        { value: 'present', label: '到岗' },
        { value: 'outing', label: '外出' },
        { value: 'off_work', label: '下班' },
        { value: 'unknown', label: '其他' },
      ],
      employmentScopeOptions: [
        { value: 'not_left', label: '未离职' },
        { value: 'left', label: '已离职' },
      ],
      perPageOptions: [10, 20, 50, 100],
      /** 与 UserModel::employmentStatusOptions 一致，由列表接口 options.status_options 下发 */
      userStatusOptions: [],

      bulkStatusVisible: false,
      bulkStatusTarget: 1,
      bulkStatusRemark: '',
      bulkStatusSubmitting: false,

      // create/edit
      formVisible: false,
      formSubmitting: false,
      formMode: 'create',
      editingId: null,
      roleOptions: [],
      inviteDeptOptions: [],
      invitePositionOptions: [],
      inviteStoreOptions: [],
      inviteStatusOptions: [],
      form: {
        account: '',
        real_name: '',
        phone: '',
        password: '',
        role_ids: [],
        dept_id: null,
        position_id: null,
        store_id: null,
        invite_count: 1,
        valid_hours: 24,
        status: 1,
      },

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

      selectedUserRows: [],
      bulkDeleteSubmitting: false,

      roleAssignBulk: false,
      orgAssignBulk: false,
      storeAssignBulk: false,
    };
  },
  computed: {
    presenceDialogTitle() {
      const t = this.presenceTarget;
      if (!t) return '出勤记录';
      const name = (t.real_name && String(t.real_name).trim()) || t.account || '';

      return name ? `出勤记录 — ${name}` : '出勤记录';
    },
    bulkAssignableSelectedCount() {
      if (!this.$canPerm('perm.admin.api.users.update')) return 0;
      return (this.selectedUserRows || []).filter((r) => r && this.canOperateSuperAdmin(r)).length;
    },
    bulkDeletableSelectedCount() {
      if (!this.$canPerm('perm.admin.api.users.destroy')) return 0;
      const selfId = this.currentUserId;
      return (this.selectedUserRows || []).filter(
        (r) => r && !r.is_super_admin && (selfId == null || Number(selfId) !== Number(r.id))
      ).length;
    },
    /** 可批量改状态：已勾选且当前操作者有权操作 */
    bulkStatusSelectedCount() {
      if (!this.$canPerm('perm.admin.api.users.status')) return 0;
      return (this.selectedUserRows || []).filter((r) => r && this.canOperateSuperAdmin(r)).length;
    },
    orgAssignTitle() {
      if (this.orgAssignBulk) {
        const n = this.bulkAssignableSelectedCount;
        return n ? `批量分配职务 — ${n} 人` : '批量分配职务';
      }
      const a = this.orgAssignTarget && this.orgAssignTarget.real_name;
      return a ? `分配职务 — ${a}` : '分配职务';
    },
    roleAssignTitle() {
      if (this.roleAssignBulk) {
        const n = this.bulkAssignableSelectedCount;
        return n ? `批量分配角色 — ${n} 人` : '批量分配角色';
      }
      const a = this.roleAssignTarget && this.roleAssignTarget.real_name;
      return a ? `分配角色 — ${a}` : '分配角色';
    },
    storeAssignTitle() {
      if (this.storeAssignBulk) {
        const n = this.bulkAssignableSelectedCount;
        return n ? `批量分配店铺 — ${n} 人` : '批量分配店铺';
      }
      const a = this.storeAssignTarget && this.storeAssignTarget.real_name;
      return a ? `分配店铺 — ${a}` : '分配店铺';
    },
  },
  created() {
    ensureAdminPermissions().then((d) => {
      if (d && d.id != null) {
        this.currentUserId = Number(d.id);
      }
      this.currentUserIsSuperAdmin = !!(d && d.is_super_admin);
    });
    this.applyRouteQueryFilters(this.$route.query);
    this.loadRoleFilterOptions();
    this.loadPositionFilterOptions();
    this.fetchUsers(1);
  },
  watch: {
    '$route.query': {
      deep: true,
      handler(nextQuery) {
        this.applyRouteQueryFilters(nextQuery);
        this.fetchUsers(1);
      },
    },
  },
  methods: {
    applyRouteQueryFilters(routeQuery) {
      const q = routeQuery || {};
      this.query.q = typeof q.q === 'string' ? q.q : '';
      this.query.role_id = q.role_id != null && q.role_id !== '' ? Number(q.role_id) : null;
      this.query.position_id = q.position_id != null && q.position_id !== '' ? Number(q.position_id) : null;
      this.query.presence_today = typeof q.presence_today === 'string' && q.presence_today !== '' ? q.presence_today : null;
      this.query.status = q.status != null && q.status !== '' ? Number(q.status) : null;
      const scope = typeof q.employment_scope === 'string' && q.employment_scope !== '' ? q.employment_scope : null;
      this.query.employment_scope = scope === 'left' || scope === 'not_left' ? scope : null;
      this.query.per_page = q.per_page != null && q.per_page !== '' ? Number(q.per_page) : 20;
      if (!this.perPageOptions.includes(this.query.per_page)) {
        this.query.per_page = 20;
      }
    },
    canOperateSuperAdmin(row) {
      if (!row || !row.is_super_admin) return true;
      return !!this.currentUserIsSuperAdmin;
    },
    canEditUser(row) {
      return !!row && this.$canPerm('perm.admin.api.users.update') && this.canOperateSuperAdmin(row);
    },
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
    userRowSelectable(row) {
      if (!row) return false;
      const isSelf = this.currentUserId != null && Number(row.id) === Number(this.currentUserId);
      const canUpdate = this.$canPerm('perm.admin.api.users.update');
      const canDestroy = this.$canPerm('perm.admin.api.users.destroy');
      const canStatus = this.$canPerm('perm.admin.api.users.status');
      if (canUpdate) return true;
      if (!this.canOperateSuperAdmin(row)) return false;
      if (canDestroy && !isSelf) return true;
      if (canStatus) return true;
      return false;
    },
    selectedAssignableUsers() {
      return (this.selectedUserRows || []).filter((r) => !!r);
    },
    selectedDeletableUsers() {
      if (!this.$canPerm('perm.admin.api.users.destroy')) return [];
      const selfId = this.currentUserId;
      return (this.selectedUserRows || []).filter(
        (r) => r && this.canOperateSuperAdmin(r) && (selfId == null || Number(r.id) !== Number(selfId))
      );
    },
    handleUserSelectionChange(selection) {
      this.selectedUserRows = Array.isArray(selection) ? selection : [];
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
        if (this.query.status != null && this.query.status !== '') {
          params.status = this.query.status;
        }
        if (this.query.employment_scope != null && this.query.employment_scope !== '') {
          params.employment_scope = this.query.employment_scope;
        }
        const { data } = await window.axios.get('/admin/api/users', { params });
        const statusRaw = (data.options && data.options.status_options) || {};
        this.userStatusOptions = Object.keys(statusRaw).map((k) => ({
          value: Number(k),
          label: String(statusRaw[k] || ''),
        }));
        if (!this.userStatusOptions.length) {
          this.userStatusOptions = [
            { value: 0, label: '离职' },
            { value: 1, label: '在职' },
            { value: 2, label: '试岗' },
            { value: 3, label: '试用' },
          ];
        }
        this.rows = (data.data || []).map((u) => ({
          ...u,
          is_super_admin: !!u.is_super_admin,
        }));
        this.meta = data.meta || this.meta;
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '加载失败');
      } finally {
        this.loading = false;
        this.$nextTick(() => {
          this.syncAdminTableMaxHeight();
          const tb = this.$refs.adminDataTable;
          if (tb && typeof tb.clearSelection === 'function') {
            tb.clearSelection();
          }
          this.selectedUserRows = [];
        });
      }
    },
    reset() {
      this.query.q = '';
      this.query.role_id = null;
      this.query.presence_today = null;
      this.query.position_id = null;
      this.query.status = null;
      this.query.employment_scope = null;
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
      this.form = {
        account: '',
        real_name: '',
        phone: '',
        password: '',
        role_ids: [],
        dept_id: null,
        position_id: null,
        store_id: null,
        invite_count: 1,
        valid_hours: 24,
        status: 1,
      };
      this.formVisible = true;
      this.loadInviteOptions();
    },
    openEdit(row) {
      if (!this.canOperateSuperAdmin(row)) {
        this.$message.warning('仅超级管理员可编辑超级管理员账号');
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
      if (!row) {
        return;
      }
      if (!this.$canPerm('perm.admin.api.users.update')) {
        return;
      }
      this.roleAssignBulk = false;
      this.roleAssignTarget = row;
      this.roleAssignIds = (row.roles || []).map((r) => r.id).filter((id) => id != null);
      this.roleAssignVisible = true;
      this.loadRoleOptions();
    },
    openBulkRoleAssign() {
      if (!this.$canPerm('perm.admin.api.users.update')) return;
      const rows = this.selectedAssignableUsers();
      if (!rows.length) {
        this.$message.warning('请先勾选需要分配角色的用户');
        return;
      }
      this.roleAssignBulk = true;
      this.roleAssignTarget = rows[0];
      this.roleAssignIds = [];
      this.roleAssignVisible = true;
      this.loadRoleOptions();
    },
    onRoleAssignClosed() {
      this.roleAssignBulk = false;
      this.roleAssignTarget = null;
      this.roleAssignIds = [];
    },
    async submitRoleAssign() {
      const roleIds = Array.isArray(this.roleAssignIds) ? this.roleAssignIds : [];
      if (this.roleAssignBulk) {
        const rows = this.selectedAssignableUsers();
        if (!rows.length) return;
        this.roleAssignSaving = true;
        let ok = 0;
        let fail = 0;
        try {
          for (let i = 0; i < rows.length; i += 1) {
            const row = rows[i];
            try {
              await window.axios.patch(`/admin/api/users/${row.id}/roles`, { role_ids: roleIds });
              ok += 1;
            } catch (e) {
              fail += 1;
            }
          }
          if (ok > 0) {
            this.$message.success(`已为 ${ok} 个用户更新角色`);
          }
          if (fail > 0) {
            this.$message.warning(`${fail} 个用户保存失败`);
          }
          this.roleAssignVisible = false;
          this.fetchUsers(this.meta.current_page);
        } finally {
          this.roleAssignSaving = false;
        }
        return;
      }
      if (!this.roleAssignTarget) return;
      this.roleAssignSaving = true;
      try {
        await window.axios.patch(`/admin/api/users/${this.roleAssignTarget.id}/roles`, {
          role_ids: roleIds,
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
      if (!row) {
        return;
      }
      if (!this.$canPerm('perm.admin.api.users.update')) {
        return;
      }
      this.orgAssignBulk = false;
      this.orgAssignTarget = row;
      this.orgAssignPositionIds = (row.positions || []).map((p) => p.id).filter((id) => id != null);
      this.orgAssignVisible = true;
      this.loadOrgOptions();
    },
    openBulkOrgAssign() {
      if (!this.$canPerm('perm.admin.api.users.update')) return;
      const rows = this.selectedAssignableUsers();
      if (!rows.length) {
        this.$message.warning('请先勾选需要分配职务的用户');
        return;
      }
      this.orgAssignBulk = true;
      this.orgAssignTarget = rows[0];
      this.orgAssignPositionIds = [];
      this.orgAssignVisible = true;
      this.loadOrgOptions();
    },
    onOrgAssignClosed() {
      this.orgAssignBulk = false;
      this.orgAssignTarget = null;
      this.orgAssignPositionIds = [];
    },
    async submitOrgAssign() {
      const positionIds = Array.isArray(this.orgAssignPositionIds) ? this.orgAssignPositionIds : [];
      if (this.orgAssignBulk) {
        const rows = this.selectedAssignableUsers();
        if (!rows.length) return;
        this.orgAssignSaving = true;
        let ok = 0;
        let fail = 0;
        try {
          for (let i = 0; i < rows.length; i += 1) {
            const row = rows[i];
            try {
              await window.axios.patch(`/admin/api/users/${row.id}/org`, {
                dept_ids: [],
                position_ids: positionIds,
              });
              ok += 1;
            } catch (e) {
              fail += 1;
            }
          }
          if (ok > 0) {
            this.$message.success(`已为 ${ok} 个用户更新职务`);
          }
          if (fail > 0) {
            this.$message.warning(`${fail} 个用户保存失败`);
          }
          this.orgAssignVisible = false;
          this.fetchUsers(this.meta.current_page);
        } finally {
          this.orgAssignSaving = false;
        }
        return;
      }
      if (!this.orgAssignTarget) return;
      this.orgAssignSaving = true;
      try {
        await window.axios.patch(`/admin/api/users/${this.orgAssignTarget.id}/org`, {
          dept_ids: [],
          position_ids: positionIds,
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
      if (!row) return;
      if (!this.$canPerm('perm.admin.api.users.update')) return;
      this.storeAssignBulk = false;
      this.storeAssignTarget = row;
      this.storeAssignRows = [];
      this.storeAssignVisible = true;
      this.loadStoreAssignmentOptions();
      this.loadUserStoreAssignRows(row.id);
    },
    openBulkStoreAssign() {
      if (!this.$canPerm('perm.admin.api.users.update')) return;
      const rows = this.selectedAssignableUsers();
      if (!rows.length) {
        this.$message.warning('请先勾选需要分配店铺的用户');
        return;
      }
      this.storeAssignBulk = true;
      this.storeAssignTarget = null;
      this.storeAssignRows = [];
      this.storeAssignVisible = true;
      this.loadStoreAssignmentOptions();
      this.addStoreAssignRow();
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
      this.storeAssignBulk = false;
      this.storeAssignTarget = null;
      this.storeAssignRows = [];
    },
    async submitStoreAssign() {
      const validRows = this.storeAssignRows.filter((r) => r.store_id != null && r.position_id != null);
      if (validRows.length !== this.storeAssignRows.length) {
        this.$message.warning('请完整选择每行的门店与职务，或删除空行');
        return;
      }
      const targets = this.storeAssignBulk ? this.selectedAssignableUsers() : this.storeAssignTarget ? [this.storeAssignTarget] : [];
      if (!targets.length) return;

      if (validRows.length === 0) {
        this.storeAssignSaving = true;
        let ok = 0;
        let fail = 0;
        try {
          for (let i = 0; i < targets.length; i += 1) {
            const u = targets[i];
            try {
              await window.axios.put(`/admin/api/users/${u.id}/stores`, { assignments: [] });
              ok += 1;
            } catch (e) {
              fail += 1;
            }
          }
          if (ok > 0) {
            this.$message.success(this.storeAssignBulk ? `已为 ${ok} 个用户清空门店分配` : '已清空门店分配');
          }
          if (fail > 0) {
            this.$message.warning(`${fail} 个用户保存失败`);
          }
          this.storeAssignVisible = false;
          this.fetchUsers(this.meta.current_page);
        } finally {
          this.storeAssignSaving = false;
        }
        return;
      }
      const mainCnt = validRows.filter((r) => r.is_main === 1).length;
      if (mainCnt !== 1) {
        this.$message.warning('须且仅能指定一个主门店');
        return;
      }
      const assignments = validRows.map((r) => ({
        store_id: Number(r.store_id),
        position_id: Number(r.position_id),
        is_main: r.is_main === 1 ? 1 : 0,
        start_date: r.start_date,
        end_date: r.end_date || '9999-12-31',
      }));
      this.storeAssignSaving = true;
      let ok = 0;
      let fail = 0;
      try {
        for (let i = 0; i < targets.length; i += 1) {
          const u = targets[i];
          try {
            await window.axios.put(`/admin/api/users/${u.id}/stores`, { assignments });
            ok += 1;
          } catch (e) {
            fail += 1;
          }
        }
        if (ok > 0) {
          this.$message.success(this.storeAssignBulk ? `已为 ${ok} 个用户更新门店分配` : '门店分配已更新');
        }
        if (fail > 0) {
          this.$message.warning(`${fail} 个用户保存失败`);
        }
        this.storeAssignVisible = false;
        this.fetchUsers(this.meta.current_page);
      } finally {
        this.storeAssignSaving = false;
      }
    },
    async submitForm() {
      if (this.formMode === 'create') {
        if (!this.form.dept_id) {
          this.$message.warning('请选择部门');
          return;
        }
        if (!this.form.position_id) {
          this.$message.warning('请选择职务');
          return;
        }
        if (!this.form.store_id) {
          this.$message.warning('请选择店铺');
          return;
        }
        if (!this.form.valid_hours || Number(this.form.valid_hours) < 1) {
          this.$message.warning('请输入有效时长（小时）');
          return;
        }
        const ic = Number(this.form.invite_count);
        if (!Number.isFinite(ic) || ic < 1 || ic > 100) {
          this.$message.warning('生成数量须在 1 ~ 100 之间');
          return;
        }
      } else if (!this.form.account || !this.form.real_name) {
        this.$message.warning('请填写账号和姓名');
        return;
      }
      this.formSubmitting = true;
      try {
        if (this.formMode === 'create') {
          const payload = {
            dept_id: Number(this.form.dept_id),
            position_id: Number(this.form.position_id),
            store_id: Number(this.form.store_id),
            valid_hours: Number(this.form.valid_hours),
            status: Number(this.form.status),
            count: Number(this.form.invite_count),
          };
          const { data } = await window.axios.post('/admin/api/users', payload);
          const pack = data?.data || {};
          const expTs = Number(pack.expires_at || 0);
          const expTxt = expTs > 0 ? this.formatTs(expTs) : '—';
          const list =
            Array.isArray(pack.invites) && pack.invites.length
              ? pack.invites
              : pack.code
                ? [{ code: pack.code, expires_at: expTs }]
                : [];
          const total = Number(pack.count || list.length) || list.length;
          const maxShow = 30;
          const head = list.slice(0, maxShow).map((x) => String(x.code || '').trim()).filter(Boolean);
          let body = `共 ${total} 条，同批有效至：${expTxt}\n\n${head.join('\n')}`;
          if (total > maxShow) {
            body += `\n\n… 另有 ${total - maxShow} 条请前往「用户管理 → 邀请列表」查看或复制。`;
          }
          body += '\n\n请复制并发送给待注册用户。';
          await this.$alert(body, total > 1 ? '邀请码已批量生成' : '邀请码已生成', {
            confirmButtonText: '我知道了',
          });
          this.$message.success(total > 1 ? `已生成 ${total} 条邀请码` : '邀请码已生成');
        } else {
          const accTrim = (this.form.account && String(this.form.account).trim()) || '';
          const payload = {
            real_name: this.form.real_name,
            phone: this.form.phone,
            account: accTrim,
            password: this.form.password,
          };
          await window.axios.put(`/admin/api/users/${this.editingId}`, payload);
          this.$message.success('用户信息已更新');
        }
        this.formVisible = false;
        this.fetchUsers(this.meta.current_page || 1);
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
    async loadInviteOptions() {
      try {
        const { data } = await window.axios.get('/admin/api/users/invite-options');
        const pack = data?.data || {};
        this.inviteDeptOptions = pack.departments || [];
        this.invitePositionOptions = pack.positions || [];
        this.inviteStoreOptions = pack.stores || [];
        const statusOptions = pack.status_options || {};
        this.inviteStatusOptions = Object.keys(statusOptions).map((k) => ({
          value: Number(k),
          label: String(statusOptions[k] || ''),
        }));
        if (!this.inviteStatusOptions.length) {
          this.inviteStatusOptions = [
            { value: 1, label: '在职' },
            { value: 2, label: '试岗' },
            { value: 3, label: '试用' },
            { value: 0, label: '离职' },
          ];
        }
      } catch (e) {
        this.inviteDeptOptions = [];
        this.invitePositionOptions = [];
        this.inviteStoreOptions = [];
        this.inviteStatusOptions = [];
        this.$message.error(e?.response?.data?.message || '加载邀请码选项失败');
      }
    },
    async confirmBulkDeleteUsers() {
      if (!this.$canPerm('perm.admin.api.users.destroy')) return;
      const rows = this.selectedDeletableUsers();
      if (!rows.length) {
        this.$message.warning('请先勾选可删除的用户（不含当前登录账号）');
        return;
      }
      try {
        await this.$confirm(
          `将永久删除已选 ${rows.length} 个用户及其关联数据（角色、门店、出勤、报销单等），且不可恢复。确认删除？`,
          '批量删除用户',
          { type: 'warning', confirmButtonText: '删除', cancelButtonText: '取消' }
        );
      } catch (e) {
        return;
      }
      this.bulkDeleteSubmitting = true;
      let ok = 0;
      let fail = 0;
      const failNames = [];
      try {
        for (let i = 0; i < rows.length; i += 1) {
          const row = rows[i];
          try {
            await window.axios.delete(`/admin/api/users/${row.id}`);
            ok += 1;
          } catch (e) {
            fail += 1;
            const label = (row && (row.real_name || row.account)) || (row && row.id) || '';
            failNames.push(String(label));
          }
        }
        if (ok > 0) {
          this.$message.success(`已删除 ${ok} 个用户`);
        }
        if (fail > 0) {
          const hint = failNames.length ? `（如：${failNames.slice(0, 3).join('、')}）` : '';
          this.$message.warning(`${fail} 个用户删除失败${hint}`);
        }
        this.fetchUsers(this.meta.current_page);
      } finally {
        this.bulkDeleteSubmitting = false;
      }
    },
    userStatusTagType(status) {
      const n = Number(status);
      if (n === 1) return 'success';
      if (n === 0) return 'info';
      return '';
    },
    openBulkStatusDialog() {
      if (!this.$canPerm('perm.admin.api.users.status')) return;
      if (this.bulkStatusSelectedCount === 0) {
        this.$message.warning('请先勾选用户');
        return;
      }
      this.bulkStatusTarget = 1;
      this.bulkStatusRemark = '';
      this.bulkStatusVisible = true;
    },
    onBulkStatusDialogClosed() {
      this.bulkStatusRemark = '';
    },
    async submitBulkStatus() {
      if (!this.$canPerm('perm.admin.api.users.status')) return;
      const rows = this.selectedAssignableUsers();
      if (!rows.length) {
        this.$message.warning('请先勾选用户');
        return;
      }
      const remark = (this.bulkStatusRemark || '').trim();
      if (!remark) {
        this.$message.warning('请输入备注');
        return;
      }
      const targetStatus = Number(this.bulkStatusTarget);
      if (Number.isNaN(targetStatus)) {
        this.$message.warning('请选择目标状态');
        return;
      }
      this.bulkStatusSubmitting = true;
      let ok = 0;
      let fail = 0;
      const failNames = [];
      try {
        for (let i = 0; i < rows.length; i += 1) {
          const row = rows[i];
          try {
            await window.axios.patch(`/admin/api/users/${row.id}/status`, {
              status: targetStatus,
              status_remark: remark,
            });
            ok += 1;
          } catch (e) {
            fail += 1;
            const label = (row && (row.real_name || row.account)) || (row && row.id) || '';
            failNames.push(String(label));
          }
        }
        if (ok > 0) {
          this.$message.success(`已更新 ${ok} 个用户状态`);
        }
        if (fail > 0) {
          const hint = failNames.length ? `（如：${failNames.slice(0, 3).join('、')}）` : '';
          this.$message.warning(`${fail} 个用户更新失败${hint}`);
        }
        this.bulkStatusVisible = false;
        this.fetchUsers(this.meta.current_page);
      } finally {
        this.bulkStatusSubmitting = false;
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

.admin-users-table-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 12px;
  flex-wrap: wrap;
}
/* 与全局 .admin-main-dock 组合：去掉卡片内上边距，避免与 fixed 条叠出双空白 */
.admin-users-table-footer.admin-main-dock {
  margin-top: 0;
}
.admin-users-bulk-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  flex: 1 1 auto;
  min-width: 0;
}
.admin-users-table-footer__pager {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  flex: 0 0 auto;
}

</style>

