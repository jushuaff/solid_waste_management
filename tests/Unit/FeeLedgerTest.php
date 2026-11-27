<?php
namespace Tests\Unit;

use App\Services\FeeLedger;
use PHPUnit\Framework\TestCase;

final class FeeLedgerTest extends TestCase
{
    public function test_converts_pesos_to_integer_centavos():void
    {
        $this->assertSame(7500,FeeLedger::centavos('75'));
        $this->assertSame(7501,FeeLedger::centavos('75.01'));
        $this->assertSame(7505,FeeLedger::centavos('75.05'));
        $this->assertSame('₱75.05',FeeLedger::money(7505));
    }

    public function test_rejects_unexpected_amount_formats():void
    {
        foreach(['-1','1.234','1e3','100,000','nan',''] as $invalid){
            try {
                FeeLedger::centavos($invalid);
                $this->fail('Unexpectedly accepted amount: '.$invalid);
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString('valid amount',$exception->getMessage());
            }
        }
    }

    public function test_rejects_amount_exceeding_configured_input_precision():void
    {
        $this->expectException(\InvalidArgumentException::class);
        FeeLedger::centavos('10000000.00');
    }
}
