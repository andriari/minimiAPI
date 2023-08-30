<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;

use DB;

class AffiliateController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }
    
    public function generateAffiliate(Request $request){
        $data = $request->all();
		try {
            if(empty($data['token'])){
                $share_user_id = null;
            }else{
                $share_user_id = $data['user']->data->user_id;
            }
            $mode = (empty($data['mode']))?'review':$data['mode'];

            switch ($mode) {
                case 'review':
                    $user_id = DB::table('minimi_content_post')->where('content_id',$data['identifier'])->value('user_id');
                    if($user_id==null || $user_id==''){
                        return response()->json(['code'=>4702,'message'=>'identifier_undefined']);
                    }
                    $insert['user_id'] = $user_id;
                break;

                default:
                    return response()->json(['code'=>4701,'message'=>'mode_undefined']);
                break;
            }

            $date = date('Y-m-d H:i:s');

            $insert['mode'] = $mode;
            $insert['mode_id'] = $data['identifier'];
            $insert['af_code_value'] = Str::random(10);
            $insert['share_user_id'] = $share_user_id;
            $insert['created_at'] = $date;
            $insert['updated_at'] = $date;
            DB::table('affiliate_code')->insert($insert);
            
            $return['creator_code'] = $insert['af_code_value'];
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'affiliate_generator_failed']);
        }
    }

    /**
     * Utility Function
    **/

    public function validateAffiliateCode($code){
        $check = DB::table('affiliate_code')->where('af_code_value',$code)->first();

        $date = date('Y-m-d H:i:s');
        $expired_at = date('Y-m-d H:i:s', strtotime($check->updated_at.' + 24 hours'));
        if($date > $expired_at){
            return FALSE;
        }
        
        return TRUE;
    }
}