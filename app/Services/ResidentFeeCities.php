<?php
namespace App\Services;

use App\Models\{User,Household,Zone,GarbageFeeCharge};

final class ResidentFeeCities
{
    /** Payment instructions are city-scoped; viewing them never grants billing access. */
    public static function ids(User $resident):array
    {
        if($resident->role_code!=='resident')return [];

        $households=Household::where('resident_user_id',(string)$resident->id)->get();
        $active=$households->filter(fn($household)=>$household->is_active && !$household->archived_at);
        $zoneIds=$active->pluck('zone_id')->filter()->map(fn($id)=>(string)$id)->unique()->all();
        $current=$zoneIds?Zone::findMany($zoneIds)->pluck('fee_city_id')
            ->filter()->map(fn($id)=>(string)$id)->all():[];

        // A registered resident may read their own zone's payment instructions
        // before a staff member links their household or generates a bill.
        if(!$current && $resident->zone_id){
            $zone=Zone::find((string)$resident->zone_id);
            if($zone && $zone->is_active && $zone->fee_city_id){
                $current[]=(string)$zone->fee_city_id;
            }
        }

        // Older bills remain tied to the city that originally issued them.
        $householdIds=$households->map(fn($household)=>(string)$household->id)->all();
        $historic=$householdIds?GarbageFeeCharge::whereIn('household_id',$householdIds)
            ->pluck('city_id')->filter()->map(fn($id)=>(string)$id)->all():[];

        return array_values(array_unique(array_merge($current,$historic)));
    }
}
