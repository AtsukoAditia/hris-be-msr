<?php

namespace Tests\Unit;

use App\Models\Position;
use App\Services\EmployeePositionResolver;
use PHPUnit\Framework\TestCase;

class EmployeePositionResolverTest extends TestCase
{
    public function test_master_id_payload_uses_position_code(): void
    {
        $resolver = new EmployeePositionResolver;
        $position = new Position(['code' => 'SOFTWARE-ENGINEER']);

        $value = $resolver->legacyValue($position, ['position_id' => 10]);

        $this->assertSame('SOFTWARE-ENGINEER', $value);
    }

    public function test_transition_payload_is_trimmed(): void
    {
        $resolver = new EmployeePositionResolver;
        $position = new Position(['code' => 'HR-STAFF']);

        $value = $resolver->legacyValue($position, ['position' => ' HR Staff ']);

        $this->assertSame('HR Staff', $value);
    }
}
