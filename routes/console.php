<?php

use App\Models\ShoppingList;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('lists:recur', function () {
    DB::table('recurring_schedules')->where('next_run', '<=', now())->orderBy('list_id')->chunk(100, function ($schedules) {
        foreach ($schedules as $schedule) {
            DB::transaction(function () use ($schedule) {
                $source = ShoppingList::whereKey($schedule->list_id)->lockForUpdate()->first();
                $current = DB::table('recurring_schedules')->where('list_id', $schedule->list_id)->first();
                if (! $source || ! $current || Carbon::parse($current->next_run)->isFuture()) {
                    return;
                }
                if ($source->expires_at?->isPast()) {
                    DB::table('recurring_schedules')->where('list_id', $source->id)->delete();

                    return;
                }
                $copy = ShoppingList::create(['name' => $source->name, 'category' => $source->category]);
                foreach ($source->participants()->active()->get() as $participant) {
                    $copy->participants()->create($participant->only(['name', 'role', 'guest_identity_id', 'user_id']));
                }
                foreach ($source->items()->get() as $item) {
                    $fields = $item->only(['name', 'description', 'quantity', 'unit', 'estimated_price', 'priority', 'notes', 'alternative']);
                    if ($item->status === 'partial') {
                        $fields['quantity'] = $item->quantity - $item->purchased;
                    }
                    $copy->items()->create($fields);
                }
                $next = $current->frequency === 'monthly' ? now()->addMonthNoOverflow() : now()->addDays($current->interval_days ?? 7);
                DB::table('recurring_schedules')->where('list_id', $source->id)->update(['next_run' => $next]);
            });
        }
    });
})->purpose('Create copies of recurring lists when they are due');

Schedule::command('lists:recur')->hourly()->withoutOverlapping();

Artisan::command('lists:deployment-check', function () {
    $key = (string) config('app.key');
    $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
    if (config('app.env') !== 'production' || config('app.debug') || config('app.url') !== 'https://list.utilynk.com'
        || ! is_string($decoded) || strlen($decoded) !== 32 || ! config('session.secure') || ! config('session.encrypt')
        || config('database.default') !== 'mysql') {
        $this->error('Check production APP_ENV, APP_DEBUG, APP_URL, APP_KEY, secure/encrypted sessions, and MySQL configuration.');

        return 1;
    }
    DB::select('select 1');
    $migrator = app('migrator');
    if (! $migrator->repositoryExists()) {
        $this->error('Initialize the production schema in a separately approved migration operation.');

        return 1;
    }
    $files = $migrator->getMigrationFiles([database_path('migrations')]);
    if (array_diff(array_keys($files), $migrator->getRepository()->getRan())) {
        $this->error('Migrations are pending. Review them and verify a restorable backup before applying them separately.');

        return 1;
    }

    return 0;
})->purpose('Verify production configuration and schema before release activation');
