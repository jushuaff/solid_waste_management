<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;
return new class extends Migration {
    protected $connection = 'mongodb';
    public function up(): void
    {
        $db = Schema::connection('mongodb');
        $definitions = [
            'reports' => [['tracking_id'], ['resident_id'], ['zone_id', 'incident_date'], ['current_status', 'incident_date']],
            'assignments' => [['report_id'], ['crew_user_id']],
            'status_histories' => [['report_id', 'changed_at']],
            'notifications' => [['recipient_user_id', 'is_read']]
        ];
        foreach ($definitions as $collection => $indexes) {
            // Never drop or overwrite existing collections (especially users).
            if (! $db->hasCollection($collection)) {
                $db->create($collection, function (Blueprint $schema) use ($collection, $indexes) {
                    foreach ($indexes as $index) {
                        if (in_array($collection, ['roles','users','reports','password_reset_tokens'], true)
                            && in_array($index[0], ['code','email','tracking_id'], true)) {
                            $schema->unique($index[0]);
                        } else {
                            $schema->index(count($index) > 1 ? array_fill_keys($index, 1) : $index[0]);
                        }
                    }
                });
            }
        }
    }
    public function down(): void
    {
        // Deliberately non-destructive for systems potentially storing citizen data.
    }
};
