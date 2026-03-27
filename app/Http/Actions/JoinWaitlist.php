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
            'wants_career' => 'sometimes|boolean',
            'wants_tournament' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first(),
            ], 400);
        }

        $validated = $validator->validated();
        $validated['email'] = strtolower($validated['email']);
        $validated['wants_career'] = $validated['wants_career'] ?? true;
        $validated['wants_tournament'] = $validated['wants_tournament'] ?? true;

        $existing = WaitlistEntry::where('email', $validated['email'])->first();

        if ($existing) {
            $existing->update([
                'wants_career' => $validated['wants_career'],
                'wants_tournament' => $validated['wants_tournament'],
            ]);

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
