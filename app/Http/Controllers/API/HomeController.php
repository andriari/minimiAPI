<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;

use DB;

class HomeController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
	}

    public function mainPage(Request $request){
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
            $limit_video = (empty($data['limit_video']))?4:$data['limit_video'];
            $limit_article = (empty($data['limit_article']))?4:$data['limit_article'];
            
            $return['banner'] = DB::table('minimi_banner')
                ->select('banner_image', 'banner_alt', 'banner_title', 'banner_embedded_link')
                ->where('status',1)
                ->orderBy('updated_at','DESC')
            ->get();

            $review = app('App\Http\Controllers\Utility\UtilityController')->listReviewShort($limit,0);
            $return['review'] = $review;

            $reviewer = app('App\Http\Controllers\Utility\UtilityController')->listReviewer($limit_reviewer,0,$view_id);
            $return['reviewer'] = $reviewer;

            $video = app('App\Http\Controllers\Utility\UtilityController')->listVideoReviewShort($limit_video,0);
            $return['video'] = $video;

            $article = app('App\Http\Controllers\Utility\UtilityController')->listArticleShort($limit_article,0);
            $return['article'] = $article;

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'load_main_page_failed']);
		}
    }

    public function mainCommercePage(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                $view_id = null;
            }else{
                $currentUser = $data['user']->data;
                $view_id = $currentUser->user_id;
            }

            $limit = (empty($data['limit']))?4:$data['limit'];
            $limit_collection = (empty($data['limit_collection']))?10:$data['limit_collection'];
            $limit_reviewer = (empty($data['limit_reviewer']))?4:$data['limit_reviewer'];
            $limit_product = (empty($data['limit_product']))?4:$data['limit_product'];
            $limit_video = (empty($data['limit_video']))?4:$data['limit_video'];
            $limit_article = (empty($data['limit_article']))?4:$data['limit_article'];
            $limit_group_buy = (empty($data['limit_group_buy']))?4:$data['limit_group_buy'];
            
            $return['banner'] = DB::table('minimi_banner')
                ->select('banner_image', 'banner_alt', 'banner_title', 'banner_embedded_link')
                ->where('status',1)
                ->orderBy('updated_at','DESC')
            ->get();

            $category = app('App\Http\Controllers\Utility\UtilityController')->getCategory_exe(7,0);
            $return['category'] = $category;

            $collection = app('App\Http\Controllers\Utility\UtilityController')->listCollection($limit_collection,0);
            $return['collection'] = $collection;

            $product = app('App\Http\Controllers\Utility\UtilityController')->listProductPhys_exe($limit_product,0);
            $return['product'] = $product;

            $review = app('App\Http\Controllers\Utility\UtilityController')->listReviewShort($limit,0,1);
            $return['review'] = $review;

            $reviewer = app('App\Http\Controllers\Utility\UtilityController')->listReviewer($limit_reviewer,0, $view_id);
            $return['reviewer'] = $reviewer;

            $video = app('App\Http\Controllers\Utility\UtilityController')->listVideoReviewShort($limit_video,0);
            $return['video'] = $video;

            $article = app('App\Http\Controllers\Utility\UtilityController')->listArticleShort($limit_article,0);
            $return['article'] = $article;

            $group_buy = app('App\Http\Controllers\Utility\UtilityController')->listGroupBuyProducts($limit_group_buy,0);
            $return['group_buy'] = $group_buy;

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'load_main_page_failed']);
		}
    }

    public function loadContentMain(Request $request, $content_type){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                $currentUser = 'empty';
            }else{
                $currentUser = $data['user']->data;
            }

            $limit = (empty($data['limit']))?4:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
            
            switch ($content_type) {
                case 1: //video
                    $review = app('App\Http\Controllers\Utility\UtilityController')->listVideoReviewShort($limit,$offset);
                    $name = 'video';
                break;
                
                case 2: //review
                    $review = app('App\Http\Controllers\Utility\UtilityController')->listReviewShort($limit,$offset);
                    $name = 'review';
                break;
                
                case 3: //article
                    $review = app('App\Http\Controllers\Utility\UtilityController')->listArticleShort($limit,$offset);
                    $name = 'article';
                break;    
                
                default:
                    return response()->json(['code'=>4042,'message'=>'undefined_content_type']);
                break;
            }

            if(!count($review['data'])){
                return response()->json(['code'=>4043,'message'=>'content_not_found']);
            }

            $return[$name] = $review['data'];
            $return['offset'] = $review['offset'];
    
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'load_more_page_failed']);
        }
    }

    public function detailContent(Request $request, $content_id){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                $currentUser = 'empty';
            }else{
                $currentUser = $data['user']->data;
            }

            $return = app('App\Http\Controllers\Utility\UtilityController')->detailReview_exe($content_id,0);
            if($return=='empty'){
                    return response()->json(['code'=>4043,'message'=>'content_not_found']);
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'load_content_failed']);
        }
    }

    public function detailContentUri(Request $request, $content_uri){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                $currentUser = 'empty';
            }else{
                $currentUser = $data['user']->data;
            }

            $content_id = DB::table('minimi_content_post')->where('content_uri',$content_uri)->value('content_id');

            $return = app('App\Http\Controllers\Utility\UtilityController')->detailReview_exe($content_id,0);
            if($return=='empty'){
                return response()->json(['code'=>4043,'message'=>'content_not_found']);
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'load_content_failed']);
        }
    }

    public function detailCollection(Request $request, $collection_id){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                $currentUser = 'empty';
            }else{
                $currentUser = $data['user']->data;
            }

            $limit = (empty($data['limit']))?100:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
            $sorting = (empty($data['sorting']))?'brand_name:asc':$data['sorting'];

            $return = app('App\Http\Controllers\Utility\UtilityController')->detailCollection_exe($collection_id,$limit,$offset,$sorting);
            if($return=='empty'){
                return response()->json(['code'=>4043,'message'=>'collection_not_found']);
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'load_content_failed']);
        }
    }
}