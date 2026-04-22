<?php

namespace Modules\Tally\Services\Integration;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Models\TallyVoucherAttachment;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Voucher attachments — upload/download/delete on whatever disk is configured
 * in tally.integration.attachments.disk (default: local).
 */
class AttachmentService
{
    public function upload(TallyConnection $connection, string $voucherMasterId, UploadedFile $file, ?int $userId = null): TallyVoucherAttachment
    {
        $disk = config('tally.integration.attachments.disk', 'local');
        $dir = "tally/attachments/{$connection->id}/{$voucherMasterId}";

        $path = $file->store($dir, $disk);

        return TallyVoucherAttachment::create([
            'tally_connection_id' => $connection->id,
            'voucher_master_id' => $voucherMasterId,
            'file_disk' => $disk,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);
    }

    public function list(TallyConnection $connection, string $voucherMasterId): Collection
    {
        return TallyVoucherAttachment::query()
            ->where('tally_connection_id', $connection->id)
            ->where('voucher_master_id', $voucherMasterId)
            ->latest('id')
            ->get();
    }

    public function stream(TallyVoucherAttachment $attachment): StreamedResponse
    {
        return Storage::disk($attachment->file_disk)->download(
            $attachment->file_path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type],
        );
    }

    public function delete(TallyVoucherAttachment $attachment): bool
    {
        Storage::disk($attachment->file_disk)->delete($attachment->file_path);

        return $attachment->delete();
    }
}
