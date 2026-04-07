import Vue from 'vue';
import ElementUI from 'element-ui';
import { Message } from 'element-ui';
import zhCn from 'element-ui/lib/locale/lang/zh-CN';
import 'element-ui/lib/theme-chalk/index.css';

import '../bootstrap';
import '../../css/admin-spa.css';
import router from './router';
import { canAdminPermission } from './permissions';
import { adminEllipsisDisplay, adminEllipsisTitle } from './tableEllipsis';

Vue.use(ElementUI, { locale: zhCn });

Vue.mixin({
  methods: {
    adminEllipsisDisplay,
    adminEllipsisTitle,
  },
});

window.axios.interceptors.response.use(
    (r) => r,
    (err) => {
        const res = err.response;
        if (
            res &&
            res.status === 403 &&
            res.config &&
            String(res.config.url || '').includes('/admin/api')
        ) {
            const msg = res.data && res.data.message ? res.data.message : '无权操作';
            Message.error(msg);
        }
        return Promise.reject(err);
    }
);

Vue.prototype.$canPerm = (code) => canAdminPermission(code);

new Vue({
    router,
    render: (h) => h('router-view'),
}).$mount('#admin-app');

