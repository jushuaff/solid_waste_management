<?php
namespace App\Services;

use App\Models\{User,Zone,PickupInspection,MrfWeighIn};
use Carbon\CarbonInterface;

class LinisMetrics
{
    /** Results are only as complete as the recorded inspections/weigh-ins, not an official diversion certificate. */
    public function forPeriod(User $viewer, CarbonInterface $from, CarbonInterface $to):array
    {
        $zones=Zone::query()->where('is_active',true)->orderBy('name');
        if(in_array($viewer->role_code,['eco_aide','crew'],true) ||
           ($viewer->role_code==='coordinator' && $viewer->zone_id)){
            abort_unless($viewer->zone_id,403,'Assign a zone to this staff account.');
            $zones->whereKey((string)$viewer->zone_id);
        }
        $zoneRows=$zones->get();
        $zoneIds=$zoneRows->map(fn($z)=>(string)$z->id)->all();
        $inspections=$zoneIds?PickupInspection::whereIn('zone_id',$zoneIds)->whereNull('archived_at')
            ->where('inspected_at','>=',$from)->where('inspected_at','<=',$to)->get():collect();
        $weights=$zoneIds?MrfWeighIn::whereIn('zone_id',$zoneIds)->whereNull('archived_at')
            ->where('measured_at','>=',$from)->where('measured_at','<=',$to)->get():collect();
        $rows=[];$insTotal=0;$insPass=0;$kgTotal=0.0;$kgDiverted=0.0;
        foreach($zoneRows as $zone){
            $id=(string)$zone->id;
            $part=$inspections->filter(fn($x)=>(string)$x->zone_id===$id);
            $mrf=$weights->filter(fn($x)=>(string)$x->zone_id===$id);
            $count=$part->count();$passes=$part->filter(fn($x)=>(bool)$x->segregated)->count();
            $total=(float)$mrf->sum('weight_kg');$diverted=(float)$mrf->sum('diverted_kg');
            $compliance=$count>0?round(100*$passes/$count,2):null;
            $diversion=$total>0?round(100*$diverted/$total,2):null;
            $color=$compliance===null?'grey':($compliance>=80?'green':($compliance>=60?'yellow':'red'));
            $rows[]=['zone'=>$zone,'inspections'=>$count,'segregated'=>$passes,'compliance'=>$compliance,
                'mrf_kg'=>round($total,3),'diverted_kg'=>round($diverted,3),
                'diversion'=>$diversion,'color'=>$color];
            $insTotal+=$count;$insPass+=$passes;$kgTotal+=$total;$kgDiverted+=$diverted;
        }
        return ['zones'=>$rows,
            'inspections'=>$insTotal,
            'segregated'=>$insPass,
            'compliance'=>$insTotal>0?round(100*$insPass/$insTotal,2):null,
            'mrf_kg'=>round($kgTotal,3),
            'diverted_kg'=>round($kgDiverted,3),
            'diversion'=>$kgTotal>0?round(100*$kgDiverted/$kgTotal,2):null,
            'from'=>$from,'to'=>$to];
    }
}
