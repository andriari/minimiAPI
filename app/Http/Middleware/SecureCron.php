<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Hash;

use Closure;
use DB;

class SecureCron
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $header = $request->header('Authorization');
        if(empty($header)){
            return response()->json(['code'=>4033,'message'=>'unauthorized_access']);
        }else{
            $exp = explode('Bearer ',$header);
            $check = DB::table('minimi_api_key')->where('mak_id',3)->first();
            if(count($exp)==2){
                if(Hash::check($exp[1], $check->token)) {
                    return $next($request);
                }else{
                    return response()->json(['code'=>4033,'message'=>'unauthorized_access']);
                }
            }else{
                return response()->json(['code'=>4033,'message'=>'unauthorized_access']);
            }
        }
    }
}
