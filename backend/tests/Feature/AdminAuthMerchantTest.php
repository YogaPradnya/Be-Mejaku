<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthMerchantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder']);
    }

    public function test_admin_routes_require_auth(): void
    {
        $this->getJson('/api/admin/stats')->assertStatus(401);
        $this->getJson('/api/admin/orders')->assertStatus(401);
        $this->getJson('/api/admin/merchants')->assertStatus(401);
    }

    public function test_login_logout_me_flow(): void
    {
        User::factory()->create([
            'email' => 'admin@mejaku.local',
            'password' => Hash::make('admin123'),
        ]);

        // Salah -> 401
        $this->postJson('/api/admin/auth/login', [
            'email' => 'admin@mejaku.local',
            'password' => 'salah',
        ])->assertStatus(401);

        // Benar -> token
        $login = $this->postJson('/api/admin/auth/login', [
            'email' => 'admin@mejaku.local',
            'password' => 'admin123',
        ]);
        $login->assertStatus(200)->assertJson(['success' => true]);
        $token = $login->json('data.token');
        $this->assertNotEmpty($token);

        // Me + logout via Bearer
        $this->getJson('/api/admin/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)->assertJson(['success' => true]);
        $this->postJson('/api/admin/auth/logout', [], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);

        // Token sudah dihapus -> 401
        // (forgetGuards: guard di-cache antar request dalam satu test,
        //  di produksi tiap request HTTP terisolasi)
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $this->getJson('/api/admin/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(401);
    }

    public function test_merchant_crud(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // Konsep single-merchant: merchant ke-2 ditolak 409.
        $this->postJson('/api/admin/merchants', [
            'name' => 'Warung Test',
            'address' => 'Hall X',
        ])->assertStatus(409);

        $id = $this->getJson('/api/admin/merchants?search=Warung')->assertStatus(200)->json('data.0.id');

        $this->putJson("/api/admin/merchants/{$id}", ['address' => 'Hall Y'])
            ->assertStatus(200);

        // Merchant satu-satunya punya menu -> hapus ditolak 409
        $withMenu = $this->getJson('/api/admin/merchants')->json('data.0.id');
        $this->deleteJson("/api/admin/merchants/{$withMenu}")->assertStatus(409);
    }

    public function test_admin_order_show(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $menuItem = MenuItem::first();
        $orderNumber = $this->postJson('/api/orders', [
            'table_code' => 'A12',
            'items' => [['menu_item_id' => $menuItem->id, 'quantity' => 1]],
        ])->json('data.order_number');

        $this->getJson("/api/admin/orders/{$orderNumber}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->getJson('/api/admin/orders/MJK-NOTEXIST')->assertStatus(404);
    }
}
