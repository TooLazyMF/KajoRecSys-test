<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AccountLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $month = $validated['month'] ?? now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $logs = $request->user()
            ->accountSessionLogs()
            ->whereBetween('logged_in_at', [$start, $end])
            ->latest('logged_in_at')
            ->paginate(50)
            ->withQueryString();

        return view('pages.settings.account-logs', [
            'month' => $month,
            'logs' => $logs,
        ]);
    }
}
