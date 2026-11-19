<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
class ProfileController extends Controller
{
    public function edit(Request $request){return view('profile.edit',['user'=>$request->user()]);}
    public function update(Request $request)
    {
        $data=$request->validate(['name'=>'required|string|max:150','contact_number'=>'nullable|string|max:35']);
        $request->user()->update($data);return back()->with('status','Profile updated.');
    }
    public function password(Request $request)
    {
        $data=$request->validate(['current_password'=>'required|string','password'=>'required|string|min:12|confirmed']);
        if(!Hash::check($data['current_password'],$request->user()->password))throw ValidationException::withMessages(['current_password'=>'Current password is incorrect.']);
        $request->user()->update(['password'=>Hash::make($data['password'])]);
        $request->session()->regenerate();return back()->with('status','Password updated.');
    }
}
