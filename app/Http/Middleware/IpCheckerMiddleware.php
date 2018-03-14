<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 2/14/2018
 * Time: 4:20 PM
 */

namespace App\Http\Middleware;
use Closure;

class IpCheckerMiddleware
{
    public function handle($request, Closure $next)
    {
        $ip = env('ACCESS_ALLOW_IP');
        $ip = explode(",", $ip);
        if (!in_array($request->ip(), $ip)) {
            return response()->json([ 'status_code' => 401, 'message' => $request->ip().' is Unauthorized for this action.' ], 401);
        }
        return $next($request);
    }
}