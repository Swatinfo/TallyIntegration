<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Tally\Services\Integration\MailService;
use Modules\Tally\Services\Integration\PdfService;
use Modules\Tally\Services\Vouchers\VoucherService;

/**
 * PDF + email convenience endpoints that sit on top of an existing voucher
 * fetched from Tally. Keeps voucher-level concerns out of the core VoucherController.
 */
class IntegrationController extends Controller
{
    public function __construct(
        private VoucherService $vouchers,
        private PdfService $pdf,
        private MailService $mail,
    ) {}

    /**
     * Stream a voucher as a PDF (Content-Type: application/pdf).
     */
    public function voucherPdf(string $masterID): Response
    {
        $voucher = $this->vouchers->get($masterID);
        if (! $voucher) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Voucher not found'], 404);
        }

        $pdf = $this->pdf->renderVoucher($voucher);
        $filename = 'voucher-'.$masterID.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * Send a voucher to one or more recipients as a PDF attachment.
     */
    public function emailVoucher(Request $request, string $masterID): JsonResponse
    {
        $validated = $request->validate([
            'to' => ['required'],
            'cc' => ['sometimes'],
            'bcc' => ['sometimes'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:4000'],
        ]);

        $voucher = $this->vouchers->get($masterID);
        if (! $voucher) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Voucher not found'], 404);
        }

        $result = $this->mail->sendVoucher($voucher, $validated);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Voucher emailed successfully',
        ]);
    }
}
