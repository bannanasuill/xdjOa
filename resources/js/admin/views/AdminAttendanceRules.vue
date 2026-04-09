<template>
  <div class="admin-attendance-rules-page">
    <el-card class="admin-mb-12 admin-page-filters">
      <div class="admin-form-row">
        <el-input
          v-model="query.q"
          placeholder="搜索门店 / 职务名称"
          clearable
          size="small"
          class="admin-w-240"
          @keyup.enter.native="fetchList"
        />
        <el-button size="small" type="primary" @click="fetchList">查询</el-button>
        <el-button size="small" @click="resetQuery">重置</el-button>
        <span class="admin-flex-spacer"></span>
        <el-button
          v-if="$canPerm('perm.admin.api.attendance_rules.store')"
          type="primary"
          size="small"
          @click="openCreate"
        >
          新增规则
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
        row-key="id"
      >
        <el-table-column label="门店范围" min-width="140" show-overflow-tooltip>
          <template slot-scope="{ row }">{{ scopeStoreLabel(row) }}</template>
        </el-table-column>
        <el-table-column label="职务范围" min-width="140" show-overflow-tooltip>
          <template slot-scope="{ row }">{{ scopePositionLabel(row) }}</template>
        </el-table-column>
        <el-table-column label="上班" width="72" align="center">
          <template slot-scope="{ row }">{{ row.work_start_time || '—' }}</template>
        </el-table-column>
        <el-table-column label="下班" width="72" align="center">
          <template slot-scope="{ row }">{{ row.work_end_time || '—' }}</template>
        </el-table-column>
        <el-table-column label="容忍(迟/早)" width="108" align="center">
          <template slot-scope="{ row }">
            {{ row.late_minutes != null ? row.late_minutes : 0 }} / {{ row.early_minutes != null ? row.early_minutes : 0 }} 分
          </template>
        </el-table-column>
        <el-table-column label="远程" width="56" align="center">
          <template slot-scope="{ row }">{{ row.allow_remote === 1 ? '是' : '否' }}</template>
        </el-table-column>
        <el-table-column label="拍照" width="56" align="center">
          <template slot-scope="{ row }">{{ row.need_photo === 1 ? '是' : '否' }}</template>
        </el-table-column>
        <el-table-column prop="priority" label="优先级" width="72" align="center" />
        <el-table-column label="状态" width="88" align="center">
          <template slot-scope="{ row }">
            <el-switch
              v-if="$canPerm('perm.admin.api.attendance_rules.status')"
              class="admin-status-switch"
              :value="row.status === 1"
              :active-color="'#13ce66'"
              :inactive-color="'#f56c6c'"
              :disabled="statusBusyId === row.id"
              @change="(on) => patchStatus(row, on ? 1 : 0)"
            />
            <span v-else>{{ row.status === 1 ? '启用' : '停用' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="148" fixed="right" align="left">
          <template slot-scope="{ row }">
            <div class="admin-dept-actions">
              <el-button v-if="$canPerm('perm.admin.api.attendance_rules.update')" size="mini" @click="openEdit(row)">
                编辑
              </el-button>
              <el-button
                v-if="$canPerm('perm.admin.api.attendance_rules.destroy')"
                type="danger"
                plain
                size="mini"
                :disabled="deleteBusyId === row.id"
                @click="confirmDelete(row)"
              >删除</el-button>
            </div>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog :title="formMode === 'create' ? '新增考勤规则' : '编辑考勤规则'" :visible.sync="formVisible" width="520px" @closed="onFormClosed">
      <el-form :model="form" label-width="120px" size="small">
        <el-form-item label="适用门店">
          <el-select v-model="form.store_id" clearable filterable placeholder="留空 = 全局默认" style="width: 100%">
            <el-option v-for="s in storeOptions" :key="s.id" :label="s.label" :value="s.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="适用职务">
          <el-select v-model="form.position_id" clearable filterable placeholder="留空 = 所有职务" style="width: 100%">
            <el-option v-for="p in positionOptions" :key="p.id" :label="p.label" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="上班时间" required>
          <el-input
            v-model="form.work_start_time"
            maxlength="8"
            placeholder="如 09:00:00 或 09:00"
            clearable
          />
        </el-form-item>
        <el-form-item label="下班时间" required>
          <el-input
            v-model="form.work_end_time"
            maxlength="8"
            placeholder="如 18:00:00 或 18:00"
            clearable
          />
        </el-form-item>
        <el-form-item label="迟到容忍(分)">
          <el-input-number v-model="form.late_minutes" :min="0" :max="1440" :controls="false" style="width: 100%" />
        </el-form-item>
        <el-form-item label="早退容忍(分)">
          <el-input-number v-model="form.early_minutes" :min="0" :max="1440" :controls="false" style="width: 100%" />
        </el-form-item>
        <el-form-item label="允许远程打卡">
          <el-switch v-model="form.allow_remote_on" active-text="允许" inactive-text="须门店定位" />
        </el-form-item>
        <el-form-item label="拍照打卡">
          <el-switch v-model="form.need_photo_on" active-text="需要" inactive-text="不需要" />
        </el-form-item>
        <el-form-item label="优先级">
          <el-input-number v-model="form.priority" :min="0" :max="999999" :controls="false" style="width: 100%" />
          <div class="admin-form-hint">数字越小越优先匹配</div>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.statusOn" active-text="启用" inactive-text="停用" />
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

const emptyForm = () => ({
  store_id: null,
  position_id: null,
  work_start_time: '09:00:00',
  work_end_time: '18:00:00',
  late_minutes: 30,
  early_minutes: 30,
  allow_remote_on: false,
  need_photo_on: true,
  priority: 0,
  statusOn: true,
});

export default {
  name: 'AdminAttendanceRules',
  mixins: [adminTableFixedHeader],
  data() {
    return {
      loading: false,
      rows: [],
      query: { q: '' },
      storeOptions: [],
      positionOptions: [],
      formVisible: false,
      formMode: 'create',
      editingId: null,
      form: emptyForm(),
      formSubmitting: false,
      statusBusyId: null,
      deleteBusyId: null,
    };
  },
  created() {
    this.fetchList();
    if (this.$canPerm('perm.admin.api.attendance_rules.form_options')) {
      this.loadFormOptions();
    }
  },
  methods: {
    scopeStoreLabel(row) {
      if (row.store_id == null) return '全局默认';
      const n = (row.store_name || '').trim();
      const c = (row.store_code || '').trim();
      if (n && c) return `${n}（${c}）`;
      return n || c || `门店 #${row.store_id}`;
    },
    scopePositionLabel(row) {
      if (row.position_id == null) return '全部职务';
      const n = (row.position_name || '').trim();
      const d = (row.dept_name || '').trim();
      if (n && d) return `${n}（${d}）`;
      return n || `职务 #${row.position_id}`;
    },
    normalizeTimeInput(raw) {
      const s = raw != null ? String(raw).trim() : '';
      if (!s) return '';
      if (/^\d{1,2}:\d{2}$/.test(s)) {
        const [h, m] = s.split(':');
        return `${String(h).padStart(2, '0')}:${m}:00`;
      }
      if (/^\d{1,2}:\d{2}:\d{2}$/.test(s)) {
        const [h, m, sec] = s.split(':');
        return `${String(h).padStart(2, '0')}:${m}:${sec}`;
      }

      return '';
    },
    resetQuery() {
      this.query.q = '';
      this.fetchList();
    },
    async loadFormOptions() {
      try {
        const { data } = await window.axios.get('/admin/api/attendance-rules/form-options');
        const pack = data.data || {};
        this.storeOptions = pack.stores || [];
        this.positionOptions = pack.positions || [];
      } catch (e) {
        this.storeOptions = [];
        this.positionOptions = [];
      }
    },
    async fetchList() {
      if (!this.$canPerm('perm.admin.api.attendance_rules.index')) {
        return;
      }
      this.loading = true;
      try {
        const q = (this.query.q || '').trim();
        const { data } = await window.axios.get('/admin/api/attendance-rules', {
          params: q ? { q } : {},
        });
        this.rows = data.data || [];
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '加载失败');
      } finally {
        this.loading = false;
        this.$nextTick(() => this.syncAdminTableMaxHeight());
      }
    },
    openCreate() {
      if (!this.$canPerm('perm.admin.api.attendance_rules.store')) return;
      this.formMode = 'create';
      this.editingId = null;
      this.form = emptyForm();
      this.ensureFormOptions();
      this.formVisible = true;
    },
    openEdit(row) {
      if (!row || !this.$canPerm('perm.admin.api.attendance_rules.update')) return;
      this.formMode = 'edit';
      this.editingId = row.id;
      const ws = row.work_start_time != null ? String(row.work_start_time) : '09:00:00';
      const we = row.work_end_time != null ? String(row.work_end_time) : '18:00:00';
      this.form = {
        store_id: row.store_id != null ? Number(row.store_id) : null,
        position_id: row.position_id != null ? Number(row.position_id) : null,
        work_start_time: ws.length === 5 ? `${ws}:00` : ws,
        work_end_time: we.length === 5 ? `${we}:00` : we,
        late_minutes: row.late_minutes != null ? Number(row.late_minutes) : 30,
        early_minutes: row.early_minutes != null ? Number(row.early_minutes) : 30,
        allow_remote_on: row.allow_remote === 1,
        need_photo_on: row.need_photo === 1,
        priority: row.priority != null ? Number(row.priority) : 0,
        statusOn: row.status === 1,
      };
      this.ensureFormOptions();
      this.formVisible = true;
    },
    ensureFormOptions() {
      if ((this.storeOptions || []).length || (this.positionOptions || []).length) return;
      if (this.$canPerm('perm.admin.api.attendance_rules.form_options')) {
        this.loadFormOptions();
      }
    },
    onFormClosed() {
      this.editingId = null;
    },
    async submitForm() {
      const ws = this.normalizeTimeInput(this.form.work_start_time);
      const we = this.normalizeTimeInput(this.form.work_end_time);
      if (!ws || !we) {
        this.$message.warning('请填写有效的上班与下班时间（HH:mm 或 HH:mm:ss）');
        return;
      }
      this.form.work_start_time = ws;
      this.form.work_end_time = we;
      const payload = {
        store_id: this.form.store_id != null && this.form.store_id !== '' ? Number(this.form.store_id) : null,
        position_id: this.form.position_id != null && this.form.position_id !== '' ? Number(this.form.position_id) : null,
        work_start_time: ws,
        work_end_time: we,
        late_minutes: this.form.late_minutes != null ? Number(this.form.late_minutes) : 30,
        early_minutes: this.form.early_minutes != null ? Number(this.form.early_minutes) : 30,
        allow_remote: this.form.allow_remote_on ? 1 : 0,
        need_photo: this.form.need_photo_on ? 1 : 0,
        priority: this.form.priority != null ? Number(this.form.priority) : 0,
        status: this.form.statusOn ? 1 : 0,
      };
      this.formSubmitting = true;
      try {
        if (this.formMode === 'create') {
          await window.axios.post('/admin/api/attendance-rules', payload);
          this.$message.success('已创建');
        } else {
          await window.axios.put(`/admin/api/attendance-rules/${this.editingId}`, payload);
          this.$message.success('已保存');
        }
        this.formVisible = false;
        this.fetchList();
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
      if (!this.$canPerm('perm.admin.api.attendance_rules.status')) return;
      const prev = row.status;
      this.statusBusyId = row.id;
      row.status = status;
      try {
        await window.axios.patch(`/admin/api/attendance-rules/${row.id}/status`, { status });
        this.$message.success('状态已更新');
      } catch (e) {
        row.status = prev;
        this.$message.error(e?.response?.data?.message || '更新失败');
      } finally {
        this.statusBusyId = null;
      }
    },
    async confirmDelete(row) {
      if (!row || !this.$canPerm('perm.admin.api.attendance_rules.destroy')) return;
      try {
        await this.$confirm(`确定删除该考勤规则（#${row.id}）吗？`, '删除确认', {
          type: 'warning',
          confirmButtonText: '删除',
          cancelButtonText: '取消',
        });
      } catch (e) {
        return;
      }
      this.deleteBusyId = row.id;
      try {
        await window.axios.delete(`/admin/api/attendance-rules/${row.id}`);
        this.$message.success('已删除');
        this.fetchList();
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '删除失败');
      } finally {
        this.deleteBusyId = null;
      }
    },
  },
};
</script>
