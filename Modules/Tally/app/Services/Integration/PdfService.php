<?php

namespace Modules\Tally\Services\Integration;

use Mpdf\Mpdf;

/**
 * PDF generation via mpdf/mpdf. Outputs binary PDF content; the caller streams
 * it to the HTTP response with Content-Type: application/pdf.
 */
class PdfService
{
    /**
     * Render a voucher dict as a PDF invoice.
     */
    public function renderVoucher(array $voucher, string $companyName = ''): string
    {
        $html = $this->voucherHtml($voucher, $companyName);

        return $this->htmlToPdf($html, "voucher-{$voucher['VOUCHERNUMBER']}");
    }

    /**
     * Generic HTML → PDF. Caller supplies full HTML; we set reasonable mpdf defaults.
     */
    public function htmlToPdf(string $html, string $title = 'document'): string
    {
        $mpdf = new Mpdf([
            'format' => config('tally.integration.pdf.paper', 'A4'),
            'tempDir' => storage_path('app/mpdf-tmp'),
            'default_font' => 'dejavusans',
        ]);

        $mpdf->SetTitle($title);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S'); // return binary string
    }

    /**
     * Minimal invoice HTML — callers can override with their own template.
     */
    private function voucherHtml(array $voucher, string $companyName = ''): string
    {
        $entries = $voucher['ALLLEDGERENTRIES.LIST'] ?? [];
        if (isset($entries['LEDGERNAME'])) {
            $entries = [$entries];
        }

        $rows = '';
        foreach ($entries as $e) {
            $rows .= '<tr><td>'.e($e['LEDGERNAME'] ?? '').'</td><td style="text-align:right">'.e($e['AMOUNT'] ?? '').'</td></tr>';
        }

        $title = e($voucher['VOUCHERTYPENAME'] ?? 'Voucher').' #'.e($voucher['VOUCHERNUMBER'] ?? '');
        $date = e($voucher['DATE'] ?? '');
        $narration = e($voucher['NARRATION'] ?? '');
        $co = e($companyName);

        return <<<HTML
<style>
  body { font-family: dejavusans, sans-serif; color: #222; }
  h1 { margin: 0 0 4px; font-size: 20px; }
  .meta { color: #666; font-size: 11px; margin-bottom: 16px; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  th, td { border-bottom: 1px solid #ddd; padding: 6px 8px; font-size: 12px; }
  th { background: #f4f4f4; text-align: left; }
</style>
<h1>$title</h1>
<div class="meta">Company: $co &nbsp;·&nbsp; Date: $date</div>
<p style="font-size:12px">$narration</p>
<table>
  <thead><tr><th>Ledger</th><th style="text-align:right">Amount</th></tr></thead>
  <tbody>$rows</tbody>
</table>
HTML;
    }
}
