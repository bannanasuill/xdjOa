<template>
  <div class="admin-user-invites-page">
    <el-card class="admin-mb-12 admin-page-filters">
      <div class="admin-form-row">
        <el-input
          v-model="query.q"
          size="small"
          clearable
          class="admin-w-240"
          placeholder="搜索邀请码/生成人/使用人"
          @keyup.enter.native="fetchList(1)"
        />
        <el-select v-model="query.status" clearable size="small" class="admin-w-160" placeholder="按状态筛选" @change="fetchList(1)">
          <el-option v-for="o in statusOptions" :key="o.value" :label="o.label" :value="o.value" />
        </el-select>
        <el-select v-model="query.used" clearable size="small" class="admin-w-140" placeholder="按使用情况" @change="fetchList(1)">
          <el-option v-for="o in usedOptions" :key="o.value" :label="o.label" :value="o.value" />
        </el-select>
        <el-button size="small" type="primary" @click="fetchList(1)">查询</el-button>
        <el-button size="small" @click="reset">重置</el-button>
      </div>
    </el-card>

    <el-card>
      <el-table :data="rows" size="mini" class="admin-data-table" :max-height="adminTableMaxHeight" v-loading="loading">
        <el-table-column type="index" label="序号" width="56" fixed="left" :index="rowIndex" />
        <el-table-column label="邀请码" min-width="140" fixed="left">
          <template slot-scope="{ row }">
            <el-button type="text" class="admin-invite-code-btn" @click="copyInviteCode(row.code)">
              {{ row.code }}
            </el-button>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template slot-scope="{ row }">
            <el-tag size="mini" :type="inviteRegisterStatusTagType(row.status)">{{ row.status_label || '—' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="有效时长" width="100" align="center">
          <template slot-scope="{ row }">{{ row.valid_hours }} 小时</template>
        </el-table-column>
        <el-table-column label="过期时间" min-width="160">
          <template slot-scope="{ row }">{{ formatTs(row.expires_at) }}</template>
        </el-table-column>
        <el-table-column label="使用情况" width="100" align="center">
          <template slot-scope="{ row }">
            <el-tag size="mini" :type="row.is_used ? 'success' : (row.is_expired ? 'danger' : 'warning')">
              {{ row.is_used ? '已使用' : (row.is_expired ? '已过期' : '未使用') }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="使用人" min-width="140">
          <template slot-scope="{ row }">
            <span>{{ row.used_user_name || row.used_user_account || '—' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="生成人" min-width="140">
          <template slot-scope="{ row }">
            <span>{{ row.created_by_name || row.created_by_account || '—' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="生成时间" min-width="160">
          <template slot-scope="{ row }">{{ formatTs(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="190" fixed="right">
          <template slot-scope="{ row }">
            <el-button
              size="mini"
              type="primary"
              plain
              :disabled="row.is_used || row.is_expired"
              @click="openQrDialog(row)"
            >
              二维码
            </el-button>
            <el-button size="mini" plain @click="openDetailDialog(row)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="admin-pager-row admin-main-dock">
        <el-select v-model="query.per_page" size="small" class="admin-w-110" @change="fetchList(1)">
          <el-option v-for="n in perPageOptions" :key="n" :label="`${n}/页`" :value="n" />
        </el-select>
        <el-pagination
          background
          layout="prev, pager, next, jumper, total"
          :page-size="meta.per_page"
          :current-page="meta.current_page"
          :total="meta.total"
          @current-change="fetchList"
        />
      </div>
    </el-card>

    <el-dialog
      title="邀请码二维码"
      :visible.sync="qrVisible"
      width="420px"
      :close-on-click-modal="false"
      @closed="onQrClosed"
    >
      <div v-if="qrTarget" class="admin-invite-qr-wrap">
        <div class="admin-invite-qr-image-wrap">
          <img class="admin-invite-qr-image" :src="qrImageUrl(qrTarget)" alt="invite-qr" />
        </div>
        <div class="admin-invite-qr-meta">
          <div>邀请码：<strong>{{ qrTarget.code }}</strong></div>
          <div>有效至：{{ formatTs(qrTarget.expires_at) }}</div>
        </div>
      </div>
    </el-dialog>

    <el-dialog
      title="邀请码详情"
      :visible.sync="detailVisible"
      width="520px"
      :close-on-click-modal="false"
      @closed="onDetailClosed"
    >
      <div v-if="detailLoading" style="text-align: center; color: #909399;">加载中...</div>
      <div v-else-if="detailTarget" class="admin-invite-detail-grid">
        <div><span class="admin-invite-detail-label">邀请码：</span>{{ detailTarget.code || '—' }}</div>
        <div><span class="admin-invite-detail-label">状态：</span>{{ detailTarget.status_label || '—' }}</div>
        <div><span class="admin-invite-detail-label">部门：</span>{{ detailTarget.dept_name || '—' }}</div>
        <div><span class="admin-invite-detail-label">职务：</span>{{ detailTarget.position_name || '—' }}</div>
        <div><span class="admin-invite-detail-label">店铺：</span>{{ detailTarget.store_name || '—' }}</div>
        <div><span class="admin-invite-detail-label">有效时长：</span>{{ detailTarget.valid_hours || 0 }} 小时</div>
        <div><span class="admin-invite-detail-label">过期时间：</span>{{ formatTs(detailTarget.expires_at) }}</div>
        <div><span class="admin-invite-detail-label">使用情况：</span>{{ detailTarget.is_used ? '已使用' : (detailTarget.is_expired ? '已过期' : '未使用') }}</div>
        <div><span class="admin-invite-detail-label">使用人：</span>{{ detailTarget.used_user_name || detailTarget.used_user_account || '—' }}</div>
        <div><span class="admin-invite-detail-label">使用时间：</span>{{ formatTs(detailTarget.used_at) }}</div>
        <div><span class="admin-invite-detail-label">生成人：</span>{{ detailTarget.created_by_name || detailTarget.created_by_account || '—' }}</div>
        <div><span class="admin-invite-detail-label">生成时间：</span>{{ formatTs(detailTarget.created_at) }}</div>
      </div>
      <div v-else style="text-align: center; color: #909399;">暂无数据</div>
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
      query: { q: '', status: null, used: null, per_page: 20 },
      meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
      perPageOptions: [10, 20, 50, 100],
      statusOptions: [],
      usedOptions: [
        { value: 1, label: '已使用' },
        { value: 0, label: '未使用' },
      ],
      qrVisible: false,
      qrTarget: null,
      detailVisible: false,
      detailLoading: false,
      detailTarget: null,
    };
  },
  created() {
    this.fetchList(1);
  },
  methods: {
    rowIndex(idx) {
      const page = this.meta.current_page || 1;
      const per = this.meta.per_page || 20;
      return (page - 1) * per + idx + 1;
    },
    formatTs(ts) {
      if (!ts) return '—';
      const d = new Date(Number(ts) * 1000);
      if (Number.isNaN(d.getTime())) return '—';
      const p = (n) => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
    },
    inviteRegisterBaseUrl() {
      if (typeof window !== 'undefined' && window.__INVITE_REGISTER_URL__) {
        return String(window.__INVITE_REGISTER_URL__);
      }
      if (typeof window !== 'undefined' && window.location && window.location.origin) {
        return `${window.location.origin}/register`;
      }
      return '/register';
    },
    inviteRegisterUrl(row) {
      const base = this.inviteRegisterBaseUrl();
      const sep = base.includes('?') ? '&' : '?';
      return `${base}${sep}invite_code=${encodeURIComponent(row.code || '')}`;
    },
    qrImageUrl(row) {
      const data = this.inviteRegisterUrl(row);
      return `https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=${encodeURIComponent(data)}`;
    },
    openQrDialog(row) {
      this.qrTarget = row || null;
      this.qrVisible = !!row;
    },
    onQrClosed() {
      this.qrTarget = null;
    },
    async openDetailDialog(row) {
      if (!row || !row.id) return;
      this.detailVisible = true;
      this.detailLoading = true;
      this.detailTarget = null;
      try {
        const { data } = await window.axios.get(`/admin/api/users/invites/${row.id}`);
        this.detailTarget = data?.data || null;
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '加载详情失败');
      } finally {
        this.detailLoading = false;
      }
    },
    onDetailClosed() {
      this.detailLoading = false;
      this.detailTarget = null;
    },
    /** 邀请码「注册后目标状态」展示色（与 UserModel 用工状态值一致） */
    inviteRegisterStatusTagType(status) {
      const n = Number(status);
      if (n === 1) return 'success';
      if (n === 0) return 'info';
      return '';
    },
    async copyInviteCode(code) {
      const text = String(code || '').trim();
      if (!text) return;
      try {
        if (navigator?.clipboard?.writeText) {
          await navigator.clipboard.writeText(text);
        } else {
          const input = document.createElement('textarea');
          input.value = text;
          document.body.appendChild(input);
          input.select();
          document.execCommand('copy');
          document.body.removeChild(input);
        }
        this.$message.success('邀请码已复制');
      } catch (e) {
        this.$message.error('复制失败，请手动复制');
      }
    },
    async fetchList(page) {
      this.loading = true;
      try {
        const params = { q: this.query.q, status: this.query.status, used: this.query.used, per_page: this.query.per_page, page };
        Object.keys(params).forEach((k) => {
          if (params[k] === null || params[k] === '' || params[k] === undefined) delete params[k];
        });
        const { data } = await window.axios.get('/admin/api/users/invites', { params });
        this.rows = data.data || [];
        this.meta = data.meta || this.meta;
        const statusRaw = (data.options && data.options.status_options) || {};
        this.statusOptions = Object.keys(statusRaw).map((k) => ({ value: Number(k), label: String(statusRaw[k]) }));
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '加载失败');
      } finally {
        this.loading = false;
        this.$nextTick(() => this.syncAdminTableMaxHeight());
      }
    },
    reset() {
      this.query = { q: '', status: null, used: null, per_page: 20 };
      this.fetchList(1);
    },
  },
};
</script>

<style scoped>
.admin-invite-qr-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.admin-invite-qr-image-wrap {
  width: 260px;
  height: 260px;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  overflow: hidden;
  background: #fff;
}

.admin-invite-qr-image {
  width: 100%;
  height: 100%;
  display: block;
}

.admin-invite-qr-meta {
  width: 100%;
  margin-top: 12px;
  color: #606266;
  line-height: 1.8;
}

.admin-invite-code-btn {
  padding: 0;
  font-size: 12px;
}

.admin-invite-detail-grid {
  color: #606266;
  line-height: 1.9;
}

.admin-invite-detail-label {
  display: inline-block;
  width: 90px;
  color: #909399;
}
</style>

