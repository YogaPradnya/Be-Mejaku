<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed database sebelum setiap test feature
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder']);
    }

    /**
     * Test mendapatkan daftar menu.
     */
    public function test_can_get_menu_list(): void
    {
        $response = $this->getJson('/api/menu');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id', 'name', 'slug', 'base_price', 'merchant'
                    ]
                ]
            ]);
            
        $this->assertTrue($response->json('success'));
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    /**
     * Test daftar meja publik untuk QR scanner.
     */
    public function test_can_list_active_tables(): void
    {
        $response = $this->getJson('/api/tables');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'floor'],
                ],
            ]);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    /**
     * Test mendapatkan detail meja.
     */
    public function test_can_get_table_detail(): void
    {
        $response = $this->getJson('/api/tables/A12');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'A12'
                ]
            ]);
    }

    /**
     * Test gagal saat meja tidak ditemukan.
     */
    public function test_fails_when_table_not_found(): void
    {
        $response = $this->getJson('/api/tables/INVALID');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test alur pembuatan pesanan dan pembayaran (E2E API Test).
     */
    public function test_order_and_payment_flow(): void
    {
        // 1. Ambil data menu
        $menuItem = MenuItem::first();
        
        // 2. Buat pesanan
        $orderPayload = [
            'table_code' => 'A12',
            'notes' => 'Tolong pedas',
            'items' => [
                [
                    'menu_item_id' => $menuItem->id,
                    'quantity' => 1
                ]
            ]
        ];

        $orderResponse = $this->postJson('/api/orders', $orderPayload);

        $orderResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);
            
        $orderNumber = $orderResponse->json('data.order_number');
        $this->assertNotEmpty($orderNumber);
        $this->assertEquals('pending', $orderResponse->json('data.payment_status'));

        // 3. Buat pembayaran
        $paymentPayload = [
            'method' => 'qris'
        ];

        $paymentResponse = $this->postJson('/api/payments/' . $orderNumber, $paymentPayload);

        $paymentResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);
            
        $this->assertNotEmpty($paymentResponse->json('data.qr_data'));
        $this->assertEquals('qris', $paymentResponse->json('data.method'));

        // 4. Cek status pembayaran (masih pending)
        $statusResponse = $this->getJson('/api/payments/' . $orderNumber . '/status');
        $statusResponse->assertStatus(200);
        $this->assertEquals('pending', $statusResponse->json('data.status'));

        // 5. Simulasi pembayaran lunas
        $simulatePayload = [
            'action' => 'pay'
        ];

        $simulateResponse = $this->postJson('/api/payments/' . $orderNumber . '/simulate', $simulatePayload);

        $simulateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // 6. Pastikan status order berubah menjadi preparing
        $this->assertEquals('paid', $simulateResponse->json('data.payment_status'));
        $this->assertEquals('preparing', $simulateResponse->json('data.preparation_status'));
    }
}
