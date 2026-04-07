/**
 * 当前登录用户在 /admin/api/me 返回的权限 code 列表（permissions 中需鉴权的节点）。
 * 登录态公共接口 me / menus / logout 不在表中，也不应做 $canPerm 判断。
 */

let codeSet = /** @type {Set<string>|null} */ (null);
let loadPromise = /** @type {Promise<Record<string, unknown>|null>|null} */ (null);

export function setAdminPermissionCodes(codes) {
  codeSet = codes && codes.length ? new Set(codes) : new Set();
}

export function canAdminPermission(code) {
  if (!code) return true;
  if (!codeSet) return false;
  return codeSet.has(code);
}

export function hasAdminPermissionCodesLoaded() {
  return codeSet !== null;
}

/**
 * 拉取 /admin/api/me，写入权限集；多次调用合并为同一请求。
 * @returns {Promise<Record<string, unknown>|null>}
 */
export function ensureAdminPermissions() {
  if (loadPromise) return loadPromise;
  loadPromise = window.axios
    .get('/admin/api/me')
    .then((res) => {
      const d = res.data?.data;
      const perms = d && Array.isArray(d.permissions) ? d.permissions : [];
      setAdminPermissionCodes(perms);
      return d || null;
    })
    .catch((err) => {
      loadPromise = null;
      throw err;
    });
  return loadPromise;
}

/** 登录页重进后台时可清空（通常整页刷新即可） */
export function resetAdminPermissionCache() {
  codeSet = null;
  loadPromise = null;
}
