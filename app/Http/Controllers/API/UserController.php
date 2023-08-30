<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;

use DB;

class UserController extends Controller
{
  public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
	}

  /**
   * Public Profile Function
  **/

  public function privateProfile(Request $request){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }

      $limit = (empty($data['limit']))?20:$data['limit'];

      $user['user_id'] = $currentUser->user_id;
      $user['fullname'] = $currentUser->fullname;
      $user['email'] = $currentUser->email;
      $user['phone'] = $currentUser->phone;
      $user['user_uri'] = $currentUser->user_uri;
      $user['gender'] = $currentUser->gender;
      $user['dob'] = ($currentUser->dob==null)?"":date('Y-m-d', strtotime($currentUser->dob));
      $user['verified'] = $currentUser->verified;
      $user['lives_in'] = '';
      if($currentUser->lives_in != null && $currentUser->lives_in != ''){
        $lives_in = $this->livesIn($currentUser->lives_in);
        if($lives_in!='empty'){
          $user['lives_in'] = $lives_in->city_name.', '.$lives_in->country_name;
        }else{
          $user['lives_in'] = $currentUser->lives_in;
        }
      }
      $user['personal_bio'] = $currentUser->personal_bio;
      $user['photo_profile'] = $currentUser->photo_profile;
      $user['active'] = $currentUser->active;
      $user['socmed_twitter'] = $currentUser->socmed_twitter;
      $user['socmed_instagram'] = $currentUser->socmed_instagram;
      $user['socmed_facebook'] = $currentUser->socmed_facebook;
      $user['website'] = $currentUser->website;
      
      $content_counter = $this->recapContentCount($currentUser->user_id,$currentUser->last_count);
      if($content_counter!='empty'){
        $user['total_content_count'] = $content_counter['total_content_count'];
        $user['review_count'] = $content_counter['review_count'];
        $user['video_count'] = $content_counter['video_count'];
        $user['article_count'] = $content_counter['article_count'];
      }else{
        $user['total_content_count'] = $currentUser->total_content_count;
        $user['review_count'] = $currentUser->review_count;
        $user['video_count'] = $currentUser->video_count;
        $user['article_count'] = $currentUser->article_count;
      }

      $user['follower_count'] = $this->countFollower($currentUser->user_id,1);
      $user['following_count'] = $this->countFollower($currentUser->user_id,0);
      $user['point_count'] = $currentUser->point_count;

      $review = app('App\Http\Controllers\Utility\UtilityController')->listReview($limit,0,2,0,$currentUser->user_id);
      
      $return['user'] = $user;
      $return['review'] = $review['data'];
      $return['offset'] = $review['offset'];
      return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'private_profile_load_failed']);
    }
  }

  public function editProfile(Request $request){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }

      $destinationPath = 'public/profile/'.$currentUser->user_uri;

      if($request->hasFile('photo_profile')){
        $image = $data['photo_profile'];
        $image_path = app('App\Http\Controllers\Utility\UtilityController')->upload_image($image,$destinationPath);
        switch ($image_path) {
          case 'too_big':
            return response()->json(['code'=>1001,'message'=>'image_too_big']);
          break;
          
          case 'not_an_image':
            return response()->json(['code'=>1002,'message'=>'invalid_image_type']);
          break;
          
          default:
            $update['photo_profile'] = $image_path;
          break;
        }
      }

      if(!empty($data['username'])){
        if($data['username']!=$currentUser->user_uri){
          $check_user_uri = app('App\Http\Controllers\Utility\UtilityController')->checkUri2($data['username']);
          if($check_user_uri=='FALSE'){
            return response()->json(['code'=>4212,'message'=>'username_already_in_used']);
          }
          $update['user_uri'] = $data['username'];
        }
      }

      if(!empty($data['fullname'])){
        $update['fullname'] = $data['fullname'];
      }

      if(!empty($data['gender'])){
        $update['gender'] = $data['gender'];
      }

      if(!empty($data['dob'])){
        $update['dob'] = date('Y-m-d', strtotime($data['dob']));
      }
      
      if(!empty($data['phone'])){
        $update['phone'] = $data['phone'];
      }

      if(!empty($data['personal_bio'])){
        $update['personal_bio'] = $data['personal_bio'];
      }

      if(!empty($data['socmed_twitter'])){
        $update['socmed_twitter'] = $data['socmed_twitter'];
      }

      if(!empty($data['socmed_instagram'])){
        $update['socmed_instagram'] = $data['socmed_instagram'];
      }

      if(!empty($data['socmed_facebook'])){
        $update['socmed_facebook'] = $data['socmed_facebook'];
      }

      if(!empty($data['website'])){
        $update['website'] = $data['website'];
      }

      if(!empty($data['lives_in'])){
        $update['lives_in'] = $data['lives_in'];
      }

      $date = date('Y-m-d H:i:s');
      $update['updated_at'] = $date;
      DB::table('minimi_user_data')->where('user_id',$currentUser->user_id)->update($update);
      
      $check = app('App\Http\Controllers\Utility\UtilityController')->check_profile($currentUser->user_id,$update);
      if($check==true){
        app('App\Http\Controllers\Utility\UtilityController')->pointCounterContent(null, $currentUser->user_id, 'complete_profile');
      }

      return response()->json(['code'=>200,'message'=>'success']);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'private_profile_edit_failed']);
    }
  }

  public function editPassword(Request $request){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }
  
      if(empty($data['password'])){
        return response()->json(['code'=>4213,'message'=>'password_is_required']);
      }else{
        if($data['password']=="" || $data['password']==null){
          return response()->json(['code'=>4213,'message'=>'password_is_required']);
        }
  
        if(empty($data['password_conf'])){
            return response()->json(['code'=>4214,'message'=>'password_confirmation_is_required']);
        }else{
          if($data['password']!=$data['password_conf']){
            return response()->json(['code'=>4220,'message'=>'password_does_not_match']);
          }   
        }
      }
  
      $date = date('Y-m-d H:i:s');
      $update['password'] = Hash::make($data['password']);
      $update['updated_at'] = $date;
      DB::table('minimi_user_data')->where('user_id',$currentUser->user_id)->update($update);
      
      return response()->json(['code'=>200,'message'=>'success']);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'private_profile_edit_failed']);
    }
  }

  public function getInviteLink(Request $request){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }

      $return['link'] = env('FRONTEND_URL').env('INVITATION').'/'.base64_encode($currentUser->user_uri);

      return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'get_invite_link_failed']);
    }
  }

  public function getInviteDetail(Request $request, $code){
    $data = $request->all();
    try {
      $user_uri = base64_decode($code);

      $check = DB::table('minimi_user_data')->select('fullname')->where(['user_uri'=>$user_uri,'active'=>1])->first();
      if(empty($check)){
        return response()->json(['code'=>4300,'message'=>'invalid_user']);
      }
      $return['fullname'] = $check->fullname;
      $return['user_uri'] = $user_uri;
      $return['code'] = $code;
      return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'invite_link_detail_failed']);
    }
  }

  public function loadContentPerUser(Request $request){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }
      $limit = (empty($data['limit']))?20:$data['limit'];
      $offset = (empty($data['offset']))?0:$data['offset'];
      $content_type = (empty($data['content_type']))?0:$data['content_type'];
      $review = app('App\Http\Controllers\Utility\UtilityController')->listReview($limit,$offset,$content_type,1,$currentUser->user_id);
      $return['review'] = $review['data'];
      $return['offset'] = $review['offset'];
      return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'private_profile_content_load_failed']);
    }
  }

  /**
   * Voucher Function
  **/

  public function listRegularVoucher(Request $request){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }
      $limit = (empty($data['limit']))?20:$data['limit'];
      $offset = (empty($data['offset']))?0:$data['offset'];
      $voucher = app('App\Http\Controllers\Utility\VoucherController')->listVoucherReguler($currentUser->user_id,$limit,$offset);
      $return['voucher'] = $voucher['data'];
      $return['offset'] = $voucher['offset'];
      return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'list_regular_voucher_failed']);
    }
  }

  public function listVoucher(Request $request){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }
      $limit = (empty($data['limit']))?20:$data['limit'];
      $offset = (empty($data['offset']))?0:$data['offset'];
      $voucher = app('App\Http\Controllers\Utility\VoucherController')->listVoucherUser($currentUser->user_id,$limit,$offset);
      $return['voucher'] = $voucher['data'];
      $return['offset'] = $voucher['offset'];
      return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'list_voucher_failed']);
    }
  }

  public function detailVoucher(Request $request, $voucher_code){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }
      
      $query = DB::table('commerce_voucher')
        ->where([
          'voucher_code'=>$voucher_code,
          'publish'=>1
        ])
      ->first();

      if(!empty($query)){
        $query->voucher_validity_end = date('d-m-Y',strtotime($query->voucher_validity_end));
        switch ($query->promo_type) {
          case 1:
            if($query->user_id == $currentUser->user_id){
              $return['voucher'] = $query;
              return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
            }
          break;
          case 2:
            $return['voucher'] = $query;
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
          break;
          default:
            return response()->json(['code'=>4302,'message'=>'voucher_not_found']);
          break;
        }
      }

      return response()->json(['code'=>4302,'message'=>'voucher_not_found']);

    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'detail_voucher_failed']);
    }
  }

  public function searchVoucher(Request $request){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }
      $limit = (empty($data['limit']))?20:$data['limit'];
      $offset = (empty($data['offset']))?0:$data['offset'];
      $voucher = app('App\Http\Controllers\Utility\VoucherController')->searchVoucherUser($currentUser->user_id,$data['search_query'],$limit,$offset);
      $return['voucher'] = $voucher['data'];
      $return['offset'] = $voucher['offset'];
      return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'search_voucher_failed']);
    }
  }

  /**
   * Gamification Function
  **/
  public function loadGamificationMain(Request $request){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }

      $date = date('Y-m-d H:i:s');
      $return['tasks'] = app('App\Http\Controllers\Utility\UtilityController')->getTask($currentUser->user_id);
      $return['point'] = app('App\Http\Controllers\Utility\UtilityController')->pointCount($currentUser->user_id,$date);
      app('App\Http\Controllers\Utility\UtilityController')->storePointMoengage($currentUser->user_id);
      return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'gamification_load_failed']);
    }
  }

  public function loadGamificationTask(Request $request){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }

      $trigger = DB::table('data_param')->where('param_tag','multiplier_event_trigger')->value('param_value');
      $multiplier = 1;
      
      if($trigger==1){
        $multiplier = DB::table('data_param')->where('param_tag','multiplier_point')->value('param_value');
      }

      $query = DB::table('point_task')->select('task_id','task_name','task_desc','task_image','task_value','task_type','content_tag')
        ->where([
          'status'=>1
        ])
        ->orderBy('task_type','DESC')
      ->get();

      $return = array();
      foreach ($query as $row) {
        $array['task_id'] = $row->task_id;
        $array['task_name'] = $row->task_name;
        $array['task_desc'] = $row->task_desc;
        $array['task_image'] = $row->task_image;
        $array['task_value'] = floatval($multiplier*$row->task_value);
        $array['content_tag'] = $row->content_tag;
        array_push($return, $array);
      }
      
      return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'gamification_load_failed']);
    }
  }

  /**
   * Public Profile Function
  **/

  public function publicProfile(Request $request, $user_uri){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        $view_id = null;
      }else{
          $currentUser = $data['user']->data;
          $view_id = $currentUser->user_id;
      }

      $user_query = DB::table('minimi_user_data')->where('user_uri',$user_uri)->first();
      if(empty($user_query)){
        return response()->json(['code'=>4044,'message'=>'user_not_found']);
      }

      $limit = (empty($data['limit']))?20:$data['limit'];

      $user['user_id'] = $user_query->user_id;
      $user['fullname'] = $user_query->fullname;
      $user['email'] = $user_query->email;
      $user['phone'] = $user_query->phone;
      $user['user_uri'] = $user_query->user_uri;
      $user['gender'] = $user_query->gender;
      $user['dob'] = $user_query->dob;
      $user['verified'] = $user_query->verified;
      $user['lives_in'] = '';
      if($user_query->lives_in != null && $user_query->lives_in != ''){
        $lives_in = $this->livesIn($user_query->lives_in);
        if($lives_in!='empty'){
          $user['lives_in'] = $lives_in->city_name.', '.$lives_in->country_name;
        }else{
          $user['lives_in'] = $user_query->lives_in;
        }
      }
      $user['personal_bio'] = $user_query->personal_bio;
      $user['photo_profile'] = $user_query->photo_profile;
      $user['active'] = $user_query->active;
      $user['socmed_twitter'] = $user_query->socmed_twitter;
      $user['socmed_instagram'] = $user_query->socmed_instagram;
      $user['socmed_facebook'] = $user_query->socmed_facebook;
      $user['website'] = $user_query->website;

      $content_counter = $this->recapContentCount($user_query->user_id,$user_query->last_count);
      if($content_counter!='empty'){
        $user['total_content_count'] = $content_counter['total_content_count'];
        $user['review_count'] = $content_counter['review_count'];
        $user['video_count'] = $content_counter['video_count'];
        $user['article_count'] = $content_counter['article_count'];
      }else{
        $user['total_content_count'] = $user_query->total_content_count;
        $user['review_count'] = $user_query->review_count;
        $user['video_count'] = $user_query->video_count;
        $user['article_count'] = $user_query->article_count;
      }

      $user['follower_count'] = $this->countFollower($user_query->user_id,1);
      $user['following_count'] = $this->countFollower($user_query->user_id,0);
      $user['point_count'] = $user_query->point_count;
      $user['followed'] = app('App\Http\Controllers\Utility\UtilityController')->checkFollow($view_id,$user_query->user_id);

      $review = app('App\Http\Controllers\Utility\UtilityController')->listReview($limit,0,2,0,$user_query->user_id);

      $return['user'] = $user;
      $return['review'] = $review['data'];
      $return['offset'] = $review['offset'];
      return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'public_profile_load_failed']);
    }
  }

  public function loadContentPerUserPublic(Request $request, $user_uri){
    $data = $request->all();
    try {
      $limit = (empty($data['limit']))?20:$data['limit'];
      $offset = (empty($data['offset']))?0:$data['offset'];
      $content_type = (empty($data['content_type']))?0:$data['content_type'];

      $user_id = DB::table('minimi_user_data')->where('user_uri',$user_uri)->value('user_id');
      if($user_id==null){
        return response()->json(['code'=>4044,'message'=>'user_not_found']);
      }

      $review = app('App\Http\Controllers\Utility\UtilityController')->listReview($limit,$offset,$content_type,0,$user_id);
      $return['review'] = $review['data'];
      $return['offset'] = $review['offset'];
      return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'public_profile_content_load_failed']);
    }
  }

  /**
   * Address Function
  **/
  public function listAddress(Request $request){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }
  
      $return = $this->listAddress_exe($currentUser->user_id);
      return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'list_address_failed']);
    }
  }

  public function addAddress(Request $request){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }
      
      $date = date('Y-m-d H:i:s');
      $address['user_id'] = $currentUser->user_id;
      $address['address_title'] = $data['address_title'];
      $address['address_pic'] = $data['address_pic'];
      $address['address_phone'] = $data['address_phone'];
      $address['address_name'] = $data['address_name'];
      $address['address_detail'] = $data['address_detail'];
      $address['address_postal_code'] = $data['address_postal_code'];
      $address['address_subdistrict_name'] = $data['address_subdistrict_name'];
      $address['address_city_name'] = $data['address_city_name'];
      $address['address_province_name'] = $data['address_province_name'];
      $address['address_country_code'] = "ID";
      $address['sicepat_destination_code'] = $data['sicepat_destination_code'];
      $address['created_at'] = $date;
      $address['updated_at'] = $date;
      $address_id = DB::table('minimi_user_address')->insertGetId($address);

      $check = DB::table('minimi_user_address')->where('default',1)->value('address_id');
      if($check==null || $check==''){
        DB::table('minimi_user_address')->where('address_id',$address_id)->update([
          'default'=>1,
          'updated_at'=>$date
        ]);
      }

      return response()->json(['code'=>200,'message'=>'success']);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'add_address_failed']);
    }
  }

  public function editAddress(Request $request){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }
      
      $check = DB::table('minimi_user_address')
        ->where([
          'address_id'=>$data['address_id'],
          'user_id'=>$currentUser->user_id
        ])
      ->first();

      if(empty($check)){
        return response()->json(['code'=>4001,'message'=>'address_invalid']);
      }

      $address['address_title'] = $data['address_title'];
      $address['address_pic'] = $data['address_pic'];
      $address['address_phone'] = $data['address_phone'];
      $address['address_name'] = $data['address_name'];
      $address['address_detail'] = $data['address_detail'];
      $address['address_postal_code'] = $data['address_postal_code'];
      $address['address_subdistrict_name'] = $data['address_subdistrict_name'];
      $address['address_city_name'] = $data['address_city_name'];
      $address['address_province_name'] = $data['address_province_name'];
      $address['sicepat_destination_code'] = $data['sicepat_destination_code'];
      $address['updated_at'] = date('Y-m-d H:i:s');

      DB::table('minimi_user_address')->where('address_id',$data['address_id'])->update($address);

      return response()->json(['code'=>200,'message'=>'success']);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'edit_address_failed']);
    }
  }

  public function detailAddress(Request $request, $address_id){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }
      
      $check = DB::table('minimi_user_address')
        ->where([
          'address_id'=>$address_id,
          'user_id'=>$currentUser->user_id
        ])
      ->first();

      if(empty($check)){
        return response()->json(['code'=>4001,'message'=>'address_invalid']);
      }

      return response()->json(['code'=>200,'message'=>'success','data'=>$check]);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'edit_address_failed']);
    }
  }

  public function setDefaultAddress(Request $request,$address_id){
    $data = $request->all();
    try {
      if(empty($data['token'])){
        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
      }else{
        $currentUser = $data['user']->data;
      }
      
      $check = DB::table('minimi_user_address')
        ->where([
          'address_id'=>$address_id,
          'user_id'=>$currentUser->user_id
        ])
      ->first();

      if(empty($check)){
        return response()->json(['code'=>4001,'message'=>'address_invalid']);
      }

      if($check->default!=1){ 
        $date = date('Y-m-d H:i:s');
  
        $address_id_2 = DB::table('minimi_user_address')->where('default',1)->value('address_id');

        if($address_id_2!=null && $address_id_2!=''){
          DB::table('minimi_user_address')->where('address_id',$address_id_2)->update([
            'default'=>0,
            'updated_at'=>$date
          ]);  
        }
  
        DB::table('minimi_user_address')->where('address_id',$address_id_2)->update([
          'default'=>1,
          'updated_at'=>$date
        ]);
      }

      return response()->json(['code'=>200,'message'=>'success']);
    } catch (QueryException $ex){
      return response()->json(['code'=>4050,'message'=>'set_default_address_failed']);
    }
  }

  /**
   * Utility Function
  **/

  public function listAddress_exe($user_id){
    $query = DB::table('minimi_user_address')
    ->select('address_id', 'address_pic', 'address_phone', 'address_name', 'address_detail', 'address_subdistrict_name', 'address_city_name', 'address_province_name', 'address_postal_code', 'country_name', 'sicepat_destination_code')
      ->leftJoin('data_country','data_country.country_code','=','minimi_user_address.address_country_code')
      ->where([
        'user_id'=>$user_id,
        'minimi_user_address.status'=>1
      ])
      ->orderBy('used_count','DESC')->orderBy('address_id','ASC')
    ->get();

    return $query;
  }

  public function livesIn($city_code){
    $check = DB::table('data_city')
      ->select('city_name','country_name')
      ->join('data_country','data_country.country_code','=','data_city.country_code')
      ->where('city_code',$city_code)
    ->first();
    if(!empty($check)){
      return $check;
    }
    return 'empty';
  }

  public function countFollower($id_user,$mode){
    $check = DB::table('data_user_follow')->select('duf_id');
    if($mode==1){
      //follower
      $check = $check->where('follow_id',$id_user);
    }else{
      //following
      $check = $check->where('user_id',$id_user);
    }
    $check = $check->where('status',1)->get();
    return count($check);
  }

  public function recapContentCount($user_id, $last_date, $duration='',$private=0){
    $date = date('Y-m-d H:i:s');
    if($last_date==null){
      $count = $this->recapperCount($user_id,$date,$private);
    }else{
      $add_time = ($duration=='')?'6 hours':$duration;
      $last_date2 = date('Y-m-d H:i:s', strtotime($last_date. ' + '.$add_time));
      if($last_date2 > $date){
        $count = 'empty';
      }else{
        $count = $this->recapperCount($user_id,$date,$private);
      }
    }
    return $count;
  }
  
  public function recapperCount($user_id,$date,$private=0){
    $count = $this->contentCount($user_id,$private);
    if($private==0){
      DB::table('minimi_user_data')->where('user_id',$user_id)->update([
        'total_content_count'=>$count['total_content_count'],
        'review_count'=>$count['review_count'],
        'video_count'=>$count['video_count'],
        'article_count'=>$count['article_count'],
        'last_count'=>$date
      ]);
    }else{
      $count2 = $this->contentCount($user_id,0);
      DB::table('minimi_user_data')->where('user_id',$user_id)->update([
        'total_content_count_private'=>$count['total_content_count'],
        'review_count_private'=>$count['review_count'],
        'video_count_private'=>$count['video_count'],
        'article_count_private'=>$count['article_count'],
        'last_count_private'=>$date,
        'total_content_count'=>$count2['total_content_count'],
        'review_count'=>$count2['review_count'],
        'video_count'=>$count2['video_count'],
        'article_count'=>$count2['article_count'],
        'last_count'=>$date
      ]);
    }
    return $count;
  }

  public function contentCount($uri,$private=0){
    if($private==1){
      $query = DB::table('minimi_content_post')
        ->select('content_id', 'content_type')
        ->whereIn('content_curated',[0,1,2])
        ->where('status',1)
        ->where('user_id',$uri)
      ->get();
    }else{
      $query = DB::table('minimi_content_post')
        ->select('content_id', 'content_type')
        ->where('content_curated',1)
        ->where('status',1)
        ->where('user_id',$uri)
      ->get();
    }

    $collection = collect($query);

    $content_ids = $collection->pluck('content_id')->values()->all();
    $video = $collection->where('content_type',1)->all();
    $review = $collection->where('content_type',2)->all();
    $article = $collection->where('content_type',3)->all();

    $result['total_content_count'] = count($content_ids);
    $result['review_count'] = count($review);
    $result['video_count'] = count($video);
    $result['article_count'] = count($article);
    return $result;
  }
}