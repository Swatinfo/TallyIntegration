<?php

namespace Modules\Tally\Services;

use Modules\Tally\Models\TallyAuditLog;
use Modules\Tally\Models\TallyConnection;

class AuditLogger
{
    public function log(
        string $action,
        string $objectType,
        ?string $objectName,
        array $requestData = [],
        array $responseData = [],
    ): void {
        try {
            TallyAuditLog::create([
                'user_id' => auth()->id(),
                'tally_connection_id' => $this->resolveConnectionId(),
                'action' => $action,
                'object_type' => $objectType,
                'object_name' => $objectName,
                'request_data' => $requestData,
                'response_data' => $responseData,
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr(request()->userAgent() ?? '', 0, 255),
            ]);
        } catch (\Throwable) {
            // Don't let audit logging failures break the main flow
        }
    }

    private function resolveConnectionId(): ?int
    {
        $connection = request()->route('connection');

        if ($connection instanceof TallyConnection) {
            return $connection->id;
        }

        if (is_string($connection)) {
            return TallyConnection::where('code', $connection)->value('id');
        }

        return null;
    }
}
