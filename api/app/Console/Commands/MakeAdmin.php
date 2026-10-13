<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:make-admin {email}')]
#[Description('Give an existing user access to the admin panel')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $user = User::where('email', strtolower((string) $this->argument('email')))->first();

        if (! $user) {
            $this->error('No user with that email. Sign up in the app first.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true])->save();
        $this->info("{$user->email} is now an admin.");

        return self::SUCCESS;
    }
}
