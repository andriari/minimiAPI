<?php

namespace App\Http\Controllers\Dash;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

use DB;

class BannerController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }
    
    public function saveBanner(Request $request){
        $data = $request->all();
        try {
            $alt = $data['alt'];
            $title = $data['title'];
            $link = $data['link'];
            if($request->hasFile('image')){
                $photo = $data['image'];
                $destinationPath = 'public/banner';
                $image_path = app('App\Http\Controllers\Utility\UtilityController')->upload_image($photo,$destinationPath);
                switch ($image_path) {
                    case 'too_big':
                        return response()->json(['code'=>1001,'message'=>'image_too_big']);
                        break;
                    case 'not_an_image':
                        return response()->json(['code'=>1002,'message'=>'invalid_image_type']);
                        break;
                    default:
                        $insert['banner_image'] = $image_path;
                        break;
                }

                $date = date('Y-m-d H:i:s');
                $insert['banner_alt'] = $alt;
                $insert['banner_title'] = $title;
                $insert['banner_embedded_link'] = $link;
                $insert['created_at'] = $date;
                $insert['updated_at'] = $date;
                DB::table('minimi_banner')->insert($insert);

                $return['banner_image'] = $image_path;
                $return['banner_alt'] = $alt;
                $return['banner_title'] = $title;
                $return['banner_embedded_link'] = $link;
                return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
            }else{
                return response()->json(['code'=>1003,'message'=>'no_image_found']);
            }
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'save_banner_failed']);
		}
    }

    public function editBanner(Request $request){
        $data = $request->all();
        try {
            if($request->hasFile('image')){
                $destinationPath = 'public/banner';
                $image_path = app('App\Http\Controllers\Utility\UtilityController')->upload_image($data['image'],$destinationPath);
                switch ($image_path){
                    case 'too_big':
                        return response()->json(['code'=>1001,'message'=>'image_too_big']);
                        break;
                    case 'not_an_image':
                        return response()->json(['code'=>1002,'message'=>'invalid_image_type']);
                        break;
                    default:
                        $update['banner_image'] = $image_path;
                        break;
                }
            }
            
            $date = date('Y-m-d H:i:s');
            $update['banner_alt'] = $data['alt'];
            $update['banner_title'] = $data['title'];
            $update['banner_embedded_link'] = $data['link'];
            $update['updated_at'] = $date;
            DB::table('minimi_banner')->where('banner_id',$data['banner_id'])->update($update);

            $query = DB::table('minimi_banner')->where('banner_id',$data['banner_id'])->first();
            $return['banner_image'] = $query->banner_image;
            $return['banner_alt'] = $query->banner_alt;
            $return['banner_title'] = $query->banner_title;
            $return['banner_embedded_link'] = $query->banner_embedded_link;
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'edit_banner_failed']);
		}
    }

    public function deleteBanner(Request $request,$banner_id){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            $update['status']=0;
            $update['updated_at']=$date;
            DB::table('minimi_banner')->where('banner_id',$banner_id)->update($update);
            
            return response()->json(['code'=>200, 'message'=>'success']);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'delete_banner_failed']);
        }
    }
}