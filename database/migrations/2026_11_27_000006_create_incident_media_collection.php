<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration {
    protected $connection = 'mongodb';

    public function up(): void
    {
        $schema = Schema::connection('mongodb');
        if (! $schema->hasCollection('incident_media')) {
            $schema->create('incident_media', function (Blueprint $collection) {
                $collection->index('report_id');
                $collection->index('uploaded_by');
                $collection->index('removed_at');
            });
        }
    }

    // Non-destructive rollback: never erase resident-supplied evidence.
    public function down(): void {}
};
