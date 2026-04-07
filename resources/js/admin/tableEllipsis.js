/** 表格单元格：超过 max 个字符则显示前 max 位 + ...，title 展示全文 */

export const ADMIN_TABLE_ELLIPSIS_MAX = 30;

/**
 * @param {unknown} value
 * @param {number} [max]
 * @returns {string}
 */
export function adminEllipsisDisplay(value, max = ADMIN_TABLE_ELLIPSIS_MAX) {
  const s = value === null || value === undefined ? '' : String(value);
  return s.length > max ? `${s.slice(0, max)}...` : s;
}

/**
 * @param {unknown} value
 * @param {number} [max]
 * @returns {string|undefined} 无需提示时返回 undefined
 */
export function adminEllipsisTitle(value, max = ADMIN_TABLE_ELLIPSIS_MAX) {
  const s = value === null || value === undefined ? '' : String(value);
  return s.length > max ? s : undefined;
}
