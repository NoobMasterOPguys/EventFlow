<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = CarbonImmutable::parse(($validated['month'] ?? now()->format('Y-m')).'-01', config('app.timezone'));
        $monthStart = $month->startOfMonth();
        $monthEnd = $month->endOfMonth();
        $calendarStart = $monthStart->startOfWeek(CarbonInterface::SUNDAY);
        $calendarEnd = $monthEnd->endOfWeek(CarbonInterface::SATURDAY);

        $events = $request->user()->events()
            ->where('start_at', '<=', $calendarEnd->endOfDay())
            ->where('end_at', '>=', $calendarStart->startOfDay())
            ->orderBy('start_at')
            ->get();
        $eventsByDate = collect();

        foreach ($events as $event) {
            $eventStart = CarbonImmutable::parse($event->start_at->toDateString(), config('app.timezone'));
            $eventEnd = CarbonImmutable::parse($event->end_at->toDateString(), config('app.timezone'));
            $firstVisibleDate = $eventStart < $calendarStart ? $calendarStart : $eventStart;
            $lastVisibleDate = $eventEnd > $calendarEnd ? $calendarEnd : $eventEnd;

            for ($eventDate = $firstVisibleDate; $eventDate <= $lastVisibleDate; $eventDate = $eventDate->addDay()) {
                $dateKey = $eventDate->toDateString();

                if (! $eventsByDate->has($dateKey)) {
                    $eventsByDate->put($dateKey, collect());
                }

                $eventsByDate->get($dateKey)->push($event);
            }
        }

        $tasksByDate = $request->user()->assignedTasks()
            ->whereBetween('due_at', [$calendarStart->startOfDay(), $calendarEnd->endOfDay()])
            ->with('event')
            ->orderBy('due_at')
            ->get()
            ->groupBy(fn ($task): string => $task->due_at->toDateString());

        $days = [];

        for ($date = $calendarStart; $date <= $calendarEnd; $date = $date->addDay()) {
            $dateKey = $date->toDateString();
            $days[] = [
                'date' => $date,
                'inMonth' => $date->month === $month->month,
                'events' => $eventsByDate->get($dateKey, collect()),
                'tasks' => $tasksByDate->get($dateKey, collect()),
            ];
        }

        return view('calendar.index', [
            'month' => $month,
            'days' => $days,
            'hasCalendarItems' => $eventsByDate->isNotEmpty() || $tasksByDate->isNotEmpty(),
            'previousMonth' => $month->subMonth()->format('Y-m'),
            'nextMonth' => $month->addMonth()->format('Y-m'),
        ]);
    }
}
