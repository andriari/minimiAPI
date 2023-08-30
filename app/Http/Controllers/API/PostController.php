<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;

use DB;

class PostController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }
    
    public function getReviewCategoryList(Request $request, $category_id){
        $data = $request->all();
		try {
            $limit = (empty($data['limit']))?10:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
        
            $category = DB::table('data_category')->select('category_id','category_name','category_picture')->where(['category_id'=>$category_id,'status'=>1])->first();
            if(empty($category)){
                return response()->json(['code'=>4045,'message'=>'category_not_found']);
            }
            
            $limit = (empty($data['limit']))?20:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
            $review = app('App\Http\Controllers\Utility\UtilityController')->listReviewCategory($category_id, $limit, $offset);
            $return['category'] = $category;
            $return['review'] = $review;
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'list_review_category_failed']);
        }
    }

    public function postReviewPropose(Request $request){
        $data = $request->all();
		try {
			if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            //$thumb = DB::table('minimi_product_gallery')->where('product_id',$data['product_id'])->value('prod_gallery_picture as pict');

            $insert_review['category_id'] = $data['category_id'];
            $insert_review['subcat_id'] = $data['subcat_id'];
            $insert_review['product_id'] = null;
            $insert_review['content_title'] = $data['product_name'];
            $insert_review['content_subtitle'] = $data['brand'];
            $insert_review['user_id'] = $currentUser->user_id;
            $insert_review['content_type'] = 4;
            $insert_review['content_text'] = $data['text'];
            $insert_review['content_embed_link'] = $data['link'];
            $insert_review['content_thumbnail'] = '';
            $date = date('Y-m-d H:i:s');
            $insert_review['created_at'] = $date;
            $insert_review['updated_at'] = $date;
            $content_id = DB::table('minimi_content_post')->insertGetId($insert_review);

            //rating
            $ratings = $data['rating'];
            if(empty($ratings)){
                return response()->json(['code'=>7001,'message'=>'rating_can_not_be_emptied']);
            }
            $total=0;
            $i=0;
            foreach ($ratings as $rating) {
                $insert_rating['content_id'] = $content_id;
                $insert_rating['rp_id'] = $rating['rp_id'];
                $insert_rating['rating_value'] = floatval($rating['value']);
                $date = date('Y-m-d H:i:s');
                $insert_rating['created_at'] = $date;
                $insert_rating['updated_at'] = $date;
                DB::table('minimi_content_rating')->insert($insert_rating);
                $total += $insert_rating['rating_value'];
                $i++;
            }
            $avg = $total/$i;
            $update['content_rating'] = round($avg,2);
            DB::table('minimi_content_post')->where('content_id',$content_id)->update($update);

            //trivia
            $trivias = $data['trivia'];
            if(!empty($trivias)){
                foreach ($trivias as $trivia) {
                    $insert_trivia['content_id'] = $content_id;
                    $insert_trivia['trivia_id'] = $trivia['trivia_id'];
                    $insert_trivia['answer_id'] = $trivia['answer_id'];
                    $date = date('Y-m-d H:i:s');
                    $insert_trivia['created_at'] = $date;
                    $insert_trivia['updated_at'] = $date;
                    DB::table('minimi_content_trivia')->insert($insert_trivia);
                }
            }

            //image
            if($request->hasFile('photo')){
                $photos = $data['photo'];
                $insert = array();
                $j=0;
                foreach($photos as $photo){
                    if($photo['image']!=null){
                        $destinationPath = 'public/review/product';
                        $image = $photo['image'];
                        $image_path = app('App\Http\Controllers\Utility\UtilityController')->upload_image($image,$destinationPath);
                        switch ($image_path) {
                            case 'too_big':
                                //nothing happen
                            break;
                            
                            case 'not_an_image':
                                //nothing happen
                            break;
                            
                            default:
                                $insert_image['content_id'] = $content_id;
                                $insert_image['cont_gallery_picture'] = $image_path;
                                $date = date('Y-m-d H:i:s');
                                $image_name = app('App\Http\Controllers\Utility\UtilityController')->altTitleImage($image_path);
                                $insert_image['cont_gallery_alt'] = $image_name;
                                $insert_image['cont_gallery_title'] = $image_name;
                                $insert_image['created_at'] = $date;
                                $insert_image['updated_at'] = $date;
                                DB::table('minimi_content_gallery')->insert($insert_image);
                                $j++;
                            break;
                        }
                    }
                }
            }
            
            $notify['content_id'] = $content_id;
            $notify['notification_message'] = 'User '.$currentUser->fullname.' baru saja mengajukan produk baru.';
            $notify['notification_target'] = env('DASHBOARD_URL').'content/review/'.$content_id;
            $notify['notification_type'] = 4;
            
            app('App\Http\Controllers\Utility\NotificationController')->notifyAdmin($notify);
            
            $return['review_id'] = $content_id;
            $return['content_type'] = 4;
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
		} catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'posting_product_review_failed']);
        }
    }

    public function postReview(Request $request){
        $data = $request->all();
		try {
			if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $linked_product_id = DB::table('minimi_product')->where('product_id',$data['product_id'])->value('linked_product_id');
            if($linked_product_id!=null){
                $product_id = $linked_product_id;
            }else{
                $product_id = $data['product_id'];
            }
            
            $prods = DB::table('minimi_product')->select('category_id','subcat_id','brand_id')->where('product_id',$product_id)->first();
            if(empty($prods)){
                return response()->json(['code'=>7002,'message'=>'product_not_found']);
            }
            $thumb = DB::table('minimi_product_gallery')->where('product_id',$product_id)->value('prod_gallery_picture as pict');
            $date = date('Y-m-d H:i:s');
            $insert_review['category_id'] = $prods->category_id;
            $insert_review['subcat_id'] = $prods->subcat_id;
            $insert_review['product_id'] = $product_id;
            $insert_review['user_id'] = $currentUser->user_id;
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
                    return response()->json(['code'=>7003,'message'=>'invalid_item']);
                }

                $exp_period = DB::table('data_param')->where('param_tag','order_review_expire_period')->value('param_value');
                $date_exp = date('Y-m-d H:i:s',strtotime($item->received_at."+".$exp_period)); //expire_review
                if($date_exp<$date){
                    return response()->json(['code'=>7004,'message'=>'item_review_expired']);
                }

                if($item->reviewed==1){
                    return response()->json(['code'=>7005,'message'=>'item_already_reviewed']);
                }

                $insert_review['order_id'] = $data['order_id'];
                $insert_review['item_id'] = $data['item_id'];
                DB::table('commerce_shopping_cart_item')->where('item_id',$data['item_id'])->update(['reviewed'=>1,'reviewed_at'=>$date]);
            }
            $insert_review['content_type'] = 2;
            $insert_review['content_text'] = $data['text'];
            $insert_review['content_embed_link'] = $data['link'];
            $insert_review['content_thumbnail'] = $thumb;
            $insert_review['created_at'] = $date;
            $insert_review['updated_at'] = $date;
            $content_id = DB::table('minimi_content_post')->insertGetId($insert_review);

            //rating
            $ratings = $data['rating'];
            if(empty($ratings)){
                return response()->json(['code'=>7001,'message'=>'rating_can_not_be_emptied']);
            }
            $total=0;
            $i=0;
            foreach ($ratings as $rating) {
                $insert_rating['content_id'] = $content_id;
                $insert_rating['rp_id'] = $rating['rp_id'];
                $insert_rating['rating_value'] = floatval($rating['value']);
                $date = date('Y-m-d H:i:s');
                $insert_rating['created_at'] = $date;
                $insert_rating['updated_at'] = $date;
                DB::table('minimi_content_rating')->insert($insert_rating);
                $total += $insert_rating['rating_value'];
                $i++;
            }
            $avg = $total/$i;
            $update['content_rating'] = round($avg,2);
            DB::table('minimi_content_post')->where('content_id',$content_id)->update($update);

            //trivia
            $trivias = $data['trivia'];
            if(!empty($trivias)){
                foreach ($trivias as $trivia) {
                    $insert_trivia['content_id'] = $content_id;
                    $insert_trivia['trivia_id'] = $trivia['trivia_id'];
                    $insert_trivia['answer_id'] = $trivia['answer_id'];
                    $date = date('Y-m-d H:i:s');
                    $insert_trivia['created_at'] = $date;
                    $insert_trivia['updated_at'] = $date;
                    DB::table('minimi_content_trivia')->insert($insert_trivia);
                }
            }

            //image
            if($request->hasFile('photo')){
                $photos = $data['photo'];
                $insert = array();
                $j=0;
                foreach($photos as $photo){
                    if($photo['image']!=null){
                        $destinationPath = 'public/review/product';
                        $image = $photo['image'];
                        $image_path = app('App\Http\Controllers\Utility\UtilityController')->upload_image($image,$destinationPath);
                        switch ($image_path) {
                            case 'too_big':
                                //nothing happen
                            break;
                            
                            case 'not_an_image':
                                //nothing happen
                            break;
                            
                            default:
                                $insert_image['content_id'] = $content_id;
                                $insert_image['cont_gallery_picture'] = $image_path;
                                $date = date('Y-m-d H:i:s');
                                $image_name = app('App\Http\Controllers\Utility\UtilityController')->altTitleImage($image_path);
                                $insert_image['cont_gallery_alt'] = $image_name;
                                $insert_image['cont_gallery_title'] = $image_name;
                                $insert_image['created_at'] = $date;
                                $insert_image['updated_at'] = $date;
                                DB::table('minimi_content_gallery')->insert($insert_image);
                                $j++;
                            break;
                        }
                    }
                }
            }
            
            $notify['content_id'] = $content_id;
            $notify['notification_message'] = 'User '.$currentUser->fullname.' baru saja menulis review.';
            $notify['notification_target'] = env('DASHBOARD_URL').'content/review/'.$content_id;
            $notify['notification_type'] = 1;

            app('App\Http\Controllers\Utility\NotificationController')->notifyAdmin($notify);
            
            $return['review_id'] = $content_id;
            $return['content_type'] = 2;
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
		} catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'posting_product_review_failed']);
        }
    }

    public function postVideo(Request $request){
        $data = $request->all();
		try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }
            
            $thumb = app('App\Http\Controllers\Utility\UtilityController')->videoThumb($data['video_link']);
            if($thumb!=FALSE){
                $insert_review['content_thumbnail'] = $thumb;
            }
            $insert_review['product_id'] = ($data['product_id']=='')?null:$data['product_id'];
            if($data['product_id']!=''){
                $prods = DB::table('minimi_product')->select('category_id','subcat_id','brand_id')->where('product_id',$data['product_id'])->first();
                $insert_review['category_id'] = $prods->category_id;
                $insert_review['subcat_id'] = $prods->subcat_id;
            }
            $insert_review['user_id'] = $currentUser->user_id;
            $insert_review['content_type'] = 1;
            $insert_review['content_title'] = $data['title'];
            $insert_review['content_text'] = $data['text'];
            $insert_review['content_video_link'] = $data['video_link'];
            $date = date('Y-m-d H:i:s');
            $insert_review['created_at'] = $date;
            $insert_review['updated_at'] = $date;
            $content_id = DB::table('minimi_content_post')->insertGetId($insert_review);

            $notify['content_id'] = $content_id;
            $notify['notification_message'] = 'User '.$currentUser->fullname.' baru saja memberikan video review.';
            $notify['notification_target'] = env('DASHBOARD_URL').'content/review/'.$content_id;
            $notify['notification_type'] = 2;
            
            app('App\Http\Controllers\Utility\NotificationController')->notifyAdmin($notify);

            $return['video_id'] = $content_id;
            $return['content_type'] = 1;
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
		} catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'posting_video_review_failed']);
		}
    }
}