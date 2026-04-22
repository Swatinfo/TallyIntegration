<?php

return [

    'name' => 'Tally',

    /*
    |--------------------------------------------------------------------------
    | TallyPrime Default Connection Settings
    |--------------------------------------------------------------------------
    */

    'host' => env('TALLY_HOST', 'localhost'),

    'port' => env('TALLY_PORT', 9000),

    'company' => env('TALLY_COMPANY', ''),

    'timeout' => env('TALLY_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Request/Response Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => env('TALLY_LOG_REQUESTS', true),
        'channel' => 'tally',
        'max_body_size' => 10240,
    ],

    /*
    |--------------------------------------------------------------------------
    | Master Data Caching
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('TALLY_CACHE_ENABLED', true),
        'ttl' => env('TALLY_CACHE_TTL', 300),
        'prefix' => 'tally',
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    */

    'circuit_breaker' => [
        'enabled' => env('TALLY_CIRCUIT_BREAKER', true),
        'failure_threshold' => 5,
        'recovery_timeout' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | XML Export Format
    |--------------------------------------------------------------------------
    |
    | TallyPrime can return Object (single-entity) exports in two formats:
    |   - BinaryXML   — per official Demo Samples; smaller payload.
    |   - $$SysName:XML — plain XML; fallback if BinaryXML triggers a crash
    |                    on the connected Tally installation.
    | Switch via TALLY_OBJECT_EXPORT_FORMAT=SysName if Tally resets the
    | connection mid-response on GET /{conn}/{entity}/{name} endpoints.
    */

    'object_export_format' => env('TALLY_OBJECT_EXPORT_FORMAT', 'BinaryXML'),

    /*
    |--------------------------------------------------------------------------
    | Workflow / Approval thresholds (Phase 9J)
    |--------------------------------------------------------------------------
    |
    | Drafts at or above a matching threshold require explicit approver action
    | (submit → approve → push). Drafts below auto-approve on submit and push
    | immediately. Empty array = all drafts require approval.
    |
    | Each rule: { type: VoucherType enum value, amount: minimum amount requiring approval }
    */

    'workflow' => [
        'enabled' => env('TALLY_WORKFLOW_ENABLED', true),
        'approval_thresholds' => [
            // ['type' => 'Payment', 'amount' => 100000],
            // ['type' => 'Journal', 'amount' => 250000],
        ],
        // Forbid self-approval (maker ≠ checker).
        'require_distinct_approver' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration glue (Phase 9I)
    |--------------------------------------------------------------------------
    */

    'integration' => [
        'pdf' => [
            'driver' => env('TALLY_PDF_DRIVER', 'mpdf'),      // mpdf is the bundled default
            'paper' => env('TALLY_PDF_PAPER', 'A4'),
        ],
        'mail' => [
            'from_address' => env('TALLY_MAIL_FROM', env('MAIL_FROM_ADDRESS', 'accounts@example.com')),
            'from_name' => env('TALLY_MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Accounts')),
        ],
        'attachments' => [
            'disk' => env('TALLY_ATTACHMENT_DISK', 'local'),
            'max_size_kb' => 10240,
            'allowed_mimes' => ['pdf', 'png', 'jpg', 'jpeg', 'xlsx', 'docx', 'txt', 'csv'],
        ],
        'webhooks' => [
            'max_attempts' => 5,
            'backoff_seconds' => [60, 300, 900, 3600, 14400],  // 1min → 4hr
            'timeout_seconds' => 10,
            'queue' => env('TALLY_WEBHOOK_QUEUE', 'default'),
        ],
        'imports' => [
            'disk' => env('TALLY_IMPORT_DISK', 'local'),
            'queue' => env('TALLY_IMPORT_QUEUE', 'default'),
            'chunk_size' => 100,
        ],
    ],

];
