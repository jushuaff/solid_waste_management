<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration {
    protected $connection='mongodb';
    public function up():void
    {
        $db=Schema::connection('mongodb');
        if(!$db->hasCollection('fee_cities')){
            $db->create('fee_cities',function(Blueprint $collection){
                $collection->unique('city_key');
            });
        }
        if(!$db->hasCollection('fee_rates')){
            $db->create('fee_rates',function(Blueprint $collection){
                $collection->unique(['city_id','effective_month']);
                $collection->index('effective_month');
            });
        }
        if(!$db->hasCollection('garbage_fee_charges')){
            $db->create('garbage_fee_charges',function(Blueprint $collection){
                $collection->unique(['household_id','billing_month']);
                $collection->index('city_id');
                $collection->index('zone_id');
                $collection->index('billing_month');
            });
        }
        if(!$db->hasCollection('garbage_fee_payments')){
            $db->create('garbage_fee_payments',function(Blueprint $collection){
                $collection->unique('receipt_code');
                $collection->index('charge_id');
                $collection->index('recorded_by');
            });
        }
    }

    // Financial history is retained; rollback never drops fee records.
    public function down():void {}
};
