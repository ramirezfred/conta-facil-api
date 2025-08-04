<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

//use Illuminate\Http\Middleware\CorsMiddleware as Middleware;

class CorsMiddleware 
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        //$response->header('Access-Control-Allow-Origin', 'http://localhost:4200');

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        return $response;
        //return $next($request);
    }
}
