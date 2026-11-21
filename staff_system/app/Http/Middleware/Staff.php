<?php
namespace App\Http\Middleware;
use Closure;
class Staff { public function handle($request, Closure $next) { abort_unless($request->user()?->isStaff(),403); return $next($request); } }
