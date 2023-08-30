<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;

use DB;

class FeedController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
	}

    public function mainFeed(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                $view_id = null;
            }else{
                $currentUser = $data['user']->data;
                $view_id = $currentUser->user_id;
            }

            $limit = (empty($data['limit']))?4:$data['limit'];
            $limit_reviewer = (empty($data['limit_reviewer']))?4:$data['limit_reviewer'];

            $feed = app('App\Http\Controllers\Utility\UtilityController')->listFeed($limit,$view_id);
            $return['feed'] = $feed;

            $reviewer = app('App\Http\Controllers\Utility\UtilityController')->listReviewer($limit_reviewer,0, $view_id);
            $return['reviewer'] = $reviewer;

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'load_main_feed_failed']);
		}
    }

    public function loadMoreFeed(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                $view_id = null;
            }else{
                $currentUser = $data['user']->data;
                $view_id = $currentUser->user_id;
            }

            $limit = (empty($data['limit']))?4:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];

            if($offset!=0){
                $arr = (empty($data['content_ids']))?array():explode(',',$data['content_ids']);
            }else{
                $arr = array();
            }
            
            $feed = app('App\Http\Controllers\Utility\UtilityController')->listFeed($limit,$view_id,$arr);
            $return['feed'] = $feed['data'];

            return response()->json(['code'=>200,'message'=>'success','data'=>$feed['data'],'content_ids'=>$feed['content_ids'],'offset'=>$feed['offset']]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'load_main_feed_failed']);
		}
    }

    /**
     * Main Feed with Date
    **/
    public function mainFeedDate(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                $view_id = null;
            }else{
                $currentUser = $data['user']->data;
                $view_id = $currentUser->user_id;
            }

            $period = (empty($data['period']))?'7 days':$data['period'];
            $limit_reviewer = (empty($data['limit_reviewer']))?4:$data['limit_reviewer'];

            $created_at = DB::table('minimi_content_post')
                ->join('minimi_user_data','minimi_user_data.user_id','=','minimi_content_post.user_id')
                ->leftJoin('minimi_product','minimi_content_post.product_id','=','minimi_product.product_id')
                ->leftJoin('data_category','data_category.category_id','=','minimi_product.category_id')
                ->leftJoin('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->leftJoin('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                ->where([
                    'minimi_content_post.status'=>1,
                    'content_curated'=>1
                ])
                ->orderBy('minimi_content_post.created_at','desc')
            ->value('minimi_content_post.created_at');
            if($created_at==null){
                $arr['start_date']=date("Y-m-d H:i:s", mktime(23, 59, 59, date('m'), date('d'), date('Y')));
            }else {
                $parse_date = $this->parseDate($created_at,'start');
                $arr['start_date']=date('Y-m-d H:i:s',strtotime($parse_date));
            }
            $end_date= date('Y-m-d H', strtotime($arr['start_date'].' - '.$period));
            $parse_date = $this->parseDate($end_date,'end');
            $arr['end_date']=date('Y-m-d H:i:s',strtotime($parse_date));

            $feed = app('App\Http\Controllers\Utility\UtilityController')->listFeedbyDate($view_id,$arr,0,10);
            $return['feed'] = $feed['data'];
            $return['offset'] = $feed['offset'];
            $return['next_date'] = $feed['next_date'];

            $reviewer = app('App\Http\Controllers\Utility\UtilityController')->listReviewer($limit_reviewer,0, $view_id);
            $return['reviewer'] = $reviewer;

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'load_main_feed_failed']);
		}
    }

    public function loadMoreFeedDate(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                $view_id = null;
            }else{
                $currentUser = $data['user']->data;
                $view_id = $currentUser->user_id;
            }
            
            $offset = (empty($data['offset']))?0:$data['offset'];
            if($data['start_date']!='empty'){
                $start_date = (empty($data['start_date']))?date('Y-m-d'):date('Y-m-d', strtotime($data['start_date']));
                $period = (empty($data['period']))?'7 days':$data['period'];
    
                $parse_date = $this->parseDate($start_date,'start',1);
                $arr['start_date']=date('Y-m-d H:i:s',strtotime($parse_date));
    
                $end_date= date('Y-m-d H', strtotime($arr['start_date'].' - '.$period));
                $parse_date = $this->parseDate($end_date,'end');
                $arr['end_date']=date('Y-m-d H:i:s',strtotime($parse_date));
            }else{
                $arr['start_date']='empty';
                $arr['end_date']='empty';
            }
            
            $feed = app('App\Http\Controllers\Utility\UtilityController')->listFeedbyDate($view_id,$arr,$offset,10);
            $return['feed'] = $feed['data'];
            $return['offset'] = $feed['offset'];
            $return['next_date'] = $feed['next_date'];
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'load_main_feed_failed']);
		}
    }

    /**
     * Utility Function
    **/

    private function parseDate($date, $mode, $date_mode=0){
        if($date_mode==0){
            $arr = explode(' ',$date);
            $date_arr = explode('-',$arr[0]);
        }elseif($date_mode==1){
            $date_arr = explode('-',$date);
        }
        
        if($mode=='end'){
            $date=date("Y-m-d H:i:s", mktime(0, 0, 0, $date_arr[1], $date_arr[2], $date_arr[0]));
        }elseif($mode=='start'){
            $date=date("Y-m-d H:i:s", mktime(23, 59, 59, $date_arr[1], $date_arr[2], $date_arr[0]));
        }

        return $date;
    }
}