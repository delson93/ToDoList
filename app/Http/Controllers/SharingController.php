<?php

namespace App\Http\Controllers;

use App\ListAccess;
use App\Models\ShoppingList;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SharingController
{
    private const ROLES = ['view', 'complete', 'edit', 'collaborate'];

    public function __construct(private ListAccess $access) {}

    public function invite(Request $request, ShoppingList $list)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(self::ROLES)],
            'name' => 'nullable|string|max:60',
            'days' => 'required|integer|between:1,90',
        ]);
        $token = bin2hex(random_bytes(32));
        DB::transaction(function () use ($request, $list, $data, $token) {
            $list = ShoppingList::whereKey($list->id)->lockForUpdate()->firstOrFail();
            $actor = $this->access->participant($request, $list, 'collaborate');
            DB::table('invitations')->insert([
                'id' => (string) Str::uuid(), 'list_id' => $list->id, 'token_digest' => hash('sha256', $token),
                'role' => $data['role'], 'name' => $data['name'] ?? null, 'expires_at' => now()->addDays($data['days']),
                'created_at' => self::accessTimestamp(), 'updated_at' => self::accessTimestamp(),
            ]);
            SyncController::changed($list, $actor->name, 'invitation.created');
        });

        return response()->json(['url' => route('join').'#'.$token], 201);
    }

    public function accept(Request $request)
    {
        $data = $request->validate(['token' => 'required|regex:/^[a-f0-9]{64}$/', 'name' => 'nullable|string|max:60']);
        $digest = hash('sha256', $data['token']);
        $listId = DB::table('invitations')->where('token_digest', $digest)->value('list_id');
        abort_unless($listId, 404, 'This invitation is unavailable.');
        $identity = $this->access->identity($request, true);
        DB::transaction(function () use ($digest, $listId, $identity, $data, $request) {
            $list = ShoppingList::whereKey($listId)->lockForUpdate()->firstOrFail();
            $invite = DB::table('invitations')->where('token_digest', $digest)->whereNull('revoked_at')->where('expires_at', '>', now())->first();
            abort_if(! $invite || $list->expires_at?->isPast(), 404, 'This invitation has expired or been revoked.');
            $participant = $list->participants()->where($identity)->first();
            $fresh = ! $participant?->access_changed_at || Carbon::parse($invite->created_at)->greaterThan($participant->access_changed_at);
            abort_if($participant?->removed_at && ! $fresh, 404, 'Ask the owner for a new invitation.');
            if (! $participant) {
                abort_if($list->participants()->active()->count() >= 100, 422, 'This list has reached its participant limit.');
                $participant = $list->participants()->create($identity + [
                    'name' => $data['name'] ?? $invite->name ?? mb_substr($request->user()?->name ?? 'Guest', 0, 60),
                    'role' => $invite->role,
                ]);
                SyncController::changed($list, $participant->name, 'participant.joined');
            } elseif ($fresh && ($participant->removed_at || ListAccess::LEVELS[$participant->role] < ListAccess::LEVELS[$invite->role])) {
                abort_if($participant->removed_at && $list->participants()->active()->count() >= 100, 422, 'This list has reached its participant limit.');
                $participant->update(['role' => $invite->role, 'removed_at' => null]);
                SyncController::changed($list, $participant->name, 'participant.permission');
            }
        });

        return response()->json(['url' => route('lists.show', $listId, false)]);
    }

    public function revoke(Request $request, ShoppingList $list, string $invitation)
    {
        DB::transaction(function () use ($request, $list, $invitation) {
            $list = ShoppingList::whereKey($list->id)->lockForUpdate()->firstOrFail();
            $actor = $this->access->participant($request, $list, 'collaborate');
            $changed = DB::table('invitations')->where(['id' => $invitation, 'list_id' => $list->id])
                ->whereNull('revoked_at')->update(['revoked_at' => now()]);
            abort_unless($changed, 404);
            SyncController::changed($list, $actor->name, 'invitation.revoked');
        });

        return response()->noContent();
    }

    public function participant(Request $request, ShoppingList $list, int $participant)
    {
        $data = $request->validate(['role' => ['required', Rule::in([...self::ROLES, 'remove', 'owner'])]]);
        DB::transaction(function () use ($request, $list, $participant, $data) {
            $list = ShoppingList::whereKey($list->id)->lockForUpdate()->firstOrFail();
            $owner = $this->access->participant($request, $list, 'owner');
            $target = $list->participants()->active()->whereKey($participant)->firstOrFail();
            abort_if($target->id === $owner->id, 422);
            switch ($data['role']) {
                case 'remove':
                    DB::table('assignments')->where('participant_id', $target->id)->delete();
                    $target->update(['removed_at' => self::accessTimestamp(), 'access_changed_at' => self::accessTimestamp()]);
                    break;
                case 'owner':
                    $owner->update(['role' => 'collaborate']);
                    $target->update(['role' => 'owner']);
                    break;
                default:
                    $target->update(['role' => $data['role'], 'access_changed_at' => self::accessTimestamp()]);
            }
            SyncController::changed($list, $owner->name, 'participant.'.$data['role']);
        });

        return response()->noContent();
    }

    private static function accessTimestamp(): string
    {
        return now()->format('Y-m-d H:i:s.u');
    }

    public function broadcast(Request $request)
    {
        $data = $request->validate(['channel_name' => 'required|regex:/^private-lists\.[a-f0-9-]{36}$/', 'socket_id' => 'required|regex:/^[0-9]+\.[0-9]+$/']);
        abort_unless(config('broadcasting.default') === 'reverb', 404);
        $list = ShoppingList::findOrFail(substr($data['channel_name'], strlen('private-lists.')));
        $this->access->participant($request, $list);

        return response()->json(json_decode(app(Factory::class)->connection('reverb')->getPusher()->authorizeChannel($data['channel_name'], $data['socket_id']), true, 512, JSON_THROW_ON_ERROR));
    }
}
