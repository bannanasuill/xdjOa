<template>
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
</template>

<script>
export default {
  data() {
    return {
      me: null,
    };
  },
  async created() {
    try {
      const { data } = await window.axios.get('/admin/api/me');
      this.me = data?.data || null;
    } catch (e) {
      this.me = null;
    }
  },
};
</script>

