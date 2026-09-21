<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ProgressService $progress) {}

    public function study(Request $request, string $period)
    {
        [$from, $to] = $this->resolveRange($request, $period);

        return $this->success($this->progress->study($request->user()->id, $from, $to));
    }

    public function sport(Request $request, string $period)
    {
        [$from, $to] = $this->resolveRange($request, $period);

        return $this->success($this->progress->sport($request->user()->id, $from, $to));
    }

    private function resolveRange(Request $request, string $period): array
    {
        if ($period === 'monthly' && $request->filled('month')) {
            $request->validate(['month' => ['date_format:Y-m']]);
        }

        return match ($period) {
            'weekly' => $this->progress->weekRange((int) $request->input('weekOffset', 0)),
            'monthly' => $this->progress->monthRange($request->input('month')),
            'range' => $this->progress->customRange($request->input('from'), $request->input('to')),
        };
    }
}
