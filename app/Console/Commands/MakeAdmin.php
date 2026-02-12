<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'app:make-admin {email : The email of the user to make admin}';

    protected $description = 'Grant admin privileges to a user';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email '{$email}' not found.");

            return self::FAILURE;
        }

        $user->update(['is_admin' => true]);

        $this->info("User '{$user->name}' ({$user->email}) is now an admin.");

        return self::SUCCESS;
    }
}
