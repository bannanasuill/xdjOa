<template>
  <el-container class="admin-shell" :class="[themeClass, { 'admin-aside--collapsed': isCollapsed }]">
    <el-aside :width="isCollapsed ? '64px' : '220px'" class="admin-aside">
      <div class="admin-brand">
        <img
          v-if="brandLogoSrc"
          :src="brandLogoSrc"
          alt=""
          class="admin-brand__logo"
        >
        <span v-if="!isCollapsed" class="admin-brand__text">{{ siteBrand }}</span>
      </div>
      <el-menu
        :key="menuBranchKey"
        :default-active="activePath"
        :default-openeds="menuDefaultOpeneds"
        router
        :collapse="isCollapsed"
        :collapse-transition="false"
        accordion
        class="admin-menu"
      >
        <el-menu-item index="/" :title="''">
          <i class="el-icon-house"></i>
          <span slot="title">首页</span>
        </el-menu-item>

        <admin-menu-nodes :items="menus" :to-route-index="toRouteIndex" :icon-class="iconClass" />
      </el-menu>

      <div class="admin-aside__footer">
        <el-button class="admin-aside__logout" size="mini" type="text" @click="logout" :title="''">
          <i class="el-icon-switch-button"></i>
          <span v-if="!isCollapsed" style="margin-left: 6px;">退出</span>
        </el-button>
      </div>
    </el-aside>

    <el-container>
      <el-header height="56px" class="admin-header">
        <div class="admin-header__left">
          <el-button
            class="admin-header__collapse"
            size="mini"
            type="text"
            @click="toggleCollapsed"
          >
            <i :class="isCollapsed ? 'el-icon-s-unfold' : 'el-icon-s-fold'"></i>
          </el-button>
          <div class="admin-header__title">
            <el-breadcrumb separator="/">
              <el-breadcrumb-item v-for="(b, idx) in breadcrumbItems" :key="idx">
                {{ b }}
              </el-breadcrumb-item>
            </el-breadcrumb>
          </div>
        </div>

        <div class="admin-header__right">
          <div v-if="meLabel" class="admin-header__me">{{ meLabel }}</div>
          <div class="admin-header__time">{{ nowText }}</div>
          <el-button
            class="admin-header__theme"
            size="small"
            type="text"
            @click="toggleTheme"
            :title="isDark ? '切换日间模式' : '切换夜间模式'"
          >
            <i :class="isDark ? 'el-icon-sunny' : 'el-icon-moon'"></i>
          </el-button>
        </div>
      </el-header>
      <el-main class="admin-main">
        <div class="admin-content">
          <router-view />
        </div>
      </el-main>
    </el-container>
  </el-container>
</template>

<script>
import AdminMenuNodes from './AdminMenuNodes.vue';
import { ensureAdminPermissions } from '../permissions';

export default {
  components: { AdminMenuNodes },
  data() {
    const initialTheme =
      (typeof window !== 'undefined' && window.__ADMIN_THEME__ && (window.__ADMIN_THEME__ === 'dark' || window.__ADMIN_THEME__ === 'light'))
        ? window.__ADMIN_THEME__
        : ((typeof window !== 'undefined' && window.localStorage && (window.localStorage.getItem('admin_theme') === 'dark' || window.localStorage.getItem('admin_theme') === 'light'))
            ? window.localStorage.getItem('admin_theme')
            : 'light');
    return {
      menus: [],
      me: null,
      isCollapsed: false,
      theme: initialTheme,
      nowText: '',
      nowTimer: null,
      shellSiteName: null,
      shellFavicon: null,
    };
  },
  computed: {
    activePath() {
      return this.$route.path;
    },
    meLabel() {
      if (!this.me) return '';
      return `${this.me.real_name || ''}(${this.me.account})`;
    },
    siteBrand() {
      if (this.shellSiteName) {
        return this.shellSiteName;
      }
      return typeof window !== 'undefined' && window.__ADMIN_SITE_NAME__
        ? window.__ADMIN_SITE_NAME__
        : '洗多家后台';
    },
    /** 固定品牌图，与 admin/app.blade.php 中 __ADMIN_FAVICON__ 同源 */
    brandLogoSrc() {
      if (this.shellFavicon) {
        return this.shellFavicon;
      }
      if (typeof window !== 'undefined' && window.__ADMIN_FAVICON__) {
        return window.__ADMIN_FAVICON__;
      }

      return '';
    },
    pageTitle() {
      if (this.$route.name === 'admin.users') return '用户';
      if (this.$route.name === 'admin.departments') return '部门与职务';
      if (this.$route.name === 'admin.logs') return '日志管理';
      if (this.$route.name === 'admin.menus') return '菜单管理';
      if (this.$route.name === 'admin.permissions') return '权限管理';
      if (this.$route.name === 'admin.roles') return '角色管理';
      if (this.$route.name === 'admin.settings') return '系统配置';
      if (this.$route.name === 'admin.expense.templates') return '报销模板';
      if (this.$route.name === 'admin.expense.apply') return '报销申请';
      return '首页';
    },
    isDark() {
      return this.theme === 'dark';
    },
    themeClass() {
      return this.isDark ? 'admin-theme-dark' : 'admin-theme-light';
    },
    breadcrumbItems() {
      const current = this.$route.path || '/';
      if (current === '/' || this.$route.name === 'admin.home') return ['首页'];

      const chain = this.findMenuChain(current);
      if (chain && chain.length) {
        return chain.map((m) => m.name).filter(Boolean);
      }
      return [this.pageTitle];
    },
    /** 切换顶级菜单分支时重建 el-menu，避免仍停留在上一分支的展开状态（如 users → menus） */
    menuBranchKey() {
      const path = this.$route.path || '/';
      if (path === '/' || this.$route.name === 'admin.home') {
        return 'home';
      }
      const chain = this.findMenuChain(path);
      if (!chain || !chain.length) {
        return `unknown-${path}`;
      }
      return `branch-${chain[0].id}`;
    },
    /** 与当前路由匹配的各级父级 submenu（index 为 sub-{id}） */
    menuDefaultOpeneds() {
      const path = this.$route.path || '/';
      if (path === '/' || this.$route.name === 'admin.home') {
        return [];
      }
      const chain = this.findMenuChain(path);
      if (!chain || chain.length < 2) {
        return [];
      }
      const openeds = [];
      for (let i = 0; i < chain.length - 1; i += 1) {
        openeds.push(`sub-${chain[i].id}`);
      }
      return openeds;
    },
  },
  created() {
    if (typeof window !== 'undefined') {
      if (window.__ADMIN_SITE_NAME__) {
        this.shellSiteName = window.__ADMIN_SITE_NAME__;
      }
      if (window.__ADMIN_FAVICON__) {
        this.shellFavicon = window.__ADMIN_FAVICON__;
      }
    }
    // theme is initialized synchronously in data() to avoid flash on refresh
    this.startClock();
    this.bootstrap();
    this._onReloadMenus = () => this.bootstrap();
    this._onReloadBranding = (ev) => this.onBrandingEvent(ev);
    window.addEventListener('admin-reload-menus', this._onReloadMenus);
    window.addEventListener('admin-reload-branding', this._onReloadBranding);
    this.applyShellBranding();
  },
  watch: {
    '$route.path'() {
      this.applyShellBranding();
    },
  },
  beforeDestroy() {
    if (this.nowTimer) {
      clearInterval(this.nowTimer);
      this.nowTimer = null;
    }
    if (this._onReloadMenus) {
      window.removeEventListener('admin-reload-menus', this._onReloadMenus);
    }
    if (this._onReloadBranding) {
      window.removeEventListener('admin-reload-branding', this._onReloadBranding);
    }
  },
  methods: {
    onBrandingEvent(ev) {
      const d = ev && ev.detail;
      if (d && d.siteName != null && d.siteName !== '') {
        this.shellSiteName = d.siteName;
        if (typeof window !== 'undefined') {
          window.__ADMIN_SITE_NAME__ = d.siteName;
        }
      }
      if (d && d.faviconHref) {
        this.shellFavicon = d.faviconHref;
        if (typeof window !== 'undefined') {
          window.__ADMIN_FAVICON__ = d.faviconHref;
        }
      }
      this.applyShellBranding();
    },
    applyShellBranding() {
      if (typeof document === 'undefined') {
        return;
      }
      const href =
        this.shellFavicon ||
        (typeof window !== 'undefined' && window.__ADMIN_FAVICON__ ? window.__ADMIN_FAVICON__ : '');
      if (href) {
        let link = document.querySelector('link[rel="icon"]');
        if (!link) {
          link = document.createElement('link');
          link.setAttribute('rel', 'icon');
          document.head.appendChild(link);
        }
        link.setAttribute('type', 'image/png');
        link.setAttribute('href', href);
      }
      const p = this.pageTitle;
      const b = this.siteBrand;
      document.title = p === '首页' ? `${b} — 后台` : `${p} — ${b}`;
    },
    toggleCollapsed() {
      this.isCollapsed = !this.isCollapsed;
    },
    initTheme() {
      const saved = (window.localStorage && window.localStorage.getItem('admin_theme')) || '';
      if (saved === 'dark' || saved === 'light') {
        this.theme = saved;
        return;
      }
      // default: follow system preference if available
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      this.theme = prefersDark ? 'dark' : 'light';
    },
    toggleTheme() {
      this.theme = this.isDark ? 'light' : 'dark';
      if (window.localStorage) {
        window.localStorage.setItem('admin_theme', this.theme);
      }
      try {
        document.documentElement.setAttribute('data-admin-theme', this.theme);
        document.documentElement.style.background = this.theme === 'dark' ? '#0b1220' : '#f5f7fa';
      } catch (e) {}
    },
    startClock() {
      const pad = (n) => String(n).padStart(2, '0');
      const tick = () => {
        const d = new Date();
        this.nowText = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(
          d.getMinutes()
        )}:${pad(d.getSeconds())}`;
      };
      tick();
      this.nowTimer = setInterval(tick, 1000);
    },
    async bootstrap() {
      try {
        const meData = await ensureAdminPermissions();
        this.me = meData || null;
        const menuRes = await window.axios.get('/admin/api/menus');
        this.menus = menuRes.data?.data || [];
      } catch (e) {
        // ignore (e.g. session expired) - backend will redirect on next request
      }
    },
    iconClass(item) {
      const icon = (item && item.icon) ? String(item.icon) : '';
      if (icon) {
        if (icon.startsWith('el-icon-')) return icon;
        // allow storing "user"/"document"/etc in db
        return `el-icon-${icon}`;
      }

      const path = (item && item.path) ? String(item.path) : '';
      const name = (item && item.name) ? String(item.name) : '';
      const p = `${path} ${name}`.toLowerCase();

      if (p.includes('/users') || p.includes('admin user') || name.includes('用户')) return 'el-icon-user';
      if (p.includes('user-logs') || p.includes('/logs') || name.includes('日志')) return 'el-icon-document';
      if (p.includes('/menus') || name.includes('菜单')) return 'el-icon-menu';
      if (p.includes('/permissions') || name.includes('权限')) return 'el-icon-key';
      if (p.includes('/roles') || name.includes('角色')) return 'el-icon-collection';
      if (p.includes('/settings') || name.includes('系统配置')) return 'el-icon-setting';
      if (p.includes('/expense') || name.includes('报销')) return 'el-icon-s-order';
      if (p.includes('/admin') || name.includes('首页')) return 'el-icon-house';
      return 'el-icon-menu';
    },
    toAdminIndex(path) {
      if (!path) return '/';
      // our router base is /admin, so menu index uses base-relative paths
      if (path === '/admin') return '/';
      if (path.startsWith('/admin/')) return path.replace(/^\/admin/, '');
      if (path.startsWith('/')) return path;
      return `/${path}`;
    },
    toRouteIndex(path) {
      return this.normalizeMenuIndex(path);
    },
    normalizeMenuIndex(path) {
      const idx = this.toAdminIndex(path);
      // legacy: /admin/user-logs -> /logs (spa route)
      if (idx === '/user-logs') return '/logs';
      return idx;
    },
    findMenuChain(currentPath) {
      const cur = currentPath || '/';
      const dfs = (items, chain) => {
        for (const item of items || []) {
          const nextChain = [...chain, item];
          if (item.children && item.children.length) {
            const hit = dfs(item.children, nextChain);
            if (hit) return hit;
          }
          const idx = this.normalizeMenuIndex(item.path);
          if (idx && idx === cur) return nextChain;
        }
        return null;
      };
      return dfs(this.menus, []);
    },
    async logout() {
      try {
        await window.axios.post('/admin/api/logout');
      } finally {
        window.location.href = '/login';
      }
    },
  },
};
</script>

