<?php

namespace App\Http\Actions;

use App\Models\WaitlistEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JoinWaitlist
{
    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email:rfc,dns|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first(),
            ], 400);
        }

        $validated = $validator->validated();
        $validated['email'] = strtolower($validated['email']);

        $existing = WaitlistEntry::where('email', $validated['email'])->first();

        if ($existing) {
            return response()->json([
                'message' => __('waitlist.already_registered'),
            ], 200);
        }

        WaitlistEntry::create($validated);

        return response()->json([
            'message' => __('waitlist.success'),
        ], 201);
    }
}
