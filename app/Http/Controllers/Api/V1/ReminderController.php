<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reminder\StoreReminderRequest;
use App\Http\Requests\Reminder\UpdateReminderRequest;
use App\Http\Resources\ReminderResource;
use App\Models\Reminder;
use App\Models\Task;
use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ReminderController extends Controller
{
    use ApiResponse, AuthorizesRequests;

    public function forTask(Task $task)
    {
        $this->authorize('view', $task);

        return $this->success(ReminderResource::collection($task->reminders()->latest('scheduled_for')->get()));
    }

    public function store(StoreReminderRequest $request)
    {
        $data = $request->validated();

        $task = Task::find($data['task_id']);

        if (! $task) {
            throw new ApiException('TASK_NOT_FOUND', 'The requested task was not found.', 404);
        }

        $reminder = Reminder::create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return $this->success(new ReminderResource($reminder), 201);
    }

    public function update(UpdateReminderRequest $request, Reminder $reminder)
    {
        $this->authorize('update', $reminder);

        $reminder->update($request->validated());

        return $this->success(new ReminderResource($reminder->fresh()));
    }

    public function destroy(Reminder $reminder)
    {
        $this->authorize('delete', $reminder);

        $reminder->delete();

        return response()->json(null, 204);
    }
}
