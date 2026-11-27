<?php
namespace App\Http\Controllers;

use App\Models\{FeeCity,Zone,Household,GarbageFeeCharge};
use App\Services\FeeLedger;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class GarbageFeeHistoryController extends Controller
{
    public function index(Request $request)
    {
        $role=$request->user()->role_code;
        abort_unless(in_array($role,['superadmin','admin','coordinator','resident'],true),403);

        $data=$request->validate([
            'status'=>['nullable','in:Unpaid,Partial,Paid'],
            'city_id'=>['nullable','string','max:80'],
            'zone_id'=>['nullable','string','max:80'],
            'household_id'=>['nullable','string','max:80'],
            'from'=>['nullable','date_format:Y-m'],
            'to'=>['nullable','date_format:Y-m'],
            'q'=>['nullable','string','max:100'],
        ]);
        $query=GarbageFeeCharge::with(['household','zone','city','payments']);
        if($role==='resident'){
            $householdIds=Household::where('resident_user_id',(string)$request->user()->id)
                ->pluck('id')->map(fn($id)=>(string)$id)->all();
            $query->whereIn('household_id',$householdIds);
        }elseif($role==='coordinator'){
            abort_unless($request->user()->zone_id,403);
            $query->where('zone_id',(string)$request->user()->zone_id);
        }
        if($role!=='resident'){
            foreach(['city_id','zone_id','household_id'] as $key){
                if(!empty($data[$key]))$query->where($key,$data[$key]);
            }
        }
        if(!empty($data['from']))$query->where('billing_month','>=',$data['from']);
        if(!empty($data['to']))$query->where('billing_month','<=',$data['to']);

        $filterStatus=$data['status']??'';
        $search=mb_strtolower(trim($data['q']??''));
        $rows=$query->get()->map(function($charge){
            $paid=FeeLedger::paid($charge);
            return [
                'charge'=>$charge,
                'paid'=>$paid,
                'balance'=>max(0,(int)$charge->amount_centavos-$paid),
                'status'=>FeeLedger::status($charge),
            ];
        })->filter(function($row)use($filterStatus,$search){
            if($filterStatus && $row['status']!==$filterStatus)return false;
            return $search==='' || str_contains(
                mb_strtolower((string)($row['charge']->household?->reference??'')),$search
            );
        })->all();

        usort($rows,static function($a,$b){
            $byMonth=strcmp((string)$b['charge']->billing_month,(string)$a['charge']->billing_month);
            if($byMonth!==0)return $byMonth;
            return strnatcasecmp((string)($a['charge']->household?->reference??''),
                (string)($b['charge']->household?->reference??''));
        });

        $summary=[];
        foreach($rows as $row){
            $charge=$row['charge'];
            $householdId=(string)$charge->household_id;
            if(!isset($summary[$householdId])){
                $summary[$householdId]=[
                    'household'=>$charge->household,
                    'city'=>$charge->city,
                    'zone'=>$charge->zone,
                    'paid_months'=>0,'unpaid_months'=>0,'partial_months'=>0,
                    'outstanding'=>0,'total_paid'=>0,'months'=>[],
                ];
            }
            $summary[$householdId][strtolower($row['status']).'_months']++;
            $summary[$householdId]['outstanding']+=$row['balance'];
            $summary[$householdId]['total_paid']+=$row['paid'];
            $summary[$householdId]['months'][]=$charge->billing_month;
        }
        $summary=array_values($summary);
        usort($summary,static fn($a,$b)=>($b['outstanding']<=>$a['outstanding'])
            ?:strnatcasecmp((string)($a['household']?->reference??''),(string)($b['household']?->reference??'')));
        $totalBalance=array_sum(array_column($rows,'balance'));
        $paidCount=count(array_filter($rows,fn($row)=>$row['status']==='Paid'));
        $unpaidCount=count(array_filter($rows,fn($row)=>$row['status']==='Unpaid'));
        $partialCount=count(array_filter($rows,fn($row)=>$row['status']==='Partial'));
        $page=max(1,(int)LengthAwarePaginator::resolveCurrentPage());
        $months=new LengthAwarePaginator(
            array_slice($rows,($page-1)*20,20),count($rows),20,$page,
            ['path'=>$request->url(),'query'=>$request->query()]
        );

        $cities=in_array($role,['superadmin','admin'],true)?FeeCity::orderBy('name')->get():collect();
        $zones=in_array($role,['superadmin','admin'],true)?Zone::orderBy('name')->get():collect();
        return view('fees.history',compact('months','summary','role','cities','zones',
            'totalBalance','paidCount','unpaidCount','partialCount','filterStatus'));
    }
}
