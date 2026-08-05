<?php

namespace Tests\Feature\Api\Santi;

use Tests\TestCase;

final class ConfigSmokeTest extends TestCase
{
    /** Validates: Requirements 6.9, 8.2 */
    public function test_santi_configuration_loads_with_expected_defaults(): void
    {
        $this->assertSame([
            'function_calling_enabled' => true,
            'max_tool_rounds' => 3,
            'max_tool_calls' => 6,
            'draft_ttl_hours' => 48,
            'draft_max_per_hour' => 10,
            'draft_max_subtotal_clp' => 5000000,
        ], config('santi'));
    }

    /** Validates: Requirements 6.9, 8.2 */
    public function test_gemini_timeout_configuration_exists(): void
    {
        $this->assertNotNull(config('services.gemini.timeout'));
    }
}
