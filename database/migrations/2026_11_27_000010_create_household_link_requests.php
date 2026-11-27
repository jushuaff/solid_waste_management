<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration {
    protected $connection='mongodb';
    public function up():void
    {
        $db=Schema::connection('mongodb');
        if(!$db->hasCollection('household_link_requests')){
            $db->create('household_link_requests',function(Blueprint $collection){
                $collection->index(['resident_user_id','status']);
                $collection->index('zone_id');
                $collection->index('status');
            });
        }
    }
    public function down():void {}
};
