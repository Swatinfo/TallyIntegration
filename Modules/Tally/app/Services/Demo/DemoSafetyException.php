<?php

namespace Modules\Tally\Services\Demo;

use RuntimeException;

/**
 * Thrown by DemoGuard / DemoConstants when a demo-path operation would
 * touch something outside the demo sandbox. Aborts the demo command.
 */
class DemoSafetyException extends RuntimeException {}
