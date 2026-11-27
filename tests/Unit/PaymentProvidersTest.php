<?php
namespace Tests\Unit;

use App\Support\PaymentProviders;
use PHPUnit\Framework\TestCase;

final class PaymentProvidersTest extends TestCase
{
    public function test_starter_catalog_contains_both_e_wallets_and_banks():void
    {
        $this->assertContains('GCash',PaymentProviders::DEFAULTS['wallet']);
        $this->assertContains('Maya Wallet',PaymentProviders::DEFAULTS['wallet']);
        $this->assertContains('BDO Unibank',PaymentProviders::DEFAULTS['bank']);
        $this->assertContains('Bank of the Philippine Islands (BPI)',PaymentProviders::DEFAULTS['bank']);
    }

    public function test_starter_catalog_does_not_duplicate_institution_names():void
    {
        $all=array_merge(...array_values(PaymentProviders::DEFAULTS));
        $normalized=array_map('mb_strtolower',$all);
        $this->assertCount(count($normalized),array_unique($normalized));
    }
}
