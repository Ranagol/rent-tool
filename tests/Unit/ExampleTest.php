<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testBasicMathWorks(): void
    {
        $result = 2 + 2;
        $this->assertSame(4, $result);
    }

    public function testStringComparison(): void
    {
        $actual = 'Hello World';
        $this->assertStringContainsString('World', $actual);
    }
}
