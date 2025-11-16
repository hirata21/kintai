<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Http\Requests\RegisterRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * Validate and create a newly registered user via Fortify.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        // FormRequest をインスタンス化して rules/messages/attributes を拝借
        $form = app(RegisterRequest::class);

        $rules      = method_exists($form, 'rules') ? $form->rules() : [];
        $messages   = method_exists($form, 'messages') ? $form->messages() : [];
        $attributes = method_exists($form, 'attributes') ? $form->attributes() : [];

        Validator::make($input, $rules, $messages, $attributes)->validate();

        // users.role が NOT NULL なら既定で 'user' を入れておくと安全
        $user = User::create([
            'name'     => $input['name'],
            'email'    => $input['email'],
            'password' => Hash::make($input['password']),
            'role'     => $input['role'] ?? 'user',
        ]);

        // Fortifyのメール認証などが動くようにイベントを発火
        event(new Registered($user));

        return $user;
    }
}