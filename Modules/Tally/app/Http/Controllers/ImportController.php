<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\StartImportRequest;
use Modules\Tally\Jobs\ProcessImportJob;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Models\TallyImportJob;
use Modules\Tally\Services\Integration\ImportService;

class ImportController extends Controller
{
    public function __construct(
        private ImportService $service,
    ) {}

    public function start(StartImportRequest $request, TallyConnection $connection, string $entity): JsonResponse
    {
        if (! in_array($entity, ['ledger', 'group', 'stock_item', 'stock-item'], true)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => "Unsupported entity type '{$entity}'. Supported: ledger, group, stock-item",
            ], 422);
        }

        $job = $this->service->queueImport($connection, $entity, $request->file('file'), auth()->id());
        ProcessImportJob::dispatch($job->id);

        return response()->json([
            'success' => true,
            'data' => $job,
            'message' => "Import job queued (id={$job->id}) — poll /import-jobs/{$job->id} for progress",
        ], 202);
    }

    public function status(TallyImportJob $importJob): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $importJob,
            'message' => 'Import job retrieved successfully',
        ]);
    }
}
