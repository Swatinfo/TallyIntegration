<?php

namespace Modules\Tally\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject per-connection path params that could inject into the Tally XML envelope.
 *
 * Form Requests (via the SafeXmlString rule) only run on POST/PUT bodies. Path
 * params like {name} and {masterID} reach the controller URL-decoded but
 * otherwise unchecked, then get embedded into generated XML. The XML builder's
 * escapeXml() prevents actual injection, but malformed input can still trigger
 * upstream errors; rejecting early surfaces a clean 422 and keeps the XML surface
 * consistent across boundaries.
 */
class GuardTallyPathParams
{
    /**
     * Route-param names that carry user-controlled text bound for Tally XML.
     *
     * @var list<string>
     */
    private const GUARDED_PARAMS = ['name', 'masterID', 'type'];

    /**
     * @var list<string>
     */
    private const DANGEROUS_TOKENS = [
        '<!DOCTYPE',
        '<!ENTITY',
        '<![CDATA[',
        '<?xml',
        '<ENVELOPE',
        '<HEADER',
        '<TALLYMESSAGE',
        '<TALLYREQUEST',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::GUARDED_PARAMS as $param) {
            $value = $request->route($param);

            if (! is_string($value) || $value === '') {
                continue;
            }

            $decoded = rawurldecode($value);
            $upper = strtoupper($decoded);

            foreach (self::DANGEROUS_TOKENS as $token) {
                if (str_contains($upper, strtoupper($token))) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => "The {$param} path parameter contains potentially dangerous XML content.",
                    ], 422);
                }
            }
        }

        return $next($request);
    }
}
