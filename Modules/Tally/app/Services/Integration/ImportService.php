<?php

namespace Modules\Tally\Services\Integration;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Models\TallyImportJob;
use Modules\Tally\Services\Masters\GroupService;
use Modules\Tally\Services\Masters\LedgerService;
use Modules\Tally\Services\Masters\StockItemService;

/**
 * CSV bulk-import of masters. Stores the uploaded file on disk, creates an
 * import job record, then ProcessImportJob picks it up from the queue.
 */
class ImportService
{
    public function __construct(
        private LedgerService $ledgers,
        private GroupService $groups,
        private StockItemService $stockItems,
    ) {}

    public function queueImport(TallyConnection $connection, string $entityType, UploadedFile $file, ?int $userId = null): TallyImportJob
    {
        $disk = config('tally.integration.imports.disk', 'local');
        $dir = "tally/imports/{$connection->id}";
        $path = $file->store($dir, $disk);

        return TallyImportJob::create([
            'tally_connection_id' => $connection->id,
            'entity_type' => $entityType,
            'file_disk' => $disk,
            'file_path' => $path,
            'status' => TallyImportJob::STATUS_QUEUED,
            'uploaded_by' => $userId,
        ]);
    }

    /**
     * Run an import job synchronously (called from ProcessImportJob::handle()).
     */
    public function run(TallyImportJob $job): void
    {
        $job->update(['status' => TallyImportJob::STATUS_RUNNING]);

        try {
            $csv = Storage::disk($job->file_disk)->get($job->file_path);
            $rows = $this->parseCsv($csv);
            $job->update(['total_rows' => count($rows)]);

            $created = 0;
            $failed = 0;
            $errors = [];

            foreach ($rows as $i => $row) {
                try {
                    $result = $this->createEntity($job->entity_type, $row);
                    if (($result['errors'] ?? 0) === 0) {
                        $created++;
                    } else {
                        $failed++;
                        $errors[] = ['row' => $i + 2, 'error' => 'Tally returned errors', 'detail' => $result];
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = ['row' => $i + 2, 'error' => $e->getMessage()];
                }

                $job->update(['processed_rows' => $i + 1, 'failed_rows' => $failed]);
            }

            $job->update([
                'status' => TallyImportJob::STATUS_COMPLETED,
                'result_summary' => ['created' => $created, 'failed' => $failed, 'errors' => array_slice($errors, 0, 100)],
            ]);
        } catch (\Throwable $e) {
            $job->update([
                'status' => TallyImportJob::STATUS_FAILED,
                'result_summary' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseCsv(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        if (count($lines) < 2) {
            return [];
        }

        $headers = array_map('trim', str_getcsv(array_shift($lines)));
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            $rows[] = array_combine($headers, array_pad($cols, count($headers), null)) ?: [];
        }

        return $rows;
    }

    private function createEntity(string $entity, array $row): array
    {
        return match ($entity) {
            'ledger' => $this->ledgers->create($row),
            'group' => $this->groups->create($row),
            'stock_item', 'stock-item' => $this->stockItems->create($row),
            default => throw new \InvalidArgumentException("Unsupported import entity: {$entity}"),
        };
    }
}
