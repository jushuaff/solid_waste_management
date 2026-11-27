<?php
namespace App\Models;

class IncidentMedia extends Document
{
    protected $table = 'incident_media';
    protected $casts = [
        'size_bytes' => 'integer',
        'removed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function report(){ return $this->belongsTo(Report::class,'report_id'); }
    public function uploader(){ return $this->belongsTo(User::class,'uploaded_by'); }
}
