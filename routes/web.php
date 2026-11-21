<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ListsController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PhotosController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SharingController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TemplatesController;
use App\Http\Middleware\PrivateResponse;
use Illuminate\Support\Facades\Route;

foreach (array_keys(config('marketing.pages')) as $path) {
    Route::view($path, 'marketing', ['path' => $path]);
}
Route::get('/sitemap.xml', fn () => response()->view('sitemap', ['paths' => array_keys(config('marketing.pages'))])->header('Content-Type', 'application/xml; charset=UTF-8'));
Route::get('/robots.txt', fn () => response("User-agent: *\nDisallow: /api/\nSitemap: ".rtrim(config('app.url'), '/')."/sitemap.xml\n")->header('Content-Type', 'text/plain; charset=UTF-8'));
Route::view('/offline', 'offline')->middleware(PrivateResponse::class)->name('offline');

Route::middleware(PrivateResponse::class)->group(function () {
    Route::view('/create', 'create')->name('create');
    Route::get('/lists', [ListsController::class, 'index'])->name('lists.index');
    Route::post('/lists', [ListsController::class, 'create'])->middleware('throttle:30,1,list-create');
    Route::get('/lists/{list}', [ListsController::class, 'show'])->whereUuid('list')->name('lists.show');
    Route::view('/join', 'app', ['screen' => 'join'])->name('join');
    Route::view('/account', 'account')->name('account');
    Route::post('/account', [AccountController::class, 'authenticate'])->middleware('throttle:8,1,account-login');
    Route::post('/account/claim', [AccountController::class, 'claim'])->middleware('auth');
    Route::post('/account/preferences', [AccountController::class, 'preferences'])->middleware('auth');
    Route::post('/account/logout', [AccountController::class, 'logout'])->middleware('auth');
    Route::view('/account/forgot-password', 'password')->name('password.request');
    Route::post('/account/forgot-password', [PasswordController::class, 'email'])->middleware('throttle:5,1,password-mail');
    Route::get('/account/reset-password/{token}', fn (string $token) => view('password', ['token' => $token]))->name('password.reset');
    Route::post('/account/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:8,1,password-reset');
    Route::get('/account/google', [AccountController::class, 'google'])->middleware('throttle:8,1,google-login');
    Route::get('/account/google/callback', [AccountController::class, 'googleCallback'])->middleware('throttle:8,1,google-callback');
    Route::get('/api/session', fn () => response()->json(['csrf' => csrf_token()]))->middleware('throttle:90,1,csrf-session');
    Route::post('/api/invitations/accept', [SharingController::class, 'accept'])->middleware('throttle:12,1,invite-accept');
    Route::post('/api/broadcasting/auth', [SharingController::class, 'broadcast'])->middleware('throttle:60,1,channel-auth');
    Route::prefix('/api/lists/{list}')->whereUuid('list')->group(function () {
        Route::get('/', [ListsController::class, 'state'])->middleware('throttle:150,1,list-read');
        Route::post('/operations', SyncController::class)->middleware('throttle:240,1,list-sync');
        Route::post('/template', [TemplatesController::class, 'store'])->middleware(['auth', 'throttle:10,1,list-template']);
        Route::delete('/', [ListsController::class, 'destroy'])->middleware('throttle:30,1,list-management');
        Route::patch('/settings', SettingsController::class)->middleware('throttle:30,1,list-management');
        Route::post('/invitations', [SharingController::class, 'invite'])->middleware('throttle:20,1,invite-create');
        Route::delete('/invitations/{invitation}', [SharingController::class, 'revoke'])->whereUuid('invitation')->middleware('throttle:30,1,list-management');
        Route::patch('/participants/{participant}', [SharingController::class, 'participant'])->whereNumber('participant')->middleware('throttle:30,1,list-management');
        Route::post('/items/{item}/photo', [PhotosController::class, 'store'])->whereUuid('item')->middleware('throttle:20,1,photo-upload');
        Route::get('/photos/{attachment}', [PhotosController::class, 'show'])->whereUuid('attachment')->middleware('throttle:480,1,photo-download')->name('photos.show');
    });
});
