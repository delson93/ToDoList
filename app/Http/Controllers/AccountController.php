<?php

namespace App\Http\Controllers;

use App\ListAccess;
use App\Models\Participant;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Socialite\Facades\Socialite;

class AccountController
{
    public function authenticate(Request $request)
    {
        $data = $request->validate([
            'mode' => 'required|in:login,register',
            'email' => 'required|email:rfc|max:255',
            'password' => ['required', 'string', 'max:72'],
        ]);
        $email = Str::lower($data['email']);
        if ($data['mode'] === 'register') {
            $request->validate(['name' => 'required|string|max:60', 'password' => ['required', Password::min(12), 'max:72'], 'email' => 'unique:users,email']);
            $user = User::create(['name' => $request->string('name'), 'email' => $email, 'password' => Hash::make($data['password'])]);
            Auth::login($user);
        } elseif (! Auth::attempt(['email' => $email, 'password' => $data['password']])) {
            return back()->withErrors(['email' => 'The email or password is incorrect.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect('/account');
    }

    public function claim(Request $request, ListAccess $access)
    {
        $guest = $access->guest($request);
        if ($guest) {
            $ids = Participant::active()->where('guest_identity_id', $guest)->pluck('list_id')->sort();
            foreach ($ids as $id) {
                DB::transaction(function () use ($request, $id, $guest) {
                    $list = ShoppingList::whereKey($id)->lockForUpdate()->first();
                    if (! $list) {
                        return;
                    }
                    $source = $list->participants()->active()->where('guest_identity_id', $guest)->first();
                    if (! $source) {
                        return;
                    }
                    $target = $list->participants()->where('user_id', $request->user()->id)->first();
                    if ($target) {
                        $sourceWins = $target->removed_at || ListAccess::LEVELS[$source->role] > ListAccess::LEVELS[$target->role];
                        $keep = $sourceWins ? $source : $target;
                        $remove = $sourceWins ? $target : $source;
                        DB::table('assignments')->where('participant_id', $remove->id)->update(['participant_id' => $keep->id]);
                        DB::table('sync_operations')->where('participant_id', $remove->id)->update(['participant_id' => $keep->id]);
                        $remove->delete();
                        $source = $keep;
                    }
                    $source->update(['user_id' => $request->user()->id, 'guest_identity_id' => null]);
                    SyncController::changed($list, $source->name, 'participant.claimed');
                });
            }
        }

        return redirect('/lists');
    }

    public function preferences(Request $request)
    {
        $data = $request->validate(['theme' => 'required|in:system,light,dark', 'locale' => 'required|in:en']);
        DB::table('user_preferences')->updateOrInsert(['user_id' => $request->user()->id], $data);

        return back()->with('status', 'Preferences saved.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/account')->withHeaders(['Clear-Site-Data' => '"storage"']);
    }

    public function google()
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'), 404);

        return Socialite::driver('google')->redirect();
    }

    public function googleCallback(Request $request)
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'), 404);
        try {
            $profile = Socialite::driver('google')->user();
        } catch (\Exception) {
            return redirect('/account')->withErrors(['google' => 'Google sign-in could not be completed.']);
        }
        abort_unless($profile->getId() && $profile->getEmail() && ($profile->user['email_verified'] ?? false) === true, 403);
        $user = User::where('google_id', $profile->getId())->first();
        if (! $user) {
            $email = Str::lower($profile->getEmail());
            if (User::where('email', $email)->exists()) {
                return redirect('/account')->withErrors(['google' => 'This address already has an account. Sign in with your password.']);
            }
            $user = new User(['name' => mb_substr($profile->getName() ?: 'Google user', 0, 60), 'email' => $email, 'password' => Hash::make(Str::random(48))]);
            $user->google_id = $profile->getId();
            $user->email_verified_at = now();
            $user->save();
        }
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/account');
    }
}
