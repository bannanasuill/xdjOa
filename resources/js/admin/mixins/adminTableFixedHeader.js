/**
 * 列表页 el-table 表头固定：依赖 max-height，按视口与表格距顶位置计算 body 区域高度。
 * 模板中 el-table 需 ref="adminDataTable" 且 :max-height="adminTableMaxHeight"。
 */
export default {
  data() {
    return {
      adminTableMaxHeight: 420,
    };
  },
  mounted() {
    this.$nextTick(() => {
      this.syncAdminTableMaxHeight();
      window.addEventListener('resize', this.syncAdminTableMaxHeight, { passive: true });
    });
  },
  activated() {
    this.$nextTick(() => this.syncAdminTableMaxHeight());
  },
  beforeDestroy() {
    window.removeEventListener('resize', this.syncAdminTableMaxHeight);
  },
  methods: {
    syncAdminTableMaxHeight() {
      this.$nextTick(() => {
        const ref = this.$refs.adminDataTable;
        const el = ref && ref.$el;
        if (!el || typeof el.getBoundingClientRect !== 'function') {
          return;
        }
        const top = el.getBoundingClientRect().top;
        /* 与 admin-spa.css 中 .admin-main-dock（min-height + 上下 padding + 边框）大致对齐 */
        const dockReserve = 80;
        const bottomGap = 12;
        const h = window.innerHeight - top - dockReserve - bottomGap;
        this.adminTableMaxHeight = Math.max(200, Math.floor(h));
      });
    },
  },
};
