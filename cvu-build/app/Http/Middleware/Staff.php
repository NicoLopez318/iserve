<?php
namespace App\Http\Middleware;
use Closure;
class Staff { public function handle($request, Closure $next) {
 abort_unless($request->user()?->isStaff(),403);
 // Staff reviews belong to the existing staff application, never this student app.
 abort_unless($request->isMethod('GET'),404);
 $path=match($request->path()){'staff/reports'=>'/workspace/reports','staff/export'=>'/staff/export',default=>'/workspace'};
 return redirect()->away(rtrim(env('STAFF_PORTAL_URL','https://goldenrod-turkey-247730.hostingersite.com'),'/').$path);
} }
