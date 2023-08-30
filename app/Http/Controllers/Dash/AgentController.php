<?php

namespace App\Http\Controllers\Dash;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

use DB;

class AgentController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }
    
    public function saveAgent(Request $request){
        $data = $request->all();
        try {
            if($request->hasFile('agent_logo')){
                $photo = $data['agent_logo'];
                $destinationPath = 'public/agent';
                $image_path = app('App\Http\Controllers\Utility\UtilityController')->upload_image($photo,$destinationPath);
                switch ($image_path) {
                    case 'too_big':
                        return response()->json(['code'=>1001,'message'=>'image_too_big']);
                        break;
                    case 'not_an_image':
                        return response()->json(['code'=>1002,'message'=>'invalid_image_type']);
                        break;
                    default:
                        $insert['agent_logo'] = $image_path;
                        break;
                }
            }
            $date = date('Y-m-d H:i:s');
            $insert['agent_name'] = $data['agent_name'];
            $insert['agent_pic_name'] = $data['agent_pic_name'];
            $insert['agent_pic_phone'] = $data['agent_pic_phone'];
            $insert['agent_type'] = $data['agent_type'];
            $insert['agent_address'] = $data['agent_address'];
            $insert['agent_bank_account'] = $data['agent_bank_account'];
            $insert['agent_bank_name'] = $data['agent_bank_name'];
            $insert['agent_account_name'] = $data['agent_account_name'];
            $insert['created_at'] = $date;
            $insert['updated_at'] = $date;
            $agent_id = DB::table('warehouse_agent')->InsertGetId($insert);

            $return['agent_id'] = $agent_id;
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'save_agent_failed']);
		}
    }

    public function editAgent(Request $request){
        $data = $request->all();
        try {
            if($request->hasFile('agent_logo')){
                $destinationPath = 'public/agent';
                $image_path = app('App\Http\Controllers\Utility\UtilityController')->upload_image($data['agent_logo'],$destinationPath);
                switch ($image_path){
                    case 'too_big':
                        return response()->json(['code'=>1001,'message'=>'image_too_big']);
                        break;
                    case 'not_an_image':
                        return response()->json(['code'=>1002,'message'=>'invalid_image_type']);
                        break;
                    default:
                        $update['agent_logo'] = $image_path;
                        break;
                }
            }
            
            $date = date('Y-m-d H:i:s');
            $update['agent_name'] = $data['agent_name'];
            $update['agent_pic_name'] = $data['agent_pic_name'];
            $update['agent_pic_phone'] = $data['agent_pic_phone'];
            $update['agent_type'] = $data['agent_type'];
            $update['agent_address'] = $data['agent_address'];
            $update['agent_bank_account'] = $data['agent_bank_account'];
            $update['agent_bank_name'] = $data['agent_bank_name'];
            $update['agent_account_name'] = $data['agent_account_name'];
            $update['updated_at'] = $date;
            DB::table('warehouse_agent')->where('agent_id',$data['agent_id'])->update($update);

            $return['agent_id'] = $data['agent_id'];
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'edit_agent_failed']);
		}
    }

    public function detailAgent(Request $request,$agent_id){
        $data = $request->all();
        try {
            $query = DB::table('warehouse_agent')
                ->where([
                    'agent_id'=>$agent_id,
                    'status'=>1
                ])
            ->first();
            
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$query]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'detail_agent_failed']);
        }
    }
    
    public function deleteAgent(Request $request,$agent_id){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            $update['status']=0;
            $update['updated_at']=$date;
            DB::table('warehouse_agent')->where('agent_id',$agent_id)->update($update);
            
            return response()->json(['code'=>200, 'message'=>'success']);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'delete_agent_failed']);
        }
    }
}