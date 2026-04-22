<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\UploadAttachmentRequest;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Models\TallyVoucherAttachment;
use Modules\Tally\Services\Integration\AttachmentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(
        private AttachmentService $service,
    ) {}

    public function index(TallyConnection $connection, string $masterID): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->list($connection, $masterID),
            'message' => 'Attachments retrieved successfully',
        ]);
    }

    public function store(UploadAttachmentRequest $request, TallyConnection $connection, string $masterID): JsonResponse
    {
        $attachment = $this->service->upload($connection, $masterID, $request->file('file'), auth()->id());

        return response()->json([
            'success' => true,
            'data' => $attachment,
            'message' => 'Attachment uploaded successfully',
        ], 201);
    }

    public function download(TallyVoucherAttachment $attachment): StreamedResponse
    {
        return $this->service->stream($attachment);
    }

    public function destroy(TallyVoucherAttachment $attachment): JsonResponse
    {
        $this->service->delete($attachment);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Attachment deleted successfully',
        ]);
    }
}
