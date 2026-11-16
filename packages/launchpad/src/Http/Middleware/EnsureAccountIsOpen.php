<?php

namespace Launchpad\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs out an account that has been closed or suspended, whatever session or remember cookie it still has */
class EnsureAccountIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && method_exists($user, 'isClosed') && $user->isClosed()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account has been closed.']);
        }

        if ($user && method_exists($user, 'isSuspended') && $user->isSuspended()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('This account has been suspended. Contact support if you think this is a mistake.')]);
        }

        return $next($request);
    }
}
