<?php

namespace Tests\Unit\Services\Chatbot;

use App\Services\Chatbot\Tools\CreateDraftOrderTool;
use PHPUnit\Framework\TestCase;

final class CreateDraftOrderToolTest extends TestCase
{
    /** Validates: Requirements 4.1, 4.2, 5.2, 6.7 */
    public function test_it_declares_only_customer_item_identifiers_and_quantities(): void
    {
        $tool = new CreateDraftOrderTool();
        $parameters = $tool->declaration()['parameters'];
        $itemProperties = $parameters['properties']['items']['items']['properties'];

        $this->assertSame('create_draft_order', $tool->name());
        $this->assertTrue($tool->requiresAuth());
        $this->assertSame([
            'items' => 'required|array|min:1|max:20',
            'items.*.product_identifier' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1|max:99',
        ], $tool->rules());
        $this->assertSame(['items'], $parameters['required']);
        $this->assertSame(['product_identifier', 'quantity'], $parameters['properties']['items']['items']['required']);
        $this->assertSame(['product_identifier', 'quantity'], array_keys($itemProperties));
    }
}
