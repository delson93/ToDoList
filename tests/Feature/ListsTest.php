<?php

namespace Tests\Feature;

use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ListsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
    }

    private function createList(): string
    {
        $response = $this->post('/lists', ['category' => 'grocery', 'name' => 'Saturday groceries'])->assertStatus(303);
        $this->withUnencryptedCookie('utilynk_guest', $response->getCookie('utilynk_guest', false)->getValue());

        $id = ShoppingList::firstOrFail()->id;
        $response->assertHeader('Location', '/lists/'.$id);

        return $id;
    }

    private function operation(string $type, string $item, int $base, array $data): array
    {
        return ['id' => Str::uuid()->toString(), 'item_id' => $item, 'type' => $type, 'base' => $base, 'data' => $data];
    }

    private function invite(string $list, string $role = 'edit'): string
    {
        $url = $this->postJson("/api/lists/$list/invitations", ['role' => $role, 'days' => 1])->assertCreated()->json('url');

        return parse_url($url, PHP_URL_FRAGMENT);
    }

    private function join(string $token): void
    {
        $this->withUnencryptedCookie('utilynk_guest', '');
        $response = $this->postJson('/api/invitations/accept', ['token' => $token, 'name' => 'Guest tester'])->assertOk();
        $this->withUnencryptedCookie('utilynk_guest', $response->getCookie('utilynk_guest', false)->getValue());
    }

    public function test_guest_can_create_complete_delete_and_restore_items(): void
    {
        $list = $this->createList();
        $this->get("/lists/$list")->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $endpoint = "/api/lists/$list/operations";
        $item = Str::uuid()->toString();
        $this->postJson($endpoint, $this->operation('add', $item, 0, ['name' => 'Rice', 'quantity' => 5]))->assertOk();
        $this->postJson($endpoint, $this->operation('status', $item, 1, ['status' => 'partial', 'purchased' => 3]))->assertOk();
        $this->postJson($endpoint, $this->operation('status', $item, 2, ['status' => 'completed']))->assertOk();
        $this->getJson("/api/lists/$list")->assertJsonPath('items.0.status', 'completed');
        $this->postJson($endpoint, $this->operation('delete', $item, 3, []))->assertOk();
        $this->getJson("/api/lists/$list")->assertJsonCount(0, 'items');
        $this->postJson($endpoint, $this->operation('restore', $item, 4, []))->assertOk();
        $this->getJson("/api/lists/$list")->assertJsonPath('items.0.name', 'Rice');
    }

    public function test_duplicate_operations_and_stale_concurrent_edits(): void
    {
        $list = $this->createList();
        $token = $this->invite($list);
        $item = Str::uuid()->toString();
        $endpoint = "/api/lists/$list/operations";
        $add = $this->operation('add', $item, 0, ['name' => 'Apples']);
        $this->postJson($endpoint, $add)->assertOk()->assertJsonPath('duplicate', false);
        $this->postJson($endpoint, $add)->assertOk()->assertJsonPath('duplicate', true);
        $this->postJson($endpoint, $this->operation('edit', $item, 1, ['quantity' => 3]))->assertOk();
        $this->join($token);
        $this->postJson($endpoint, $this->operation('edit', $item, 1, ['quantity' => 2]))->assertConflict();
        $state = $this->getJson("/api/lists/$list")->assertOk()->assertJsonPath('items.0.version', 2);
        $this->getJson("/api/lists/$list", ['If-None-Match' => $state->headers->get('ETag')])->assertStatus(304);
        $this->assertDatabaseCount('sync_operations', 2);
    }

    public function test_guest_invitation_acceptance_and_view_only_permissions(): void
    {
        $list = $this->createList();
        $token = $this->invite($list, 'view');
        $this->assertDatabaseHas('invitations', ['token_digest' => hash('sha256', $token)]);
        $this->join($token);
        $this->getJson("/api/lists/$list")->assertJsonPath('actor.name', 'Guest tester')->assertJsonPath('actor.role', 'view');
        $this->postJson("/api/lists/$list/operations", $this->operation('add', Str::uuid()->toString(), 0, ['name' => 'Unauthorized']))->assertForbidden();
        $this->postJson("/api/lists/$list/invitations", ['role' => 'edit', 'days' => 1])->assertForbidden();
    }

    public function test_complete_only_invitation_cannot_modify_item_details(): void
    {
        $list = $this->createList();
        $token = $this->invite($list, 'complete');
        $item = Str::uuid()->toString();
        $endpoint = "/api/lists/$list/operations";
        $this->postJson($endpoint, $this->operation('add', $item, 0, ['name' => 'Bread']))->assertOk();
        $this->join($token);
        $this->postJson($endpoint, $this->operation('edit', $item, 1, ['name' => 'Cake']))->assertForbidden();
        $this->postJson($endpoint, $this->operation('status', $item, 1, ['name' => 'Cake', 'status' => 'completed']))->assertOk();
        $this->assertDatabaseHas('list_items', ['id' => $item, 'name' => 'Bread', 'status' => 'completed']);
    }

    public function test_revoked_and_expired_invitations_cannot_be_accepted(): void
    {
        $list = $this->createList();
        $revoked = $this->invite($list);
        $expired = $this->invite($list);
        $id = DB::table('invitations')->where('token_digest', hash('sha256', $revoked))->value('id');
        $this->deleteJson("/api/lists/$list/invitations/$id")->assertNoContent();
        $this->travel(2)->days();
        foreach ([$revoked, $expired] as $token) {
            $this->postJson('/api/invitations/accept', ['token' => $token])->assertNotFound();
        }
    }

    public function test_private_lists_and_photos_require_authorization(): void
    {
        Storage::fake('local');
        $list = $this->createList();
        $item = Str::uuid()->toString();
        $this->postJson("/api/lists/$list/operations", $this->operation('add', $item, 0, ['name' => 'Bread']))->assertOk();
        $upload = "/api/lists/$list/items/$item/photo";
        $this->postJson($upload, ['photo' => UploadedFile::fake()->create('unsafe.svg', 1, 'image/svg+xml')])->assertUnprocessable();
        $url = $this->postJson($upload, ['photo' => UploadedFile::fake()->image('bread.png', 800, 400)])->assertCreated()->json('url');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->withUnencryptedCookie('utilynk_guest', '');
        $this->get("/lists/$list")->assertNotFound();
        $this->getJson("/api/lists/$list")->assertNotFound()->assertJsonMissing(['name' => 'Bread']);
        $this->get($url)->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee($list);
    }

    public function test_claim_moves_guest_access_to_account(): void
    {
        $list = $this->createList();
        $this->post('/account', ['mode' => 'register', 'name' => 'Tester', 'email' => 'tester@example.com', 'password' => 'test-password-123'])->assertRedirect('/account');
        $this->post('/account/claim')->assertRedirect('/lists');
        $this->getJson("/api/lists/$list")->assertOk()->assertJsonPath('actor.role', 'owner');
        $this->assertDatabaseHas('participants', ['list_id' => $list, 'guest_identity_id' => null]);
    }

    public function test_old_invitation_cannot_restore_removed_participant_access(): void
    {
        $list = $this->createList();
        $ownerCookie = $this->unencryptedCookies['utilynk_guest'];
        $token = $this->invite($list, 'edit');
        $this->join($token);
        $guestCookie = $this->unencryptedCookies['utilynk_guest'];
        $guest = $this->getJson("/api/lists/$list")->assertOk()->json('actor.id');
        $this->withUnencryptedCookie('utilynk_guest', $ownerCookie);
        $this->patchJson("/api/lists/$list/participants/$guest", ['role' => 'view'])->assertNoContent();
        $this->withUnencryptedCookie('utilynk_guest', $guestCookie);
        $this->postJson('/api/invitations/accept', ['token' => $token])->assertOk();
        $this->getJson("/api/lists/$list")->assertJsonPath('actor.role', 'view');
        $this->withUnencryptedCookie('utilynk_guest', $ownerCookie);
        $this->patchJson("/api/lists/$list/participants/$guest", ['role' => 'remove'])->assertNoContent();
        $this->withUnencryptedCookie('utilynk_guest', $guestCookie);
        $this->getJson("/api/lists/$list")->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->postJson('/api/invitations/accept', ['token' => $token])->assertNotFound();
    }

    public function test_reverb_signs_only_channels_for_authorized_participants(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-id',
        ]);
        $list = $this->createList();
        $actor = $this->getJson("/api/lists/$list")->assertOk()->json('actor.id');
        $request = ['channel_name' => "private-lists.$list.$actor", 'socket_id' => '12.34'];
        $this->postJson('/api/broadcasting/auth', $request)->assertOk()->assertJsonStructure(['auth']);
        $this->postJson('/api/broadcasting/auth', ['channel_name' => "private-lists.$list.".($actor + 1), 'socket_id' => '12.34'])->assertForbidden();
        $this->withUnencryptedCookie('utilynk_guest', '');
        $this->postJson('/api/broadcasting/auth', $request)->assertNotFound();
    }

    public function test_recurrence_carries_the_remaining_amount_into_a_new_list(): void
    {
        $list = $this->createList();
        $endpoint = "/api/lists/$list";
        $item = Str::uuid()->toString();
        $this->postJson("$endpoint/operations", $this->operation('add', $item, 0, ['name' => 'Rice', 'quantity' => 5, 'status' => 'partial', 'purchased' => 3]))->assertOk();
        $this->patchJson("$endpoint/settings", ['name' => 'Weekly groceries', 'frequency' => 'weekly'])->assertNoContent();
        $this->travel(8)->days();
        $this->artisan('lists:recur')->assertSuccessful();
        $this->artisan('lists:recur')->assertSuccessful();
        $this->assertDatabaseCount('lists', 2);
        $copy = ShoppingList::whereKeyNot($list)->firstOrFail();
        $this->assertDatabaseHas('list_items', ['list_id' => $copy->id, 'name' => 'Rice', 'status' => 'pending', 'quantity' => 2]);
        $this->getJson("/api/lists/$copy->id")->assertOk();
    }

    public function test_password_reset_uses_the_standard_broker(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/account/forgot-password', ['email' => $user->email])->assertRedirect();
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/account/reset-password', [
                'email' => $user->email, 'token' => $notification->token,
                'password' => 'new-password-for-tests', 'password_confirmation' => 'new-password-for-tests',
            ])->assertRedirect('/account');

            return Hash::check('new-password-for-tests', $user->fresh()->password);
        });
    }

    public function test_owner_can_save_and_reuse_a_personal_template(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/lists', ['category' => 'grocery', 'template' => 'grocery'])->assertStatus(303);
        $list = ShoppingList::firstOrFail();
        $this->postJson("/api/lists/$list->id/template")->assertNoContent();
        $template = DB::table('templates')->value('id');
        $this->post('/lists', ['category' => 'shopping', 'saved_template' => $template])->assertStatus(303);
        $copy = ShoppingList::whereKeyNot($list->id)->firstOrFail();
        $this->assertSame('grocery', $copy->category);
        $this->assertEquals($list->items()->count(), $copy->items()->count());
    }

    public function test_only_owner_can_delete_list_and_remove_shared_access(): void
    {
        $list = $this->createList();
        $owner = $this->unencryptedCookies['utilynk_guest'];
        $token = $this->invite($list, 'collaborate');
        $this->join($token);
        $guest = $this->unencryptedCookies['utilynk_guest'];

        $this->deleteJson("/api/lists/$list")->assertForbidden();
        $this->withUnencryptedCookie('utilynk_guest', $owner);
        $this->deleteJson("/api/lists/$list")->assertNoContent();
        $this->get('/lists')->assertDontSee('Saturday groceries');
        $this->get("/lists/$list")->assertNotFound();

        $this->withUnencryptedCookie('utilynk_guest', $guest);
        $this->getJson("/api/lists/$list")->assertNotFound();
        $this->postJson('/api/invitations/accept', ['token' => $token])->assertNotFound();
    }

    public function test_account_forms_keep_redirects_same_origin_behind_https_proxy(): void
    {
        config(['app.url' => 'https://list.utilynk.com']);
        $origin = 'http://list.utilynk.com';
        $account = $origin.'/account';
        $this->get($account)->assertOk();
        $this->from($account)->post($account, ['mode' => 'register'])
            ->assertHeader('Location', '/account')->assertSessionHasErrors('email');
        $credentials = ['email' => 'proxy@example.com', 'password' => 'test-password-123'];
        $this->post($account, ['mode' => 'register', 'name' => 'Proxy tester'] + $credentials)
            ->assertHeader('Location', '/account');
        $this->assertAuthenticated();
        $this->post($account.'/logout')->assertHeader('Location', '/account');
        $this->assertGuest();
        $this->from($account)->post($account, ['mode' => 'login', 'password' => 'invalid'] + $credentials)
            ->assertHeader('Location', '/account')->assertSessionHasErrors('email');
        $this->post($account, ['mode' => 'login'] + $credentials)
            ->assertHeader('Location', '/account');
        $this->assertAuthenticated();
    }
}
