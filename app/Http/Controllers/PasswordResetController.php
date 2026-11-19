<?php
namespace App\Http\Controllers;
use App\Mail\ResetPasswordMail;
use App\Models\{User,PasswordResetToken};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class PasswordResetController extends Controller
{
    public function requestForm(){return view('auth.forgot');}
    public function requestLink(Request $request)
    {
        $email=strtolower(trim($request->validate(['email'=>'required|email'])['email']));
        $user=User::where('email',$email)->where('is_active',true)->first();
        if($user){
            $token=Str::random(64);
            PasswordResetToken::where('email',$email)->delete();
            PasswordResetToken::create(['email'=>$email,'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addMinutes(60)]);
            Mail::to($user->email)->send(new ResetPasswordMail(route('password.reset',['token'=>$token,'email'=>$email])));
        }
        return back()->with('status','If this email is registered, a password reset link has been sent.');
    }
    public function resetForm(Request $request,string $token){return view('auth.reset',['token'=>$token,'email'=>$request->query('email','')]);}
    public function reset(Request $request)
    {
        $data=$request->validate(['token'=>'required|string','email'=>'required|email','password'=>'required|string|min:12|confirmed']);
        $email=strtolower(trim($data['email']));
        $stored=PasswordResetToken::where('email',$email)->first();
        if(!$stored || !hash_equals((string)$stored->token_hash,hash('sha256',$data['token'])) || now()->greaterThan($stored->expires_at)){
            throw ValidationException::withMessages(['email'=>'This reset link is invalid or has expired.']);
        }
        $user=User::where('email',$email)->where('is_active',true)->first();
        if(!$user) throw ValidationException::withMessages(['email'=>'This reset link is invalid or has expired.']);
        $user->password=Hash::make($data['password']);$user->setRememberToken(Str::random(60));$user->save();
        PasswordResetToken::where('email',$email)->delete();
        return redirect()->route('login')->with('status','Password updated. Please sign in.');
    }
}
