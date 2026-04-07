<template>
  <div>
    <el-card class="admin-mb-12 admin-page-filters">
      <div class="admin-form-row">
        <el-input
          v-model="query.q"
          placeholder="搜索账号/姓名/url/ip/结果"
          clearable
          size="small"
          class="admin-w-260"
          @keyup.enter.native="fetchLogs(1)"
        />
        <el-select v-model="query.log_type" clearable size="small" class="admin-w-140" placeholder="日志类型" @change="fetchLogs(1)">
          <el-option v-for="v in logTypeOptions" :key="v.value" :label="v.label" :value="v.value" />
        </el-select>
        <el-select v-model="query.tagtype" clearable size="small" class="admin-w-160" placeholder="对象类型" @change="fetchLogs(1)">
          <el-option v-for="v in targetTypeOptions" :key="v.value" :label="v.label" :value="v.value" />
        </el-select>
        <el-select v-model="query.module" clearable size="small" class="admin-w-140" placeholder="模块" @change="fetchLogs(1)">
          <el-option v-for="v in moduleOptions" :key="v.value" :label="v.label" :value="v.value" />
        </el-select>
        <el-select v-model="query.action" clearable size="small" class="admin-w-140" placeholder="动作" @change="fetchLogs(1)">
          <el-option v-for="v in actionOptions" :key="v.value" :label="v.label" :value="v.value" />
        </el-select>
        <el-date-picker
          v-model="query.start_at"
          type="datetime"
          value-format="yyyy-MM-ddTHH:mm"
          format="yyyy-MM-dd HH:mm"
          size="small"
          placeholder="开始时间"
          @change="fetchLogs(1)"
        />
        <el-date-picker
          v-model="query.end_at"
          type="datetime"
          value-format="yyyy-MM-ddTHH:mm"
          format="yyyy-MM-dd HH:mm"
          size="small"
          placeholder="结束时间"
          @change="fetchLogs(1)"
        />
        <el-button size="small" type="primary" @click="fetchLogs(1)">查询</el-button>
        <el-button size="small" @click="reset">重置</el-button>
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
        <el-table-column label="账号" width="120" fixed="left">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(row.account)">{{ adminEllipsisDisplay(row.account) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="姓名" min-width="120">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(row.real_name)">{{ adminEllipsisDisplay(row.real_name) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="110">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(logTypeLabel(row.log_type))">{{ adminEllipsisDisplay(logTypeLabel(row.log_type)) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="模块" min-width="110">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(moduleLabel(row.module))">{{ adminEllipsisDisplay(moduleLabel(row.module)) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="动作" min-width="110">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(actionLabel(row.action))">{{ adminEllipsisDisplay(actionLabel(row.action)) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="对象类型" min-width="130">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(targetTypeLabel(row.target_type))">{{ adminEllipsisDisplay(targetTypeLabel(row.target_type)) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="对象ID" width="90">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(row.target_id)">{{ adminEllipsisDisplay(row.target_id) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="IP" width="130">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(row.ip)">{{ adminEllipsisDisplay(row.ip) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="结果说明" min-width="220" fixed="right">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(row.message)">{{ adminEllipsisDisplay(row.message) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="时间" width="170" fixed="right">
          <template slot-scope="{ row }">
            <span :title="adminEllipsisTitle(formatTs(row.created_at))">{{ adminEllipsisDisplay(formatTs(row.created_at)) }}</span>
          </template>
        </el-table-column>
      </el-table>

      <div class="admin-pager-row admin-main-dock">
        <el-select v-model="query.per_page" size="small" class="admin-w-110" @change="fetchLogs(1)">
          <el-option v-for="n in options.per_page" :key="n" :label="`${n}/页`" :value="n" />
        </el-select>
        <el-pagination
          background
          layout="prev, pager, next, jumper, total"
          :page-size="meta.per_page"
          :current-page="meta.current_page"
          :total="meta.total"
          @current-change="fetchLogs"
        />
      </div>
    </el-card>
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
      query: {
        q: '',
        log_type: null,
        tagtype: null,
        module: null,
        action: null,
        start_at: null,
        end_at: null,
        per_page: 20,
      },
      options: {
        target_type: [],
        log_type: [],
        module: [],
        action: [],
        per_page: [10, 20, 50, 100],
      },
      labels: {
        log_type: {},
        target_type: {},
        module: {},
        action: {},
      },
    };
  },
  computed: {
    logTypeOptions() {
      const raw = this.options.log_type || [];
      if (raw.length > 0 && typeof raw[0] === 'object') return raw;
      return raw.map((v) => ({ value: v, label: this.logTypeLabel(v) }));
    },
    targetTypeOptions() {
      const raw = this.options.target_type || [];
      if (raw.length > 0 && typeof raw[0] === 'object') return raw;
      return raw.map((v) => ({ value: v, label: this.targetTypeLabel(v) }));
    },
    moduleOptions() {
      const raw = this.options.module || [];
      if (raw.length > 0 && typeof raw[0] === 'object') return raw;
      return raw.map((v) => ({ value: v, label: this.moduleLabel(v) }));
    },
    actionOptions() {
      const raw = this.options.action || [];
      if (raw.length > 0 && typeof raw[0] === 'object') return raw;
      return raw.map((v) => ({ value: v, label: this.actionLabel(v) }));
    },
  },
  created() {
    this.fetchLogs(1);
  },
  methods: {
    logTypeLabel(v) {
      if (!v) return '';
      return (this.labels.log_type && this.labels.log_type[v]) ? this.labels.log_type[v] : v;
    },
    targetTypeLabel(v) {
      if (!v) return '';
      return (this.labels.target_type && this.labels.target_type[v]) ? this.labels.target_type[v] : v;
    },
    moduleLabel(v) {
      if (!v) return '';
      return (this.labels.module && this.labels.module[v]) ? this.labels.module[v] : v;
    },
    actionLabel(v) {
      if (!v) return '';
      return (this.labels.action && this.labels.action[v]) ? this.labels.action[v] : v;
    },
    formatTs(ts) {
      if (!ts) return '';
      const d = new Date(ts * 1000);
      const pad = (n) => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(
        d.getMinutes()
      )}:${pad(d.getSeconds())}`;
    },
    reset() {
      this.query = {
        q: '',
        log_type: null,
        tagtype: null,
        module: null,
        action: null,
        start_at: null,
        end_at: null,
        per_page: 20,
      };
      this.fetchLogs(1);
    },
    async fetchLogs(page) {
      this.loading = true;
      try {
        const { data } = await window.axios.get('/admin/api/user-logs', {
          params: { ...this.query, page },
        });
        this.rows = data.data || [];
        this.meta = data.meta || this.meta;
        if (data.options) {
          this.options = { ...this.options, ...data.options };
        }
        if (data.labels) {
          this.labels = { ...this.labels, ...data.labels };
        }
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '加载失败');
      } finally {
        this.loading = false;
        this.$nextTick(() => this.syncAdminTableMaxHeight());
      }
    },
  },
};
</script>

