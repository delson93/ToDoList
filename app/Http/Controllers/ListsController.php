<?php

namespace App\Http\Controllers;

use App\ListAccess;
use App\Models\Participant;
use App\Models\ShoppingList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ListsController
{
    public const CATEGORIES = ['shopping' => 'Shopping List', 'grocery' => 'Grocery List', 'todo' => 'To-Do List', 'packing' => 'Packing List', 'event' => 'Event Checklist', 'custom' => 'Custom List'];

    public const TEMPLATES = [
        'grocery' => ['Fresh vegetables', 'Fruit', 'Bread', 'Milk', 'Eggs'],
        'packing' => ['Travel documents', 'Phone charger', 'Toiletries', 'Change of clothes'],
        'event' => ['Confirm venue', 'Invite guests', 'Plan refreshments', 'Check equipment'],
        'todo' => ['Choose priorities', 'Gather supplies', 'Review progress'],
    ];

    public function __construct(private ListAccess $access) {}

    public function create(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:120',
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'saved_template' => 'nullable|integer|min:1',
            'template' => ['nullable', Rule::in(array_keys(self::TEMPLATES))],
        ]);
        $identity = $this->access->identity($request, true);
        $list = DB::transaction(function () use ($data, $identity, $request) {
            $saved = isset($data['saved_template']) && $request->user()
                ? DB::table('templates')->where('user_id', $request->user()->id)->find($data['saved_template']) : null;
            abort_if(isset($data['saved_template']) && ! $saved, 404);
            $list = ShoppingList::create(['name' => $data['name'] ?? $saved?->name ?? self::CATEGORIES[$data['category']], 'category' => $saved?->category ?? $data['category']]);
            $list->participants()->create($identity + ['name' => mb_substr($request->user()?->name ?? 'List creator', 0, 60), 'role' => 'owner']);
            $items = $saved ? json_decode($saved->items, true, flags: JSON_THROW_ON_ERROR)
                : array_map(fn ($name) => ['name' => $name], self::TEMPLATES[$data['template'] ?? ''] ?? []);
            $list->items()->createMany($items);

            return $list;
        });

        return redirect()->route('lists.show', $list, 303);
    }

    public function index(Request $request)
    {
        $identity = $this->access->identity($request);
        $lists = $identity ? ShoppingList::whereHas('participants', fn ($query) => $query->active()->where($identity))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('updated_at')->get(['id', 'name', 'category']) : collect();

        return view('app', ['screen' => 'library', 'lists' => $lists]);
    }

    public function show(Request $request, ShoppingList $list)
    {
        $this->access->participant($request, $list);

        return view('app', ['screen' => 'list', 'listId' => $list->id]);
    }

    public function state(Request $request, ShoppingList $list)
    {
        return DB::transaction(function () use ($request, $list) {
            $list = ShoppingList::whereKey($list->id)->sharedLock()->firstOrFail();
            $actor = $this->access->participant($request, $list);
            $etag = '"'.$list->id.'-'.$list->version.'-'.$actor->id.'-'.$actor->role.'"';
            if ($request->header('If-None-Match') === $etag) {
                return response('', 304)->header('ETag', $etag);
            }

            return response()->json($this->snapshot($list, $actor))->header('ETag', $etag);
        });
    }

    private function snapshot(ShoppingList $list, Participant $actor): array
    {
        $items = $list->items()->orderBy('created_at')->orderBy('id')->get();
        $ids = $items->modelKeys();
        $assignments = DB::table('assignments')->whereIn('item_id', $ids)->pluck('participant_id', 'item_id');
        $attachments = DB::table('attachments')->whereIn('item_id', $ids)->pluck('id', 'item_id');
        $comments = DB::table('comments')->whereIn('item_id', $ids)->orderBy('id')->get()->groupBy('item_id');
        $items->each(function ($item) use ($assignments, $attachments, $comments) {
            $item->assigned_to = $assignments[$item->id] ?? null;
            $item->photo = isset($attachments[$item->id]) ? route('photos.show', [$item->list_id, $attachments[$item->id]], false) : null;
            $item->comments = $comments[$item->id] ?? [];
        });
        $identity = $actor->user_id ? ['user_id' => $actor->user_id] : ['guest_identity_id' => $actor->guest_identity_id];
        $recentLists = DB::table('participants')->where($identity)->whereNull('removed_at')->latest('id')->limit(30)->pluck('list_id');
        $suggestions = DB::table('list_items')->join('lists', 'lists.id', '=', 'list_items.list_id')
            ->whereIn('list_items.list_id', $recentLists)->whereNull('list_items.deleted_at')
            ->where(fn ($query) => $query->whereNull('lists.expires_at')->orWhere('lists.expires_at', '>', now()))
            ->select('list_items.name')->groupBy('list_items.name')->orderByRaw('COUNT(*) DESC')->limit(30)->pluck('name');

        return [
            'list' => $list,
            'can_save_template' => $actor->user_id !== null && $actor->role === 'owner',
            'actor' => $actor,
            'items' => $items,
            'deleted' => ListAccess::LEVELS[$actor->role] >= 2
                ? $list->items()->onlyTrashed()->latest('deleted_at')->limit(30)->get(['id', 'name', 'version']) : [],
            'suggestions' => $suggestions,
            'participants' => $list->participants()->active()->get(),
            'activity' => DB::table('activity_history')->where('list_id', $list->id)->latest('id')->limit(30)->get(['actor', 'action', 'created_at']),
            'invitations' => ListAccess::LEVELS[$actor->role] >= 3
                ? DB::table('invitations')->where('list_id', $list->id)->latest()->limit(50)->get(['id', 'name', 'role', 'expires_at', 'revoked_at']) : [],
            'recurrence' => DB::table('recurring_schedules')->where('list_id', $list->id)->first(),
        ];
    }
}
