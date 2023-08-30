<?php

namespace App\Http\Controllers\Dash;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

use DB;

class RewardController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
	}

    /*
        reward begin
    */
    public function saveReward(Request $request){
        $data = $request->all();
        try {
            if($request->hasFile('image')){
                $destinationPath = 'public/reward';
                $image_path = app('App\Http\Controllers\Utility\UtilityController')->upload_image($data['image'],$destinationPath);
                switch ($image_path) {
                    case 'too_big':
                        return response()->json(['code'=>1001,'message'=>'image_too_big','data'=>$return]);
                        break;
                    case 'not_an_image':
                        return response()->json(['code'=>1002,'message'=>'invalid_image_type','data'=>$return]);
                        break;
                    default:
                        $insert['reward_thumbnail'] = $image_path;
                        break;
                }

                $date = date('Y-m-d H:i:s');
                $slug = app('App\Http\Controllers\Utility\UtilityController')->slug($data['reward_name']);
                $reward_uri = app('App\Http\Controllers\Utility\UtilityController')->uri($slug);

                $insert['reward_name'] = $data['reward_name'];
                $insert['reward_uri'] = $reward_uri;
                $insert['reward_desc'] = $data['reward_desc'];
                $insert['reward_point_price'] = $data['reward_point_price'];
                $insert['reward_stock'] = $data['reward_stock'];
                $insert['reward_embed_link'] = $data['reward_embed_link'];
                $insert['created_at'] = $date;
                $insert['updated_at'] = $date;

                $reward_id = DB::table('affiliate_reward')->insertGetId($insert);

                $return['reward_id'] = $reward_id;
                return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
            }
            
            return response()->json(['code'=>1003,'message'=>'no_image_found','data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'save_reward_failed']);
		}
    }

    public function editReward(Request $request){
        $data = $request->all();
        try {
            if($request->hasFile('image')){
                $destinationPath = 'public/reward';
                $image_path = app('App\Http\Controllers\Utility\UtilityController')->upload_image($data['image'],$destinationPath);
                switch ($image_path){
                    case 'too_big':
                        return response()->json(['code'=>1001,'message'=>'image_too_big']);
                        break;
                    case 'not_an_image':
                        return response()->json(['code'=>1002,'message'=>'invalid_image_type']);
                        break;
                    default:
                        $update['reward_thumbnail'] = $image_path;
                        break;
                }
            }
            
            $update = array(
                'reward_name'=>$data['reward_name'],
                'reward_desc'=>$data['reward_desc'],
                'reward_point_price'=>$data['reward_point_price'],
                'reward_stock'=>$data['reward_stock'],
                'reward_embed_link'=>$data['reward_embed_link'],
                'updated_at'=>date('Y-m-d H:i:s')
            );

            DB::table('affiliate_reward')->where('reward_id',$data['reward_id'])->update($update);

            $return['reward_id'] = $data['reward_id'];
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'edit_reward_failed']);
		}
    }

    public function deleteReward(Request $request, $reward_id){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            
            DB::table('affiliate_reward')->where('reward_id',$reward_id)->update([
                'status'=>0,
                'updated_at'=>$date
            ]);

            $return['reward_id'] = $reward_id;
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'delete_reward_failed']);
		}
    }

    public function detailReward(Request $request, $reward_id){
        $data = $request->all();
        try {
            $return = DB::table('affiliate_reward')
                ->where([
                    'reward_id' => $reward_id,
                    'status' => 1
                ])
            ->first();

            if(!empty($return)){
                return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
            }else{
                return response()->json(['code'=>400, 'message'=>'not_found']);
            }
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'loading_reward_failed']);
		}
    }

    /*
        reward end
    */
}