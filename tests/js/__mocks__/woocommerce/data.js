/**
 * Mock for the @woocommerce/data webpack external.
 *
 * Only the store-name constants used by the source are provided; tests build
 * their own `select` doubles on top of these.
 */
export const SETTINGS_STORE_NAME = 'wc/admin/settings';
export const OPTIONS_STORE_NAME = 'wc/admin/options';
export const REPORTS_STORE_NAME = 'wc/admin/reports';
export const ITEMS_STORE_NAME = 'wc/admin/items';
