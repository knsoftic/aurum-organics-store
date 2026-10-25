<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->guest(route('admin.login'));
        }

        if (! Auth::user()->is_admin) {
            Auth::logout();

            return redirect()->route('admin.login')->withErrors(['email' => 'This account does not have admin access.']);
        }

        return $next($request);
    }
}
