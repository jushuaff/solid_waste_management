<?php
namespace App\Http\Controllers;
use App\Models\{User,Role,Zone};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller
{
    public function showLogin(){return view('auth.login');}
    public function login(Request $request)
    {
        $data=$request->validate(['email'=>'required|email','password'=>'required|string']);
        $data['email']=strtolower(trim($data['email']));
        if(!Auth::attempt([...$data,'is_active'=>true],$request->boolean('remember'))){
            throw ValidationException::withMessages(['email'=>'The credentials are incorrect or the account is inactive.']);
        }
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }
    public function showRegister(){return view('auth.register',['zones'=>Zone::where('is_active',true)->get()]);}
    public function register(Request $request)
    {
        $data=$request->validate(['name'=>'required|string|max:150','email'=>'required|email|max:190',
          'password'=>'required|string|min:12|confirmed','zone_id'=>'required|string','privacy'=>'accepted']);
        $data['email']=strtolower(trim($data['email']));
        if(User::where('email',$data['email'])->exists()) throw ValidationException::withMessages(['email'=>'Email is already registered.']);
        abort_unless(Zone::where('is_active',true)->find($data['zone_id']),422,'Please select a valid collection zone.');
        $role=Role::where('code','resident')->firstOrFail();
        $user=User::create(['name'=>$data['name'],'email'=>$data['email'],'password'=>Hash::make($data['password']),
          'role_id'=>(string)$role->id,'zone_id'=>$data['zone_id'],'is_active'=>true]);
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('dashboard');
    }
    public function logout(Request $request)
    {
        Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
