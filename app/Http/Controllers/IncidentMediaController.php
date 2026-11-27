<?php
namespace App\Http\Controllers;

use App\Models\{Report,IncidentMedia};
use App\Services\IncidentAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IncidentMediaController extends Controller
{
    /** Authenticated, range-aware image/video response for authorized case participants only. */
    public function show(Request $request, string $report, string $media, IncidentAccess $access)
    {
        $case=Report::findOrFail($report);
        $access->authorize($request,$case);
        $item=IncidentMedia::where('report_id',(string)$case->id)
            ->whereNull('removed_at')->findOrFail($media);

        $disk=Storage::disk('local');
        abort_unless($disk->exists($item->storage_path),404);
        $type=$item->mime_type;
        abort_unless(in_array($type,['image/jpeg','image/png','image/webp','video/mp4','video/webm','video/quicktime'],true),404);

        $headers=[
            'Content-Type'=>$type,
            'Cache-Control'=>'private, no-store, max-age=0',
            'Pragma'=>'no-cache',
            'X-Content-Type-Options'=>'nosniff',
            'Content-Security-Policy'=>"default-src 'none'; sandbox",
        ];
        if($request->boolean('download')) {
            return $disk->download($item->storage_path,$item->original_name,$headers);
        }
        $headers['Content-Disposition']='inline';
        return response()->file($disk->path($item->storage_path),$headers);
    }

    /** Residents may withdraw their own attachments only before staff action. */
    public function destroy(Request $request,string $report,string $media,IncidentAccess $access)
    {
        $case=Report::findOrFail($report);
        $access->authorize($request,$case);
        abort_unless($request->user()->role_code==='resident'
            && (string)$case->resident_id===(string)$request->user()->id
            && $case->current_status==='Under Review',403,
            'Evidence can only be removed while the report is awaiting review.');

        $item=IncidentMedia::where('report_id',(string)$case->id)
            ->whereNull('removed_at')->findOrFail($media);
        $item->removed_at=now();
        $item->removed_by=(string)$request->user()->id;
        $item->save();
        Storage::disk('local')->delete($item->storage_path);

        return back()->with('status','Attachment removed from your incident report.');
    }
}
