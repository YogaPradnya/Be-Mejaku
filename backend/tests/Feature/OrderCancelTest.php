<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCancelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder']);
        $admin = \App\Models\User::firstOrCreate(
            ['email' => 'admin@mejaku.local'],
            ['name' => 'Administrator', 'password' => bcrypt('admin123')]
        );
        $this->token = \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser($admin);
    }

    private function makeOrder(int $qty = 2): string
    {
        $menuItem = MenuItem::first();
        $res = $this->postJson('/api/orders', [
            'table_code' => 'A12',
            'items' => [['menu_item_id' => $menuItem->id, 'quantity' => $qty]],
        ]);
        $res->assertStatus(201);

        return $res->json('data.order_number');
    }

    public function test_cancel_restores_stock(): void
    {
        $menuItem = MenuItem::first();
        $before = $menuItem->stock;
        $orderNumber = $this->makeOrder(2);
        $this->assertEquals($before - 2, $menuItem->fresh()->stock);

        $res = $this->postJson(
            "/api/admin/orders/{$orderNumber}/cancel",
            [],
            ['Authorization' => 'Bearer ' . $this->token]
        );

        $res->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals('cancelled', $res->json('data.payment_status'));
        $this->assertEquals($before, $menuItem->fresh()->stock);
    }

    public function test_cancel_paid_order_rejected(): void
    {
        $orderNumber = $this->makeOrder(1);
        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $order->update(['payment_status' => 'paid']);

        $res = $this->postJson(
            "/api/admin/orders/{$orderNumber}/cancel",
            [],
            ['Authorization' => 'Bearer ' . $this->token]
        );

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_cancel_requires_auth(): void
    {
        $orderNumber = $this->makeOrder(1);

        $this->postJson("/api/admin/orders/{$orderNumber}/cancel", [])
            ->assertStatus(401);
    }

    public function test_public_status_update_requires_auth(): void
    {
        $orderNumber = $this->makeOrder(1);

        $this->patchJson("/api/orders/{$orderNumber}/status", [
            'preparation_status' => 'preparing',
        ])->assertStatus(401);
    }
}
