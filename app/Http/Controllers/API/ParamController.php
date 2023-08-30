<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;

use DB;

class ParamController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }
    
    public function searchBrand(Request $request){
        $data = $request->all();
        try {
            $limit = (empty($data['limit']))?10:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
			$ret = app('App\Http\Controllers\Utility\UtilityController')->searchBrand_exe($data['search_query'],$limit,$offset);
            if($ret==FALSE){
                return response()->json(['code'=>4045,'message'=>'brand_not_found','search_query'=>$data['search_query']]);
            }
            return response()->json(['code'=>200,'message'=>'success','data'=>$ret['data'],'offset'=>$ret['offset'],'search_query'=>$data['search_query']]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'search_brand_failed']);
		}
    }

    public function getBrandList(Request $request){
        $data = $request->all();
		try {
            $limit = (empty($data['limit']))?10:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
            $ret = app('App\Http\Controllers\Utility\UtilityController')->getBrand_exe($limit,$offset);
            if($ret==FALSE){
                return response()->json(['code'=>4045,'message'=>'product_not_found']);
            }
            return response()->json(['code'=>200,'message'=>'success','data'=>$ret['data'],'offset'=>$ret['offset']]);
		} catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'posting_product_review_failed']);
		}
    }

    public function getCategoryList(Request $request){
        $data = $request->all();
		try {
            $limit = (empty($data['limit']))?10:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
            $ret = app('App\Http\Controllers\Utility\UtilityController')->getCategory_exe($limit,$offset);
            if($ret==FALSE){
                return response()->json(['code'=>4045,'message'=>'category_not_found']);
            }
            return response()->json(['code'=>200,'message'=>'success','data'=>$ret['data'],'offset'=>$ret['offset']]);
		} catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'posting_product_review_failed']);
		}
    }

    public function getSubcategoryList(Request $request, $category_id){
        $data = $request->all();
		try {
            $limit = (empty($data['limit']))?10:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
            $ret = app('App\Http\Controllers\Utility\UtilityController')->getSubcategory_exe($category_id,$limit,$offset);
            if($ret==FALSE){
                return response()->json(['code'=>4045,'message'=>'sub_category_not_found']);
            }
            return response()->json(['code'=>200,'message'=>'success','data'=>$ret['data'],'offset'=>$ret['offset']]);
		} catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'posting_product_review_failed']);
		}
    }

    public function getRating(Request $request){
        $data = $request->all();
		try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser=$data['user']->data;
            }

            $rating = DB::table('data_rating_param')
                ->select('data_rating_param.rp_id','data_rating_param.rating_name')
                ->where('data_rating_param.status', 1)
            ->get();

            return response()->json(['code'=>200,'message'=>'success','data'=>$rating]);    
		} catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'get_rating_failed']);
		}
    }

    public function getTrivia(Request $request){
        $data = $request->all();
		try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser=$data['user']->data;
            }

            $trivia = DB::table('data_trivia')
                ->select('data_trivia.trivia_id','data_trivia.trivia_question')
                ->where('status', 1)
            ->get();
            $col_trivia = collect($trivia);
            $trivia_ids = $col_trivia->pluck('trivia_id')->all();

            $answer = DB::table('data_trivia_answer')
                ->select('data_trivia_answer.answer_id','data_trivia_answer.trivia_id','data_trivia_answer.answer_content')
                ->whereIn('trivia_id',$trivia_ids)
                ->where('status',1)
            ->get();
            $col_answer = collect($answer);

            foreach ($trivia as $row) {
                $finds = $col_answer->where('trivia_id',$row->trivia_id)->all();
                $answer = array();
                foreach ($finds as $find) {
                    $col['answer_id'] = $find->answer_id;
                    $col['answer_content'] = $find->answer_content;
                    array_push($answer,$col);
                }
                $row->answer = $answer;
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$trivia]);    
		} catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'get_trivia_failed']);
		}
    }
}