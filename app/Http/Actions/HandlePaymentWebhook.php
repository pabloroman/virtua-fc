<?php

namespace App\Http\Actions;

use App\Models\WaitlistEntry;
use App\Services\BetaInviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HandlePaymentWebhook
{
    public function __invoke(Request $request, BetaInviteService $inviteService): JsonResponse
    {
        $verificationToken = $request->input('verification_token');

        if ($verificationToken !== config('beta.webhook_secret')) {
            return response()->json(['error' => 'Invalid verification token'], 403);
        }

        $dataJson = $request->input('data');

        if (! $dataJson) {
            return response()->json(['error' => 'Missing data field'], 400);
        }

        $data = json_decode($dataJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'Invalid JSON in data field'], 400);
        }

        $email = strtolower($data['supporter_email'] ?? $data['email'] ?? '');

        if (! $email) {
            Log::info('Payment webhook received without email address.', [
                'message_id' => $data['message_id'] ?? null,
            ]);

            return response()->json(['status' => 'ok']);
        }

        if ($inviteService->hasAlreadyBeenInvited($email)) {
            Log::info('Payment webhook: email already invited.', ['email' => $email]);

            return response()->json(['status' => 'ok']);
        }

        $entry = WaitlistEntry::where('email', $email)->first();

        if (! $entry) {
            Log::info('Payment webhook: email not on waitlist.', ['email' => $email]);

            return response()->json(['status' => 'ok']);
        }

        $inviteService->invite($entry);

        Log::info('Payment webhook: beta invite sent.', ['email' => $email]);

        return response()->json(['status' => 'ok']);
    }
}
