<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class EnsureActive
{
    public function handle(Request $request,Closure $next)
    {
        if(!$request->user()?->is_active){Auth::logout();$request->session()->invalidate();abort(403,'Account inactive.');}
        return $next($request);
    }
}
