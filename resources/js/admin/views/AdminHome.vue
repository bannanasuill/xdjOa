<template>
  <div class="admin-home">
    <el-card>
      <div slot="header">欢迎回来</div>
      <div v-if="me" class="admin-home__me">
        <p class="admin-home__line">
          当前用户：<b>{{ me.account }}</b>
          <span v-if="me.real_name">（{{ me.real_name }}）</span>
        </p>
        <div v-if="me.roles && me.roles.length" class="admin-home__roles">
          <span class="admin-home__roles-label">当前角色：</span>
          <el-tag
            v-for="r in me.roles"
            :key="`${r.id}-${r.name}`"
            size="small"
            :type="r.is_system ? 'danger' : 'success'"
            class="admin-home__role-tag"
          >
            {{ r.name }}
          </el-tag>
        </div>
        <p v-else class="admin-home__roles-none">当前角色：<span class="admin-tag--muted">暂未分配</span></p>
      </div>
      <div v-else class="admin-home__loading">正在加载用户信息…</div>
    </el-card>

    <el-card v-if="$canPerm('perm.admin.api.users.index')" class="admin-mt-12">
      <div slot="header">人员状态汇总</div>
      <div v-if="statusSummaryLoading" class="admin-home__loading">正在加载汇总…</div>
      <template v-else-if="statusSummary">
        <p class="admin-home__stat-total">人员合计：<b>{{ statusSummary.total }}</b></p>
        <div class="admin-home__stat-grid">
          <div
            v-for="item in statusSummary.by_status"
            :key="'st-' + item.status"
            class="admin-home__stat-item"
            :class="'admin-home__stat-item--' + statusStatModifier(item.status)"
          >
            <div class="admin-home__stat-count">{{ item.count }}</div>
            <div class="admin-home__stat-label">{{ item.label }}</div>
          </div>
        </div>
      </template>
      <p v-else class="admin-home__roles-none">暂无汇总数据</p>
    </el-card>
  </div>
</template>

<script>
export default {
  data() {
    return {
      me: null,
      statusSummary: null,
      statusSummaryLoading: false,
    };
  },
  async created() {
    try {
      const { data } = await window.axios.get('/admin/api/me');
      this.me = data?.data || null;
    } catch (e) {
      this.me = null;
    }
    if (this.$canPerm('perm.admin.api.users.index')) {
      this.statusSummaryLoading = true;
      try {
        const { data } = await window.axios.get('/admin/api/dashboard/user-status-summary');
        this.statusSummary = data?.data || null;
      } catch (e) {
        this.statusSummary = null;
      } finally {
        this.statusSummaryLoading = false;
      }
    }
  },
  methods: {
    statusStatModifier(status) {
      const n = Number(status);
      if (n === 1) return 'on';
      if (n === 0) return 'off';
      if (n === -1) return 'other';
      return 'mid';
    },
  },
};
</script>

