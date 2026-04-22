<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Tally\Http\Requests\StoreWebhookEndpointRequest;
use Modules\Tally\Jobs\DeliverWebhookJob;
use Modules\Tally\Models\TallyWebhookEndpoint;
use Modules\Tally\Services\Integration\WebhookDispatcher;

class WebhookController extends Controller
{
    public function __construct(
        private WebhookDispatcher $dispatcher,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => TallyWebhookEndpoint::latest('id')->get(),
            'message' => 'Webhook endpoints retrieved successfully',
        ]);
    }

    public function show(TallyWebhookEndpoint $webhook): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $webhook->load('deliveries:id,tally_webhook_endpoint_id,event,status,created_at'),
            'message' => 'Webhook endpoint retrieved successfully',
        ]);
    }

    public function store(StoreWebhookEndpointRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['secret'] = Str::random(40);
        $endpoint = TallyWebhookEndpoint::create($validated);

        // Return the secret once on creation — not on subsequent reads ($hidden).
        $response = $endpoint->toArray();
        $response['secret'] = $validated['secret'];

        return response()->json([
            'success' => true,
            'data' => $response,
            'message' => 'Webhook endpoint created; store the secret — it will not be shown again',
        ], 201);
    }

    public function update(StoreWebhookEndpointRequest $request, TallyWebhookEndpoint $webhook): JsonResponse
    {
        $webhook->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $webhook->fresh(),
            'message' => 'Webhook endpoint updated successfully',
        ]);
    }

    public function destroy(TallyWebhookEndpoint $webhook): JsonResponse
    {
        $webhook->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Webhook endpoint deleted successfully',
        ]);
    }

    public function deliveries(Request $request, TallyWebhookEndpoint $webhook): JsonResponse
    {
        $perPage = min(200, max(1, (int) $request->query('per_page', 50)));
        $rows = $webhook->deliveries()->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
            'message' => 'Deliveries retrieved successfully',
        ]);
    }

    public function test(TallyWebhookEndpoint $webhook): JsonResponse
    {
        $delivery = $this->dispatcher->queue($webhook, 'webhook.test', [
            'test' => true,
            'endpoint_id' => $webhook->id,
            'endpoint_name' => $webhook->name,
        ]);

        DeliverWebhookJob::dispatchSync($delivery->id);

        return response()->json([
            'success' => true,
            'data' => $delivery->fresh(),
            'message' => 'Test payload dispatched',
        ]);
    }
}
