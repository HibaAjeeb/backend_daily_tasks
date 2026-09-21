<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SportSession\StoreSportSessionRequest;
use App\Http\Requests\SportSession\UpdateSportSessionRequest;
use App\Http\Resources\SportSessionResource;
use App\Models\SportSession;
use App\Services\ProgressService;
use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SportSessionController extends Controller
{
    use ApiResponse, AuthorizesRequests;

    public function __construct(private readonly ProgressService $progress) {}

    public function index(Request $request)
    {
        $sessions = SportSession::query()
            ->when($request->dateFrom, fn ($q) => $q->whereDate('date', '>=', $request->dateFrom))
            ->when($request->dateTo, fn ($q) => $q->whereDate('date', '<=', $request->dateTo))
            ->when($request->has('isCompleted'), fn ($q) => $q->where('is_completed', $request->boolean('isCompleted')))
            ->latest('date')
            ->get();

        return $this->success(SportSessionResource::collection($sessions));
    }

    public function store(StoreSportSessionRequest $request)
    {
        $session = SportSession::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->success(new SportSessionResource($session), 201);
    }

    public function show(SportSession $sportSession)
    {
        $this->authorize('view', $sportSession);

        return $this->success(new SportSessionResource($sportSession));
    }

    public function update(UpdateSportSessionRequest $request, SportSession $sportSession)
    {
        $this->authorize('update', $sportSession);

        $sportSession->update($request->validated());

        return $this->success(new SportSessionResource($sportSession->fresh()));
    }

    public function complete(Request $request, SportSession $sportSession)
    {
        $this->authorize('update', $sportSession);

        $data = $request->validate([
            'isCompleted' => ['required', 'boolean'],
            'actualDurationMinutes' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($sportSession, $data): void {
            $sportSession->update([
                'is_completed' => $data['isCompleted'],
                'actual_duration_minutes' => $data['actualDurationMinutes'] ?? $sportSession->actual_duration_minutes,
                'completed_at' => $data['isCompleted'] ? now() : null,
            ]);

            $this->progress->recordSportSessionCompletion($sportSession->fresh());
        });

        return $this->success(new SportSessionResource($sportSession->fresh()));
    }

    public function destroy(SportSession $sportSession)
    {
        $this->authorize('delete', $sportSession);

        $sportSession->delete();

        return response()->json(null, 204);
    }
}
