<?php

namespace App\Actions\Fortify;

use App\Actions\Auth\RegisterUser;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private RegisterUser $registerUser) {}

    /**
     * Validate and create a newly registered user with their organization.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'organization_name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ])->validate();

        return $this->registerUser->execute(
            $input['name'],
            $input['email'],
            $input['password'],
            $input['organization_name'],
        );
    }
}
