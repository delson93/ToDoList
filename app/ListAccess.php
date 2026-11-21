<?php

namespace App;

use App\Models\Participant;
use App\Models\ShoppingList;
use App\Policies\ListPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

class ListAccess
{
    public const LEVELS = ['view' => 0, 'complete' => 1, 'edit' => 2, 'collaborate' => 3, 'owner' => 4];

    public function __construct(private ListPolicy $policy) {}

    public function identity(Request $request, bool $create = false): ?array
    {
        if ($request->user()) {
            return ['user_id' => $request->user()->id];
        }
        $guest = $this->guest($request, $create);

        return $guest ? ['guest_identity_id' => $guest] : null;
    }

    public function guest(Request $request, bool $create = false): ?int
    {
        $token = $request->cookie('utilynk_guest');
        $id = $token ? DB::table('guest_identities')->where('credential', hash('sha256', $token))->value('id') : null;
        if (! $id && $create) {
            $token = bin2hex(random_bytes(32));
            $id = DB::table('guest_identities')->insertGetId(['credential' => hash('sha256', $token), 'created_at' => now(), 'updated_at' => now()]);
            Cookie::queue('utilynk_guest', $token, 525600, '/', null, null, true, false, 'lax');
        }

        return $id;
    }

    public function participant(Request $request, ShoppingList $list, string $required = 'view'): Participant
    {
        $identity = $this->identity($request);
        abort_if(! $identity || $list->expires_at?->isPast(), 404);
        $participant = $list->participants()->active()->where($identity)->first();
        abort_if(! $participant, 404);
        abort_if(! $this->policy->allows($participant, $required), 403);

        return $participant;
    }
}
