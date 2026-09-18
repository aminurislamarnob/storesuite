---
name: write-feature-docs
description: Write or update end-user documentation for StoreSuite frontend dashboard pages and admin settings by browsing the live site, capturing screenshots, and producing markdown in docs/plugin-docs/. Use when the user asks to document a feature, refresh screenshots, or write user-facing docs for a dashboard page or settings tab.
argument-hint: Which feature(s) to document (e.g. "attributes page", "all undocumented pages", "refresh product screenshots")
---

# StoreSuite Feature Documentation Skill

Task: $ARGUMENTS

You write **documentation for shop owners and shop managers** — not developers. Someone who has never seen the plugin should be able to read a page and use the feature. Never document PHP classes, hooks, or file paths here; that belongs in `CLAUDE.md`.

## Ground Rules

1. **Never write from source code alone.** Every claim must come from a screen you actually loaded in the browser. Read the code only to find *where* a screen lives or what a control is called — then go look at it.
2. **Every major screen gets a screenshot**, placed immediately under the heading it illustrates.
3. **Match the existing house style** (see below). The docs read like a friendly walkthrough, not a reference manual.
4. Don't invent field names, button labels, or behaviour. If a control's purpose isn't obvious from the screen, read the template/JS to confirm before describing it.

## Where Things Live

| Thing | Location |
|-------|----------|
| Docs output | `docs/plugin-docs/<feature-name>/<feature-name>.md` |
| Screenshots | `docs/plugin-docs/<feature-name>/screenshots/screenshot-N.png` — a `screenshots/` subfolder beside the markdown, referenced as `screenshots/screenshot-N.png` |
| Frontend dashboard | `http://woocommerce.test/storesuite-dashboard/` |
| Sub-pages | `.../storesuite-dashboard/<endpoint>/` — endpoint slugs are listed in `includes/Rewrites.php` → `init_query_vars()` |
| Admin settings | `http://woocommerce.test/wp-admin/admin.php?page=storesuite` (hash routes: `#/`, `#/dashboard-settings`, `#/appearance-settings`, `#/pagination-settings`, `#/changelog`) |
| Templates for a page | `templates/<module>/` |
| Landing/index doc | `docs/manage-woocommerce-from-frontend-using-storesuite.md` — the overview article that links the rest |

Everything this skill produces goes under `docs/plugin-docs/`. The older folders directly under `docs/` (`product-management/`, `order-manaments/`, …) are legacy — leave them alone unless the task explicitly says to migrate or refresh them.

So a finished feature looks like:

```
docs/plugin-docs/
└── attributes-management/
    ├── attributes-management.md
    └── screenshots/
        ├── screenshot-1.png
        └── screenshot-2.png
```

### Screenshot numbering

Screenshot filenames are **sequential across all of `docs/plugin-docs/`**, so no two docs reuse a number. Before capturing, find the highest existing number:

```bash
ls docs/plugin-docs/*/screenshots/screenshot-*.png 2>/dev/null | grep -oE '[0-9]+\.png' | grep -oE '[0-9]+' | sort -n | tail -1
```

Continue from there (start at `1` if nothing exists yet). Don't renumber existing files.

## Workflow

### 1. Inventory what's missing

List the dashboard endpoints in `includes/Rewrites.php` (`init_query_vars`), the sidebar items in `includes/DashboardMenu.php`, and the admin settings routes in `src/Components/Layout.js`, then diff against `ls docs/plugin-docs/`. Report the gap before starting so the user can see the plan.

### 2. Open the browser

Load the Chrome tools in **one** ToolSearch call:

```
select:mcp__claude-in-chrome__tabs_context_mcp,mcp__claude-in-chrome__tabs_create_mcp,mcp__claude-in-chrome__navigate,mcp__claude-in-chrome__computer,mcp__claude-in-chrome__read_page,mcp__claude-in-chrome__get_page_text,mcp__claude-in-chrome__resize_window,mcp__claude-in-chrome__find
```

Call `tabs_context_mcp` first. Create a **new tab** for this work — don't hijack one of the user's. The user is already logged in to `woocommerce.test`; if a page redirects to login, stop and tell them.

Resize the window to a wide desktop viewport (around **1600×1000**) before capturing so tables aren't cramped and the sidebar is expanded.

### 3. Walk every screen of the feature

For each feature, cover the full loop, not just the list view:

- The **list/index** screen (table columns, what each column means, search, pagination)
- **Filters** and any slide-in panels — open them
- **Row actions** — hover/open the `⋯` menu and describe each item
- **Bulk actions** — open the dropdown and list the options
- **Add new** form — every section, every field, which are required
- **Edit** form — call out only what differs from Add
- **Empty states**, confirmation modals, and success/error notices when you can trigger them safely

Do **not** click Delete, submit destructive actions, or anything that fires a `confirm()` dialog on the user's real data. Describe those from the UI instead.

### 4. Capture screenshots

Create the screenshots folder first:

```bash
mkdir -p docs/plugin-docs/<feature-name>/screenshots
```

Take a screenshot with `computer` (action `screenshot`, `save_to_disk: true`), then move the returned file to `docs/plugin-docs/<feature-name>/screenshots/screenshot-N.png`. Verify each one landed and has real dimensions:

```bash
sips -g pixelWidth -g pixelHeight docs/plugin-docs/<feature-name>/screenshots/screenshot-N.png
```

Scroll so the relevant UI is in frame. Prefer one clean full-screen capture per concept over many partial ones. Existing screenshots run roughly 3000–3600px wide (retina); anything in that ballpark is fine.

### 5. Write the markdown

Create `docs/plugin-docs/<feature-name>/<feature-name>.md`. Image paths are relative to that file, so they always start with `screenshots/`. Follow this shape:

```markdown
# <Doing the thing> in StoreSuite

<A short, warm intro: what this section is for and why someone would open it.>

To get there, click **<Menu Item>** in the left sidebar.

---

## The <Thing> List

![<Alt text>](screenshots/screenshot-N.png)

<What the screen is. Then a table of columns:>

| Column | What it tells you |
|--------|-------------------|
| **Name** | ... |

### Finding a <thing> fast
### Quick actions on any row
### Doing things in bulk

---

## Filtering

![Filters](screenshots/screenshot-N.png)

---

## Adding a New <Thing>

![Add New](screenshots/screenshot-N.png)

### The basics
- **Field Name** *(required)* — what it does, in plain language.

---

## Editing an Existing <Thing>

---

## Tips
> **Tip:** ...
```

### 6. Link it from the overview

Add or update the feature's section in `docs/manage-woocommerce-from-frontend-using-storesuite.md` so the new page is reachable.

## House Style

Read `docs/product-management/product-management.md` before writing — it's the reference implementation. The voice:

- **Second person, present tense.** "Click **Filter** and a panel slides in from the side."
- **Bold for anything the user clicks or types into** — buttons, field labels, menu items.
- **Explain the *why*, not just the *what*.** "Great for restock day." "So you know what to expect."
- **Short paragraphs.** Two or three sentences, then a break.
- Tables for column/field reference. Bulleted lists for options.
- `> **Tip:**` blockquotes for genuinely useful advice — sparingly, one or two per page.
- `---` horizontal rules between major sections.
- No jargon without explanation. No "simply", "just", or "obviously".
- Say "your store", "your team", "your catalog" — it's their shop.

## Finishing

After each feature is written:

1. Confirm every `![...](screenshots/screenshot-N.png)` resolves to a file that exists.
2. Re-read the doc as if you'd never used the plugin — is anything unexplained?
3. Report which screens you captured and anything you couldn't reach (permission-gated, needed data you didn't want to create, etc.).
