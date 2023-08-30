<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

use DB;

class MenuController extends Controller
{
  public function __construct(){
    date_default_timezone_set("Asia/Jakarta");
  }

  public function postResponse(Request $request){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }

      $check = DB::table('data_menu_stats')
        ->where([
          'menu_tag'=>$data['menu_tag'],
          'user_id'=>$currentUser->user_id
        ])
      ->first();

      if(!empty($check)){
        return response()->json(['code'=>200,'message'=>'responded_already','data'=>$check]);
      }
      
      DB::table('data_menu_stats')->insert([
        'menu_tag' => $data['menu_tag'],
        'user_id' => $currentUser->user_id,
        'response' => $data['response'],
        'created_at' => date('Y-m-d H:i:s')
      ]);

      return response()->json(['code'=>200,'message'=>'success']);
    } catch (QueryException $ex) {
      return response()->json(['code'=>4050,'message'=>'post_response_failed']);
    }
  }

  public function getResponse(Request $request, $menu_tag){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }

      $check = DB::table('data_menu_stats')
        ->where([
          'menu_tag'=>$menu_tag,
          'user_id'=>$currentUser->user_id
        ])
      ->first();

      if(empty($check)){
        return response()->json(['code'=>41201,'message'=>'response_not_found']);
      }
      
      return response()->json(['code'=>200,'message'=>'success','data'=>$check]);
    } catch (QueryException $ex) {
      return response()->json(['code'=>4050,'message'=>'get_response_failed']);
    }
  }
}