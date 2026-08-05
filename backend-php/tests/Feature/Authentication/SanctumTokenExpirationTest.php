<?php

namespace Tests\Feature\Authentication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SanctumTokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that an active Sanctum token is accepted with HTTP 200.
     */
    public function test_active_sanctum_token_is_accepted(): void
    {
        $user = User::factory()->create();
        $tokenObj = $user->createToken('test-token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenObj->plainTextToken)
            ->getJson('/api/cliente/perfil');

        $response->assertStatus(200);
    }

    /**
     * Test that an expired Sanctum token is rejected with HTTP 401.
     */
    public function test_expired_sanctum_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $tokenObj = $user->createToken('test-token');
        \Illuminate\Support\Facades\DB::table('personal_access_tokens')
            ->where('id', $tokenObj->accessToken->id)
            ->update(['created_at' => now()->subHours(25)]);

        $responseExpired = $this->withHeader('Authorization', 'Bearer ' . $tokenObj->plainTextToken)
            ->getJson('/api/cliente/perfil');

        $responseExpired->assertStatus(401);
    }
}
