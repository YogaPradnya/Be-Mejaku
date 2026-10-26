<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder']);
        $this->actingAsJwt(\App\Models\User::factory()->create());
    }

    private function createOrder(array $overrides = []): string
    {
        $menuItem = MenuItem::first();
        $payload = array_merge([
            'table_code' => 'A12',
            'items' => [['menu_item_id' => $menuItem->id, 'quantity' => 1]],
        ], $overrides);

        $res = $this->postJson('/api/orders', $payload);
        $res->assertStatus(201);

        return $res->json('data.order_number');
    }

    public function test_admin_stats_structure(): void
    {
        $this->createOrder();

        $res = $this->getJson('/api/admin/stats');
        $res->assertStatus(200)->assertJson(['success' => true]);
        $res->assertJsonStructure([
            'success',
            'data' => [
                'date', 'revenue_today', 'total_orders_today',
                'sold_items_today', 'active_kitchen', 'pending_payments', 'top_items',
            ],
        ]);
        // Belum paid -> revenue 0, 1 order pending
        $this->assertEquals(0, $res->json('data.revenue_today'));
        $this->assertEquals(1, $res->json('data.total_orders_today'));
    }

    public function test_admin_orders_list_filter_and_pagination(): void
    {
        $orderNumber = $this->createOrder();

        $res = $this->getJson('/api/admin/orders?per_page=10');
        $res->assertStatus(200)->assertJson(['success' => true]);
        $res->assertJsonStructure(['success', 'data', 'meta' => ['current_page', 'total']]);
        $this->assertGreaterThanOrEqual(1, $res->json('meta.total'));

        // Filter preparation_status
        $filtered = $this->getJson('/api/admin/orders?preparation_status=received');
        $filtered->assertStatus(200);
        foreach ($filtered->json('data') as $row) {
            $this->assertEquals('received', $row['preparation_status']);
        }

        // Search by order number
        $search = $this->getJson('/api/admin/orders?search=' . $orderNumber);
        $search->assertStatus(200);
        $this->assertEquals(1, $search->json('meta.total'));
    }

    public function test_admin_update_preparation_and_payment(): void
    {
        $orderNumber = $this->createOrder();

        $prep = $this->patchJson("/api/admin/orders/{$orderNumber}/preparation", [
            'preparation_status' => 'preparing',
        ]);
        $prep->assertStatus(200)->assertJson([
            'success' => true,
            'data' => ['preparation_status' => 'preparing'],
        ]);

        $pay = $this->patchJson("/api/admin/orders/{$orderNumber}/payment", [
            'payment_status' => 'paid',
        ]);
        $pay->assertStatus(200)->assertJson([
            'success' => true,
            'data' => ['payment_status' => 'paid'],
        ]);

        // Stats ikut berubah: revenue > 0
        $stats = $this->getJson('/api/admin/stats');
        $this->assertGreaterThan(0, $stats->json('data.revenue_today'));
    }

    public function test_admin_orders_404(): void
    {
        $res = $this->patchJson('/api/admin/orders/MJK-NOTEXIST/preparation', [
            'preparation_status' => 'ready',
        ]);
        $res->assertStatus(404)->assertJson(['success' => false]);
    }
}
