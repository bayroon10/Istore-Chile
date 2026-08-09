<?php

namespace Tests\Feature\Api\Santi;

use App\Models\Order;
use App\Models\User;
use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\ToolExecutor;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ForbiddenOperationsPropertyTest extends TestCase
{
    use RefreshDatabase;
    use WithSantiGenerators;

    private const PROPERTY_SEED = 7_500_2026;

    /** @var list<string> */
    private const INTERNAL_METHOD_ATTACKS = [
        'DB::delete',
        'DB::statement',
        'User::destroy',
        'App\Models\Order::truncate',
        'Order::destroy',
        'Illuminate\Support\Facades\DB::raw',
        'exec',
        'system',
        'passthru',
        'shell_exec',
        'file_get_contents',
        'unlink',
        'eval',
    ];

    /** @var list<string> */
    private const FORBIDDEN_BUSINESS_OPERATIONS = [
        'process_payment',
        'refund_order',
        'stripe_charge',
        'cancel_order',
        'fulfill_order',
        'ship_order',
        'update_order_status',
    ];

    /**
     * Property 14a: Unapproved tool names and internal method attacks are rejected without execution or database writes.
     *
     * **Validates: Requirements 4.15, 4.16, 6.4, 9.2, 9.3**
     */
    public function test_unapproved_tools_and_internal_method_attacks_are_rejected(): void
    {
        $executor = app(ToolExecutor::class);
        $user = $this->santiCustomer(['name' => 'Persistent-Test-Customer']);
        $databaseWrites = [];

        DB::listen(static function (QueryExecuted $query) use (&$databaseWrites): void {
            if (preg_match('/^\s*(?:insert|update|delete|replace|merge)\b/i', $query->sql) === 1) {
                $databaseWrites[] = $query->sql;
            }
        });

        $this->runSantiProperty(function (int $iteration, int $seed) use ($executor, $user, &$databaseWrites): void {
            $context = new ToolContext(
                user: $user,
                correlationId: (string) Str::uuid(),
                draftRequestId: (string) Str::uuid(),
            );

            // Test internal method invocation attacks
            $attackName = self::INTERNAL_METHOD_ATTACKS[$iteration % count(self::INTERNAL_METHOD_ATTACKS)];
            $writesBefore = count($databaseWrites);

            $response = $executor->execute($attackName, [
                'id' => 1,
                'sql' => 'DELETE FROM users',
            ], $context)->toFunctionResponse();

            $this->assertFalse($response['ok'], "Internal method attack {$attackName} must be rejected.");
            $this->assertContains(
                $response['error_code'],
                ['UNKNOWN_TOOL', 'FORBIDDEN_OPERATION'],
                "Internal method attack {$attackName} must return UNKNOWN_TOOL or FORBIDDEN_OPERATION.",
            );
            $this->assertSame($writesBefore, count($databaseWrites), 'Internal method attack must not perform database writes.');
        }, seed: self::PROPERTY_SEED, iterations: self::SANTI_PROPERTY_ITERATIONS);

        $this->assertSame([], $databaseWrites, 'No database writes must be performed during internal method attack tests.');
    }

    /**
     * Property 14b: Forbidden business operation keywords explicitly return FORBIDDEN_OPERATION.
     *
     * **Validates: Requirements 4.15, 4.16, 9.2, 9.3**
     */
    public function test_forbidden_business_operations_return_forbidden_operation(): void
    {
        $executor = app(ToolExecutor::class);

        foreach (self::FORBIDDEN_BUSINESS_OPERATIONS as $operation) {
            $context = new ToolContext(
                user: null,
                correlationId: (string) Str::uuid(),
                draftRequestId: (string) Str::uuid(),
            );

            $response = $executor->execute($operation, ['order_id' => 1], $context)->toFunctionResponse();

            $this->assertFalse($response['ok'], "Forbidden operation {$operation} must fail.");
            $this->assertSame(
                'FORBIDDEN_OPERATION',
                $response['error_code'],
                "Forbidden operation {$operation} must return FORBIDDEN_OPERATION error code.",
            );
        }
    }
}
