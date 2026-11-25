<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration {
    protected $connection='mongodb';
    public function up():void {
        $db=Schema::connection('mongodb');
        // Never overwrite existing collections. No existing users collection is modified.
        if(!$db->hasCollection('households')) {
            $db->create('households',function(Blueprint $schema) {
                $schema->index('zone_id');
                $schema->index('reference');
            });
        }
        if(!$db->hasCollection('pickup_inspections')) {
            $db->create('pickup_inspections',function(Blueprint $schema) {
                $schema->index(['zone_id'=>1,'inspected_at'=>-1]);
                $schema->index('household_id');
                $schema->index('inspector_user_id');
            });
        }
        if(!$db->hasCollection('mrf_weigh_ins')) {
            $db->create('mrf_weigh_ins',function(Blueprint $schema) {
                $schema->index(['zone_id'=>1,'measured_at'=>-1]);
                $schema->index('category_id');
            });
        }
    }
    public function down():void {
        // Preserve recorded operational and citizen data.
    }
};
