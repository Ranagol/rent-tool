<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Tool;
use App\Entity\ToolStatus;
use PHPUnit\Framework\TestCase;

class ToolTest extends TestCase
{
    public function testNewToolIsAvailableByDefault(): void
    {
        $this->assertSame(ToolStatus::Available, (new Tool())->getStatus());
    }

    public function testToolStoresItsFields(): void
    {
        $tool = (new Tool())
            ->setName('Angle grinder')
            ->setSerialNumber('AG-001')
            ->setImageUrl('https://example.com/ag.jpg')
            ->setDailyRentCost('8.00')
            ->setStatus(ToolStatus::InRepair);

        $this->assertSame('Angle grinder', $tool->getName());
        $this->assertSame('AG-001', $tool->getSerialNumber());
        $this->assertSame('8.00', $tool->getDailyRentCost());
        $this->assertSame(ToolStatus::InRepair, $tool->getStatus());
    }

    public function testRentedIsNotAManualStatus(): void
    {
        $this->assertNotContains(ToolStatus::Rented, ToolStatus::manual());
        $this->assertCount(3, ToolStatus::manual());
    }
}
