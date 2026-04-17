<?php

namespace Modules\Tally\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tally\Enums\TallyPermission;
use Symfony\Component\HttpFoundation\Response;

class CheckTallyPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $tallyPermission = TallyPermission::tryFrom($permission);

        if (! $tallyPermission) {
            return $next($request);
        }

        $userPermissions = $user->tally_permissions ?? [];

        if (! in_array($tallyPermission->value, $userPermissions, true)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => "You do not have the '{$tallyPermission->value}' permission.",
            ], 403);
        }

        return $next($request);
    }
}
