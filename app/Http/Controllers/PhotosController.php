<?php

namespace App\Http\Controllers;

use App\ListAccess;
use App\Models\ShoppingList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotosController
{
    public function store(Request $request, ShoppingList $list, string $item, ListAccess $access)
    {
        $access->participant($request, $list, 'edit');
        $request->validate(['photo' => 'required|file|mimetypes:image/jpeg,image/png,image/webp|max:5120|dimensions:max_width=5000,max_height=5000']);
        $image = @imagecreatefromstring(file_get_contents($request->file('photo')->getRealPath()));
        abort_unless($image, 422, 'This photo could not be read.');
        $width = min(640, imagesx($image));
        $thumbnail = imagescale($image, $width);
        abort_unless($thumbnail, 422, 'This photo could not be resized.');
        ob_start();
        $encoded = imagewebp($thumbnail, null, 82);
        $contents = ob_get_clean();
        abort_unless($encoded && $contents, 422, 'This photo could not be saved.');
        $id = (string) Str::uuid();
        $path = 'item-photos/'.$id.'.webp';
        $disk = Storage::disk('local');
        abort_unless($disk->put($path, $contents), 503);
        try {
            $previous = DB::transaction(function () use ($list, $item, $request, $access, $id, $path) {
                $list = ShoppingList::whereKey($list->id)->lockForUpdate()->firstOrFail();
                $actor = $access->participant($request, $list, 'edit');
                $list->items()->findOrFail($item);
                $existing = DB::table('attachments')->where('item_id', $item);
                $previous = $existing->value('path');
                $existing->delete();
                DB::table('attachments')->insert(['id' => $id, 'item_id' => $item, 'path' => $path]);
                SyncController::changed($list, $actor->name, 'item.photo');

                return $previous;
            });
        } catch (\Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }
        if ($previous) {
            $disk->delete($previous);
        }

        return response()->json(['url' => route('photos.show', [$list, $id], false)], 201);
    }

    public function show(Request $request, ShoppingList $list, string $attachment, ListAccess $access)
    {
        $access->participant($request, $list);
        $path = DB::table('attachments')->join('list_items', 'list_items.id', '=', 'attachments.item_id')
            ->where('list_items.list_id', $list->id)->whereNull('list_items.deleted_at')
            ->where('attachments.id', $attachment)->value('attachments.path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Content-Type' => 'image/webp']);
    }
}
