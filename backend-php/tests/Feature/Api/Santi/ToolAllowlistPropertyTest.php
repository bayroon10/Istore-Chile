<?php

namespace Tests\Feature\Api\Santi;

use App\Services\Chatbot\ToolContext;
use App\Services\Chatbot\ToolContract;
use App\Services\Chatbot\ToolExecutor;
use App\Services\Chatbot\ToolResult;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ToolAllowlistPropertyTest extends TestCase
{
    use WithSantiGenerators;

    /** @var list<string> */
    private const APPROVED_TOOL_NAMES = [
        'check_stock',
        'search_products',
        'create_draft_order',
    ];

    private const PROPERTY_SEED = 7_203_2026;

    /**
     * Property 3: The tool allowlist is inviolable.
     *
     * **Validates: Requirements 1.6, 6.4, 6.10**
     */
    public function test_unapproved_tool_names_are_rejected_without_handlers_or_database_writes(): void
    {
        [$executor, $handlers] = $this->executorWithTrackingHandlers();
        $declaredNames = array_column($executor->declarations()[0]['functionDeclarations'], 'name');
        $databaseWrites = [];

        $this->assertSame(self::APPROVED_TOOL_NAMES, $declaredNames);

        DB::listen(static function (QueryExecuted $query) use (&$databaseWrites): void {
            if (preg_match('/^\s*(?:insert|update|delete|replace|merge)\b/i', $query->sql) === 1) {
                $databaseWrites[] = $query->sql;
            }
        });

        $this->runSantiProperty(function (int $iteration, int $seed) use ($executor, $handlers, &$databaseWrites): void {
            $toolCall = $this->unapprovedToolCall($iteration);
            $writesBeforeExecution = count($databaseWrites);

            $this->assertNotContains($toolCall['name'], self::APPROVED_TOOL_NAMES);

            $response = $executor->execute(
                $toolCall['name'],
                ['untrusted' => "allowlist-{$seed}-{$iteration}"],
                new ToolContext(
                    user: null,
                    correlationId: "tool-allowlist-property-{$seed}-{$iteration}",
                    draftRequestId: "tool-allowlist-draft-{$seed}",
                ),
            )->toFunctionResponse();

            $this->assertSame(
                $toolCall['expected_code'],
                $response['error_code'],
                "{$toolCall['category']} name was not rejected with its bounded error.",
            );
            $this->assertSame(
                0,
                array_sum(array_map(static fn (ToolAllowlistTrackingTool $handler): int => $handler->calls, $handlers)),
                "{$toolCall['category']} name invoked an approved handler.",
            );
            $this->assertSame(
                $writesBeforeExecution,
                count($databaseWrites),
                "{$toolCall['category']} name performed a database write.",
            );
        }, seed: self::PROPERTY_SEED, iterations: self::SANTI_PROPERTY_ITERATIONS);

        $this->assertSame([], $databaseWrites, 'Unapproved tool names must not write to the database.');
    }

    /**
     * @return array{ToolExecutor, list<ToolAllowlistTrackingTool>}
     */
    private function executorWithTrackingHandlers(): array
    {
        $handlers = array_map(
            static fn (string $name): ToolAllowlistTrackingTool => new ToolAllowlistTrackingTool($name),
            self::APPROVED_TOOL_NAMES,
        );

        return [new ToolExecutor($handlers), $handlers];
    }

    /**
     * @return array{name: string, expected_code: 'UNKNOWN_TOOL'|'FORBIDDEN_OPERATION', category: string}
     */
    private function unapprovedToolCall(int $iteration): array
    {
        $canonicalName = self::APPROVED_TOOL_NAMES[$iteration % count(self::APPROVED_TOOL_NAMES)];
        $suffix = sprintf('%d-%08x', $iteration, mt_rand(0, PHP_INT_MAX));

        return match ($iteration % 9) {
            0 => [
                'name' => strtoupper($canonicalName),
                'expected_code' => 'UNKNOWN_TOOL',
                'category' => 'case-variant',
            ],
            1 => [
                'name' => " \t{$canonicalName}\n",
                'expected_code' => 'UNKNOWN_TOOL',
                'category' => 'leading-and-trailing-whitespace',
            ],
            2 => [
                'name' => "{$canonicalName}!{$suffix}",
                'expected_code' => 'UNKNOWN_TOOL',
                'category' => 'punctuation',
            ],
            3 => [
                'name' => "{$canonicalName}-herramienta-ñ-工具-{$suffix}",
                'expected_code' => 'UNKNOWN_TOOL',
                'category' => 'unicode',
            ],
            4 => [
                'name' => "process_payment_{$suffix}",
                'expected_code' => 'FORBIDDEN_OPERATION',
                'category' => 'payment-wording',
            ],
            5 => [
                'name' => "refund-order-{$suffix}",
                'expected_code' => 'FORBIDDEN_OPERATION',
                'category' => 'refund-wording',
            ],
            6 => [
                'name' => "stripe-charge-{$suffix}",
                'expected_code' => 'FORBIDDEN_OPERATION',
                'category' => 'stripe-wording',
            ],
            7 => [
                'name' => "cancel-order-{$suffix}",
                'expected_code' => 'FORBIDDEN_OPERATION',
                'category' => 'cancel-wording',
            ],
            default => [
                'name' => "unapproved-tool-{$suffix}",
                'expected_code' => 'UNKNOWN_TOOL',
                'category' => 'arbitrary-name',
            ],
        };
    }
}

final class ToolAllowlistTrackingTool implements ToolContract
{
    public int $calls = 0;

    public function __construct(private readonly string $toolName)
    {
    }

    public function name(): string
    {
        return $this->toolName;
    }

    /** @return array<string, mixed> */
    public function declaration(): array
    {
        return [
            'name' => $this->toolName,
            'parameters' => [
                'type' => 'object',
                'properties' => [],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'handled' => ['type' => 'boolean'],
            ],
        ];
    }

    /** @return array<string, string|array> */
    public function rules(): array
    {
        return [];
    }

    public function requiresAuth(): bool
    {
        return false;
    }

    /** @param array<string, mixed> $args */
    public function handle(array $args, ToolContext $ctx): ToolResult
    {
        $this->calls++;

        return ToolResult::ok(['handled' => true]);
    }
}
