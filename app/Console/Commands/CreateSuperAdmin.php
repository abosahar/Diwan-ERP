<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates (or promotes) the platform administrator. The password is typed
 * interactively, never passed on the command line or shipped in a seeder.
 * Two-factor authentication is enforced on the first login.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'erp:create-super-admin {email} {--name=Platform Admin}';

    protected $description = 'Create the platform super administrator';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $password = $this->secret('Password (at least 10 characters)');
        $confirm = $this->secret('Type the password again');

        $validator = Validator::make(['email' => $email, 'password' => $password, 'password_confirmation' => $confirm], [
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        if ($user && $user->company_id) {
            $this->error('This email belongs to a company account. Use a separate email for the platform administrator.');

            return self::FAILURE;
        }

        $user ??= new User;
        $user->forceFill([
            'name' => $this->option('name'),
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'active',
            'is_super_admin' => true,
            'company_id' => null,
            'email_verified_at' => now(),
        ])->save();

        $this->info("Platform administrator {$email} created. Two-factor authentication is required at the first login.");

        return self::SUCCESS;
    }
}
