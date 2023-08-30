<?php

namespace App\Http\Controllers\Dash;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

use DB;

class AppController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }
    
    public function addVersion(Request $request){
        $data = $request->all();
        try {
            $insert['version_code'] = $data['version_code'];
            $insert['version_name'] = $data['version_name'];
            $insert['version_message'] = $data['version_message'];
            $insert['version_date'] = date('Y-m-d H:i:s', strtotime($data['version_date']));
            $insert['version_status'] = $data['version_status'];
            $insert['android_status'] = $data['android_status'];
            $insert['ios_status'] = $data['ios_status'];
            DB::table('data_app_version')->insert($insert);

            return response()->json(['code'=>200,'message'=>'success']);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'save_version_failed']);
		}
    }

    public function editVersion(Request $request){
        $data = $request->all();
        try {
            $update['version_code'] = $data['version_code'];
            $update['version_name'] = $data['version_name'];
            $update['version_message'] = $data['version_message'];
            $update['version_date'] = date('Y-m-d H:i:s', strtotime($data['version_date']));
            $update['version_status'] = $data['version_status'];
            $update['android_status'] = $data['android_status'];
            $update['ios_status'] = $data['ios_status'];
            DB::table('data_app_version')->where('version_id',$data['version_id'])->update($update);

            return response()->json(['code'=>200, 'message'=>'success']);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'edit_version_failed']);
		}
    }
}