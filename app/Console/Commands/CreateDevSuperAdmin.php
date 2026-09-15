<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateDevSuperAdmin extends Command
{
    protected $signature = 'dev:create-super-admin
                            {--username=ibrahim : The local development username}
                            {--email=ibrahim@local.test : The local development email}';

    protected $description = 'Create or update a local development super-admin without storing a plaintext password';

    public function handle(): int
    {
        $username = (string) $this->option('username');
        $email = (string) $this->option('email');
        $password = $this->secret('Password (input is hidden)');

        if (! is_string($password) || mb_strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['username' => $username],
            [
                'name' => ucfirst($username),
                'email' => $email,
                'email_verified_at' => now(),
                'is_super_admin' => true,
            ],
        );

        $user->forceFill(['password' => Hash::make($password)])->save();

        $this->info("Local super-admin '{$username}' is ready.");
        $this->line('No plaintext password was written to the repository or displayed.');

        return self::SUCCESS;
    }
}
