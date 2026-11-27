<?php
namespace App\Http\Controllers;

use App\Models\{FeeCity,FeeRate,Zone};
use App\Services\FeeLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FeeSettingsController extends Controller
{
    private function authorizeAdmin(Request $request):void
    {
        abort_unless($request->user()->role_code==='superadmin',403);
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $cities=FeeCity::with(['rates','zones'])->orderBy('name')->get();
        $zones=Zone::orderBy('name')->get();
        return view('fees.settings',compact('cities','zones'));
    }

    public function storeCity(Request $request)
    {
        $this->authorizeAdmin($request);
        $data=$request->validate([
            'name'=>['required','string','max:120'],
            'region'=>['required','string','max:120'],
        ]);
        $name=trim($data['name']);
        $region=trim($data['region']);
        $key=Str::slug($name).'-'.Str::slug($region);
        if(FeeCity::where('city_key',$key)->exists()){
            throw ValidationException::withMessages(['name'=>'This city is already registered for the selected region.']);
        }
        FeeCity::create([
            'city_key'=>$key,'name'=>$name,'region'=>$region,
            'created_by'=>(string)$request->user()->id,
        ]);
        return back()->with('status','City added. Set its household rate and assign the applicable collection zones.');
    }

    public function storeRate(Request $request,string $city)
    {
        $this->authorizeAdmin($request);
        $city=FeeCity::findOrFail($city);
        $data=$request->validate([
            'effective_month'=>['required','date_format:Y-m'],
            'amount'=>['required','regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'ordinance_reference'=>['required','string','max:250'],
            'notes'=>['nullable','string','max:800'],
        ]);
        if(FeeRate::where('city_id',(string)$city->id)
            ->where('effective_month',$data['effective_month'])->exists()){
            throw ValidationException::withMessages(['effective_month'=>'A rate already exists for that effective month. Choose a new effective month rather than overwriting history.']);
        }
        $amount=FeeLedger::centavos($data['amount']);
        if($amount<1){
            throw ValidationException::withMessages(['amount'=>'The monthly rate must be greater than zero.']);
        }

        FeeRate::create([
            'city_id'=>(string)$city->id,
            'effective_month'=>$data['effective_month'],
            'amount_centavos'=>$amount,
            'ordinance_reference'=>trim($data['ordinance_reference']),
            'notes'=>$data['notes']??null,
            'created_by'=>(string)$request->user()->id,
        ]);
        return back()->with('status','Household rate saved. Existing monthly charges retain their original rates.');
    }

    public function assignZones(Request $request,string $city)
    {
        $this->authorizeAdmin($request);
        $city=FeeCity::findOrFail($city);
        $data=$request->validate([
            'zone_ids'=>['sometimes','array'],
            'zone_ids.*'=>['required','string','distinct'],
        ]);
        $selected=array_values($data['zone_ids']??[]);
        $selectedZones=[];
        foreach($selected as $id){
            $zone=Zone::find($id);
            if(!$zone){
                throw ValidationException::withMessages(['zone_ids'=>'An invalid collection zone was selected.']);
            }
            if($zone->fee_city_id && (string)$zone->fee_city_id!==(string)$city->id){
                throw ValidationException::withMessages(['zone_ids'=>$zone->name.' belongs to another city. Unassign it from that city first.']);
            }
            $selectedZones[(string)$zone->id]=$zone;
        }

        foreach(Zone::where('fee_city_id',(string)$city->id)->get() as $zone){
            if(!isset($selectedZones[(string)$zone->id])){
                $zone->fee_city_id=null;
                $zone->save();
            }
        }
        foreach($selectedZones as $zone){
            if((string)($zone->fee_city_id??'')!==(string)$city->id){
                $zone->fee_city_id=(string)$city->id;
                $zone->save();
            }
        }
        return back()->with('status','Collection-zone city assignments updated. Existing charges were not changed.');
    }
}
