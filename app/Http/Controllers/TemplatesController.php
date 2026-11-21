<?php

namespace App\Http\Controllers;

use App\ListAccess;
use App\Models\ShoppingList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TemplatesController
{
    public function store(Request $request, ShoppingList $list, ListAccess $access)
    {
        DB::transaction(function () use ($request, $list, $access) {
            $list = ShoppingList::whereKey($list->id)->sharedLock()->firstOrFail();
            $access->participant($request, $list, 'owner');
            $id = $request->user()->id;
            abort_if(DB::table('templates')->where('user_id', $id)->count() >= 30, 422, 'Your account has reached its template limit.');
            $items = $list->items()->get()->map(fn ($item) => $item->only([
                'name', 'description', 'quantity', 'unit', 'estimated_price', 'priority', 'notes', 'alternative',
            ]));
            DB::table('templates')->insert([
                'user_id' => $id, 'name' => $list->name, 'category' => $list->category,
                'items' => json_encode($items, JSON_THROW_ON_ERROR),
            ]);
        });

        return response()->noContent();
    }
}
