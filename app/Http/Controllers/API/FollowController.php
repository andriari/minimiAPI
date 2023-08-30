<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;

use DB;

class FollowController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
	}

    public function setUserFollow(Request $request, $user_uri){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }
    
            $verdict = $this->followExe($currentUser->user_id,$user_uri,1);
            if($verdict['verdict']=='success'){
                if(empty($data['origin'])){
                    return response()->json(['code'=>200,'message'=>'success']);
                }else{
                    $origin_user_name = DB::table('minimi_user_data')->where('user_id',$data['origin'])->value('user_uri');
                    $user['id_user'] = $data['origin'];
                    $user['username'] = $origin_user_name;
                    $following = $this->listFollowing_exe($currentUser->user_id,$data['origin'],0,50);
                    switch ($following) {
                        case 'empty':
                            $following_array = array();
                        break;
                        default:
                            $following_array = $following;
                        break;
                    }
        
                    $follower = $this->listFollower_exe($currentUser->user_id,$data['origin'],0,50);
                    switch ($follower) {
                        case 'empty':
                            $follower_array = array();
                        break;
                        default:
                            $follower_array = $follower;
                        break;
                    }
                    $result = collect([
                        'origin_user' => $user,
                        'following' => $following_array,
                        'follower' => $follower_array
                    ]);
                    return response()->json(['code'=>200,'message'=>'success','data'=>$result]);
                }
            }else{
                return response()->json(['code'=>4604,'message'=>'unable_to_follow']);
            }
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'follow_user_failed']);
        }
    }

    public function setUserUnfollow(Request $request, $user_uri){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }
    
            $verdict = $this->followExe($currentUser->user_id,$user_uri,0);
            if($verdict['verdict']=='success'){
                if(empty($data['origin'])){
                    return response()->json(['code'=>200,'message'=>'success']);
                }else{
                    $origin_user_name = DB::table('minimi_user_data')->where('user_id',$data['origin'])->value('user_uri');
                    $user['id_user'] = $data['origin'];
                    $user['username'] = $origin_user_name;
                    $following = $this->listFollowing_exe($currentUser->user_id,$data['origin'],0,50);
                    switch ($following) {
                        case 'empty':
                            $following_array = array();
                        break;
                        default:
                            $following_array = $following;
                        break;
                    }
        
                    $follower = $this->listFollower_exe($currentUser->user_id,$data['origin'],0,50);
                    switch ($follower) {
                        case 'empty':
                            $follower_array = array();
                        break;
                        default:
                            $follower_array = $follower;
                        break;
                    }
                    $result = collect([
                        'origin_user' => $user,
                        'following' => $following_array,
                        'follower' => $follower_array
                    ]);
                    return response()->json(['code'=>200,'message'=>'success','data'=>$result]);
                }
            }else{
                return response()->json(['code'=>4604,'message'=>'unable_to_unfollow']);
            }
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'unfollow_user_failed']);
        }
    }

    public function listFollowing(Request $request, $user_id, $offset=0){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                $view_id = "";
            }else{
                $currentUser = $data['user']->data;
                $view_id = $currentUser->user_id;
            }
    
            $limit = 50;
            $return = $this->listFollowing_exe($view_id, $user_id, $offset, $limit);
            switch ($return) {
                case 'empty':
                    return response()->json(['code'=>4600,'message'=>'following_empty']);
                break;        
                default:
                    return response()->json(['code'=>200,'message'=>'success','data'=>$return['data'],'offset'=>$return['offset']]);
                break;
            }
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'list_following_failed']);
        }
    }

    public function listFollower(Request $request, $user_id, $offset=0){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                $view_id = "";
            }else{
                $currentUser = $data['user']->data;
                $view_id = $currentUser->user_id;
            }
    
            $limit = 50;
            $return = $this->listFollower_exe($view_id, $user_id, $offset, $limit);
            switch ($return) {
                case 'empty':
                    return response()->json(['code'=>4600,'message'=>'follower_empty']);
                break;        
                default:
                    return response()->json(['code'=>200,'message'=>'success','data'=>$return['data'],'offset'=>$return['offset']]);
                break;
            }
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'list_follower_failed']);
        }
    }

    public function checkFollowerSelf(Request $request, $offset=0){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
                $view_id = $currentUser->user_id;
            }
            
            $limit = 100;
            $offset_count = $offset*$limit;
            $next_offset_count = ($offset+1)*$limit;
            $query = $this->getFollower($view_id,$offset_count,$limit);
            if($query!="empty"){
                $collection = collect($query);
                $pluck = $collection->pluck('user_id');
                $user_ids = $pluck->all();

                $user_ids = array_diff($user_ids, array($view_id));
                $check = $this->checkFollowing($view_id,$user_ids);
                $user_ids = array_diff($user_ids, $check);

                foreach ($query as $row) {
                    if($view_id!=""){
                        if(in_array($row->user_id,$user_ids)){
                            $row->follow = 0;
                        }else{
                            if($row->user_id==$view_id){
                                $row->follow = 2;
                            }else{
                                $row->follow = 1;
                            }
                        }
                    }else{
                    $row->follow = 0;
                    }
                }

                $next_offset = "empty";
                if(count($query)==$limit){
                    $query2 = $this->getFollower($view_id,$next_offset_count,$limit);
                    if($query2!="empty"){
                        $next_offset = $next_offset_count/$limit;
                    }
                }
                return response()->json(['code'=>200,'message'=>'success','data'=>$query,'offset'=>$next_offset]);
            }else{
                return response()->json(['code'=>4600,'message'=>'follower_empty']);
            }
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'check_follower_failed']);
        }
    }

    public function checkFollowingSelf(Request $request, $offset=0){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
                $view_id = $currentUser->user_id;
            }
            
            $limit = 100;
            $offset_count = $offset*$limit;
            $next_offset_count = ($offset+1)*$limit;
            $query = $this->getFollowing($view_id,$offset_count,$limit);
            if($query!="empty"){
                $collection = collect($query);
                $pluck = $collection->pluck('user_id');
                $user_ids = $pluck->all();

                $user_ids = array_diff($user_ids, array($view_id));
                $check = $this->checkFollowing($view_id,$user_ids);
                $user_ids = array_diff($user_ids, $check);

                foreach ($query as $row) {
                    if($view_id!=""){
                        if(in_array($row->user_id,$user_ids)){
                            $row->follow = 0;
                        }else{
                            if($row->user_id==$view_id){
                                $row->follow = 2;
                            }else{
                                $row->follow = 1;
                            }
                        }
                    }else{
                    $row->follow = 0;
                    }
                }

                $next_offset = "empty";
                if(count($query)==$limit){
                    $query2 = $this->getFollowing($view_id,$next_offset_count,$limit);
                    if($query2!="empty"){
                        $next_offset = $next_offset_count/$limit;
                    }
                }
                return response()->json(['code'=>200,'message'=>'success','data'=>$query,'offset'=>$next_offset]);
            }else{
                return response()->json(['code'=>4600,'message'=>'following_empty']);
            }
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'check_following_failed']);
        }
    }

    /**
     * Utility Function
    **/

    public function followExe($user_id,$user_uri,$status){
        $query = DB::table('data_user_follow')->join('minimi_user_data','minimi_user_data.user_id','=','data_user_follow.follow_id')->where(['data_user_follow.user_id'=>$user_id,'minimi_user_data.user_uri'=>$user_uri,'minimi_user_data.active'=>1])->first();
        $follow = DB::table('minimi_user_data')->where(['minimi_user_data.user_uri'=>$user_uri,'minimi_user_data.active'=>1])->first();
        $return['verdict'] = "fail";
        $return['user_id'] = $follow->user_id;
        if(empty($query)){
          if($user_id!=$follow->user_id){
            $date = date('Y-m-d H:i:s');
            DB::table('data_user_follow')->insert([
                'user_id'=>$user_id,
                'follow_id'=>$follow->user_id,
                'status'=>$status,
                'created_at'=>$date,
                'updated_at'=>$date
            ]);
          }else{
            return $return;
          }
        }else{
          DB::table('data_user_follow')->where('duf_id',$query->duf_id)->update([
            'status'=>$status,
            'updated_at'=>date('Y-m-d H:i:s')
          ]);
        }
        $return['verdict'] = "success";
        return $return;
    }

    //////// -- Following -- /////////

    public function listFollowing_exe($view_id, $user_id, $offset, $limit){
        $offset_count = $offset*$limit;
        $next_offset_count = ($offset+1)*$limit;
    
        $query = $this->getFollowing($user_id,$offset_count,$limit);
        if($query!="empty"){
            if($view_id!=""&&$view_id!=$user_id){
                $collection = collect($query);
                $pluck = $collection->pluck('user_id');
                $user_ids = $pluck->all();
                $user_ids = array_diff($user_ids, array($view_id));
                $check = $this->checkFollowing($view_id,$user_ids);
                if($check!='empty'){
                    $user_ids = array_diff($user_ids, $check);
                }
            }
            foreach ($query as $row) {
                if($view_id!=""){
                    if($view_id==$user_id){
                        $row->follow = 1;
                    }else{
                        if(in_array($row->user_id,$user_ids)){
                            $row->follow = 0;
                        }else{
                            if($row->user_id==$view_id){
                                $row->follow = 2;
                            }else{
                                $row->follow = 1;
                            }
                        }
                    }
                }else{
                    $row->follow = 0;
                }
            }
            $query2 = $this->getFollowing($user_id,$next_offset_count,$limit);
            if($query2!="empty"){
                $return['offset'] = strval($next_offset_count/$limit);
            }else{
                $return['offset'] = "empty";
            }
            $return['data'] = $query;
            return $return;
        }else{
            return 'empty';
        }
    }

    public function getFollowing($user_id,$offset,$limit){
        $query = DB::table('data_user_follow')
            ->select('minimi_user_data.user_id','minimi_user_data.fullname','minimi_user_data.photo_profile','minimi_user_data.user_uri','minimi_user_data.verified')
            ->join('minimi_user_data','data_user_follow.follow_id','=','minimi_user_data.user_id')
            ->where(['data_user_follow.user_id'=>$user_id,'status'=>1])
            ->skip($offset)->take($limit)
        ->get();
        if(count($query)){
            return $query;
        }else{
            return "empty";
        }
    }

    public function checkFollowing($user_id,$follow_ids){
        $query = DB::table('data_user_follow')
            ->where(['data_user_follow.user_id'=>$user_id,'status'=>1])
            ->whereIn('data_user_follow.follow_id',$follow_ids)
        ->get();
        if(count($query)){
            $collection = collect($query);
            $pluck = $collection->pluck('follow_id');
            $follow_ids = $pluck->all();
            return $follow_ids;
        }else{
            return "empty";
        }
    }

    //////// -- Follower -- /////////

    public function listFollower_exe($view_id, $user_id, $offset, $limit){
        $offset_count = $offset*$limit;
        $next_offset_count = ($offset+1)*$limit;
        $query = $this->getFollower($user_id,$offset_count,$limit);
        if($query!="empty"){
            if($view_id!=""){
                $collection = collect($query);
                $pluck = $collection->pluck('user_id');
                $user_ids = $pluck->all();
                $user_ids = array_diff($user_ids, array($view_id));
                $check = $this->checkFollowing($view_id,$user_ids);
                if($check!="empty"){
                   $user_ids = array_diff($user_ids, $check);
                }
            }
            foreach ($query as $row) {
                if($view_id!=""){
                    if(in_array($row->user_id,$user_ids)){
                        $row->follow = 0;
                    }else{
                        if($row->user_id==$view_id){
                            $row->follow = 2;
                        }else{
                            $row->follow = 1;
                        }
                    }
                }else{
                    $row->follow = 0;
                }
            }
            $query2 = $this->getFollower($user_id,$next_offset_count,$limit);
            if($query2!="empty"){
                $return['offset'] = strval($next_offset_count/$limit);
            }else{
                $return['offset'] = "empty";
            }
            $return['data'] = $query;
            return $return;
        }else{
            return 'empty';
        }
    }

    public function getFollower($user_id,$offset,$limit){
        $query = DB::table('data_user_follow')
            ->select('minimi_user_data.user_id','minimi_user_data.fullname','minimi_user_data.photo_profile','minimi_user_data.user_uri','minimi_user_data.verified')
            ->join('minimi_user_data','data_user_follow.user_id','=','minimi_user_data.user_id')
            ->where(['data_user_follow.follow_id'=>$user_id,'status'=>1])
            ->skip($offset)->take($limit)
        ->get();
        if(count($query)){
            return $query;
        }else{
            return "empty";
        }
    }
}