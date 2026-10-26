<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_docs_ui_tersedia(): void
    {
        $this->get('/api/docs')
            ->assertStatus(200)
            ->assertSee('swagger-ui', false);
    }

    public function test_docs_yaml_valid(): void
    {
        $this->get('/api/docs.yaml')
            ->assertStatus(200)
            ->assertSee('openapi: 3.0.3', false);
    }

    public function test_docs_json_meringkas_path(): void
    {
        $res = $this->getJson('/api/docs.json')->assertStatus(200);
        $this->assertTrue($res->json('success'));
        $this->assertGreaterThanOrEqual(30, $res->json('data.paths'));
    }
}
