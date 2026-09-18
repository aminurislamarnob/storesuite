# Managing Products in StoreSuite

Everything you need to sell — adding products, updating prices, tracking stock — lives in the **Products** section of your StoreSuite dashboard. No WordPress admin, no clutter. Just your catalog, in one clean view.

To get there, click **Products** in the left sidebar. It opens into **All Products**, and the sub-menu underneath gives you quick links to **Add New Product**, **Categories**, **Brands**, **Tags**, and **Attributes**.

---

## The Products List

![All Products](screenshots/screenshot-4.png)

This is your home base. Every product in your store appears here in a simple table, and each row tells you the essentials at a glance:

| Column | What it tells you |
|--------|-------------------|
| **Image** | The product's main photo (a placeholder shows if you haven't added one yet) |
| **Name** | The product title — click it to jump straight into editing |
| **Category** | Which categories the product belongs to |
| **Status** | Whether it's **Online** (live on your store), a draft, or pending review |
| **SKU** | Your stock-keeping unit, if you've set one |
| **Stock** | A colored badge — **In Stock**, **Out of Stock**, or **On Backorder** |
| **Price** | The current price — if the product is on sale, you'll see the old price crossed out next to the new one |
| **Type** | Simple, Variable, and so on |

At the bottom you'll see how many products you have in total (for example, *"Showing 1 to 8 of 20"*) along with page numbers to move through your catalog.

### Finding a product fast

Type a product name into the **Search Product** box at the top and press Enter. The list narrows to matching products right away.

### Quick actions on any product

![Row actions menu](screenshots/screenshot-5.png)

Click the **⋯** (three dots) at the right end of any row to open a small menu:

- **View** — opens the product page on your live store, so you can see exactly what customers see.
- **Edit** — opens the full editing screen.
- **Quick edit** — change the basics right from the list without leaving the page.
- **Delete** — moves the product to trash.

### Doing things in bulk

Need to update many products at once? Tick the checkboxes on the left of the rows you want, then choose from the **Bulk actions** dropdown above the table:

- **Edit** — change shared details across all selected products in one go.
- **Move to Trash** — remove several products at once.
- **Export** — download the selected products as a file.

Pick your action and click **Apply**. The **Export** button in the top-right corner does the same for your whole list.

### Bringing products in from a file

Next to **Export** you'll find an **Import** button. It opens a step-by-step wizard that turns a CSV file into products — perfect for moving a catalog over from another store, adding a supplier's list, or updating prices in bulk.

It's a guide of its own: see [Importing Products from a CSV](../product-import/product-import.md).

---

## Filtering Your Products

![Product filters](screenshots/screenshot-6.png)

When your catalog grows, scrolling isn't fun anymore. Click the **Filter** button at the top right and a panel slides in from the side with four ways to narrow things down:

- **Filter by category** — show only products from a specific category.
- **Filter by product type** — only Simple products, only Variable, and so on.
- **Filter by stock status** — quickly find everything that's out of stock or on backorder. Great for restock day.
- **Filter by brand** — show products from a single brand.

You can combine filters — for example, *out-of-stock products in the Clothing category*. Once you've made your picks, click **Filter Products**. To go back to seeing everything, hit **Reset**.

> **Tip:** Filters work together with search, so you can search for a name *and* filter by category at the same time.

---

## Adding a New Product

![Add New Product — details](screenshots/screenshot-7.png)

Click **+ Add Product** at the top of the products list (or **Add New Product** in the sidebar). You'll land on a clean form that walks you through everything, top to bottom. Only one field is truly required — the title — so you can start simple and fill in the rest as you go.

### The basics

- **Product Title** *(required)* — the name customers will see.
- **Permalink** — the web address for the product. Leave it blank and it's created automatically from the title.
- **Product Description** — the full story of your product, with a formatting toolbar for bold, lists, links, and more.
- **Product Short Description** — the brief summary that appears near the top of the product page.
- **Product Image** and **Product Gallery Images** — upload the main photo and any extra shots.

> **Tip:** See the **Generate with AI** buttons beside the title, descriptions, and images? StoreSuite can draft your product copy and even create images for you — a real head start when you're adding a lot of products. You control which of these AI buttons appear (and how they write) under **WooCommerce → StoreSuite → AI**.

### General information (right-hand panel)

- **Type** — **Simple** for a single product, or **Variable** if it comes in options like sizes or colors (choosing Variable lets you build variations from your attributes).
- **Virtual** / **Downloadable** — toggle these on for non-physical products, like a service or a file.
- **Status** — **Publish** to go live, or save it as a draft for later.
- **Brand** and **Tags** — file the product under a brand and add tags to help shoppers find it.
- **Enable Reviews?** — decide whether customers can leave reviews.

### Pricing

![Pricing and inventory](screenshots/screenshot-8.png)

- **Regular Price** — the normal price.
- **Sale Price** — a lower price for a promotion. **Schedule** lets you set start and end dates so the sale turns on and off by itself.
- **Cost of goods** — what the item costs you, used for profit reporting.

### Inventory

- **SKU** — your internal stock-keeping code.
- **GTIN, UPC, EAN, or ISBN** — a barcode or global product number, if you use one.
- **Enable product stock management** — turn this on to track exact quantities; StoreSuite then counts stock down with each sale.
- **Stock Status** — In stock, out of stock, or on backorder.
- **Limit Purchases to 1 Item Per Order?** — handy for limited or one-per-customer items.

### Shipping, linked products, and attributes

![Shipping, linked products, attributes](screenshots/screenshot-9.png)

- **Shipping** — **Weight**, **Dimensions** (length, width, height), and a **Shipping Class** for grouping items with similar shipping costs.
- **Linked Products** — set **Upsells** (nicer alternatives shown on the product page) and **Cross-sells** (add-ons suggested at checkout).
- **Attributes** — add characteristics like size or color. Pick an existing attribute and click **Add Attribute**, or build a one-off with **+ New custom attribute**. Attributes are also what power variations on a Variable product.

### Others

At the very bottom, the **Others** box holds the finishing touches: **Catalog Visibility** (where the product shows up), **Menu Order** (its sort position), a **Mark this product as featured** toggle, an **Available for POS** toggle, and a **Purchase Note** shown to the customer after they buy.

When everything looks right, click **Add Product** to save. **Back** returns you to the list without saving.

---

## Search Engine Settings (Yoast SEO)

If your store uses the **Yoast SEO** plugin, an **SEO (Yoast)** box appears at the bottom of the product form, right under **Others**. It lets you decide how the product looks in Google and when it's shared on social media — the same settings your site admin sees in the WordPress admin, without leaving your dashboard. If Yoast SEO isn't installed, the box isn't shown and nothing else changes.

Everything here is optional. Leave a field blank and your store's site-wide SEO settings are used instead.

### The SEO tab

![SEO tab with Google preview](screenshots/screenshot-49.png)

- **Focus keyphrase** — the search term you'd like this product to be found for, like "blue widget". In the **Desktop** preview its words are shown in bold, the way Google highlights what someone searched for.
- **Google preview** — a live picture of your product as a search result. It updates as you type, and it follows the product's title, permalink, and short description too. Flip the **Mobile / Desktop** switch to see both layouts.
- **SEO title** — the blue headline in the search result. The greyed-out text in the empty field is your store's default pattern, so you know what you'll get if you leave it alone.
- **Meta description** — the short text under the headline. If you leave it blank, the preview shows what Google is likely to pick instead.
- **Mark as cornerstone content** — switch this on for the handful of products that matter most to your store. (You'll only see it if the feature is enabled in Yoast.)

The coloured bar under each field tells you whether the length works. **Green** is good. **Orange** means the description is on the short or long side — aim for roughly 120 to 156 characters. **Red** means the title is too wide to fit, or there's no description at all. Cornerstone products are held to a stricter standard, so a short description shows red instead of orange.

#### Inserting variables

![Insert variable menu](screenshots/screenshot-50.png)

Variables are placeholders that fill themselves in — `%%title%%` becomes the product's name, `%%sitename%%` your store's name, `%%sep%%` the separator your site uses. They save you retyping, and they stay correct if the product is renamed later.

Click **Insert variable** next to the SEO title or meta description and pick from the list, or type **%** in the field and keep typing to narrow it down. The most useful ones — **Site title**, **Title**, and **Separator** — are at the top. The preview shows the finished result straight away.

### The Social tab

![Social tab](screenshots/screenshot-51.png)

This controls how the product looks when someone shares its link. There are two blocks:

- **Social media appearance** — used by Facebook, WhatsApp, LinkedIn, and most other places a link gets shared.
- **X appearance** — only needed if you want the product to look different on X. Leave it untouched and X uses the social media appearance settings too.

Each block has the same three settings:

- **Image** — click **Select image** to choose one from your media library. **Replace image** swaps it and **Remove image** clears it. Leave it empty to share the product's own image.
- **Title** and **Description** — leave these blank to reuse your SEO title and meta description. **Insert variable** works here as well.

A block only appears if it's switched on in your site's Yoast settings. If both are off, the tab is hidden.

### The Advanced tab

![Advanced tab](screenshots/screenshot-52.png)

These are the powerful ones, so tread carefully:

- **Allow search engines to show this product in search results?** — choose **No** to keep a product out of Google. The first option follows your store's default for products and tells you what that currently is — for example **Yes (current default for Products)**.
- **Should search engines follow links on this product?** — almost always **Yes**.
- **Meta robots advanced** — extra instructions for search engines. **No Image Index** keeps the product's images out of image search, **No Archive** stops search engines offering a saved copy of the page, and **No Snippet** hides the text preview under the result. Most products need none of these.
- **Breadcrumbs Title** — a shorter name to use in breadcrumb trails. Leave blank to use the product title.
- **Canonical URL** — only fill this in when the same product lives at another address that search engines should treat as the original. Leave blank to use the product's own link.

> **Tip:** Don't see the **Advanced** tab? That's deliberate. A wrong setting here can make a product vanish from search results, so Yoast reserves it for administrators and editors by default — exactly as it does in the WordPress admin. Your site admin can open it up to shop managers from Yoast's settings if they'd like you to have it.

One thing to know: StoreSuite saves your SEO settings, but it doesn't run Yoast's content analysis. The coloured SEO and readability scores your admin sees in the WordPress admin won't refresh until the product is next opened there. What search engines and social networks see is always up to date.

---

## Editing an Existing Product

Click a product's name in the list, or choose **Edit** from its **⋯** menu. The edit screen is the same form as Add New Product, pre-filled with the product's current details — change what you need and save. For a Variable product, you'll also see a **Variations** area where you manage each combination (size, color, and so on) with its own price and stock.

---

## A Few Friendly Tips

- **Start rough, refine later.** Only the title is required — save a draft and come back to flesh it out.
- **Use Quick edit for price and stock tweaks.** It's the fastest way to bump a price or mark something out of stock without opening the full editor.
- **Let AI do the first draft.** Generate a description, then edit it to sound like you — much faster than a blank page.
