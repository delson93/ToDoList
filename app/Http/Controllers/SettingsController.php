<?php

namespace App\Http\Controllers;

use App\ListAccess;
use App\Models\ShoppingList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController
{
    public function __invoke(Request $request, ShoppingList $list, ListAccess $access)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'expires_at' => 'nullable|date|after:now',
            'frequency' => 'nullable|in:weekly,monthly,custom',
            'interval_days' => 'nullable|required_if:frequency,custom|integer|between:1,365',
            'shopper' => 'nullable|integer',
        ]);
        DB::transaction(function () use ($request, $list, $access, $data) {
            $list = ShoppingList::whereKey($list->id)->lockForUpdate()->firstOrFail();
            $actor = $access->participant($request, $list, 'owner');
            $list->update(['name' => $data['name'], 'expires_at' => $data['expires_at'] ?? null]);
            $frequency = $data['frequency'] ?? null;
            if ($frequency) {
                $days = (int) ($data['interval_days'] ?? 7);
                $existing = DB::table('recurring_schedules')->where('list_id', $list->id)->first();
                $interval = $frequency === 'custom' ? $days : null;
                if (! $existing || $existing->frequency !== $frequency || ($existing->interval_days === null ? null : (int) $existing->interval_days) !== $interval) {
                    DB::table('recurring_schedules')->updateOrInsert(['list_id' => $list->id], [
                        'frequency' => $frequency, 'interval_days' => $interval,
                        'next_run' => $frequency === 'monthly' ? now()->addMonthNoOverflow() : now()->addDays($frequency === 'weekly' ? 7 : $days),
                    ]);
                }
            } else {
                DB::table('recurring_schedules')->where('list_id', $list->id)->delete();
            }
            if (! empty($data['shopper'])) {
                $shopper = $list->participants()->active()->findOrFail($data['shopper']);
                abort_if(ListAccess::LEVELS[$shopper->role] < 1, 422, 'Choose someone who can complete items.');
                $ids = $list->items()->where('status', '!=', 'completed')->pluck('id');
                DB::table('assignments')->upsert($ids->map(fn ($id) => ['item_id' => $id, 'participant_id' => $shopper->id])->all(), ['item_id'], ['participant_id']);
                $list->items()->whereIn('id', $ids)->increment('version');
            }
            SyncController::changed($list, $actor->name, 'list.settings');
        });

        return response()->noContent();
    }
}
