<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;

use DB;

class ProductController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
	}

    public function searchProduct(Request $request){
        $data = $request->all();
		try {
			if(empty($data['token'])){
                $currentUser=array();
            }else{
                $currentUser=$data['user']->data;
            }

            $search_query = $data['search_query'];
            $limit=$data['limit'];
            $offset_count=$data['offset']*$limit;
            $next_offset_count=($data['offset']+1)*$limit;
            $mode = (!empty($data['mode']))?$data['mode']:'all';
            $crumb = 0;
            switch ($mode) {
                case 'wishlist':
                    if(empty($currentUser)){
                        return response()->json(['code'=>4034,'message'=>'login_to_continue']);
                    }

                    $query = DB::table('minimi_product')
                        ->select('minimi_product.product_id','product_uri','product_type','product_name','product_price','product_rating','brand_name','category_name','subcat_name')
                        ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                        ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                        ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                        ->join('minimi_product_wishlist','minimi_product_wishlist.product_id','=','minimi_product.product_id')
                        ->where([
                            'minimi_product.status'=>1,
                            'minimi_product_wishlist.status'=>1,
                            'minimi_product_wishlist.user_id'=>$currentUser->user_id
                        ])
                        ->where(function ($query) use ($search_query){
                            $query->where('product_name','like','%'.$search_query.'%')
                                ->orWhere('brand_name','like','%'.$search_query.'%');
                        });
                break;
                case 'category':
                    $category_name = DB::table('data_category')->where(['category_id'=>$data['category_id'],'status'=>1])->value('category_name');
                    if($category_name==null){
                        return response()->json(['code'=>4045,'message'=>'category_not_found']);
                    }
                    $query = DB::table('minimi_product')
                        ->select('minimi_product.product_id','product_uri','product_type','product_name','product_price','product_rating','brand_name','category_name','subcat_name')
                        ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                        ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                        ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                        ->where([
                            'minimi_product.status'=>1,
                            'minimi_product.category_id'=>$data['category_id']
                        ])
                        ->where(function ($query) use ($search_query){
                            $query->where('product_name','like','%'.$search_query.'%')
                                ->orWhere('brand_name','like','%'.$search_query.'%');
                        });
                    $crumb = 1;
                break;
                default:
                    $query = DB::table('minimi_product')
                        ->select('minimi_product.product_id','product_uri','product_type','product_name','product_price','product_rating','brand_name','category_name','subcat_name')
                        ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                        ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                        ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                        ->where('minimi_product.status',1)
                        ->where(function ($query) use ($search_query){
                            $query->where('product_name','like','%'.$search_query.'%')
                                ->orWhere('brand_name','like','%'.$search_query.'%');
                        });
                break;
            }

            $query = $query->skip($offset_count)->take($limit)->get();

            
            if(!count($query)){
                return response()->json(['code'=>4045,'message'=>'product_not_found','search_query'=>$search_query]);
            }

            $col_prod = collect($query);
            $product_ids = $col_prod->pluck('product_id')->all();
            
            $images = DB::table('minimi_product_gallery')
                ->select('product_id','prod_gallery_picture as pict','prod_gallery_alt as alt','prod_gallery_title as title')
                ->whereIn('product_id',$product_ids)
                ->where('main_poster',1)
            ->get();
            $col_image = collect($images);

            $rating = DB::table('minimi_content_rating_tab')
                ->select('product_id','value')
                ->whereIn('product_id',$product_ids)
                ->where('tag','review_count')
            ->get();
            $col_rating = collect($rating);

            foreach ($query as $row) {
                $row->discount = '5%';
			    $row->price_before_discount = (1+0.05)*$row->product_price;
                $find = $col_image->where('product_id',$row->product_id)->first();
                if($find==null){
                    $row->pict = "";
                    $row->alt = "";
                    $row->title = "";
                }else{
                    $row->pict = $find->pict;
                    $row->alt = $find->alt;
                    $row->title = $find->title;
                }

                $rating = $col_rating->where('product_id',$row->product_id)->first();
                if($rating==null){
                    $row->review_count = 0;
                }else{
                    $row->review_count = $rating->value;
                }
            }

            $next_offset = 'empty';
            if(count($query)>=$limit){
                switch ($mode) {
                    case 'wishlist':
                        if(empty($currentUser)){
                            return response()->json(['code'=>4034,'message'=>'login_to_continue']);
                        }
    
                        $query2 = DB::table('minimi_product')
                            ->select('minimi_product.product_id')
                            ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                            ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                            ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                            ->join('minimi_product_wishlist','minimi_product_wishlist.product_id','=','minimi_product.product_id')
                            ->where([
                                'minimi_product.status'=>1,
                                'minimi_product_wishlist.status'=>1,
                                'minimi_product_wishlist.user_id'=>$currentUser->user_id
                            ])
                            ->where(function ($query2) use ($search_query){
                                $query2->where('product_name','like','%'.$search_query.'%')
                                    ->orWhere('brand_name','like','%'.$search_query.'%');
                            });
                    break;
                    case 'category':
                        $query2 = DB::table('minimi_product')
                            ->select('minimi_product.product_id')
                            ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                            ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                            ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                            ->where([
                                'minimi_product.status'=>1,
                                'minimi_product.category_id'=>$data['category_id']
                            ])
                            ->where(function ($query2) use ($search_query){
                                $query2->where('product_name','like','%'.$search_query.'%')
                                    ->orWhere('brand_name','like','%'.$search_query.'%');
                            });
                    break;
                    default:
                        $query2 = DB::table('minimi_product')
                            ->select('minimi_product.product_id')
                            ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                            ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                            ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                            ->where('minimi_product.status',1)
                            ->where(function ($query2) use ($search_query){
                                $query2->where('product_name','like','%'.$search_query.'%')
                                    ->orWhere('brand_name','like','%'.$search_query.'%');
                            });
                    break;
                }
    
                $query2 = $query2->skip($next_offset_count)->take($limit)->get();
                
                if(count($query2)>0){
                    $next_offset = $next_offset_count/$limit;
                }
            }
            
            if($crumb==1){
                return response()->json(['code'=>200,'message'=>'success','data'=>$query,'offset'=>$next_offset,'limit'=>$limit,'search_query'=>$search_query,'crumb'=>array('Kategori',$category_name)]);
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$query,'offset'=>$next_offset,'limit'=>$limit,'search_query'=>$search_query]);
		} catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'detail_product_failed']);
		}
    }

    public function searchProductByTags(Request $request){
        $data = $request->all();
		try {
			if(empty($data['token'])){
                $currentUser=array();
            }else{
                $currentUser=$data['user']->data;
            }

            $search_query = $data['search_query'];
            $limit=$data['limit'];
            $offset_count=$data['offset']*$limit;
            $next_offset_count=($data['offset']+1)*$limit;
            
            $query_tag = DB::table('minimi_tags')
                ->select('tag_id')
                ->where('tag','like', $search_query.'%')
            ->get();
            $col_tag = collect($query_tag);
            $tag_ids = $col_tag->pluck('tag_id')->all();
            $query_relation = DB::table('relation_product_tag')
                ->whereIn('tag_id',$tag_ids)
            ->get();
            $col_rel = collect($query_relation);
            $product_ids = $col_rel->pluck('product_id')->all();
            
            $query = DB::table('minimi_product')
                ->select('brand_name','category_name','subcat_name','product_name','product_uri','product_id')
                ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                ->where('minimi_product.status',1)
                ->whereIn('product_id',$product_ids)
            ->skip($offset_count)->take($limit)->get();
            
            $next_offset = 'empty';
            if(count($query)>=$limit){
                $query2 = DB::table('minimi_product')
                    ->select('brand_name','category_name','subcat_name','product_name','product_uri','product_id')
                    ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                    ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                    ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                    ->where('minimi_product.status',1)
                    ->whereIn('product_id',$product_ids)
                ->skip($next_offset_count)->take($limit)->get();
    
                if(count($query2)>0){
                    $next_offset = $next_offset_count/$limit;
                }
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$query,'offset'=>$next_offset,'limit'=>$limit,'search_query'=>$search_query]);
		} catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'detail_product_failed']);
		}
    }

    public function detailProductform(Request $request, $product_uri){
        $data = $request->all();
		try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser=$data['user']->data;
            }
            
            $date = date('Y-m-d H:i:s');

            $query = DB::table('minimi_product')
                ->select('minimi_product.product_id','product_name','brand_name','category_name','subcat_name','product_rating','prod_gallery_picture as pict','prod_gallery_alt as alt','prod_gallery_title as title')
                ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                ->join('minimi_product_gallery','minimi_product_gallery.product_id','=','minimi_product.product_id')
                ->where('minimi_product.status',1)
                ->where('product_uri',$product_uri)
            ->first();

            if(empty($query)){
                return response()->json(['code'=>4045,'message'=>'product_not_found']);
            }

            $task = DB::table('point_task')->select('task_id', 'task_name', 'task_value', 'task_type', 'task_limit')->where('content_tag', 'post_2')->first();

            if(!empty($data['order_id'])&&!empty($data['item_id'])){
                $item = DB::table('commerce_booking')
                    ->join('commerce_shopping_cart_item','commerce_booking.cart_id','=','commerce_shopping_cart_item.cart_id')
                    ->where([
                        'order_id'=>$data['order_id'],
                        'item_id'=>$data['item_id'],
                        'status'=>1
                    ])
                ->first();

                if(empty($item)){
                    return response()->json(['code'=>4045,'message'=>'product_not_found']);
                }

                $exp_period = DB::table('data_param')->where('param_tag','order_review_expire_period')->value('param_value');
                $date_exp = date('Y-m-d H:i:s',strtotime($item->received_at."+".$exp_period)); //expire_review
                if($date_exp<$date){
                    return response()->json(['code'=>4049,'message'=>'item_review_expired']);
                }
                if($item->reviewed==1){
                    return response()->json(['code'=>4048,'message'=>'item_already_reviewed']);
                }
                $query->expire_date = $date_exp;
            }else{
                $query->expire_date = "";
            }
            $query->point = $task->task_value;

            $trivia = DB::table('relation_product_trivia')
                ->select('data_trivia.trivia_id','data_trivia.trivia_question')
                ->join('data_trivia', 'data_trivia.trivia_id','=','relation_product_trivia.trivia_id')
                ->where([
                    'product_id'=>$query->product_id,
                    'relation_product_trivia.status'=>1,
                    'data_trivia.status'=>1
                ])
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

            $rating = DB::table('relation_product_rating')
                ->select('data_rating_param.rp_id','data_rating_param.rating_name')
                ->join('data_rating_param', 'data_rating_param.rp_id','=','relation_product_rating.rp_id')
                ->where([
                    'product_id'=>$query->product_id,
                    'relation_product_rating.status'=>1,
                    'data_rating_param.status'=>1
                ])
            ->get();

            $return['product'] = $query;
            $return['rating'] = $rating;
            $return['trivia'] = $trivia;

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);    
		} catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'detail_product_failed']);
		}
    }

    public function listDigitalProduct(Request $request){
        $data = $request->all();
		try {
			if(empty($data['token'])){
                $currentUser=array();
            }else{
                $currentUser=$data['user'];
            }

            $limit = (empty($data['limit']))?10:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
            $offset_count = $offset*$limit;
            $next_offset_count = ($offset+1)*$limit;

            $query = DB::table('minimi_product')
                ->select('product_id','product_uri','product_name','product_sub_name','product_short_desc','product_desc','product_price','category_name','subcat_name','last_date')
                ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->where('minimi_product.status',1)
                ->where('minimi_product.product_type',2)
                ->where('minimi_product.product_price','>',0)
            ->skip($offset_count)->take($limit)->get();

            $col_prod = collect($query);
		    $product_ids = $col_prod->pluck('product_id')->all();

            $bundle = DB::table('minimi_product_digital')
                ->select('product_id','voucher_count', 'voucher_duration', 'voucher_minimum', 'voucher_value', 'discount_type', 'voucher_type', 'voucher_name', 'voucher_desc', 'voucher_tnc')
                ->whereIn('product_id',$product_ids)
            ->get();
            $col_bundle = collect($bundle);

            $images = DB::table('minimi_product_gallery')
                ->select('product_id','prod_gallery_picture as pict','prod_gallery_alt as alt','prod_gallery_title as title')
                ->whereIn('product_id',$product_ids)
                ->where('main_poster',1)
            ->get();
            $col_image = collect($images);

            foreach ($query as $row) {
                $find = $col_bundle->where('product_id',$row->product_id)->first();
                if($find==null){
                    $row->bundle = array();
                }else{
                    unset($find->product_id);
                    $row->bundle = $find;
                }

                $find_image = $col_image->where('product_id',$row->product_id)->first();
                if($find_image==null){
                    $row->pict = "";
                    $row->alt = "";
                    $row->title = "";
                }else{
                    $row->pict = $find_image->pict;
                    $row->alt = $find_image->alt;
                    $row->title = $find_image->title;
                }
            }

            $next_offset = 'empty';
            if(count($query)>=$limit){
                $query2 = DB::table('minimi_product')
                    ->select('product_id')
                    ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                    ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                    ->where('minimi_product.status',1)
                    ->where('minimi_product.product_type',2)
                    ->where('minimi_product.product_price','>',0)
                ->skip($next_offset_count)->take($limit)->get();
                
                if(count($query2)>0){
                    $next_offset = $next_offset_count/$limit;
                }
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$query,'offset'=>$next_offset]);    
		} catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'detail_product_failed']);
		}
    }
    
    public function detailDigitalProduct(Request $request, $product_uri){
        $data = $request->all();
		try {
			if(empty($data['token'])){
                $currentUser=array();
            }else{
                $currentUser=$data['user'];
            }

            $query = DB::table('minimi_product')
                ->select('product_id','product_uri','product_name','product_sub_name','product_short_desc','product_desc','product_price','category_name','subcat_name')
                ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->where('minimi_product.status',1)
                ->where('minimi_product.product_type',2)
                ->where('minimi_product.product_price','>',0)
                ->where('product_uri',$product_uri)
            ->first();

            if(empty($query)){
                return response()->json(['code'=>4045,'message'=>'product_not_found']);
            }

            $bundle = DB::table('minimi_product_digital')
                ->select('voucher_count', 'voucher_duration', 'voucher_minimum', 'voucher_value', 'discount_type', 'voucher_type', 'voucher_name', 'voucher_desc', 'voucher_tnc')
                ->where('product_id',$query->product_id)
            ->first();

            $images = DB::table('minimi_product_gallery')
                ->select('prod_gallery_picture as pict','prod_gallery_alt as alt','prod_gallery_title as title')
                ->where([
                    'product_id'=>$query->product_id,
                    'status'=>1
                ])
                ->orderBy('main_poster')
                ->orderBy('created_at')
            ->get();

            $query->product_buyable = 1;

            $return['info'] = $query;
            $return['image'] = $images;
            $return['bundle'] = $bundle;
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);    
		} catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'detail_product_failed']);
		}
    }

    public function getProductVariant(Request $request, $product_uri){
        $data = $request->all();
		try {
			if(empty($data['token'])){
                $view_id=null;
            }else{
                $currentUser=$data['user']->data;
                $view_id=$currentUser->user_id;
            }

            $product = DB::table('minimi_product')
                ->select('product_id','product_name')
                ->where([
                    'product_uri'=>$product_uri,
                    'status'=>1
                ])
                ->where('product_price','>',0)
            ->first();

            if(empty($product)){
                return response()->json(['code'=>4141,'message'=>'product_not_buyable']);
            }

            $variant = DB::table('minimi_product_variant')
                ->select('variant_id','variant_sku','variant_name','stock_count','stock_price')
                ->where([
                    'product_id'=>$product->product_id,
                    'status'=>1,
                    'publish'=>1
                ])
                ->whereNotNull('stock_price')
                ->where('stock_price','>',0)
            ->get();

            foreach ($variant as $row) {
                $arr = explode($product->product_name.' ',$row->variant_name);
                if(count($arr)>1){
                    $row->variant_name = $arr[1];
                }
                $row->discount = '5%';
			    $row->price_before_discount = (1 + 0.05) * $row->stock_price;
            }
            
            return response()->json(['code'=>200,'message'=>'success','data'=>$variant]);
		} catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'load_variant_failed']);
		}
    }

    public function detailProduct(Request $request, $product_uri){
        $data = $request->all();
		//try {
			if(empty($data['token'])){
                $view_id=null;
            }else{
                $currentUser=$data['user']->data;
                $view_id=$currentUser->user_id;
            }

            $limit = (empty($data['limit']))?10:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
            $offset_count = $offset*$limit;
            $next_offset_count = ($offset+1)*$limit;

            $query = DB::table('minimi_product')
                ->select('product_id','product_uri','product_name','product_desc','product_price','product_price_gb','product_weight','product_delivery_flag', 'product_condition', 'product_minimum_purchase', 'product_purchase_count_fake as product_purchase_count', 'product_rating','brand_name','category_name','subcat_name','last_date')
                ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                ->where('minimi_product.status',1)
                ->where('minimi_product.product_type',1)
                ->where('product_uri',$product_uri)
            ->first();

            if(empty($query)){
                $redirect = DB::table('data_redirect_order')
                    ->select('data_redirect_order.*')
                    ->join('minimi_product','minimi_product.product_uri','=','data_redirect_order.uri_from')
                    ->where([
                        'uri_from' => $product_uri,
                        'data_redirect_order.status' => 1
                    ])
                ->first();
                
                if(empty($redirect)){
                    return response()->json(['code'=>4045,'message'=>'product_not_found']);
                }

                $red['full_url'] = $redirect->prefix_to.$redirect->uri_to;
                $red['prefix'] = $redirect->prefix_to;
                $red['uri'] = $redirect->uri_to;
                return response()->json(['code'=>201,'message'=>'redirect','data'=>$red]);
            }

            $images = DB::table('minimi_product_gallery')
                ->select('prod_gallery_picture as pict','prod_gallery_alt as alt','prod_gallery_title as title')
                ->where([
                    'product_id'=>$query->product_id,
                    'status'=>1
                ])
                ->orderBy('main_poster','DESC')
            ->get();

            $variant = DB::table('minimi_product_variant')
                ->select('variant_id','variant_sku','variant_name','stock_weight','stock_count','stock_price','stock_price_gb','stock_restriction_count')
                ->where([
                    'product_id'=>$query->product_id,
                    'status'=>1,
                    'publish'=>1
                ])
            ->get();

            foreach ($variant as $value) {
                $value->stock_restriction_count = ($value->stock_restriction_count==null)?99:$value->stock_restriction_count;
            }
            
            if($query->product_price>0){
                if($query->product_price_gb>0){
                    $product_buyable = 2;
                }else{
                    $product_buyable = 1;
                }
            }else{
                if($query->product_price_gb>0){
                    $product_buyable = 3;
                }else{
                    $product_buyable = 0;
                }
            }

            $query->product_price_range = '';

            if($product_buyable==1){
                $price = array();
                foreach($variant as $row){
                    $arr = explode($query->product_name.' ',$row->variant_name);
                    if(count($arr)>1){
                        $row->variant_name = $arr[1];
                    }
                    $row->discount = '5%';
                    if($row->stock_price>0){
                        $row->price_before_discount = (1 + 0.05) * $row->stock_price;
                        array_push($price,$row->stock_price);
                    }else{
                        $row->price_before_discount = 0;
                    }
                }
                
                if(!empty($price)){
                    sort($price);
                    $product_price_range = 'Rp '.number_format($price[0], 0, ',', '.');
                    if(count($price)>1){
                        if($price[count($price)-1] != $price[0]){
                            $product_price_range .= ' - Rp '.number_format($price[count($price)-1], 0, ',', '.');
                        }
                    }
                }else{
                    $product_price_range = '';
                }

                $query->discount = '5%';
                $query->price_before_discount = (1 + 0.05) * $query->product_price;
                $query->product_price_range = $product_price_range;
            }elseif($product_buyable==2||$product_buyable==3){
                foreach($variant as $row){
                    $arr = explode($query->product_name.' ',$row->variant_name);
                    if(count($arr)>1){
                        $row->variant_name = $arr[1];
                    }
                }
            }

            $tab = $this->tabulateReview($query->product_id,$query->last_date);
            $query->total_review = $tab['total_review'];
            $query->star_count = $tab['star_count'];
            $query->product_buyable = $product_buyable;
            if($query->product_purchase_count==0){
                $num = rand(5,55);
                $query->product_purchase_count = $num;
                DB::table('minimi_product')->where('product_id',$query->product_id)->update([
                    'product_purchase_count_fake'=>$num
                ]);
            }

            if(!empty($currentUser)){
                $wish_id = DB::table('minimi_product_wishlist')
                    ->where([    
                        'user_id'=>$currentUser->user_id,
                        'product_id'=>$query->product_id,
                        'status'=>1
                    ])
                ->value('wish_id');

                if($wish_id!=null && $wish_id!=''){
                    $query->loved = 1;
                }else{
                    $query->loved = 0;
                }
            }else{
                $query->loved = 0;
            }

            $review = app('App\Http\Controllers\Utility\UtilityController')->listReviewFull($query->product_id, $limit, $offset, $view_id);

            $return['info'] = $query;
            $return['image'] = $images;
            $return['review'] = $review;
            $return['variant'] = $variant;
            if($product_buyable==2||$product_buyable==3){
                $return['group_buy'] = app('App\Http\Controllers\API\GroupBuyController')->getGroupBuyProduct($query->product_id);
            }
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);    
		//} catch (QueryException $ex){
        //    return response()->json(['code'=>4050,'message'=>'detail_product_failed']);
		//}
    }

    public function loadContentPerProduct(Request $request, $product_uri){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                $view_id=null;
            }else{
                $currentUser=$data['user']->data;
                $view_id=$currentUser->user_id;
            }

            $limit = (empty($data['limit']))?10:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
        
            $product_id = DB::table('minimi_product')->where('product_uri',$product_uri)->value('product_id');
            if($product_id==null){
                return response()->json(['code'=>4045,'message'=>'product_not_found']);
            }
            
            $limit = (empty($data['limit']))?20:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
            $content_type = (empty($data['content_type']))?0:$data['content_type'];
            $review = app('App\Http\Controllers\Utility\UtilityController')->listReviewFull($product_id, $limit, $offset, $view_id);
            $return['review'] = $review['data'];
            $return['offset'] = $review['offset'];
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'private_profile_content_load_failed']);
        }
    }

    public function listProductPhysical(Request $request){
        $data = $request->all();
        try {
            $limit = (empty($data['limit']))?10:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
            
            $product = app('App\Http\Controllers\Utility\UtilityController')->listProductPhys_exe($limit, $offset);
            $return['product'] = $product['data'];
            $return['offset'] = $product['offset'];
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'product_list_load_failed']);
        }
    }

    public function getProductCategoryList(Request $request, $category_id){
        $data = $request->all();
        try {
            $limit = (empty($data['limit']))?10:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
        
            $category_name = DB::table('data_category')->where(['category_id'=>$category_id,'status'=>1])->value('category_name');
            if($category_name==null){
                return response()->json(['code'=>4045,'message'=>'category_not_found']);
            }
            
            $limit = (empty($data['limit']))?20:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
            $product = app('App\Http\Controllers\Utility\UtilityController')->listProductCategory($category_id, $limit, $offset);
            $return['product'] = $product['data'];
            $return['offset'] = $product['offset'];
            $return['crumbs'] = array('Kategori',$category_name);
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'product_category_load_failed']);
        }
    }

    public function getProductSubcategoryList(Request $request, $subcat_id){
        $data = $request->all();
        try {
            $limit = (empty($data['limit']))?10:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
        
            $subcat = DB::table('data_category_sub')
                ->select('category_name','category_id','subcat_name')
                ->join('data_category','data_category.category_id','=','data_category_sub.category_id')
                ->where(['subcat_id'=>$subcat_id,'status'=>1])
            ->first();
            if(empty($subcat)){
                return response()->json(['code'=>4045,'message'=>'subcategory_not_found']);
            }
            
            $limit = (empty($data['limit']))?20:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
            $product = app('App\Http\Controllers\Utility\UtilityController')->listProductSubcategory($subcat_id, $limit, $offset);
            $return['product'] = $product['data'];
            $return['offset'] = $product['offset'];
            $return['crumbs'] = array('Kategori',$subcat['category_name'],$subcat['subcat_name']);
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'product_category_load_failed']);
        }
    }

    public function getProductBrandList(Request $request, $brand_id){
        $data = $request->all();
        try {
            $limit = (empty($data['limit']))?10:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
        
            $brand_code = DB::table('data_brand')->where(['brand_id'=>$brand_id,'status'=>1])->value('brand_code');
            if($brand_code==null){
                return response()->json(['code'=>4045,'message'=>'brand_not_found']);
            }
            
            $limit = (empty($data['limit']))?20:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
            $product = app('App\Http\Controllers\Utility\UtilityController')->listProductBrand($brand_id, $limit, $offset);
            $return['product'] = $product['data'];
            $return['offset'] = $product['offset'];
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'product_category_load_failed']);
        }
    }

    /**
     * Utility Functionality
    **/

    public function tabulateReview($product_id,$last_date){
        $date = date('Y-m-d H:i:s');
        if($last_date==null){
            $count = app('App\Http\Controllers\Utility\UtilityController')->recapperCount($product_id,1);
        }else{
            $last_date2 = date('Y-m-d H:i:s', strtotime($last_date. ' + 6 hours'));
            if($last_date2 > $date){
                $count = app('App\Http\Controllers\Utility\UtilityController')->recapperShow($product_id);
            }else{
                $count = app('App\Http\Controllers\Utility\UtilityController')->recapperCount($product_id,1);
            }
        }
        return $count;
    }
}