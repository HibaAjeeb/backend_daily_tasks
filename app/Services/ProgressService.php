<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\SportProgressSnapshot;
use App\Models\SportSession;
use App\Models\StudyProgressSnapshot;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ProgressService
{
    public function recordStudyTaskCompletion(Task $task): void
    {
        $date = ($task->due_date ?: now())->toDateString();
        $query = Task::withoutGlobalScopes()
            ->where('user_id', $task->user_id)
            ->where('type', 'study')
            ->whereDate('due_date', $date);

        $values = [
            'tasks_planned' => (clone $query)->count(),
            'tasks_completed' => (clone $query)->where('is_completed', true)->count(),
            'study_minutes' => (clone $query)->where('is_completed', true)->sum('duration_minutes'),
        ];

        $snapshot = StudyProgressSnapshot::where('user_id', $task->user_id)
            ->whereDate('date', $date)
            ->first();

        if ($snapshot) {
            $snapshot->update($values);
        } else {
            StudyProgressSnapshot::create([
                'user_id' => $task->user_id,
                'date' => $date,
                ...$values,
            ]);
        }

        $this->invalidate('study', $task->user_id);
    }

    public function recordSportSessionCompletion(SportSession $session): void
    {
        $date = $session->getRawOriginal('date');
        $query = SportSession::withoutGlobalScopes()
            ->where('user_id', $session->user_id)
            ->where('date', $date);

        $values = [
            'sessions_planned' => (clone $query)->count(),
            'sessions_completed' => (clone $query)->where('is_completed', true)->count(),
            'sport_minutes' => (clone $query)->where('is_completed', true)->sum('actual_duration_minutes'),
        ];

        $snapshot = SportProgressSnapshot::where('user_id', $session->user_id)
            ->whereDate('date', $date)
            ->first();

        if ($snapshot) {
            $snapshot->update($values);
        } else {
            SportProgressSnapshot::create([
                'user_id' => $session->user_id,
                'date' => $date,
                ...$values,
            ]);
        }

        $this->invalidate('sport', $session->user_id);
    }

    public function study(int $userId, string $from, string $to): array
    {
        return Cache::remember(
            $this->cacheKey('study', $userId, $from, $to),
            now()->addSeconds(60),
            function () use ($userId, $from, $to) {
                $points = StudyProgressSnapshot::where('user_id', $userId)
                    ->whereDate('date', '>=', $from)
                    ->whereDate('date', '<=', $to)
                    ->orderBy('date')
                    ->get();

                $planned = $points->sum('tasks_planned');

                return [
                    'range' => ['from' => $from, 'to' => $to],
                    'points' => $points->map(fn ($point) => [
                        'date' => $point->date->toDateString(),
                        'tasksCompleted' => $point->tasks_completed,
                        'tasksPlanned' => $point->tasks_planned,
                        'studyMinutes' => $point->study_minutes,
                    ])->values()->all(),
                    'summary' => [
                        'completionRate' => $planned ? round($points->sum('tasks_completed') / $planned * 100, 1) : 0,
                        'totalStudyMinutes' => $points->sum('study_minutes'),
                    ],
                ];
            },
        );
    }

    public function sport(int $userId, string $from, string $to): array
    {
        return Cache::remember(
            $this->cacheKey('sport', $userId, $from, $to),
            now()->addSeconds(60),
            function () use ($userId, $from, $to) {
                $points = SportProgressSnapshot::where('user_id', $userId)
                    ->whereDate('date', '>=', $from)
                    ->whereDate('date', '<=', $to)
                    ->orderBy('date')
                    ->get();

                $planned = $points->sum('sessions_planned');

                return [
                    'range' => ['from' => $from, 'to' => $to],
                    'points' => $points->map(fn ($point) => [
                        'date' => $point->date->toDateString(),
                        'sessionsCompleted' => $point->sessions_completed,
                        'sessionsPlanned' => $point->sessions_planned,
                        'sportMinutes' => $point->sport_minutes,
                    ])->values()->all(),
                    'summary' => [
                        'completionRate' => $planned ? round($points->sum('sessions_completed') / $planned * 100, 1) : 0,
                        'totalSportMinutes' => $points->sum('sport_minutes'),
                    ],
                ];
            },
        );
    }

    public function weekRange(int $weekOffset = 0): array
    {
        $start = now()->startOfWeek(Carbon::MONDAY)->addWeeks($weekOffset);

        return [$start->toDateString(), $start->copy()->endOfWeek(Carbon::SUNDAY)->toDateString()];
    }

    public function monthRange(?string $month = null): array
    {
        $start = $month ? Carbon::parse($month.'-01')->startOfMonth() : now()->startOfMonth();

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }

    public function customRange(?string $from, ?string $to): array
    {
        if (! $from || ! $to) {
            throw new ApiException('INVALID_DATE_RANGE', 'The from and to dates are required.', 422);
        }

        if ($to < $from) {
            throw new ApiException('INVALID_DATE_RANGE', 'The end date must be after the start date.', 422);
        }

        return [$from, $to];
    }

    private function cacheKey(string $type, int $userId, string $from, string $to): string
    {
        return "progress:{$type}:{$userId}:v{$this->version($type, $userId)}:{$from}:{$to}";
    }

    private function version(string $type, int $userId): int
    {
        return (int) Cache::get($this->versionKey($type, $userId), 1);
    }

    private function invalidate(string $type, int $userId): void
    {
        Cache::forever($this->versionKey($type, $userId), $this->version($type, $userId) + 1);
    }

    private function versionKey(string $type, int $userId): string
    {
        return "progress:{$type}:version:{$userId}";
    }
}
