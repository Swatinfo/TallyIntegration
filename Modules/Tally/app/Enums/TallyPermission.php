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
}
