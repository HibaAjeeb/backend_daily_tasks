<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sync\PushSyncRequest;
use App\Http\Requests\Sync\ResolveConflictRequest;
use App\Http\Resources\SyncConflictResource;
use App\Models\SyncConflict;
use App\Services\SyncService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SyncService $sync) {}

    public function push(PushSyncRequest $request)
    {
        $data = $request->validated();

        return $this->success($this->sync->push(
            $request->user()->id,
            $data['deviceId'],
            $data['changes'],
        ));
    }

    public function pull(Request $request)
    {
        $data = $request->validate([
            'since' => ['nullable', 'date'],
            'sinceId' => ['nullable', 'string', 'max:100'],
            'deviceId' => ['nullable', 'string', 'max:100'],
            'pageSize' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return $this->success($this->sync->pull(
            $request->user()->id,
            $data['since'] ?? null,
            (int) ($data['pageSize'] ?? 200),
            $data['deviceId'] ?? null,
            $data['sinceId'] ?? null,
        ));
    }

    public function status(Request $request)
    {
        $data = $request->validate(['deviceId' => ['required', 'string', 'max:100']]);

        return $this->success($this->sync->status($request->user()->id, $data['deviceId']));
    }

    public function conflicts(Request $request)
    {
        $data = $request->validate([
            'deviceId' => ['required', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,resolved'],
            'entity' => ['nullable', 'string', 'max:30'],
            'pageSize' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $conflicts = SyncConflict::where('user_id', $request->user()->id)
            ->where('device_id', $data['deviceId'])
            ->where('status', $data['status'] ?? 'pending')
            ->when($data['entity'] ?? null, fn ($query, $entity) => $query->where('entity', $entity))
            ->latest('detected_at')
            ->paginate((int) ($data['pageSize'] ?? 20));

        return $this->paginated(SyncConflictResource::collection($conflicts));
    }

    public function resolve(ResolveConflictRequest $request, string $conflict)
    {
        $model = SyncConflict::where('user_id', $request->user()->id)->find($conflict);

        if (! $model) {
            throw new ApiException('CONFLICT_NOT_FOUND', 'The requested conflict was not found.', 404);
        }

        $data = $request->validated();

        return $this->success($this->sync->resolveConflict(
            $request->user()->id,
            $model,
            $data['resolution'],
            $data['mergedData'] ?? [],
        ));
    }

    public function resolveAll(Request $request)
    {
        $data = $request->validate([
            'resolution' => ['required', 'in:keep_client,keep_server'],
            'deviceId' => ['required', 'string', 'max:100'],
        ]);

        $count = $this->sync->resolveAll($request->user()->id, $data['deviceId'], $data['resolution']);

        return $this->success(['resolvedCount' => $count]);
    }
}
