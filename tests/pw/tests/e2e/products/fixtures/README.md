# Product CSV import fixtures

Three files for the CSV import wizard checks. Every SKU is prefixed `CSV-` so a run
is easy to spot and easy to clean up:

```bash
wp eval 'foreach ( wc_get_products( [ "limit" => -1, "return" => "ids" ] ) as $id ) {
	if ( 0 === strpos( (string) get_post_meta( $id, "_sku", true ), "CSV-" ) ) { wp_delete_post( $id, true ); }
}'
```

Headers use WooCommerce's own export column names, so the mapping step auto-maps
every column and needs no hand-correction.

## `products-import-basic.csv`

Five valid simple products. Import with **Update existing products** off, against a
store that has no `CSV-` products yet.

| Summary tile | Expected |
|---|---|
| Products imported | 5 |
| everything else | absent |

The done step should read *"Your products are ready to review."* with **no**
**View import log** button.

## `products-import-update.csv`

The same five SKUs with changed names, prices, stock and published state. Run
`products-import-basic.csv` first, then import this one with **Update existing
products** **on**.

| Summary tile | Expected |
|---|---|
| Products updated | 5 |
| Products imported | absent — nothing is created |

Spot-check that `CSV-TEE-1` is renamed to *Cotton Crew T-Shirt (2026 cut)* at 27.50
and that `CSV-CAP-4` went out of stock and unpublished, rather than a second copy of
each product appearing in the list.

With the switch **off** this same file is the duplicate-catalog trap the docs warn
about — every row is skipped as *"A product with this SKU already exists."*

## `products-import-errors.csv`

Four rows: two import, one fails, one is skipped. Import with **Update existing
products** off, against a store with no `CSV-` products.

| Row | Outcome | Reason shown in the log |
|---|---|---|
| 1 `CSV-OK-6` | imported | — |
| 2 `CSV-BAD-7` | failed | Invalid product type. (`widget` is not a WooCommerce type) |
| 3 `CSV-OK-9` | imported | — |
| 4 `CSV-OK-6` | skipped | A product with this SKU already exists. (repeats row 1) |

| Summary tile | Expected |
|---|---|
| Products imported | 2 |
| Products skipped | 1 |
| Products failed | 1 |

The done step should read *"some rows need your attention"*, show a **View import
log** button, and expand to a two-row table naming both reasons.
