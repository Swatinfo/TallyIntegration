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

    // Phase 9F — order processing + inventory dispatch vouchers
    case SalesOrder = 'Sales Order';
    case PurchaseOrder = 'Purchase Order';
    case Quotation = 'Quotation';
    case DeliveryNote = 'Delivery Note';
    case ReceiptNote = 'Receipt Note';
    case RejectionIn = 'Rejections In';
    case RejectionOut = 'Rejections Out';
    case StockJournal = 'Stock Journal';
    case PhysicalStock = 'Physical Stock';

    // Phase 9G — manufacturing + job work voucher types
    case ManufacturingJournal = 'Manufacturing Journal';
    case JobWorkInOrder = 'Job Work In Order';
    case JobWorkOutOrder = 'Job Work Out Order';
}
