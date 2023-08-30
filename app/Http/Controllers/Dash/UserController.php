<?php

namespace App\Http\Controllers\Dash;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

use DB;

class UserController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }
    
    public function detailUser(Request $request, $user_id){
        try {
            $user_query = DB::table('minimi_user_data')->where('user_id',$user_id)->first();

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
                $lives_in = app('App\Http\Controllers\API\UserController')->livesIn($user_query->lives_in);
                if($lives_in!='empty'){
                    $user['lives_in'] = $lives_in->city_name.', '.$lives_in->country_name;
                }
            }
            $user['personal_bio'] = $user_query->personal_bio;
            $user['photo_profile'] = $user_query->photo_profile;
            $user['active'] = $user_query->active;
            $user['socmed_twitter'] = $user_query->socmed_twitter;
            $user['socmed_instagram'] = $user_query->socmed_instagram;
            $user['socmed_facebook'] = $user_query->socmed_facebook;
            $user['website'] = $user_query->website;

            $content_counter = app('App\Http\Controllers\API\UserController')->recapContentCount($user_query->user_id,$user_query->last_count);
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

            $user['follower_count'] = app('App\Http\Controllers\API\UserController')->countFollower($user_query->user_id,1);
            $user['following_count'] = app('App\Http\Controllers\API\UserController')->countFollower($user_query->user_id,0);
            $user['point_count'] = $user_query->point_count;
            $user['created_at'] = $user_query->created_at;

            $review = app('App\Http\Controllers\Utility\UtilityController')->listReview(20,0,2,1,$user_query->user_id);

            $return['user'] = $user;
            $point_history = app('App\Http\Controllers\Utility\UtilityController')->pointTransactionHistory_exe($user_query->user_id,0);
            $return['point_history'] = ($point_history=='empty')?array():$point_history;
            $return['review'] = $review['data'];
            $return['offset'] = $review['offset'];

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'detail_user_failed']);
        }
    }

    public function loadContentPerUser(Request $request, $user_id){
        $data = $request->all();
        try {
            $limit = (empty($data['limit']))?20:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
            $content_type = (empty($data['content_type']))?0:$data['content_type'];
            $review = app('App\Http\Controllers\Utility\UtilityController')->listReview($limit,$offset,$content_type,1,$user_id);
            $return['review'] = $review['data'];
            $return['offset'] = $review['offset'];
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'user_content_load_failed']);
        }
      }
}