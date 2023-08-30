<?php

namespace App\Http\Controllers\Dash;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

use DB;

class PointController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }
    
    public function addPointToUser(Request $request){
        $data = $request->all();
        try {
            app('App\Http\Controllers\Utility\UtilityController')->addPoint($data['user_id'], $data['point_amount'], $data['remarks'], $data['admin_id']);
            return response()->json(['code'=>200,'message'=>'success']);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'add_point_failed']);
		}
    }

    public function removePointFromUser(Request $request){
        $data = $request->all();
        try {
            $return = app('App\Http\Controllers\Utility\UtilityController')->removePoint($data['user_id'], $data['point_amount'], $data['remarks'], $data['admin_id']);
            if($return==FALSE){
                return response()->json(['code'=>4301,'message'=>'insufficient_point']);
            }
            return response()->json(['code'=>200,'message'=>'success']);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'remove_point_failed']);
		}
    }

    public function multiplierTrigger(Request $request,$mode){
        $data = $request->all();
        try {
            DB::table('data_param')->where('param_tag','multiplier_event_trigger')->update([
                'param_value'=>$mode
            ]);
            return response()->json(['code'=>200,'message'=>'success']);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'point_multiplier_trigger_failed']);
		}
    }

    public function multiplierStatus(Request $request){
        $data = $request->all();
        try {
            $return['status'] = DB::table('data_param')->where('param_tag','multiplier_event_trigger')->value('param_value');
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'point_multiplier_trigger_failed']);
		}
    }
}