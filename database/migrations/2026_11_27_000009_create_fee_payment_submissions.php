<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration {
    protected $connection='mongodb';
    public function up():void
    {
        $db=Schema::connection('mongodb');
        if(!$db->hasCollection('fee_payment_submissions')){
            $db->create('fee_payment_submissions',function(Blueprint $collection){
                $collection->unique('submission_code');
                $collection->index('charge_id');
                $collection->index('resident_user_id');
                $collection->index('status');
                $collection->index('city_id');
                $collection->index('created_at');
            });
        }
    }

    // Preserve submitted receipts, decisions, and evidence on rollback.
    public function down():void {}
};
