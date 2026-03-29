<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteUserToAdmin extends Command
{
    protected $signature   = 'pastoreyes:promote-admin {email}';
    protected $description = 'Promote a user to administrator by email address';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("No user found with email: {$email}");
            return self::FAILURE;
        }

        if ($user->is_admin) {
            $this->info("User {$email} is already an administrator.");
            return self::SUCCESS;
        }

        $user->is_admin = true;
        $user->save();

        $this->info("User {$email} has been promoted to administrator.");
        return self::SUCCESS;
    }
}
