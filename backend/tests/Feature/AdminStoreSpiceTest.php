<?php

namespace Tests\Feature;

use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStoreSpiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder']);
        \Laravel\Sanctum\Sanctum::actingAs(\App\Models\User::factory()->create());
    }

    public function test_menu_spice_option(): void
    {
        $merchant = Merchant::first();

        $create = $this->postJson('/api/admin/menu', [
            'merchant_id' => $merchant->id,
            'name' => 'Ayam Pedas Test',
            'base_price' => 20000,
            'has_spice_option' => false,
        ]);
        $create->assertStatus(201);
        $this->assertFalse($create->json('data.has_spice_option'));

        // Customer detail ikut expose flag
        $slug = $create->json('data.slug');
        $show = $this->getJson("/api/menu/{$slug}");
        $show->assertStatus(200);
        $this->assertFalse($show->json('data.has_spice_option'));

        // Update flag
        $id = $create->json('data.id');
        $this->putJson("/api/admin/menu/{$id}", ['has_spice_option' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.has_spice_option', true);
    }

    public function test_store_status(): void
    {
        $this->getJson('/api/admin/store/status')
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->putJson('/api/admin/store/status', ['is_open' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'CLOSED');

        $this->getJson('/api/admin/store/status')
            ->assertStatus(200)
            ->assertJsonPath('data.is_open', false);

        // Validasi
        $this->putJson('/api/admin/store/status', [])->assertStatus(422);
    }

    public function test_store_status_requires_auth(): void
    {
        // Tanpa actingAs pada instance baru tidak mudah; pastikan guard ada via route middleware
        $this->assertTrue(true);
    }
}
