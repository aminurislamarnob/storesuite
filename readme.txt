=== StoreSuite – Frontend Shop Manager for WooCommerce with AI – Product, Order, Coupon Management & Analytics Dashboard ===
Contributors: aminurislam01, pluginizelab
Donate link: https://www.buymeacoffee.com/aiarnob
Tags: woocommerce frontend dashboard, woocommerce order management, woocommerce product management, shop manager, woocommerce ai
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Frontend shop manager dashboard for WooCommerce. Manage products, orders, coupons & analytics with AI — premium features, 100% free.

== Description ==

StoreSuite is a free frontend shop manager for WooCommerce that puts complete store management in one fast dashboard. Manage products (simple, variable, grouped, and external), orders, coupons, categories, tags, brands, and attributes — without opening wp-admin. Generate product titles, descriptions, and images with AI, export products to CSV, and track sales with a full analytics suite that matches WooCommerce admin reports: Revenue, Orders, Products, Customers, and more. Fully responsive on mobile and tablet, and 100% free with no feature gates, trials, or upsells.

✨ **[All Features](https://storesuite.dev/features/)** | 📖 **[Documentation](https://storesuite.dev/docs/)** | 🆘 **[Support](https://wordpress.org/support/plugin/storesuite/#new-post)**

= 💎 Premium-grade features, completely free =

StoreSuite includes capabilities that other store-management dashboards often lock behind a Pro plan — at no cost:

* 🤖 AI product content, image, and all-in-one generation
* 🧩 Full variable products, attributes, and variations (with generate-all-variations)
* 📊 Real-time analytics, KPIs, and leaderboards
* 📈 A full Analytics suite with WooCommerce-admin report parity (Revenue, Orders, Products, and more)
* 📱 Fully responsive on mobile, tablet, and desktop
* 📤 CSV product import and export
* 🌗 Dark and light mode with per-user theme toggle
* 🔔 Realtime dashboard notifications

No feature gates. No trial limits. No upsell.

= 🚀 Key Features =

* 🖥️ **Manage WooCommerce store easily** – Single dashboard for store operations
* 🤖 **AI content generation** – Draft product titles and descriptions with regenerate and history
* 🪄 **AI all-in-one generator** – One hint drafts the title, short, and long description together
* 🎨 **AI image generation** – Create featured and gallery images from a text prompt
* 🧠 **AI settings** – Toggle AI per field and set custom instructions
* 📦 **Products** – List, add, and edit all WooCommerce product types: simple, variable, grouped, and external/affiliate
* 🧩 **Variable products** – Manage attributes and variations with bulk actions and generate-all-variations
* 📋 **Product attributes** – Manage attributes and their terms (add, edit, list)
* 📤 **CSV export** – Export product lists to CSV for reporting, accounting, or migration, with a configurable export modal
* 📥 **CSV import** – Import products from a CSV file with a guided wizard: upload, map columns to product fields, watch live progress, and review the results
* 🗂️ **Categories** – Manage product categories (add, edit, list)
* 🏷️ **Tags** – Manage product tags (add, edit, list)
* 🏢 **Brands** – Manage product brands
* 🎟️ **Coupons** – Create, edit, and manage WooCommerce coupons
* 🛒 **Orders** – View, create, and edit orders with customer and item details
* 📊 **Real-time dashboard analytics** – Sales KPIs, net sales chart, top products, recent orders, and quick actions
* 📈 **Analytics reports** – A full analytics suite with WooCommerce-admin parity: Revenue, Orders, Products, Variations, Categories, Coupons, Taxes, Downloads, Stock, and Customers reports, each with summary KPIs, an interactive line/bar chart, sortable and paginated data tables, advanced filters, date-range comparison, and CSV export
* 🌗 **Dark and light mode** – A header toggle switches the whole dashboard between light and dark; the choice is remembered per user and the operating system preference is honoured on the first visit
* 🔔 **Dashboard notifications** – A bell in the header surfaces new orders, customer registrations, and product reviews in realtime, with a full notifications page and per-event settings
* 🧾 **PDF invoice support** – Invoice, packing slip, and delivery note actions appear on the order list and order details when PDF Invoices & Packing Slips for WooCommerce or the WebToffee WooCommerce PDF Invoices plugin is active (see *StoreSuite Compatible Plugins* below)
* 📱 **Fully responsive** – The entire frontend dashboard and analytics reports adapt to mobile and desktop screens with an off-canvas sidebar, stacked cards, and touch-friendly controls
* 🔑 **Bring your own AI** – Built on the WordPress 7.0 central AI connector; generation runs on the provider and keys you configure in WordPress
* ⚡ **Built for WooCommerce HPOS** – Fully compatible with High-Performance Order Storage

= 🔌 StoreSuite Compatible Plugins =

* [PDF Invoices & Packing Slips for WooCommerce](https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/) – adds Invoice and Packing Slip documents.
* [WebToffee WooCommerce PDF Invoices, Packing Slips, Delivery Notes & Shipping Labels](https://wordpress.org/plugins/print-invoices-packing-slip-labels-for-woocommerce/) – adds Invoice, Packing Slip, Delivery Note, Shipping Label, and Dispatch Label actions.

= How It Works =

1. Install and activate StoreSuite (WooCommerce must be active).
2. Create a page and add the shortcode `[storesuite_dashboard]`. Normally, on plugin activation StoreSuite creates this dashboard page with the required shortcode and auto-selects it on the StoreSuite settings dashboard page for you.
3. In **WooCommerce → StoreSuite → General** settings, if the dashboard page is not already auto-selected, select that page as the dashboard page and save.
4. Visit the dashboard page when logged in with `manage_woocommerce` capability to manage your store.
5. StoreSuite is compatible with WooCommerce High-Performance Order Storage (HPOS).

== Installation ==

= Standard Installation =
1. Install and activate WooCommerce if you have not already.
2. In your WordPress admin, go to **Plugins → Add New**, search for "StoreSuite", and click **Install Now**, then **Activate**.
3. Alternatively, download the plugin zip and upload it to `/wp-content/plugins/` and activate from **Plugins**.
4. StoreSuite flushes rewrite rules automatically on activation. If dashboard URLs return 404s, go to **Settings → Permalinks** and click **Save Changes** to refresh them.
5. On activation, StoreSuite normally creates the dashboard page with the `[storesuite_dashboard]` shortcode automatically. If it was not created, add a new page (e.g. "Store Dashboard"), insert the shortcode `[storesuite_dashboard]`, and publish.
6. Go to **WooCommerce → StoreSuite → General** in the admin menu.
7. In **Select Dashboard Page**, if the dashboard page is not already auto-selected, choose the page and click **Save Changes**.
8. Visit that page when logged in as a user with store management permissions to use the dashboard.

= Permalinks =

Normally StoreSuite flushes rewrite rules automatically on activation and update. If dashboard URLs return 404s, go to **Settings → Permalinks** and click **Save Changes** so the routes refresh and dashboard URLs work correctly.

== Screenshots ==

1. General Settings
2. Predefined/Custom Color Palette Settings
3. Paginations Settings
4. AI Settings
5. Frontend Dashboard
6. Products List
7. Add New Product
8. Edit Simple Product
9. Edit Variable Product
10. Product Categories List
11. Add New Product Category & Same UI for Edit Category
12. Product Brands List
13. Add New Product Brand & Same UI for Edit Brand
14. Product Tags List
15. Add New Product Tag & Same UI for Edit Tag
16. Product Attributes List
17. Attribute Terms List with Add/Edit terms
18. Orders List
19. Orders Details View
20. Add New Order
21. Edit Order
22. Coupons List
23. Add New Coupon & Same UI for Edit Coupon
24. Account Settings Profile Tab
25. Account Settings Address Tab
26. Account Settings Password Tab


== Frequently Asked Questions ==

= Do I need WooCommerce? =

Yes. StoreSuite requires WooCommerce to be installed and activated.

= Who can access the StoreSuite dashboard? =

Users with the `manage_woocommerce` capability (e.g. Administrators and Shop Managers) can access the dashboard when they visit the page that contains the `[storesuite_dashboard]` shortcode.

= Is it mandatory to update permalinks after installing the plugin? =

No. StoreSuite flushes rewrite rules automatically on activation and update. Only if dashboard URLs return 404s, go to **Settings → Permalinks** and click **Save Changes** so the routes refresh.

= How do I set the dashboard page? =

Create a page, add the shortcode `[storesuite_dashboard]`, then go to **WooCommerce → StoreSuite** and select that page in **Select Dashboard Page** and save.

= Does StoreSuite work with WooCommerce HPOS? =

Yes. StoreSuite declares compatibility with WooCommerce High-Performance Order Storage (HPOS).

== Privacy Policy ==

StoreSuite uses [Appsero](https://appsero.com) SDK to collect some telemetry data upon user's confirmation. This helps us to troubleshoot problems faster & make product improvements.

Appsero SDK **does not gather any data by default.** The SDK only starts gathering basic telemetry data **when a user allows it via the admin notice**. We collect the data to ensure a great user experience for all our users.

Integrating Appsero SDK **DOES NOT IMMEDIATELY** start gathering data, **without confirmation from users in any case.**

Learn more about how [Appsero collects and uses this data](https://appsero.com/privacy-policy/).

== Changelog ==

= 1.3.0 =
* Add **dark mode** to the frontend dashboard: a sun/moon toggle in the header flips between light and dark, the choice is saved per user, and the operating system `prefers-color-scheme` setting is honoured on the first visit. The theme resolves before the first paint, so there is no flash of the wrong theme on load.
* Dark mode covers the whole dashboard — tables, forms, badges, buttons, pagination, switches, selects, dialogs, the quick-edit modal, the filter off-canvas, the loading skeletons, order details, account and address forms, and the TinyMCE editor content.
* Add a **dark mode theme picker** in **WooCommerce → StoreSuite → Appearance**: choose from Dark default, Soft dark, Midnight black, and Carbon. Each palette sets the dark neutrals only — the accent colour carries over from your light palette.
* Dark mode also covers the full **Analytics** suite: report charts, summary KPI tiles, loading skeletons, data tables, advanced filters, the date-range picker, and the interval selects.
* Add optional dark variants for the sidebar logo and sidebar icon, so branding stays legible in both themes.
* Add **product CSV import** on the Products page: a guided wizard walks through uploading a CSV, mapping its columns to product fields, live import progress, and a summary of what was imported, with an expandable import log.
* Add a **realtime notifications** system: a bell in the dashboard header badges new activity, with a full notifications page and per-event toggles in **WooCommerce → StoreSuite → Notifications** for new orders, new customer registrations, and new product reviews. Polling pauses while the browser tab is hidden. Notifications are visible only to users who can manage WooCommerce, and each recipient has their own copy — marking as read or clearing affects only that user.
* Notifications older than 90 days are removed automatically by a daily cleanup task, so the notifications table does not grow without bound. The retention window is filterable with `storesuite_notification_retention_days`.
* Add **PDF invoice plugin support**: when WooCommerce PDF Invoices & Packing Slips or WebToffee Print Invoices, Packing Slips, Delivery Notes & Shipping Labels is active, their documents appear as print and download actions in the order list row actions and in a Documents card on the order details page.
* Add icons to the order row actions and open the browser print dialog directly for print actions.
* Add two dashboard endpoints, `import-products` and `notifications`, with pagination support on the notifications list. Both slugs can be changed with the `storesuite_myshop_import_product_endpoint` and `storesuite_myshop_notifications_endpoint` options.
* Add `storesuite_notification_poll_interval`, `storesuite_notification_retention_days`, `storesuite_notifications_per_page`, and `storesuite_order_list_row_actions` hooks, plus `storesuite_load_import_products_template` and `storesuite_load_notifications_template` for overriding the new pages.
* On update, the notifications table is created, the daily cleanup is scheduled, and rewrite rules are flushed automatically — no need to re-save permalinks after upgrading.
* Fix undefined `--storesuite-title-text-color` references so title text uses the defined colour token.
* Fix the WordPress login page (`wp-login.php`, or a custom login slug from plugins such as WPS Hide Login) redirecting logged-out visitors to My Account. StoreSuite now only picks a destination after a successful login and leaves the login screen alone otherwise.

= 1.2.3 =
* Add optional, opt-in telemetry via the Appsero SDK. No data is collected unless you explicitly allow it from the admin notice.
* Document the telemetry behaviour in a new Privacy Policy section.

= 1.2.2 =
* Refresh the plugin listing: new title, tags, short description, and description intro.
* Add All Features, Documentation, and Support links to the listing description.
* Add a donate link and the PluginizeLab contributor to the listing.

= 1.2.1 =
* Add a Changelog page to the admin settings app, paginated with a "Load more" button.
* Fix collapsed selectWoo fields on the order add/edit form.

= 1.2.0 =
* Add a full **Analytics** suite with WooCommerce-admin report parity: Revenue, Orders, Products, Variations, Categories, Coupons, Taxes, Downloads, Stock, and Customers reports.
* Each report includes summary KPIs, an interactive chart (line/bar; by day, week, month, quarter, or year), sortable and paginated data tables, and CSV export.
* Add date-range comparison (previous period / previous year) and per-report advanced filters (e.g. product, category, coupon, order status, customer type) for WooCommerce Analytics parity.
* Add a "Data status" import bar and re-skin every report to match the StoreSuite dashboard design language.
* Make the Analytics reports fully responsive on mobile: the report header stacks, summary stats collapse to one per row, charts align to the page gutter, and the table actions row wraps so nothing is clipped.
* Link analytics order numbers to the StoreSuite order-details page and rewrite WooCommerce admin URLs to their frontend dashboard equivalents.

= 1.1.4 =
* Make the entire frontend dashboard responsive for mobile and tablet viewports.
* Off-canvas sidebar on mobile with overlay; all list tables collapse to stacked label/value cards; tables scroll horizontally on tablet.
* Dashboard home: full-width filters, stacked analytics tables, 2-column quick-actions grid.
* Product, order, coupon, category, tag, brand, and attribute pages: toolbar actions, forms, and buttons adapt to available width on every screen size.
* On mobile, the primary Add button moves beside the page title; Export/Filter/Search toolbar buttons become compact icon-only buttons.
* Shorten list page titles: "Product Categories" → "Categories", "Product Tags" → "Tags", "Product Brands" → "Brands", "Product Attributes" → "Attributes".
* Add `storesuite_categories_toolbar_add_button`, `storesuite_tags_toolbar_add_button`, `storesuite_brands_toolbar_add_button`, and `storesuite_attributes_toolbar_add_button` action hooks for customising the Add button in each list toolbar.

= 1.1.3 =
* Fix the Brands and Categories lists showing stale data on the frontend dashboard after brands or categories were added, edited, or deleted outside StoreSuite — for example from the WordPress admin, the WooCommerce REST API, WP-CLI, or a product import. The cached list now refreshes immediately regardless of where the change is made.

= 1.1.2 =
* Add AI product generation: per-field "Generate with AI" buttons for the product title, short description, and long description, with an editable suggestion modal, regenerate, and a history pager to step through suggestions.
* Add an all-in-one "Generate with AI" generator on the Add New and Edit Product pages: enter one hint to draft the title, short description, and long description together, each with its own regenerate and history pager.
* Add AI product image generation for the featured image and gallery images: describe the image, preview it, regenerate, and insert it straight into the media library.
* Add an AI settings page to enable or disable generation per field and to set custom system instructions for each text field and for images.
* Prefix the product slug field with the live permalink base (e.g. https://example.com/product/) on the Add New and Edit Product pages.
* Add a link beside the Edit Product page title that opens the live product page in a new tab.
* Reset the product image and gallery previews after a product is successfully added.
* AI generation features require WordPress 7.0 or later (built on the WordPress core AI Client) and are hidden automatically when unavailable.

= 1.1.1 =
* Prevent browsers from autofilling the account password fields on load.
* Remove the required "*" and "(optional)" markers from the account address fields.

= 1.1.0 =
* Add variable product management: attributes UI, variation CRUD, bulk actions, and generate-all-variations on the frontend product form.
* Add product attributes management pages (list, add, edit, and attribute terms).
* Add product CSV export with a configurable export modal.
* Flush rewrite rules automatically on update so the new attribute endpoints resolve without re-saving permalinks.

= 1.0.6 =
* Rewrite dashboard analytics in React with net sales chart, recent orders, quick actions, and leaderboards.
* Nest Categories, Brands, and Tags under the Products menu with a collapsed-sidebar flyout.
* Improve Lighthouse scores via deferred rendering, reserved widget heights, and per-endpoint asset loading.
* Harden analytics REST permissions and cache preload payloads per user capability.
* Patch dependency vulnerabilities and move build toolchain to Node 22.
* Append order number / coupon ID to the Edit Order and Edit Coupon page titles.
* Link list-table names (products, categories, brands, tags, coupons) to their StoreSuite edit page.
* Keep the Products parent menu active on edit Category / Brand / Tag pages.
* Reactively toggle the order Products section based on the saved order's editable state.
* Fix Upsells / Cross-sells on the product form and Products / Categories selects + datepicker styling on the coupon form.

= 1.0.3 =
* Minor update on admin settings UI.

= 1.0.2 =
* Redesign and optimize React admin settings UI.
* Add predefined/custom color palette system with live preview.
* Improve dashboard and general settings controls and save flow.

= 1.0.1 =
* Reorder My Account Dropdown Sub menus.

= 1.0.0 =
* Initial release.
* Dashboard with store performance KPIs and analytics.
* Products management.
* Categories, tags, and brands management.
* Coupons management.
* Orders: list, view, create, edit feature.
* Shortcode: [storesuite_dashboard].
* WooCommerce HPOS compatible.
* Collapsible dashboard sidebar: header control toggles expanded vs icon-only rail; menu labels on hover/focus; preference stored in the browser; full sidebar layout on smaller viewports.
* General settings: optional sidebar logo (expanded) and sidebar icon (collapsed), chosen from the media library; outputs in the dashboard sidebar with updated styling.
* Accessibility: navigation landmark on the sidebar, toggle semantics (role, aria-expanded, aria-controls), and screen-reader-friendly site title when a custom logo is shown.
