<?php
namespace App\Services;
use App\Models\{Report,StatusHistory,Assignment,User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class ReportWorkflow
{
    public function __construct(private NoticeService $notices) {}
    public function execute(callable $callback): mixed
    {
        if(config('swm.mongo_transactions')) return DB::connection('mongodb')->transaction($callback);
        return $callback(); // Development standalone MongoDB; set transactions=true in a replica set for production.
    }
    public function transition(Report $report,string $next,string $reason,?User $actor=null,bool $notify=true): void
    {
        $prior=(string)$report->current_status;
        if(!\App\Support\ReportStateMachine::canTransition($prior,$next))throw ValidationException::withMessages(['status'=>'Invalid transition: '.$prior.' → '.$next]);
        // Atomic compare-and-swap prevents two staff users from overwriting a stale status.
        $count=Report::query()->whereKey($report->getKey())->where('current_status',$prior)->update(['current_status'=>$next]);
        if(!$count)throw ValidationException::withMessages(['status'=>'This report was already updated. Refresh and try again.']);
        StatusHistory::create(['report_id'=>(string)$report->id,'changed_by'=>$actor?(string)$actor->id:null,
          'actor_type'=>$actor?'user':'system','from_status'=>$prior,'to_status'=>$next,
          'reason'=>$reason,'changed_at'=>now()]);
        $report->current_status=$next;
        if($notify)$this->notices->report($report,$next);
    }
}
