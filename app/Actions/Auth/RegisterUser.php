<?php

namespace App\Actions\Auth;

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterUser
{
    public function execute(
        string $name,
        string $email,
        string $password,
        string $organizationName,
    ): User {
        return DB::transaction(function () use (
            $name,
            $email,
            $password,
            $organizationName
        ) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $organization = Organization::create([
                'name' => $organizationName,
                'slug' => $this->uniqueSlug($organizationName),
                'timezone' => 'UTC',
                'currency' => 'GBP',
                'settings' => [],
            ]);

            OrganizationUser::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'role' => 'owner',
            ]);

            $user->update([
                'current_organization_id' => $organization->id,
            ]);

            return $user;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;

        while (Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
