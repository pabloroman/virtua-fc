<?php

namespace App\Console\Commands;

use App\Models\InviteCode;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateBetaInvites extends Command
{
    protected $signature = 'beta:generate-codes
                            {--emails= : Comma-separated list of emails}
                            {--file= : Path to a file with one email per line}
                            {--count=0 : Generate this many open (no-email) codes}
                            {--expires= : Expiration date (Y-m-d)}';

    protected $description = 'Generate beta invite codes for waitlist emails or as open codes';

    public function handle(): int
    {
        $emails = $this->resolveEmails();
        $openCount = (int) $this->option('count');
        $expiresAt = $this->option('expires');

        if (empty($emails) && $openCount === 0) {
            $this->error('Provide --emails, --file, or --count to generate codes.');

            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;

        foreach ($emails as $email) {
            $email = strtolower(trim($email));

            if (! $email) {
                continue;
            }

            $existing = InviteCode::where('email', $email)->first();
            if ($existing) {
                $this->line("  Skipped (exists): {$email} → {$existing->code}");
                $skipped++;

                continue;
            }

            $invite = InviteCode::create([
                'code' => $this->generateCode(),
                'email' => $email,
                'max_uses' => 1,
                'expires_at' => $expiresAt,
            ]);

            $this->info("  Created: {$email} → {$invite->code}");
            $created++;
        }

        for ($i = 0; $i < $openCount; $i++) {
            $invite = InviteCode::create([
                'code' => $this->generateCode(),
                'email' => null,
                'max_uses' => 1,
                'expires_at' => $expiresAt,
            ]);

            $this->info("  Created open code: {$invite->code}");
            $created++;
        }

        $this->newLine();
        $this->info("Done. Created: {$created}, Skipped: {$skipped}");

        $this->showSummary();

        return self::SUCCESS;
    }

    private function resolveEmails(): array
    {
        $emails = [];

        if ($this->option('emails')) {
            $emails = array_merge($emails, explode(',', $this->option('emails')));
        }

        if ($this->option('file')) {
            $path = $this->option('file');
            if (! file_exists($path)) {
                $this->error("File not found: {$path}");

                return $emails;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $emails = array_merge($emails, $lines);
        }

        return $emails;
    }

    private function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (InviteCode::where('code', $code)->exists());

        return $code;
    }

    private function showSummary(): void
    {
        $total = InviteCode::count();
        $used = InviteCode::where('times_used', '>', 0)->count();
        $pending = InviteCode::where('times_used', 0)->where('invite_sent', false)->count();
        $sent = InviteCode::where('invite_sent', true)->where('times_used', 0)->count();

        $this->newLine();
        $this->table(
            ['Status', 'Count'],
            [
                ['Total codes', $total],
                ['Used', $used],
                ['Sent (unused)', $sent],
                ['Pending (not sent)', $pending],
            ]
        );
    }
}
