<?php
namespace App\Services;

use App\Models\Report;
use Illuminate\Http\Request;

class IncidentAccess
{
    /** Same ownership and role checks for report details and private image/video bytes. */
    public function authorize(Request $request, Report $report): void
    {
        $role = $request->user()->role_code;
        $id = (string)$request->user()->id;

        if (in_array($role,['superadmin','admin'],true)) return;
        if ($role === 'coordinator') {
            abort_unless($request->user()->zone_id
                && (string)$request->user()->zone_id === (string)$report->zone_id,404);
            return;
        }
        if ($role === 'crew') {
            abort_unless((string)$report->assigned_crew_id === $id,404);
            return;
        }
        if ($role === 'resident') {
            abort_unless((string)$report->resident_id === $id,404);
            return;
        }
        abort(403);
    }
}
