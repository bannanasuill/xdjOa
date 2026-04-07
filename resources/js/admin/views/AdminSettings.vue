<template>
  <div class="admin-settings-page">
    <el-card>
      <div slot="header">
        <span>系统配置</span>
      </div>

      <el-form v-loading="loading" :model="form" label-width="120px" size="small" class="admin-settings-form">
        <el-form-item label="站点名称">
          <el-input
            v-model="form.site_name"
            maxlength="100"
            placeholder="浏览器标题与登录页展示；留空保存则恢复默认「洗多家后台」"
            :disabled="!canSave"
          />
        </el-form-item>
        <el-form-item label="网站图标 URL">
          <el-input
            v-model="form.site_favicon"
            maxlength="500"
            placeholder="完整 URL，或站内路径如 /favicon.ico；留空保存则使用默认 favicon"
            :disabled="!canSave"
          />
          <div class="admin-form-hint">支持 http(s) 链接或相对站点根路径。</div>
        </el-form-item>
        <el-form-item label="用户默认密码">
          <el-input
            v-model="form.default_user_password"
            type="text"
            autocomplete="new-password"
            maxlength="200"
            placeholder="至少 6 位；留空保存表示不修改"
            :disabled="!canSave"
            class="admin-settings-pw-input"
          />
        </el-form-item>
      </el-form>
      <div v-if="canSave" class="admin-main-dock admin-form-footer--fixed">
        <el-button size="small" type="primary" :loading="saving" @click="submit">保存</el-button>
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  data() {
    return {
      loading: false,
      saving: false,
      configState: {
        default_user_password_set: false,
        site_favicon: '',
        site_name: '',
      },
      form: {
        site_name: '',
        site_favicon: '',
        default_user_password: '',
      },
      clearSavedPassword: false,
    };
  },
  computed: {
    canSave() {
      return this.$canPerm('perm.admin.api.settings.update');
    },
  },
  created() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      try {
        const { data } = await window.axios.get('/admin/api/system-config');
        const row = data.data || {};
        this.configState = {
          default_user_password_set: !!row.default_user_password_set,
          site_favicon: row.site_favicon || '',
          site_name: row.site_name || '',
        };
        this.form = {
          site_name: this.configState.site_name,
          site_favicon: this.configState.site_favicon,
          default_user_password:
            row.default_user_password != null && row.default_user_password !== undefined
              ? String(row.default_user_password)
              : '',
        };
        this.clearSavedPassword = false;
        this.publishBranding(row);
      } catch (e) {
        this.$message.error(e?.response?.data?.message || '加载失败');
      } finally {
        this.loading = false;
      }
    },
    publishBranding(row) {
      if (typeof window === 'undefined') {
        return;
      }
      const siteName = row && row.site_name_display != null ? row.site_name_display : '洗多家后台';
      const faviconHref =
        row && row.site_favicon_resolved != null ? row.site_favicon_resolved : '';
      window.__ADMIN_SITE_NAME__ = siteName;
      if (faviconHref) {
        window.__ADMIN_FAVICON__ = faviconHref;
      }
      try {
        window.dispatchEvent(
          new CustomEvent('admin-reload-branding', {
            detail: { siteName, faviconHref: faviconHref || window.__ADMIN_FAVICON__ },
          })
        );
      } catch (err) {
        /* ignore */
      }
    },
    async submit() {
      if (!this.canSave) return;
      this.saving = true;
      try {
        const payload = {
          site_name: this.form.site_name,
          site_favicon: this.form.site_favicon,
        };
        const raw = (this.form.default_user_password || '').trim();
        if (this.clearSavedPassword) {
          payload.default_user_password = '';
        } else if (raw !== '') {
          payload.default_user_password = raw;
        }
        await window.axios.put('/admin/api/system-config', payload);
        this.$message.success('已保存');
        this.clearSavedPassword = false;
        await this.load();
      } catch (e) {
        const msg =
          e?.response?.data?.errors &&
          Object.values(e.response.data.errors)
            .flat()
            .filter(Boolean)
            .join('；');
        this.$message.error(msg || e?.response?.data?.message || '保存失败');
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

<style scoped>
.admin-settings-form {
  max-width: 640px;
}
.admin-settings-pw-input {
  max-width: 640px;
}
.admin-settings-pw-empty {
  font-size: 12px;
  color: #909399;
}
</style>
