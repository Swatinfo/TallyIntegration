<?php

namespace Modules\Tally\Enums;

enum TallyPermission: string
{
    case ViewMasters = 'view_masters';
    case ManageMasters = 'manage_masters';
    case ViewVouchers = 'view_vouchers';
    case ManageVouchers = 'manage_vouchers';
    case ViewReports = 'view_reports';
    case ManageConnections = 'manage_connections';

    // Phase 9J — approver role, distinct from ManageVouchers (maker).
    // A user with only this permission can approve/reject other users' drafts
    // but cannot create or edit their own.
    case ApproveVouchers = 'approve_vouchers';

    // Phase 9I — integration management (webhooks, imports, attachments).
    case ManageIntegrations = 'manage_integrations';

    // Phase 9I — send vouchers as email (PDF attachment).
    case SendInvoices = 'send_invoices';
}
