# Deferred Phase Plans

Detailed implementation specs for phases that are **intentionally deferred** because they have external dependencies that must be resolved before coding. When those decisions are made, use this document as the build brief.

**Last updated:** 2026-04-17

Status summary:

| Phase | Theme | Blocked on | Est. effort |
|---|---|---|---|
| **9E** | Tax compliance (GST returns, E-Invoice, E-Way Bill, TDS/TCS) | GSP (GST Suvidha Provider) partnership | 5 engineer-weeks |
| **9I** | Integration glue (PDF, email, attachments, webhooks, CSV import) | PDF library + mail driver + storage disk decisions | 4 engineer-weeks |

---

# Phase 9E — Tax Compliance

## Scope

Tax/compliance features required for any Indian B2B business operating at ₹5Cr+ turnover. Everything here is **mandatory filing** or compliance — skipping it blocks customers from shipping to production in India.

### Features

| Feature | Tally source | Notes |
|---|---|---|
| **GSTR-1** export | Tally report `GSTR-1` | Monthly outward-supply filing |
| **GSTR-3B** export | Tally report `GSTR-3B` | Monthly summary filing |
| **GSTR-2A / 2B reconciliation** | Tally report + external GSTN data | ITC matching |
| **E-Invoicing (IRN + QR)** | External — IRP portal via GSP | Mandatory for turnover > ₹5Cr |
| **E-Way Bill generation** | External — NIC portal via GSP | Mandatory for goods > ₹50k |
| **HSN-wise summary** | Tally report `HSN Summary` | GST compliance requirement |
| **TDS Outstandings + Form 26Q** | Tally report `TDS Outstandings` + Form 26Q export | Mandatory TDS filers |
| **TCS returns** | Tally report + return generation | E-commerce / metals / scrap |
| **RCM (Reverse Charge Mechanism)** dedicated flow | Voucher flag + dedicated report | Services from unregistered vendors |
| **ITC mismatch report** | Compares internal vouchers vs 2A/2B data | Monthly compliance exercise |
| **GST liability payment voucher** | Voucher type in Tally | Monthly settlement |

## External dependencies — MUST be resolved before starting

### 1. GST Suvidha Provider (GSP) partnership — HARD BLOCK

E-Invoicing and E-Way Bill APIs can only be accessed through a licensed GSP (NIC does not expose public APIs). Options:

| Provider | Approx. pricing | Notes |
|---|---|---|
| **ClearTax (Clear)** | Usage-based | Largest market share; REST API |
| **Masters India** | Usage-based | Strong documentation |
| **IRIS GST** | Enterprise | Good for large volumes |
| **Cygnet InfoTech** | Usage-based | REST + SOAP options |
| **TaxPro (KSolves)** | Usage-based | Budget option |

**Decision needed:** which GSP? Credentials + sandbox access come after signing a commercial agreement with the chosen vendor.

### 2. Digital Signature Certificate (DSC) — OPTIONAL until production

For IRN signing in production. NIC sandbox allows unsigned calls for testing.

- **Class 3 DSC** for the authorised signatory of each registered GSTIN
- Token-based (USB crypto device) or cloud-based DSC via providers like eMudhra

### 3. Test / Sandbox credentials

Before production: GSTN provides a sandbox for E-Invoicing and E-Way Bill. Most GSPs expose this as their own sandbox endpoint.

---

## Architecture

### New tables (4)

```
tally_gst_profiles            — one per registered GSTIN (a company may have many)
├── id
├── tally_connection_id (FK)
├── gstin (string, unique)
├── legal_name, trade_name, state, pincode
├── gsp_provider (string)     — 'cleartax' | 'masters_india' | 'iris' | ...
├── gsp_credentials (encrypted json)
├── irp_environment (string)  — 'sandbox' | 'production'
├── dsc_alias (string, nullable)
└── is_active, created_at, updated_at

tally_einvoices               — one per generated IRN
├── id
├── tally_gst_profile_id (FK)
├── voucher_master_id (string) — links back to Tally voucher
├── voucher_number (string)
├── irn (string, unique)
├── ack_number, ack_date
├── qr_code (text, base64)
├── signed_invoice (text, IRP payload)
├── signed_qr (text)
├── status (string)           — 'generated' | 'cancelled' | 'failed'
├── error_data (json, nullable)
├── cancelled_at, cancel_reason
└── created_at, updated_at

tally_eway_bills
├── id
├── tally_gst_profile_id (FK)
├── voucher_master_id (string)
├── ewb_number (string, unique)
├── ewb_date
├── valid_until (datetime)
├── from_pincode, to_pincode
├── transport_mode, transport_doc_number
├── vehicle_number
├── status (string)           — 'active' | 'cancelled' | 'extended' | 'expired'
├── cancel_reason, cancelled_at
├── extension_count
└── created_at, updated_at

tally_tax_filings            — tracks GSTR-1, GSTR-3B, TDS returns
├── id
├── tally_gst_profile_id (FK)
├── filing_type (string)      — 'gstr_1' | 'gstr_3b' | 'gstr_2a' | 'tds_26q' | ...
├── period_month, period_year
├── status (string)           — 'draft' | 'generated' | 'filed' | 'amended'
├── data (json)               — the full return payload
├── filed_at, acknowledgement_number
└── created_at, updated_at
```

### New services (3)

```
Modules/Tally/app/Services/Tax/
├── GspClient.php              (interface) — abstract GSP provider
├── Providers/
│   ├── ClearTaxGspClient.php
│   ├── MastersIndiaGspClient.php
│   └── IrisGspClient.php      (as needed)
├── EInvoiceService.php        — generate/cancel/fetch IRN + QR
├── EWayBillService.php        — generate/cancel/extend/fetch E-way bill
└── TaxReturnService.php       — GSTR-1 / 3B / 26Q builders from Tally reports
```

`GspClient` interface methods:
```php
interface GspClient {
    public function generateIrn(array $payload): array;
    public function cancelIrn(string $irn, string $reason): array;
    public function fetchIrn(string $irn): array;
    public function generateEWayBill(array $payload): array;
    public function cancelEWayBill(string $ewbNumber, string $reason): array;
    public function extendEWayBill(string $ewbNumber, array $payload): array;
    public function fetchEWayBill(string $ewbNumber): array;
}
```

### New controllers (3)

- `GstProfileController` — CRUD for `tally_gst_profiles` (credentials encrypted at rest)
- `EInvoiceController` — generate / cancel / fetch IRN; listing with filters
- `EWayBillController` — generate / cancel / extend / fetch; listing

### New ReportController dispatch types (6)

- `gstr-1`, `gstr-3b`, `gstr-2a`, `gstr-2b-reconciliation`
- `hsn-summary`
- `tds-outstandings`, `tds-26q`

### New routes (~15)

```
POST   /connections/{id}/gst-profiles          (CRUD)
GET    /gst-profiles/{id}/einvoices
POST   /gst-profiles/{id}/einvoices            (generates IRN)
POST   /einvoices/{id}/cancel
GET    /einvoices/{id}

GET    /gst-profiles/{id}/eway-bills
POST   /gst-profiles/{id}/eway-bills
POST   /eway-bills/{id}/cancel
POST   /eway-bills/{id}/extend
GET    /eway-bills/{id}

POST   /gst-profiles/{id}/tax-filings/{type}   — build + save draft return
POST   /tax-filings/{id}/submit                — file with GSTN (via GSP)

GET    /{c}/reports/gstr-1?period=YYYYMM
GET    /{c}/reports/gstr-3b?period=YYYYMM
... (6 new report types)
```

### New permissions (2)

- `manage_tax` — create/update GST profiles, generate returns
- `manage_einvoicing` — issue/cancel IRN and E-Way Bill

### New config block

```php
'tax' => [
    'default_gsp' => env('TALLY_GSP_PROVIDER', 'cleartax'),
    'irp_environment' => env('TALLY_IRP_ENV', 'sandbox'),
    'eway_bill_buffer_hours' => 6,  // warn when EWB expires within X hrs
    'dsc_token_path' => env('TALLY_DSC_TOKEN_PATH'),  // optional for signing
],
```

## Implementation sequence (5 weeks)

| Week | Deliverable |
|---|---|
| 1 | GSP partnership signed; sandbox creds received. `tally_gst_profiles` table + CRUD. Abstract `GspClient` interface + chosen provider adapter. |
| 2 | E-Invoicing: `EInvoiceService` + `tally_einvoices` table + 4 routes. End-to-end sandbox test (generate → QR → cancel). |
| 3 | E-Way Bill: `EWayBillService` + `tally_eway_bills` table + 5 routes. Extension flow. |
| 4 | Tax returns: `TaxReturnService` + 6 new report types (GSTR-1/3B/2A/2B, HSN summary, TDS). GSTR payload builder from Tally report data. |
| 5 | `TaxFilingController` for draft/submit/amend + `tally_tax_filings` table. Integration tests with sandbox. Docs + smoke test. |

## Open questions to resolve before starting

1. **Which GSP?** Default implementation targets one; swap via config.
2. **Multi-GSTIN handling** — does the MVP support multiple GSTINs per company (yes, `tally_gst_profiles` is plural) or just one?
3. **DSC signing** — cloud-based (provider API) or on-prem USB token (server needs physical access)?
4. **Retention** — how long do we keep `signed_invoice` payloads? IRP requires 8 years by law; confirm storage plan.
5. **Webhook from GSP** — most GSPs can push IRN-cancelled notifications. Adds a webhook endpoint.

## Smoke test additions

When 9E ships:
- New `phase_9e_tax` covering GSTIN CRUD, one IRN generate + cancel in sandbox, one E-Way Bill generate + cancel, one GSTR-1 draft build.

---

# Phase 9I — Integration Glue

> ✅ **SHIPPED 2026-04-17.** This section is retained as the historical build brief. Current state: see `product-roadmap.md` § Phase 9I and `Modules/Tally/docs/API-USAGE.md` § 10a. The decisions landed as: **mpdf** (PDF), **Laravel's configured mailer** (`log` for dev, swap via `MAIL_MAILER`), **`local` disk** (swap via `FILESYSTEM_DISK`), **database queue** for webhooks.

## Scope

The external-facing integration layer that connects Tally data to the rest of the business: PDF invoices, email delivery, CSV/Excel bulk operations, webhooks to downstream systems, file attachments on vouchers.

### Features

| Feature | Complexity |
|---|---|
| **CSV / Excel import** of masters (bulk-create ledgers / stock items / groups from a spreadsheet) | Medium |
| **CSV / Excel import** of vouchers (month-end bulk loads) | Medium |
| **CSV / Excel export** of any master/voucher list | Small |
| **PDF invoice generation** on demand | Medium |
| **Email invoice** directly from API (send to customer) | Small |
| **Voucher attachments** — upload/download supporting documents | Medium |
| **Webhooks** — outbound HTTP POST on Tally events (voucher-created, sync-completed, etc.) with retry + delivery log | Large |
| **WhatsApp notification** on voucher | Small — deferred, needs separate provider decision |
| **Digital signature** on generated PDFs | Deferred |

## External dependencies — MUST be resolved before starting

### 1. PDF library

| Option | Pros | Cons |
|---|---|---|
| **dompdf** | Zero config, pure PHP, simple HTML→PDF | Limited CSS support; slow on complex layouts |
| **spatie/browsershot** | Chromium-based, pixel-perfect, great CSS | Requires Chromium on server + Node |
| **mpdf** | Battle-tested, good i18n | Older API |

**Recommendation:** `browsershot` for invoices (customer-facing, needs to look right). `dompdf` for internal reports.

### 2. Mail driver

Configure one of:

| Driver | Notes |
|---|---|
| **SMTP** | Works with any provider; slowest option |
| **AWS SES** | Cheapest at volume, best deliverability with warm IPs |
| **Postmark** | Premium transactional deliverability |
| **Resend** | Modern API, good DX |

**Decision:** which mail driver for the MVP?

### 3. Attachment storage disk

| Disk | When to pick |
|---|---|
| `local` | Single-server deployment; not production for multi-server |
| `s3` | Production multi-server; requires AWS creds |
| `gcs` | Google Cloud deployment |

**Decision:** which disk?

### 4. Queue driver (already configured, but webhooks scale this up)

If webhooks fire at high volume, `database` queue becomes a bottleneck. Upgrade to `redis` or `sqs`.

---

## Architecture

### New tables (4)

```
tally_webhook_endpoints        — registered webhook targets
├── id
├── tally_connection_id (FK, nullable)  — null = global subscription
├── name, url, secret (for HMAC signing)
├── events (json array)        — ['voucher.created', 'sync.completed', ...]
├── headers (json, nullable)   — custom headers
├── is_active
├── failure_count, last_failure_at
└── created_at, updated_at

tally_webhook_deliveries       — per-delivery attempt log
├── id
├── tally_webhook_endpoint_id (FK)
├── event (string)
├── payload (json)
├── attempt_number
├── status (string)             — 'pending' | 'delivered' | 'failed'
├── response_code, response_body (truncated)
├── delivered_at, next_retry_at
└── created_at

tally_voucher_attachments
├── id
├── tally_connection_id (FK)
├── voucher_master_id (string)
├── file_disk (string)
├── file_path (string)
├── original_name, mime_type, size_bytes
├── uploaded_by (FK users)
└── created_at, updated_at

tally_import_jobs              — async CSV/Excel import tracking
├── id
├── tally_connection_id (FK)
├── entity_type (string)       — 'ledger' | 'stock_item' | 'voucher' | ...
├── file_path (string)
├── total_rows, processed_rows, failed_rows
├── status                     — 'queued' | 'running' | 'completed' | 'failed'
├── result_summary (json)
├── uploaded_by (FK users)
└── created_at, updated_at
```

### New services (5)

```
Modules/Tally/app/Services/Integration/
├── PdfService.php             — render voucher → PDF via chosen lib
├── MailService.php            — send voucher as PDF attachment
├── AttachmentService.php      — upload/download/delete on chosen disk
├── ImportService.php          — parse CSV/Excel, queue ImportBatchJob, track progress
└── WebhookDispatcher.php      — deliver with HMAC signature, exponential backoff retry
```

### New jobs (2)

```
ProcessImportJob.php           — consume an import job, create entities
DeliverWebhookJob.php          — one delivery attempt; reschedules on failure
```

### New events (wire to WebhookDispatcher via listener)

Existing events already emit the right signals:
- `TallyVoucherCreated`, `TallyVoucherAltered`, `TallyVoucherCancelled`
- `TallyMasterCreated`, `TallyMasterUpdated`, `TallyMasterDeleted`
- `TallySyncCompleted`, `TallyConnectionHealthChanged`

Add one listener class `DispatchWebhooksOnTallyEvent` that handles all events.

### New controllers (5)

- `WebhookController` — CRUD for endpoints + delivery log viewer
- `AttachmentController` — upload / list / download / delete per voucher
- `ImportController` — POST upload → queue + GET status
- `ExportController` — streaming CSV/Excel of masters/vouchers
- `PdfController` — on-demand voucher PDF (streaming)
- `MailController` — send an invoice by email

### New routes (~15)

```
GET/POST   /connections/{id}/webhooks          (CRUD)
GET/DELETE /webhooks/{id}
GET        /webhooks/{id}/deliveries           (delivery history)
POST       /webhooks/{id}/test                 (fire a test event)

POST       /{c}/vouchers/{id}/attachments      (upload, multipart)
GET        /{c}/vouchers/{id}/attachments
GET        /attachments/{id}/download          (streamed file)
DELETE     /attachments/{id}

POST       /{c}/import/{entity}                (POST CSV, returns import-job id)
GET        /import-jobs/{id}                   (progress)
GET        /{c}/export/{entity}                (streamed CSV)

GET        /{c}/vouchers/{id}/pdf              (streamed PDF)
POST       /{c}/vouchers/{id}/email            (send to {to, cc?, bcc?})
```

### New permissions (2)

- `manage_integrations` — webhooks, imports, attachments
- `send_invoices` — email vouchers to customers

### New config block

```php
'integration' => [
    'pdf' => [
        'driver' => env('TALLY_PDF_DRIVER', 'browsershot'),  // dompdf | browsershot | mpdf
        'template' => env('TALLY_PDF_TEMPLATE', 'default'),
    ],
    'mail' => [
        'from_address' => env('TALLY_MAIL_FROM', 'accounts@example.com'),
        'from_name' => env('TALLY_MAIL_FROM_NAME', 'Accounts'),
    ],
    'attachments' => [
        'disk' => env('TALLY_ATTACHMENT_DISK', 'local'),
        'max_size_kb' => 10240,
        'allowed_mimes' => ['pdf', 'png', 'jpg', 'jpeg', 'xlsx', 'docx'],
    ],
    'webhooks' => [
        'max_attempts' => 5,
        'backoff_seconds' => [60, 300, 900, 3600, 14400],  // 1min → 4hr
        'timeout_seconds' => 10,
        'queue' => 'tally-webhooks',
    ],
    'imports' => [
        'queue' => 'tally-imports',
        'chunk_size' => 100,
    ],
],
```

## Implementation sequence (4 weeks)

| Week | Deliverable |
|---|---|
| 1 | Decisions locked: PDF lib, mail driver, storage disk. `PdfService` + `MailService` + on-demand voucher PDF + email endpoint. |
| 2 | `AttachmentService` + attachments CRUD + `tally_voucher_attachments` table. |
| 3 | `ImportService` + `ProcessImportJob` + `tally_import_jobs` table + 2 routes (POST import, GET status). CSV export. Excel support behind a flag (needs `phpoffice/phpspreadsheet`). |
| 4 | Webhooks: `WebhookDispatcher` + `DeliverWebhookJob` + `tally_webhook_endpoints` + `tally_webhook_deliveries` + event listener + 4 routes. HMAC signing + exponential backoff. |

## Open questions to resolve before starting

1. **PDF library** — browsershot (pixel-perfect) or dompdf (zero-dep)?
2. **Mail driver** — SES / Postmark / SMTP?
3. **Attachment storage** — local / S3 / GCS?
4. **PDF template** — one default layout, or configurable per-customer templates (bigger scope)?
5. **Webhook retry budget** — 5 attempts over 4 hours (recommended) or different?
6. **Excel support** — MVP is CSV only; Excel via `phpoffice/phpspreadsheet` is ~+3 days.

## Smoke test additions

When 9I ships:
- New `phase_9i_integration` covering: upload attachment → list → download → delete; create webhook endpoint → fire test → inspect delivery log; POST one-line CSV import → poll until `completed`; download voucher PDF; email a voucher (mock mail driver in test).

---

# How to unblock these phases

Both plans are ready to implement **the moment** the external decisions are made. Send the answers to the "Open questions" sections above, and either phase can kick off in the next session.

- For **9E**: send the GSP vendor + sandbox credentials (sandbox is fine for initial coding; production DSC can wait).
- For **9I**: send the 3 choices (PDF lib, mail driver, storage disk). Everything else is internal.
