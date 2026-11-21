<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as Broker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController
{
    public function email(Request $request)
    {
        $data = $request->validate(['email' => 'required|email:rfc|max:255']);
        abort_if(app()->isProduction() && in_array(config('mail.default'), ['log', 'array'], true), 503, 'Password recovery is temporarily unavailable.');
        Broker::sendResetLink(['email' => Str::lower($data['email'])]);

        return back()->with('status', 'If that address has an account, a reset link will be sent.');
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string|max:128',
            'email' => 'required|email:rfc|max:255',
            'password' => ['required', 'confirmed', Password::min(12), 'max:72'],
        ]);
        $data['email'] = Str::lower($data['email']);
        $status = Broker::reset($data, function (User $user, string $password): void {
            DB::transaction(function () use ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])->setRememberToken(Str::random(60));
                $user->save();
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table'))->where('user_id', $user->id)->delete();
                }
            });
            event(new PasswordReset($user));
        });

        return $status === Broker::PASSWORD_RESET
            ? redirect('/account')->with('status', 'Password updated. Sign in with your new password.')
            : back()->withErrors(['email' => 'This reset link is invalid or has expired.']);
    }
}
