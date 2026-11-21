<?php

namespace App\Http\Controllers;

use App\Events\ListChanged;
use App\ListAccess;
use App\Models\ShoppingList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SyncController
{
    public function __construct(private ListAccess $access) {}

    public function __invoke(Request $request, ShoppingList $list)
    {
        $operation = $request->validate([
            'id' => 'required|uuid',
            'item_id' => 'required|uuid',
            'type' => 'required|in:add,status,edit,delete,restore,comment',
            'participant' => 'sometimes|required|integer|min:1',
            'base' => 'nullable|integer|min:0',
            'data' => 'present|array',
        ]);
        $digest = hash('sha256', json_encode($operation, JSON_THROW_ON_ERROR));
        $result = DB::transaction(function () use ($request, $list, $operation, $digest) {
            $list = ShoppingList::whereKey($list->id)->lockForUpdate()->firstOrFail();
            $required = match ($operation['type']) {
                'status' => 'complete',
                'comment' => 'collaborate',
                default => 'edit',
            };
            $actor = $this->access->participant($request, $list, $required);
            abort_if(isset($operation['participant']) && (int) $operation['participant'] !== $actor->id, 403, 'Return to the identity that saved this change to sync it.');
            $receipt = DB::table('sync_operations')->where(['list_id' => $list->id, 'operation_id' => $operation['id']])->first();
            if ($receipt) {
                abort_unless($receipt->participant_id == $actor->id && hash_equals($receipt->digest, $digest), 409, 'Operation identifier has already been used.');

                return ['duplicate' => true, 'version' => $list->version];
            }

            $this->apply($list, $actor->name, $operation);
            DB::table('sync_operations')->insert([
                'list_id' => $list->id, 'operation_id' => $operation['id'], 'participant_id' => $actor->id,
                'digest' => $digest, 'created_at' => now(),
            ]);
            self::changed($list, $actor->name, 'item.'.$operation['type']);

            return ['duplicate' => false, 'version' => $list->version];
        });

        return response()->json($result);
    }

    private function apply(ShoppingList $list, string $author, array $operation): void
    {
        $type = $operation['type'];
        $item = $list->items()->withTrashed()->find($operation['item_id']);
        if ($type === 'add') {
            abort_if($item || DB::table('list_items')->where('id', $operation['item_id'])->exists(), 409, 'Item identifier has already been used.');
            abort_if($list->items()->withTrashed()->count() >= 2000, 422, 'This list has reached its item limit.');
            $item = $list->items()->make();
            $item->id = $operation['item_id'];
            $item->status = 'pending';
            $item->version = 0;
        } else {
            abort_if(! $item, 409, 'This item is no longer on the list.');
            abort_if($type !== 'restore' && $item->trashed(), 409, 'This item was deleted.');
            abort_if($type !== 'comment' && (int) ($operation['base'] ?? 0) !== (int) $item->version, 409, 'This item changed on another device.');
        }

        if ($type === 'comment') {
            $data = Validator::make($operation['data'], ['body' => 'required|string|max:1000'])->validate();
            abort_if(DB::table('comments')->where('item_id', $item->id)->count() >= 100, 422, 'This item has reached its comment limit.');
            DB::table('comments')->insert($data + ['item_id' => $item->id, 'author' => $author, 'created_at' => now()]);

            return;
        }

        if (in_array($type, ['add', 'edit', 'status'], true)) {
            $data = $this->validateItem($list, $operation);
            $assignee = $data['assigned_to'] ?? null;
            $hasAssignment = array_key_exists('assigned_to', $data);
            unset($data['assigned_to']);
            $item->fill($data);
            if ($item->status === 'partial') {
                abort_unless($item->quantity > 0 && $item->purchased > 0 && $item->purchased < $item->quantity, 422, 'Enter a purchased amount smaller than the requested quantity.');
            }
            $item->version++;
            $item->save();
            if ($hasAssignment) {
                DB::table('assignments')->where('item_id', $item->id)->delete();
                if ($assignee) {
                    DB::table('assignments')->insert(['item_id' => $item->id, 'participant_id' => $assignee]);
                }
            }

            return;
        }

        $item->version++;
        $item->deleted_at = $type === 'delete' ? now() : null;
        $item->save();
    }

    private function validateItem(ShoppingList $list, array $operation): array
    {
        $statusRules = [
            'status' => 'required|in:pending,completed,unavailable,partial',
            'purchased' => 'nullable|numeric|min:0|max:9999999|decimal:0,3',
        ];
        if ($operation['type'] === 'status') {
            return Validator::make($operation['data'], $statusRules)->validate();
        }

        return Validator::make($operation['data'], [
            'name' => ($operation['type'] === 'add' ? 'required' : 'sometimes|required').'|string|max:150',
            'description' => 'nullable|string|max:1500',
            'quantity' => 'nullable|numeric|gt:0|max:9999999|decimal:0,3',
            'unit' => 'nullable|string|max:32',
            'estimated_price' => 'nullable|numeric|min:0|max:99999999|decimal:0,2',
            'actual_price' => 'nullable|numeric|min:0|max:99999999|decimal:0,2',
            'priority' => 'nullable|in:low,medium,high',
            'notes' => 'nullable|string|max:1500',
            'alternative' => 'nullable|string|max:150',
            'due_date' => 'nullable|date_format:Y-m-d',
            'assigned_to' => ['nullable', 'integer', Rule::exists('participants', 'id')->where('list_id', $list->id)->whereNull('removed_at')],
            'status' => 'sometimes|'.$statusRules['status'],
            'purchased' => $statusRules['purchased'],
        ])->validate();
    }

    public static function changed(ShoppingList $list, string $actor, string $action): void
    {
        $list->increment('version');
        DB::table('activity_history')->insert(['list_id' => $list->id, 'actor' => $actor, 'action' => $action, 'created_at' => now()]);
        DB::afterCommit(function () use ($list) {
            if (config('broadcasting.default') === 'reverb') {
                try {
                    event(new ListChanged($list->id));
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        });
    }
}
