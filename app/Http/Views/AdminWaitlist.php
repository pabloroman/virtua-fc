<?php

namespace App\Http\Views;

use App\Models\WaitlistEntry;
use Illuminate\Http\Request;

class AdminWaitlist
{
    public function __invoke(Request $request)
    {
        $search = $request->query('search');

        $query = WaitlistEntry::with('inviteCode');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $searchLower = mb_strtolower($search);
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchLower}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$searchLower}%"]);
            });
        }

        $entries = $query->orderBy('created_at', 'desc')
            ->paginate(50)
            ->appends($request->query());

        $total = WaitlistEntry::count();
        $pending = WaitlistEntry::whereDoesntHave('inviteCode')->count();
        $invited = WaitlistEntry::whereHas('inviteCode', function ($q) {
            $q->where('times_used', 0);
        })->count();
        $registered = WaitlistEntry::whereHas('inviteCode', function ($q) {
            $q->where('times_used', '>', 0);
        })->count();

        return view('admin.waitlist', [
            'entries' => $entries,
            'search' => $search,
            'total' => $total,
            'pending' => $pending,
            'invited' => $invited,
            'registered' => $registered,
        ]);
    }
}
