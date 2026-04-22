<?php

namespace Modules\Tally\Logging;

use Illuminate\Support\Facades\File;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

class TallyLogChannel
{
    public function __invoke(array $config): Logger
    {
        $path = self::ensureTodayLogFile();

        $handler = new StreamHandler(
            $path,
            $this->resolveLevel($config['level'] ?? 'debug'),
        );

        $handler->setFormatter(new LineFormatter(
            format: "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            dateFormat: 'Y-m-d H:i:s',
            allowInlineLineBreaks: true,
            ignoreEmptyContextAndExtra: true,
        ));

        return new Logger('tally', [$handler]);
    }

    /**
     * Guarantee that storage/logs/tally/tally-DD-MM-YYYY.log
     * (and its parent directory) exist. Returns the absolute path.
     */
    public static function ensureTodayLogFile(): string
    {
        $directory = storage_path('logs'.DIRECTORY_SEPARATOR.'tally');

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0o755, true, true);
        }

        $path = $directory.DIRECTORY_SEPARATOR.'tally-'.date('d-m-Y').'.log';

        if (! File::exists($path)) {
            File::put($path, '');
        }

        return $path;
    }

    private function resolveLevel(string $level): Level
    {
        return match (strtolower($level)) {
            'emergency' => Level::Emergency,
            'alert' => Level::Alert,
            'critical' => Level::Critical,
            'error' => Level::Error,
            'warning' => Level::Warning,
            'notice' => Level::Notice,
            'info' => Level::Info,
            default => Level::Debug,
        };
    }
}
