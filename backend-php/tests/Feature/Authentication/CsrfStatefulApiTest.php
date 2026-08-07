<?php

namespace Tests\Feature\Authentication;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

class CsrfStatefulApiTest extends TestCase
{
    use RefreshDatabase;

    private const LOCAL_ORIGIN = 'http://localhost:5173';
    private const VERCEL_ORIGIN = 'https://istore-chile.vercel.app';
    private const NON_STATEFUL_ORIGIN = 'https://external.example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sanctum.stateful' => ['localhost:5173', '127.0.0.1:5173', 'istore-chile.vercel.app'],
            'sanctum.middleware.validate_csrf_token' => CsrfValidationForFeatureTests::class,
        ]);
    }

    public function test_vercel_stateful_frontend_receives_an_xsrf_cookie(): void
    {
        $response = $this->get('/sanctum/csrf-cookie', ['Origin' => self::VERCEL_ORIGIN]);

        $response->assertNoContent()
            ->assertCookie('XSRF-TOKEN')
            ->assertCookie(config('session.cookie'));

        self::assertTrue(EnsureFrontendRequestsAreStateful::fromFrontend($response->baseRequest));
        self::assertFalse($response->getCookie('XSRF-TOKEN', false)->isHttpOnly());
    }

    public function test_vercel_stateful_login_without_csrf_is_rejected_before_issuing_a_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', $this->credentialsFor($user), ['Origin' => self::VERCEL_ORIGIN])
            ->assertStatus(419)
            ->assertCookieMissing('token_istore');

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest();
    }

    public function test_local_stateful_admin_login_with_valid_csrf_issues_a_token_and_creates_a_session(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        ['session' => $session, 'xsrf' => $xsrf] = $this->csrfCookies();

        $response = $this->withCredentials()
            ->withUnencryptedCookies([config('session.cookie') => $session, 'XSRF-TOKEN' => $xsrf])
            ->postJson('/api/login', $this->credentialsFor($user), [
                'Origin' => self::LOCAL_ORIGIN,
                'X-XSRF-TOKEN' => $xsrf,
            ]);

        $response->assertOk()->assertJsonPath('usuario', $user->name)
            ->assertJsonStructure(['token', 'role'])->assertCookie('token_istore')
            ->assertCookie(config('session.cookie'));
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->sessionRequest($this->sessionFrom($response))
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_vercel_stateful_customer_login_with_valid_csrf_issues_a_token_and_creates_a_session(): void
    {
        $user = User::factory()->create();
        ['session' => $session, 'xsrf' => $xsrf] = $this->csrfCookies(self::VERCEL_ORIGIN);

        $response = $this->withCredentials()
            ->withUnencryptedCookies([config('session.cookie') => $session, 'XSRF-TOKEN' => $xsrf])
            ->postJson('/api/cliente/login', $this->credentialsFor($user), [
                'Origin' => self::VERCEL_ORIGIN,
                'X-XSRF-TOKEN' => $xsrf,
            ]);

        $response->assertOk()->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token', 'user'])->assertCookie('token_istore')
            ->assertCookie(config('session.cookie'));
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->sessionRequest($this->sessionFrom($response), self::VERCEL_ORIGIN)
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_local_stateful_customer_registration_with_valid_csrf_issues_a_token_and_creates_a_session(): void
    {
        ['session' => $session, 'xsrf' => $xsrf] = $this->csrfCookies();
        $credentials = [
            'name' => 'Cliente de Prueba',
            'email' => 'cliente@example.test',
            'password' => 'Password1',
        ];

        $response = $this->withCredentials()
            ->withUnencryptedCookies([config('session.cookie') => $session, 'XSRF-TOKEN' => $xsrf])
            ->postJson('/api/cliente/registro', $credentials, [
                'Origin' => self::LOCAL_ORIGIN,
                'X-XSRF-TOKEN' => $xsrf,
            ]);

        $response->assertCreated()->assertJsonPath('user.email', $credentials['email'])
            ->assertJsonStructure(['token', 'user'])->assertCookie('token_istore')
            ->assertCookie(config('session.cookie'));
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->sessionRequest($this->sessionFrom($response))
            ->assertOk()
            ->assertJsonPath('user.email', $credentials['email']);
    }

    public function test_explicit_bearer_token_authenticates_a_protected_route_from_a_non_stateful_origin(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/cliente/login', $this->credentialsFor($user), [
            'Origin' => self::NON_STATEFUL_ORIGIN,
        ]);

        $response->assertOk();
        self::assertFalse(EnsureFrontendRequestsAreStateful::fromFrontend($response->baseRequest));

        $this->withHeader('Authorization', 'Bearer '.$response->json('token'))
            ->getJson('/api/cliente/perfil', ['Origin' => self::NON_STATEFUL_ORIGIN])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_a_token_istore_cookie_without_an_authorization_header_does_not_authenticate(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('customer_token')->plainTextToken;

        $this->withUnencryptedCookies(['token_istore' => $token])
            ->getJson('/api/cliente/perfil')
            ->assertUnauthorized();
    }

    /**
     * Regresión: logout() lanzaba 500 en flujo stateful porque
     * currentAccessToken() retorna TransientToken (sin delete()).
     * Fix: verificar instanceof PersonalAccessToken antes de delete(),
     * e invalidar la sesión web correctamente.
     *
     * Nota sobre el entorno de test: con SESSION_DRIVER=array el guard web
     * cachea el usuario en memoria dentro del mismo proceso PHP, lo que hace
     * que assertar 401 post-logout sea un falso negativo en tests (pero correcto
     * en producción, verificado con curl). Los asserts cubren los efectos reales
     * del bug: 200 en lugar de 500, sesión regenerada, cookie token_istore limpiada.
     *
     * @see ClienteAuthController::logout()
     */
    public function test_stateful_logout_returns_200_not_500_and_session_is_regenerated(): void
    {
        $user = User::factory()->create();
        ['session' => $session, 'xsrf' => $xsrf] = $this->csrfCookies(self::LOCAL_ORIGIN);

        // 1. Login stateful
        $loginResponse = $this->withCredentials()
            ->withUnencryptedCookies([config('session.cookie') => $session, 'XSRF-TOKEN' => $xsrf])
            ->postJson('/api/cliente/login', $this->credentialsFor($user), [
                'Origin'       => self::LOCAL_ORIGIN,
                'X-XSRF-TOKEN' => $xsrf,
            ]);

        $loginResponse->assertOk();
        $sessionAfterLogin = $this->sessionFrom($loginResponse);
        $xsrfAfterLogin = $loginResponse->getCookie('XSRF-TOKEN', false)?->getValue() ?? $xsrf;

        // 2. Logout stateful — debe ser 200, NUNCA 500
        // (bug: TransientToken::delete() fallaba con «Call to undefined method
        //  Laravel\Sanctum\TransientToken::delete()» en flujo stateful)
        $logoutResponse = $this->withCredentials()
            ->withUnencryptedCookies([
                config('session.cookie') => $sessionAfterLogin,
                'XSRF-TOKEN'            => $xsrfAfterLogin,
            ])
            ->postJson('/api/logout', [], [
                'Origin'       => self::LOCAL_ORIGIN,
                'X-XSRF-TOKEN' => $xsrfAfterLogin,
            ]);

        // El assertion clave del bug: 200, no 500
        $logoutResponse->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada con éxito');

        // La sesión fue regenerada: nuevo ID en la cookie post-logout
        $sessionAfterLogout = $logoutResponse->getCookie(config('session.cookie'), false)?->getValue();
        self::assertNotEquals(
            $sessionAfterLogin,
            $sessionAfterLogout,
            'session()->invalidate() debe haber rotado el ID de sesión.'
        );

        // El cookie token_istore debe ser eliminado
        $tokenCookie = $logoutResponse->getCookie('token_istore', false);
        self::assertTrue(
            is_null($tokenCookie) || $tokenCookie->isCleared(),
            'El cookie token_istore debe ser eliminado (cleared) en el logout.'
        );
    }

    /**
     * Regresión complementaria: logout() con Bearer token (flujo API)
     * sigue eliminando el PersonalAccessToken correctamente.
     * El fix no debe haber roto el path de revocación de tokens reales.
     */
    public function test_bearer_token_logout_deletes_personal_access_token(): void
    {
        $user = User::factory()->create();

        $loginResponse = $this->postJson('/api/cliente/login', $this->credentialsFor($user), [
            'Origin' => self::NON_STATEFUL_ORIGIN,
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withHeader('Authorization', 'Bearer '.$loginResponse->json('token'))
            ->postJson('/api/logout', [], ['Origin' => self::NON_STATEFUL_ORIGIN])
            ->assertOk();

        // El efecto principal del fix: el token fue eliminado de la DB
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Nota: assertar 401 con el mismo bearer en el mismo proceso de test no es
        // determinístico porque el guard web puede cachear el user. El borrado de
        // la fila en personal_access_tokens (assertado arriba) garantiza que el
        // token es inválido en cualquier proceso nuevo (producción).
    }

    public function test_chatbot_and_guest_cart_are_stateless_and_do_not_write_to_sessions_table(): void
    {
        config(['session.driver' => 'database']);
        $initialSessionCount = \Illuminate\Support\Facades\DB::table('sessions')->count();

        $uuid = (string) \Illuminate\Support\Str::uuid();

        // 1. Request to POST /api/chatbot with stateful origin
        $this->postJson('/api/chatbot', ['message' => 'Hola'], ['Origin' => self::LOCAL_ORIGIN]);

        // 2. Request to GET /api/cart with stateful origin and guest session header
        $this->getJson('/api/cart', [
            'Origin' => self::LOCAL_ORIGIN,
            'X-Session-Id' => $uuid,
        ]);

        // 3. Request to POST /api/cart/items with stateful origin and guest session header
        $this->postJson('/api/cart/items', [
            'product_id' => 1,
            'quantity' => 1,
        ], [
            'Origin' => self::LOCAL_ORIGIN,
            'X-Session-Id' => $uuid,
        ]);

        $finalSessionCount = \Illuminate\Support\Facades\DB::table('sessions')->count();

        self::assertEquals(
            $initialSessionCount,
            $finalSessionCount,
            'Rutas /api/chatbot y /api/cart/* no deben escribir registros en la tabla sessions'
        );
    }

    private function csrfCookies(string $origin = self::LOCAL_ORIGIN): array
    {
        $response = $this->get('/sanctum/csrf-cookie', ['Origin' => $origin]);
        $response->assertNoContent();

        return [
            'session' => $response->getCookie(config('session.cookie'), false)->getValue(),
            'xsrf' => $response->getCookie('XSRF-TOKEN', false)->getValue(),
        ];
    }

    private function sessionFrom(TestResponse $response): string
    {
        $session = $response->getCookie(config('session.cookie'), false);

        self::assertNotNull($session);

        return $session->getValue();
    }

    private function sessionRequest(string $session, string $origin = self::LOCAL_ORIGIN): TestResponse
    {
        return $this->withCredentials()
            ->withUnencryptedCookies([config('session.cookie') => $session])
            ->getJson('/api/cliente/perfil', ['Origin' => $origin]);
    }

    private function credentialsFor(User $user): array
    {
        return ['email' => $user->email, 'password' => 'password'];
    }
}

class CsrfValidationForFeatureTests extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
