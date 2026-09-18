import path from 'path';

/**
 * Storage-state file locations, exported once so specs never rebuild the
 * paths by hand.
 */
export const AUTH_DIR = path.join( __dirname, '..', 'playwright', '.auth' );

export const ADMIN_STATE = path.join( AUTH_DIR, 'admin.json' );
export const MANAGER_STATE = path.join( AUTH_DIR, 'manager.json' );
export const CUSTOMER_STATE = path.join( AUTH_DIR, 'customer.json' );
