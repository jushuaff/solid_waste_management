<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration {
    protected $connection='mongodb';
    public function up():void
    {
        $db=Schema::connection('mongodb');
        if(!$db->hasCollection('fee_providers')){
            $db->create('fee_providers',function(Blueprint $collection){
                $collection->unique('name_key');
                $collection->index('type');
                $collection->index('is_active');
            });
        }
    }
    // Do not remove saved institutions on rollback.
    public function down():void {}
};
