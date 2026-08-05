<?php

namespace Tests\Unit\Services\Chatbot;

use App\Services\Chatbot\Tools\CheckStockTool;
use PHPUnit\Framework\TestCase;

final class CheckStockToolTest extends TestCase
{
    /** Validates: Requirements 2.1, 2.2, 5.1 */
    public function test_it_declares_the_bounded_public_stock_contract(): void
    {
        $tool = new CheckStockTool();

        $this->assertSame('check_stock', $tool->name());
        $this->assertFalse($tool->requiresAuth());
        $this->assertSame(['product_identifier' => 'required|string|max:255'], $tool->rules());
        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'product_identifier' => [
                    'type' => 'string',
                    'description' => 'ID numérico del producto o su slug exacto.',
                ],
            ],
            'required' => ['product_identifier'],
        ], $tool->declaration()['parameters']);
    }
}
