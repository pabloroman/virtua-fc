<?php

namespace App\Http\Views;

use Illuminate\Http\Request;

class Dashboard
{
    public function __invoke(Request $request)
    {
        return redirect()->route('select-team');
    }
}
