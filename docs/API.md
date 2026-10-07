# BPC Inventory System API

For developers of other applications that read data from the BPC Inventory System: reporting tools (Power BI, Excel), other company systems, dashboards. Version 1 is **read-only**.

- Base URL: `https://ims.byldinc.com/api/v1`
- Machine-readable description (OpenAPI 3.1): [`https://ims.byldinc.com/api/v1/openapi.yaml`](https://ims.byldinc.com/api/v1/openapi.yaml), also in the repository at `resources/api/openapi.yaml`. Open it in [Swagger Editor](https://editor.swagger.io) or import it into Postman.
- Rules behind the API: [SPEC.md, section 7a](../SPEC.md#7a-api-read-only-version-1).

## 1. Getting access

1. Ask a BPC Inventory administrator for an **API client**. They create it under **Admin ▸ API clients ▸ New API client**: a name for your application, and optionally one site your application may see.
2. The administrator receives a **token** once, e.g. `2|ims_8Lkcqvg…`, and passes it to you through a safe channel (a password manager, not plain email).
3. Keep it secret, like a password: store it in your application's secret store, never in source code.
4. Lost or exposed? The administrator issues a new one (**Issue new token**); the old token stops working immediately.

![API client with its token](user-guide/images/api-client-token.png)

## 2. First request

```sh
TOKEN='2|ims_...'
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
     "https://ims.byldinc.com/api/v1/stock?needs_replenishment=1"
```

```json
{
  "data": [
    {
      "item": { "id": 12, "sku": "CS-30001", "name": "Air filter element", "uom": "pc" },
      "site": "BPC002",
      "qty": "6.000",
      "min_level": "0.000",
      "location": "CO-CS01",
      "is_two_bin": true,
      "qty_per_bin": "6.000",
      "avg_cost": "41.3000",
      "value": "247.8000",
      "status": "refill",
      "on_order": "6.000",
      "last_counted_at": "2026-06-08T07:30:00Z"
    }
  ],
  "links": { "first": "…?page=1", "last": "…?page=1", "prev": null, "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 50, "total": 6 }
}
```

PowerShell:

```powershell
$headers = @{ Authorization = "Bearer $env:IMS_TOKEN"; Accept = "application/json" }
Invoke-RestMethod "https://ims.byldinc.com/api/v1/items?sku=SP-10001" -Headers $headers
```

Power BI / Excel: **Get Data ▸ Web ▸ Advanced**, URL as above, header `Authorization` = `Bearer <token>`.

## 3. Endpoints

| Endpoint | Returns | Filters |
|---|---|---|
| `GET /sites` | sites you may see | |
| `GET /categories` | categories, two levels, with full name | |
| `GET /items` | items with their stock per site | `q` (part of SKU, name, MPN), `sku` (exact), `category` (id; a group includes its subcategories), `criticality` (`HIGH`, `NORMAL`, `LOW`), `active`, `updated_since`, `site` |
| `GET /items/{id}` | one item with stock per site and its suppliers | `site` |
| `GET /suppliers` | suppliers | `active` |
| `GET /suppliers/{id}` | one supplier with the items it supplies, last price, pack size | |
| `GET /stock` | stock per item and site | `item` (id), `sku`, `needs_replenishment`, `two_bin`, `site` |
| `GET /machine-types` | machine types (A, B, C, …) | |
| `GET /machine-types/{code}` | one type with its full parts list | |
| `GET /machines` | the machine register | `type` (letter), `active`, `site` |
| `GET /machines/{sku}` | one machine with the parts list for its revision | |
| `GET /purchase-orders` | orders with lines, newest first | `status` (`open` or a status), `supplier` (id), `updated_since`, `site` |
| `GET /purchase-orders/{number}` | one order, e.g. `PO-2026-0007` | |
| `GET /stock-movements` | every stock movement, oldest first | `after_id`, `item`, `sku`, `type`, `machine`, `from`, `to`, `site` |

Field-by-field descriptions are in the OpenAPI file.

## 4. Conventions

- **Numbers are strings.** `"qty": "6.000"`, `"avg_cost": "41.3000"`: exact values as stored (quantities 3 decimals, USD 4 decimals). Parse them as decimals; floats lose cents.
- **Times** are ISO 8601 in UTC (`2026-10-07T09:15:00Z`); dates are `YYYY-MM-DD`. Sites are in New York time (BPC001) and Denver time (BPC002).
- **Codes** are stable English values: statuses `DRAFT` … `CANCELLED`, movement types `RECEIPT`, `ISSUE_MACHINE`, `ISSUE_GENERAL`, `TRANSFER_OUT`, `TRANSFER_IN`, `ADJUSTMENT`, stock status `out`, `below_min`, `refill`, `ok`, `no_minimum`.
- **On order** counts what is outstanding on orders already sent to suppliers. It is never part of `qty`.
- **Paging:** `page` and `per_page` (default 50, at most 200). Follow `links.next` until it is `null`.
- **Site scope:** without `site`, you get every site you may see. A client limited to one site gets 403 when asking for another, and 404 for a single record (machine, order) at another site.

## 5. Keeping a copy in sync

- **Movements** never change and are never deleted. Store the highest `id` you received and ask for `GET /stock-movements?after_id=<id>&per_page=200`, following `links.next`, on each run.
- **Items and purchase orders**: `updated_since=<last run time>` returns what changed. Purchase order lines change with the order.
- **Stock**: fetch `GET /stock` in full; it is one row per item and site.

## 6. Errors and limits

| Status | Meaning | What to do |
|---|---|---|
| 401 | no token, wrong token, replaced token, or the client was deactivated | ask the administrator for a new token |
| 403 | token limited to another site | drop `site` or use your own site |
| 404 | no such record (or it is at a site you may not see) | check the identifier |
| 405 | not a GET request | version 1 only reads |
| 422 | invalid filter; `errors` lists each field | fix the parameter |
| 429 | more than 120 requests per minute | wait `Retry-After` seconds |

Every answer carries `X-RateLimit-Limit` and `X-RateLimit-Remaining`. Errors are JSON: `{"message": "…"}`.

## 7. Security

- HTTPS only. Tokens are stored hashed; nobody can read a token back, not even the administrator.
- A token can only read. Ask for the smallest scope you need: a client limited to one site cannot see the other.
- Administrators see when each token was last used under **Admin ▸ API clients**.
