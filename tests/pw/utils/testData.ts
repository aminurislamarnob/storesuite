/**
 * Test users and the seed data created by bin/e2e-provision.sh.
 */
export const users = {
	admin: {
		username: process.env.ADMIN_USER || 'admin',
		password: process.env.ADMIN_PASSWORD || 'password',
	},
	manager: {
		username: process.env.MANAGER_USER || 'manager',
		password: process.env.MANAGER_PASSWORD || 'password',
	},
	customer: {
		username: process.env.CUSTOMER_USER || 'customer',
		password: process.env.CUSTOMER_PASSWORD || 'password',
	},
};

/**
 * Products seeded by the provisioning script. The store-wide low stock
 * threshold is 3, so "Red Cap" (2) and "Sticker Pack" (1) are low stock.
 */
export const seed = {
	products: {
		hoodie: { name: 'Blue Hoodie', sku: 'HOOD-1', stock: 20 },
		cap: { name: 'Red Cap', sku: 'CAP-1', stock: 2 },
		scarf: { name: 'Green Scarf', sku: 'SCARF-1', stock: null },
		mug: { name: 'Black Mug', sku: 'MUG-1', stock: 8 },
		stickers: { name: 'Sticker Pack', sku: 'STICK-1', stock: 1 },
	},
	coupon: 'welcome10',
	lowStockThreshold: 3,
};

export const dashboardPath = '/storesuite-dashboard';
