<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTablePromoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder']);
        $this->actingAsJwt(\App\Models\User::factory()->create());
    }

    public function test_table_crud_and_delete_guard(): void
    {
        // List
        $list = $this->getJson('/api/admin/tables');
        $list->assertStatus(200)->assertJson(['success' => true]);
        $this->assertGreaterThanOrEqual(3, $list->json('meta.total'));

        // Create
        $create = $this->postJson('/api/admin/tables', [
            'code' => 'd01',
            'floor' => 'Lantai 1',
        ]);
        $create->assertStatus(201)->assertJson(['success' => true]);
        $this->assertEquals('D01', $create->json('data.code'));
        $id = $create->json('data.id');

        // Duplicate -> 422
        $this->postJson('/api/admin/tables', [
            'code' => 'D01', 'floor' => 'Lantai 2',
        ])->assertStatus(422);

        // Toggle off
        $toggle = $this->patchJson("/api/admin/tables/{$id}/toggle");
        $toggle->assertStatus(200);
        $this->assertFalse($toggle->json('data.is_active'));

        // Buat order aktif di meja A12, lalu coba hapus A12 -> 409
        $menuItem = MenuItem::first();
        $this->postJson('/api/orders', [
            'table_code' => 'A12',
            'items' => [['menu_item_id' => $menuItem->id, 'quantity' => 1]],
        ])->assertStatus(201);
        $a12 = $this->getJson('/api/admin/tables?search=A12')->json('data.0.id');
        $this->deleteJson("/api/admin/tables/{$a12}")->assertStatus(409);

        // Hapus meja tanpa order aktif -> OK
        $this->deleteJson("/api/admin/tables/{$id}")->assertStatus(200);
    }

    public function test_promo_crud(): void
    {
        $list = $this->getJson('/api/admin/promos');
        $list->assertStatus(200)->assertJson(['success' => true]);

        $create = $this->postJson('/api/admin/promos', [
            'code' => 'hemat10k',
            'type' => 'fixed',
            'value' => 10000,
            'min_order' => 50000,
            'is_active' => true,
        ]);
        $create->assertStatus(201);
        $this->assertEquals('HEMAT10K', $create->json('data.code'));
        $id = $create->json('data.id');

        // Percentage > 100 ditolak
        $this->postJson('/api/admin/promos', [
            'code' => 'BADPCT', 'type' => 'percentage', 'value' => 150,
        ])->assertStatus(422);

        $update = $this->putJson("/api/admin/promos/{$id}", ['value' => 8000]);
        $update->assertStatus(200);
        $this->assertEquals(8000, $update->json('data.value'));

        $this->deleteJson("/api/admin/promos/{$id}")->assertStatus(200);
        $this->getJson("/api/admin/promos/{$id}")->assertStatus(404);
    }
}
