<?php

namespace Modules\Tally\Services\Integration;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/**
 * Send vouchers as email — HTML body + PDF attachment. Uses whatever mail
 * driver is configured in MAIL_MAILER (log driver is fine for dev).
 */
class MailService
{
    public function __construct(
        private PdfService $pdf,
    ) {}

    /**
     * Email a voucher as a PDF attachment.
     *
     * @param  array  $voucher  Tally voucher data
     * @param  array{to:string|array, cc?:string|array, bcc?:string|array, subject?:string, body?:string}  $recipients
     */
    public function sendVoucher(array $voucher, array $recipients, string $companyName = ''): array
    {
        $pdfContent = $this->pdf->renderVoucher($voucher, $companyName);
        $vchNumber = $voucher['VOUCHERNUMBER'] ?? 'voucher';
        $filename = "voucher-{$vchNumber}.pdf";

        $subject = $recipients['subject']
            ?? sprintf('%s #%s from %s',
                $voucher['VOUCHERTYPENAME'] ?? 'Voucher',
                $vchNumber,
                $companyName ?: config('tally.integration.mail.from_name'));

        $body = $recipients['body']
            ?? "Please find attached {$voucher['VOUCHERTYPENAME']} #{$vchNumber}.\n\nRegards,\n"
             .config('tally.integration.mail.from_name');

        Mail::raw($body, function (Message $m) use ($recipients, $subject, $pdfContent, $filename) {
            $m->from(
                config('tally.integration.mail.from_address'),
                config('tally.integration.mail.from_name'),
            );
            $m->to($recipients['to']);
            if (! empty($recipients['cc'])) {
                $m->cc($recipients['cc']);
            }
            if (! empty($recipients['bcc'])) {
                $m->bcc($recipients['bcc']);
            }
            $m->subject($subject);
            $m->attachData($pdfContent, $filename, ['mime' => 'application/pdf']);
        });

        return [
            'sent_to' => (array) $recipients['to'],
            'subject' => $subject,
            'attachment' => $filename,
            'attachment_size' => strlen($pdfContent),
        ];
    }
}
