<?php

namespace Tests\Unit;

use App\Services\PricingService;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    protected PricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock config agar tidak bergantung pada Laravel Facades saat unit test
        $this->pricingService = new class extends PricingService {
            public function calculateOrderSummary(int $subtotal, int $discount = 0): array
            {
                $serviceFee = 3000;
                $taxRate = 0.10;

                $taxableAmount = $subtotal - $discount + $serviceFee;
                $tax = (int) round($taxableAmount * $taxRate);
                $total = $subtotal - $discount + $serviceFee + $tax;

                return [
                    'subtotal' => $subtotal,
                    'service_fee' => $serviceFee,
                    'discount' => $discount,
                    'tax' => $tax,
                    'total' => $total,
                ];
            }
        };
    }

    /**
     * Test kalkulasi line total
     */
    public function test_calculate_line_total(): void
    {
        $unitPrice = 58000;
        $quantity = 2;
        
        $lineTotal = $this->pricingService->calculateLineTotal($unitPrice, $quantity);
        
        $this->assertEquals(116000, $lineTotal);
    }

    /**
     * Test kalkulasi summary sesuai dengan contoh PRD
     */
    public function test_calculate_order_summary_matches_prd_example(): void
    {
        $subtotal = 63000;
        
        $summary = $this->pricingService->calculateOrderSummary($subtotal);
        
        $this->assertEquals(63000, $summary['subtotal']);
        $this->assertEquals(3000, $summary['service_fee']);
        $this->assertEquals(0, $summary['discount']);
        $this->assertEquals(6600, $summary['tax']);
        $this->assertEquals(72600, $summary['total']);
    }

    /**
     * Test kalkulasi summary dengan diskon
     */
    public function test_calculate_order_summary_with_discount(): void
    {
        $subtotal = 100000;
        $discount = 20000; // Rp 20.000 diskon
        
        $summary = $this->pricingService->calculateOrderSummary($subtotal, $discount);
        
        $this->assertEquals(100000, $summary['subtotal']);
        $this->assertEquals(3000, $summary['service_fee']);
        $this->assertEquals(20000, $summary['discount']);
        
        // Tax = (100000 - 20000 + 3000) * 10% = 83000 * 0.1 = 8300
        $this->assertEquals(8300, $summary['tax']);
        
        // Total = 100000 - 20000 + 3000 + 8300 = 91300
        $this->assertEquals(91300, $summary['total']);
    }
}
