<?php

namespace App\Http\Actions;

use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegisterDeviceToken
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:500'],
            'platform' => ['required', 'string', 'in:ios,android'],
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $request->input('token')],
            [
                'user_id' => Auth::id(),
                'platform' => $request->input('platform'),
            ]
        );

        return response()->json(['status' => 'ok']);
    }
}
