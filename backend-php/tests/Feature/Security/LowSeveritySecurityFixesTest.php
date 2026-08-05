<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LowSeveritySecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that security headers are injected in HTTP responses.
     */
    public function test_responses_contain_security_headers(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->assertHeader('Content-Security-Policy', "default-src 'self'; frame-ancestors 'none';");
    }

    /**
     * Test that client login rejects malformed email or missing password with HTTP 422 before querying DB.
     */
    public function test_login_rejects_malformed_email_with_422(): void
    {
        $response = $this->postJson('/api/cliente/login', [
            'email' => 'not-an-email-address',
            'password' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test mass assignment protection on Category and Product store/update endpoints.
     */
    public function test_mass_assignment_strips_unallowed_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        // Category store
        $responseCat = $this->postJson('/api/categories', [
            'name' => 'Insumos Médicos',
            'icon' => 'flask',
            'unallowed_extra_field' => 'malicious_value',
        ]);

        $responseCat->assertStatus(201);
        $this->assertDatabaseHas('categories', ['name' => 'Insumos Médicos']);

        $catId = $responseCat->json('data.id');
        $catDb = Category::find($catId);
        $this->assertArrayNotHasKey('unallowed_extra_field', $catDb->toArray());

        // Product store
        $responseProd = $this->postJson('/api/products', [
            'name' => 'Balanza de Precisión',
            'category_id' => $catId,
            'price' => 5000,
            'stock' => 10,
            'unallowed_extra_field' => 'malicious_value',
        ]);

        $responseProd->assertStatus(201);
        $prodId = $responseProd->json('data.id');
        $prodDb = Product::find($prodId);
        $this->assertArrayNotHasKey('unallowed_extra_field', $prodDb->toArray());
    }
}
