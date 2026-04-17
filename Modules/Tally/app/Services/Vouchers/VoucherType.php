<?php

namespace Modules\Tally\Services\Vouchers;

enum VoucherType: string
{
    case Sales = 'Sales';
    case Purchase = 'Purchase';
    case Payment = 'Payment';
    case Receipt = 'Receipt';
    case Journal = 'Journal';
    case Contra = 'Contra';
    case CreditNote = 'Credit Note';
    case DebitNote = 'Debit Note';
}
