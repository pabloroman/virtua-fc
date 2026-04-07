<?php

namespace App\Http\Views;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminTechTools
{
    public function __invoke(Request $request)
    {
        return view('admin.tech-tools', [
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => \Illuminate\Foundation\Application::VERSION,
            'appEnv' => config('app.env'),
            'queueConnection' => config('queue.default'),
            'failedJobsCount' => DB::table('failed_jobs')->count(),
        ]);
    }
}
