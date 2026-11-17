# Currency conventions

USD is the source of truth for every amount in this store. NPR is an optional
override that an admin can enter, and otherwise it is derived from USD.

## Storage

For each money field `<field>`:

| Column | Type | Required | Meaning |
| --- | --- | --- | --- |
| `<field>` | `DECIMAL(...)` | yes | Amount in USD. |
| `<field>_npr` | `DECIMAL(12,2)` | no | Manual NPR override. `NULL` means auto. |

The effective NPR amount is:

```text
effective_npr = <field>_npr ?? round(<field> * usd_to_npr_rate)
```

Products follow this with `price` / `price_npr` and
`compare_at_price` / `compare_at_price_npr`. New tables that need money should
use the same `<field>` + `<field>_npr` pattern. Categories and collections do
not store amounts.

## The rate

- Stored in the single-row `store_settings` table as
  `usd_to_npr_rate DECIMAL(10,4)` (initial default `135`).
- Edited at **Administration → Store settings** (`/admin/store-settings`).
  The rate must be greater than zero.
- Read through `App\Services\CurrencyConverter`, which caches it. Saving the
  setting clears the cache automatically.
- `CurrencyConverter::usdToNpr()` rounds to whole rupees, matching the
  storefront. `CurrencyConverter::nprToUsd()` returns two decimal places.
  bcmath is used when installed; otherwise the code falls back to
  `round(..., PHP_ROUND_HALF_UP)`.

## Rules

- Never store only an NPR amount. Every money value needs its USD source.
- Never convert NPR back into USD to fill the USD column; USD is entered by
  the admin, NPR is the derived side.
- Seeded catalog data leaves all `_npr` columns `NULL` so it is always
  converted at the current rate.
- Orders (when they exist) must snapshot the USD amount, the NPR amount and
  the rate used at the time of the order. Do not recompute historical totals
  from the current rate.
