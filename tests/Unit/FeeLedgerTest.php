<?php
namespace Tests\Unit;

use App\Services\FeeLedger;
use App\Models\{GarbageFeeCharge,GarbageFeePayment};
use Illuminate\Database\Eloquent\Collection;
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

    public function test_unverified_receipt_never_counts_as_paid_in_ledger():void
    {
        // Pending receipts live in a separate submissions collection. Until an
        // authorized reviewer records a verified payment, the bill is unpaid.
        $charge=new GarbageFeeCharge(['amount_centavos'=>7500]);
        $charge->setRelation('payments',new Collection());
        $this->assertSame(0,FeeLedger::paid($charge));
        $this->assertSame(7500,FeeLedger::balance($charge));
        $this->assertSame('Unpaid',FeeLedger::status($charge));
    }

    public function test_only_recorded_payments_reduce_outstanding_balance():void
    {
        $charge=new GarbageFeeCharge(['amount_centavos'=>7500]);
        $charge->setRelation('payments',new Collection([
            new GarbageFeePayment(['amount_centavos'=>2500,'status'=>'recorded']),
            new GarbageFeePayment(['amount_centavos'=>5000,'status'=>'void']),
        ]));
        $this->assertSame(2500,FeeLedger::paid($charge));
        $this->assertSame(5000,FeeLedger::balance($charge));
        $this->assertSame('Partial',FeeLedger::status($charge));
    }

    public function test_confirming_full_amount_changes_bill_status_to_paid():void
    {
        $charge=new GarbageFeeCharge(['amount_centavos'=>7500]);
        $charge->setRelation('payments',new Collection([
            new GarbageFeePayment(['amount_centavos'=>7500,'status'=>'recorded']),
        ]));
        $this->assertSame(7500,FeeLedger::paid($charge));
        $this->assertSame(0,FeeLedger::balance($charge));
        $this->assertSame('Paid',FeeLedger::status($charge));
    }

}
