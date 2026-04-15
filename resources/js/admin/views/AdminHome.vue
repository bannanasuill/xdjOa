<template>
  <div class="admin-home">
    <el-card shadow="never" class="admin-home__welcome-card">
      <div v-if="me" class="admin-home__hero">
        <div class="admin-home__hero-main">
          <p class="admin-home__hero-subtitle">工作台</p>
          <h2 class="admin-home__hero-title">欢迎回来，{{ displayName }}</h2>
          <p class="admin-home__hero-account">账号：{{ me.account }}</p>
        </div>
        <div class="admin-home__hero-side">
          <div v-if="me.roles && me.roles.length" class="admin-home__roles">
            <span class="admin-home__roles-label">当前角色</span>
            <div class="admin-home__roles-list">
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
          </div>
          <div v-else class="admin-home__roles-none">当前角色：<span class="admin-tag--muted">暂未分配</span></div>
        </div>
      </div>
      <div v-else class="admin-home__loading">正在加载用户信息…</div>
    </el-card>

    <el-card v-if="quickActions.length" class="admin-mt-12" shadow="never">
      <div slot="header" class="admin-home__card-header">
        <span>快捷操作</span>
        <div class="admin-home__card-header-right">
          <span class="admin-home__card-header-sub">常用功能一键直达（支持拖拽排序）</span>
          <div v-if="quickHasOverflow" class="admin-home__quick-pager">
            <el-button
              type="text"
              size="mini"
              :disabled="!canQuickPrev"
              class="admin-home__quick-pager-btn"
              @click="quickPrev"
            >←</el-button>
            <span class="admin-home__quick-pager-text">{{ quickPageText }}</span>
            <el-button
              type="text"
              size="mini"
              :disabled="!canQuickNext"
              class="admin-home__quick-pager-btn"
              @click="quickNext"
            >→</el-button>
          </div>
        </div>
      </div>
      <div class="admin-home__quick-grid">
        <button
          v-for="item in visibleQuickActions"
          :key="item.route"
          type="button"
          class="admin-home__quick-item"
          :class="{ 'is-dragging': quickActionDraggingRoute === item.route }"
          draggable="true"
          @dragstart="onQuickDragStart(item.route)"
          @dragover="onQuickDragOver"
          @drop="onQuickDrop(item.route)"
          @dragend="onQuickDragEnd"
          @click="goQuick(item.route)"
        >
          <span class="admin-home__quick-icon">{{ item.icon }}</span>
          <span class="admin-home__quick-title">{{ item.title }}</span>
          <span class="admin-home__quick-desc">{{ item.desc }}</span>
        </button>
      </div>
    </el-card>

    <el-card v-if="$canPerm('perm.admin.api.users.index')" class="admin-mt-12" shadow="never">
      <div slot="header" class="admin-home__card-header">
        <span>人员状态总览</span>
        <span class="admin-home__card-header-sub">按今日最新状态统计</span>
      </div>
      <div v-if="statusSummaryLoading" class="admin-home__loading">正在加载汇总…</div>
      <template v-else-if="statusSummary">
        <div class="admin-home__kpis">
          <div class="admin-home__kpi admin-home__kpi--total admin-home__kpi--clickable" @click="jumpToUsersByEmployment('all')">
            <span class="admin-home__kpi-label">人员合计</span>
            <b class="admin-home__kpi-value">{{ statusSummary.total }}</b>
          </div>
          <div class="admin-home__kpi admin-home__kpi--active admin-home__kpi--clickable" @click="jumpToUsersByEmployment('not_left')">
            <span class="admin-home__kpi-label">未离职</span>
            <b class="admin-home__kpi-value">{{ notLeftCount }}</b>
          </div>
          <div class="admin-home__kpi admin-home__kpi--left admin-home__kpi--clickable" @click="jumpToUsersByEmployment('left')">
            <span class="admin-home__kpi-label">已离职</span>
            <b class="admin-home__kpi-value">{{ leftCount }}</b>
          </div>
        </div>
        <div class="admin-home__presence-closure">
          <div class="admin-home__presence-closure-title">未离职当下出勤结构（闭环）</div>
          <div class="admin-home__presence-chart-wrap">
            <div class="admin-home__presence-pie" :style="presencePieStyle">
              <div class="admin-home__presence-pie-inner">
                <b>{{ notLeftCount }}</b>
                <span>未离职</span>
              </div>
            </div>
            <div class="admin-home__presence-legend">
              <div
                v-for="item in presenceSegments"
                :key="item.key"
                class="admin-home__presence-legend-item admin-home__presence-legend-item--clickable"
                @click="jumpToUsersByPresence(item.key)"
              >
                <span class="admin-home__presence-legend-dot" :style="{ backgroundColor: item.color }"></span>
                <span class="admin-home__presence-legend-label">{{ item.label }}</span>
                <span class="admin-home__presence-legend-value">{{ item.count }}（{{ item.ratio }}%）</span>
              </div>
            </div>
          </div>
        </div>
      </template>
      <p v-else class="admin-home__roles-none">暂无汇总数据</p>
    </el-card>
  </div>
</template>

<script>
const QUICK_ACTIONS = [
  {
    route: 'admin.users',
    title: '用户管理',
    desc: '查看用户并分配角色/职务',
    icon: 'U',
    perm: 'perm.admin.users',
  },
  {
    route: 'admin.users.invites',
    title: '邀请列表',
    desc: '管理注册码与使用状态',
    icon: 'I',
    perm: 'perm.admin.users.invites',
  },
  {
    route: 'admin.attendance_rules',
    title: '考勤规则',
    desc: '配置到岗范围与打卡策略',
    icon: 'A',
    perm: 'perm.admin.attendance_rules',
  },
  {
    route: 'admin.stores',
    title: '门店管理',
    desc: '维护门店与定位参数',
    icon: 'S',
    perm: 'perm.admin.stores',
  },
];

export default {
  data() {
    return {
      me: null,
      statusSummary: null,
      statusSummaryLoading: false,
      quickActionOrder: [],
      quickActionDraggingRoute: '',
      quickActionStart: 0,
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
  computed: {
    displayName() {
      if (!this.me) return '';
      const rn = this.me.real_name ? String(this.me.real_name).trim() : '';
      return rn || String(this.me.account || '');
    },
    presenceNowPresent() {
      return this.presenceNowValue('present');
    },
    presenceNowOuting() {
      return this.presenceNowValue('outing');
    },
    presenceNowNotArrived() {
      return this.presenceNowValue('not_arrived');
    },
    notLeftCount() {
      const p = this.statusSummary && this.statusSummary.employment ? this.statusSummary.employment : {};
      const n = Number(p.not_left ?? 0);
      return Number.isFinite(n) && n > 0 ? n : 0;
    },
    leftCount() {
      const p = this.statusSummary && this.statusSummary.employment ? this.statusSummary.employment : {};
      const n = Number(p.left ?? 0);
      return Number.isFinite(n) && n > 0 ? n : 0;
    },
    quickActions() {
      const list = QUICK_ACTIONS.filter((x) => this.$canPerm(x.perm));
      if (!this.quickActionOrder.length) return list;
      const rank = {};
      this.quickActionOrder.forEach((route, i) => {
        rank[route] = i;
      });
      return list.slice().sort((a, b) => {
        const ar = Object.prototype.hasOwnProperty.call(rank, a.route) ? rank[a.route] : 999;
        const br = Object.prototype.hasOwnProperty.call(rank, b.route) ? rank[b.route] : 999;
        if (ar !== br) return ar - br;
        return 0;
      });
    },
    visibleQuickActions() {
      return this.quickActions.slice(this.quickActionStart, this.quickActionStart + 5);
    },
    canQuickPrev() {
      return this.quickActionStart > 0;
    },
    canQuickNext() {
      return this.quickActionStart + 5 < this.quickActions.length;
    },
    quickHasOverflow() {
      return this.quickActions.length > 5;
    },
    quickPageText() {
      if (!this.quickActions.length) return '0/0';
      const page = Math.floor(this.quickActionStart / 5) + 1;
      const total = Math.max(1, Math.ceil(this.quickActions.length / 5));
      return `${page}/${total}`;
    },
    presenceSegments() {
      return [
        { key: 'present', label: '在岗', count: this.presenceNowPresent, ratio: this.presenceRatio(this.presenceNowPresent), color: '#13ce66' },
        { key: 'outing', label: '外出', count: this.presenceNowOuting, ratio: this.presenceRatio(this.presenceNowOuting), color: '#e6a23c' },
        { key: 'not_arrived', label: '未到岗', count: this.presenceNowNotArrived, ratio: this.presenceRatio(this.presenceNowNotArrived), color: '#909399' },
      ];
    },
    presencePieStyle() {
      const p1 = Number(this.presenceRatio(this.presenceNowPresent) || 0);
      const p2 = Number(this.presenceRatio(this.presenceNowOuting) || 0);
      const c1 = Math.max(0, Math.min(100, p1));
      const c2 = Math.max(c1, Math.min(100, c1 + p2));
      return {
        background: `conic-gradient(#13ce66 0% ${c1}%, #e6a23c ${c1}% ${c2}%, #909399 ${c2}% 100%)`,
      };
    },
  },
  methods: {
    quickActionStorageKey() {
      const uid = this.me && this.me.id != null ? String(this.me.id) : 'anon';
      return `admin.home.quick_actions.order.${uid}`;
    },
    restoreQuickActionOrder() {
      try {
        const raw = window.localStorage.getItem(this.quickActionStorageKey());
        const parsed = raw ? JSON.parse(raw) : [];
        this.quickActionOrder = Array.isArray(parsed) ? parsed.filter((x) => typeof x === 'string' && x) : [];
      } catch (e) {
        this.quickActionOrder = [];
      }
    },
    persistQuickActionOrder() {
      try {
        window.localStorage.setItem(this.quickActionStorageKey(), JSON.stringify(this.quickActionOrder));
      } catch (e) {
        // ignore storage failures
      }
    },
    presenceNowValue(key) {
      const now = this.statusSummary && this.statusSummary.presence_now ? this.statusSummary.presence_now : {};
      const n = Number(now[key] ?? 0);
      return Number.isFinite(n) && n > 0 ? n : 0;
    },
    presenceRatio(value) {
      const base = Number(this.notLeftCount || 0);
      if (!Number.isFinite(base) || base <= 0) return 0;
      const raw = (Number(value || 0) / base) * 100;
      return Math.max(0, Math.min(100, Number(raw.toFixed(1))));
    },
    goQuick(routeName) {
      if (!routeName) return;
      this.$router.push({ name: routeName });
    },
    jumpToUsersByEmployment(scope) {
      const query = {};
      if (scope === 'left' || scope === 'not_left') {
        query.employment_scope = scope;
      }
      this.$router.push({ name: 'admin.users', query });
    },
    jumpToUsersByPresence(segmentKey) {
      const map = {
        present: 'present',
        outing: 'outing',
        not_arrived: 'not_arrived',
      };
      const presence = map[segmentKey] || '';
      const query = { employment_scope: 'not_left' };
      if (presence) {
        query.presence_today = presence;
      }
      this.$router.push({ name: 'admin.users', query });
    },
    quickPrev() {
      this.quickActionStart = Math.max(0, this.quickActionStart - 5);
    },
    quickNext() {
      const maxStart = Math.max(0, this.quickActions.length - 5);
      this.quickActionStart = Math.min(maxStart, this.quickActionStart + 5);
    },
    onQuickDragStart(route) {
      this.quickActionDraggingRoute = route || '';
    },
    onQuickDragOver(e) {
      if (e && typeof e.preventDefault === 'function') e.preventDefault();
    },
    onQuickDrop(targetRoute) {
      const from = this.quickActionDraggingRoute;
      const to = targetRoute || '';
      if (!from || !to || from === to) {
        this.quickActionDraggingRoute = '';
        return;
      }
      const current = this.quickActions.map((x) => x.route);
      const ordered = current.slice();
      const fromIdx = ordered.indexOf(from);
      const toIdx = ordered.indexOf(to);
      if (fromIdx < 0 || toIdx < 0) {
        this.quickActionDraggingRoute = '';
        return;
      }
      ordered.splice(fromIdx, 1);
      ordered.splice(toIdx, 0, from);
      this.quickActionOrder = ordered;
      this.persistQuickActionOrder();
      this.quickActionDraggingRoute = '';
    },
    onQuickDragEnd() {
      this.quickActionDraggingRoute = '';
    },
  },
  watch: {
    me: {
      immediate: true,
      handler() {
        this.restoreQuickActionOrder();
      },
    },
    quickActions: {
      deep: true,
      handler(list) {
        const maxStart = Math.max(0, (Array.isArray(list) ? list.length : 0) - 5);
        if (this.quickActionStart > maxStart) {
          this.quickActionStart = maxStart;
        }
      },
    },
  },
};
</script>

