<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;

use DB;

class GamificationController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }
    
    public function getReward(Request $request){
        $data = $request->all();
		try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $return = DB::table('affiliate_reward')->where('status',1)->get();

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'affiliate_generator_failed']);
        }
    }
}