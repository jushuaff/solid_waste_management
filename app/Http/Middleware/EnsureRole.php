<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        abort_unless($request->user() && $request->user()->is_active,403,'Your account is inactive.');
        abort_unless(in_array($request->user()->role_code,$roles,true),403,'This action is not permitted for your role.');
        return $next($request);
    }
}
