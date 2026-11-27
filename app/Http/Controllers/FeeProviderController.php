<?php
namespace App\Http\Controllers;

use App\Models\FeeProvider;
use App\Support\PaymentProviders;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FeeProviderController extends Controller
{
    public function store(Request $request)
    {
        abort_unless($request->user()->role_code==='superadmin',403);
        $data=$request->validate([
            'name'=>['required','string','min:2','max:100'],
            'type'=>['required','in:wallet,bank'],
        ]);
        $name=trim($data['name']);
        if(PaymentProviders::isKnown($name) ||
            FeeProvider::where('name_key',Str::lower($name))->exists()){
            throw ValidationException::withMessages(['name'=>'This provider already exists in the dropdown.']);
        }
        FeeProvider::create([
            'name'=>$name,'name_key'=>Str::lower($name),
            'type'=>$data['type'],'is_active'=>true,
            'created_by'=>(string)$request->user()->id,
        ]);
        return redirect()->route('fees.accounts.index')
            ->with('status','Payment provider added to the receiving-account dropdown.');
    }

    public function toggle(Request $request,string $provider)
    {
        abort_unless($request->user()->role_code==='superadmin',403);
        $entry=FeeProvider::findOrFail($provider);
        $entry->is_active=!$entry->is_active;
        $entry->updated_by=(string)$request->user()->id;
        $entry->save();
        return redirect()->route('fees.accounts.index')->with('status',
            $entry->is_active?'Provider enabled.':'Provider hidden from new receiving-account choices. Existing accounts remain unchanged.');
    }
}
