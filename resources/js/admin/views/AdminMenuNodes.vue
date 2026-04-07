<template>
  <div class="admin-menu-nodes-host">
    <template v-for="item in items">
      <el-submenu
        v-if="item.children && item.children.length"
        :key="`sub-${item.id}`"
        :index="`sub-${item.id}`"
        :title="''"
      >
        <template slot="title">
          <span class="admin-submenu-title" @click="onParentTitleClick(item)">
            <i :class="iconClass(item)"></i>
            <span>{{ item.name }}</span>
          </span>
        </template>
        <admin-menu-nodes :items="item.children" :to-route-index="toRouteIndex" :icon-class="iconClass" />
      </el-submenu>
      <el-menu-item v-else :key="`mi-${item.id}`" :index="toRouteIndex(item.path)" :title="''">
        <i :class="iconClass(item)"></i>
        <span slot="title">{{ item.name }}</span>
      </el-menu-item>
    </template>
  </div>
</template>

<script>
export default {
  name: 'AdminMenuNodes',
  props: {
    items: {
      type: Array,
      default: () => [],
    },
    toRouteIndex: {
      type: Function,
      required: true,
    },
    iconClass: {
      type: Function,
      required: true,
    },
  },
  methods: {
    onParentTitleClick(item) {
      if (!item || !item.path) return;
      const idx = this.toRouteIndex(item.path);
      if (!idx) return;
      if (this.$route.path === idx) return;
      this.$router.push(idx).catch(() => {});
    },
  },
};
</script>
