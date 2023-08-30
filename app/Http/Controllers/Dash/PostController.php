<?php

namespace App\Http\Controllers\Dash;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

use DB;

class PostController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }
    
    public function detailContent(Request $request, $content_id){
        $data = $request->all();
        try {
            $return = app('App\Http\Controllers\Utility\UtilityController')->detailReview_exe($content_id,1);
            if($return=='empty'){
                return response()->json(['code'=>4043,'message'=>'content_not_found']);
            }
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'load_content_failed']);
		}
    }

    public function changeStatusContent(Request $request){
        $data = $request->all();
        try {
            $content_id = $data['content_id'];
            $status = $data['status'];
            $content_query = DB::table('minimi_content_post')->select('user_id','content_type','content_rating','content_curated','content_video_link','content_title','content_text','product_id','purpose_flag','created_at')->where('content_id',$content_id)->first();
            
            if($content_query->content_curated==1){
                return response()->json(['code'=>4150,'message'=>'content_already_approved']);
            }

            $date = date('Y-m-d H:i:s');

            DB::table('minimi_content_post')->where('content_id',$content_id)->update([
                'content_curated'=>$status,
                'updated_at'=>$date
            ]);
            
            $point_amount = 0;

            if($status==1){
                if($content_query->product_id!=NULL){
                    $count = app('App\Http\Controllers\Utility\UtilityController')->recapperCount($content_query->product_id);                
                    app('App\Http\Controllers\Utility\UtilityController')->ratingCounter($content_query->product_id);
                }
                $article_recap = app('App\Http\Controllers\API\UserController')->recapperCount($content_query->user_id,$date,0);
                $check = app('App\Http\Controllers\Utility\UtilityController')->check_threshold($content_query->user_id,$content_query->content_type);
                if($check==true){
                    $point = app('App\Http\Controllers\Utility\UtilityController')->pointCounterContent($content_id, $content_query->user_id, "post_".$content_query->content_type);
                    $point_amount = $point['point_amount'];
                }
            }
            
            $actions = array();
            if($content_query->content_type==2){
                if($content_query->purpose_flag==0){
                    $act['action'] = "complete_review";
                    $act['attributes']['com_review_id'] = $content_id;
                    $act['attributes']['com_review_rating'] = $content_query->content_rating;
                    $act['attributes']['com_review_by'] = DB::table('minimi_user_data')->where('user_id',$content_query->user_id)->value('user_uri');
                    $act['attributes']['com_review_product'] = DB::table('minimi_product')->where('product_id',$content_query->product_id)->value('product_name');
                    $act['attributes']['com_review_date'] = $content_query->created_at;
                    $act['attributes']['com_review_status'] = $status;
                    $act['attributes']['com_review_poin'] = $point_amount;
                }elseif ($content_query->purpose_flag==1) {
                    $act['action'] = "complete_product";
                    $act['attributes']['com_product_id'] = $content_query->product_id;
                    $act['attributes']['com_product_rating'] = $content_query->content_rating;
                    $act['attributes']['com_product_by'] = DB::table('minimi_user_data')->where('user_id',$content_query->user_id)->value('user_uri');
                    $act['attributes']['com_review_product'] = DB::table('minimi_product')->where('product_id',$content_query->product_id)->value('product_name');
                    $act['attributes']['com_product_date'] = $content_query->created_at;
                    $act['attributes']['com_review_status'] = $status;
                    $act['attributes']['com_product_poin'] = $point_amount;
                }
                
                $trivia = DB::table('minimi_content_trivia')
					->select('data_trivia.trivia_question','minimi_content_trivia.trivia_id','data_trivia_answer.answer_content')
					->join('data_trivia','minimi_content_trivia.trivia_id','=','data_trivia.trivia_id')
					->join('data_trivia_answer','minimi_content_trivia.answer_id','=','data_trivia_answer.answer_id')
					->where('minimi_content_trivia.content_id',$content_id)
                ->get();
                foreach ($trivia as $triv) {
                    if($triv->trivia_id==1){
                        $act['attributes']['com_recomm_product'] = $triv->answer_content;
                    }elseif($triv->trivia_id==2){
                        $act['attributes']['com_interest_product'] = $triv->answer_content;
                    }
                }
                array_push($actions,$act);

                app('App\Http\Controllers\Utility\UtilityController')->storeEventMoengage($content_query->user_id,$actions);
            }elseif ($content_query->content_type==1) {
                $act['action'] = "complete_video";
                $act['attributes']['com_video_id'] = $content_id;
                $act['attributes']['com_video_date'] = $content_query->created_at;
                $act['attributes']['com_video_by'] = DB::table('minimi_user_data')->where('user_id',$content_query->user_id)->value('user_uri');
                $act['attributes']['com_video_link'] = $content_query->content_video_link;
                $act['attributes']['com_video_title'] = $content_query->content_title;
                $act['attributes']['com_video_desc'] = $content_query->content_text;
                $act['attributes']['com_video_status'] = $status;
                $act['attributes']['com_video_poin'] = $point_amount;
                array_push($actions,$act);
                
                app('App\Http\Controllers\Utility\UtilityController')->storeEventMoengage($content_query->user_id,$actions);
            }

            return response()->json(['code'=>200,'message'=>'success']);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'post_curation_failed']);
		}
    }
    
    public function saveArticle(Request $request){
        $data = $request->all();
        try {
            $destinationPath = 'public/review/article';

            if(empty($data['thumbnail'])){
                return response()->json(['code'=>1003,'message'=>'no_image_found']);
            }

            if($request->hasFile('thumbnail')){
                $image = $data['thumbnail'];
                $image_path = app('App\Http\Controllers\Utility\UtilityController')->upload_image($image,$destinationPath);
                switch ($image_path) {
                    case 'too_big':
                        return response()->json(['code'=>1001,'message'=>'image_too_big']);
                    break;
                    
                    case 'not_an_image':
                        return response()->json(['code'=>1002,'message'=>'invalid_image_type']);
                    break;
                    
                    default:
                        $insert_review['content_thumbnail'] = $image_path;
                    break;
                }
            }else{
                return response()->json(['code'=>1003,'message'=>'no_image_found']);
            }

            $insert_review['product_id'] = ($data['product_id']=='')?null:$data['product_id'];
            $insert_review['user_id'] = null;
            $insert_review['meta_tag'] = ($data['meta_tag']=='')?null:$data['meta_tag'];
            $insert_review['meta_desc'] = ($data['meta_desc']=='')?null:$data['meta_desc'];
            $insert_review['content_type'] = 3;
            $insert_review['content_curated'] = 1;
            $insert_review['content_title'] = $data['title'];
            $insert_review['content_subtitle'] = $data['subtitle'];
            $insert_review['content_text'] = $data['text'];
            $insert_review['content_uri'] = ($data['uri']=='')?null:$data['uri'];
            $date = date('Y-m-d H:i:s');
            $insert_review['created_at'] = $date;
            $insert_review['updated_at'] = $date;
            DB::table('minimi_content_post')->insert($insert_review);

            return response()->json(['code'=>200,'message'=>'success']);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'save_article_failed']);
		}   
    }

    public function editArticle(Request $request){
        $data = $request->all();
        try {
            $destinationPath = 'public/review/article';

            if($request->hasFile('thumbnail')){
                $image = $data['thumbnail'];
                if($image!=NULL){
                    $image_path = app('App\Http\Controllers\Utility\UtilityController')->upload_image($image,$destinationPath);
                    switch ($image_path) {
                        case 'too_big':
                            return response()->json(['code'=>1001,'message'=>'image_too_big']);
                        break;
                        
                        case 'not_an_image':
                            return response()->json(['code'=>1002,'message'=>'invalid_image_type']);
                        break;
                        
                        default:
                            $update['content_thumbnail'] = $image_path;
                        break;
                    }
                }
            }

            $update['product_id'] = ($data['product_id']=='')?null:$data['product_id'];
            $update['content_type'] = 3;
            $update['meta_tag'] = $data['meta_tag'];
            $update['meta_desc'] = $data['meta_desc'];
            $update['content_title'] = $data['title'];
            $update['content_subtitle'] = $data['subtitle'];
            $update['content_text'] = $data['text'];
            $update['content_uri'] = $data['uri'];
            $date = date('Y-m-d H:i:s');
            $update['updated_at'] = $date;
            DB::table('minimi_content_post')->where('content_id',$data['content_id'])->update($update);

            return response()->json(['code'=>200,'message'=>'success']);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'save_article_failed']);
		}   
    }

    public function detailArticle(Request $request, $content_id){
        $data = $request->all();
        try {
            $return = app('App\Http\Controllers\Utility\UtilityController')->detailReview_exe($content_id,1);
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'save_article_failed']);
		}   
    }

    public function givePoint(Request $request){
        $data = $request->all();
        try {
            $content_id = ($data['content_id']=='')?null:$data['content_id'];
            $user_id = $data['user_id'];
            $content_tag = $data['tag'];
            $ret = app('App\Http\Controllers\Utility\UtilityController')->pointCounterContent($content_id, $user_id, $content_tag);
            if($ret['message']=='success'){
                return response()->json(['code'=>200,'message'=>'success']);
            }else{
                return response()->json(['code'=>400,'message'=>$ret['message']]);
            }
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'post_curation_failed']);
		}
    }

    public function assignProduct(Request $request){
        $data = $request->all();
        try {
            $content = DB::table('minimi_content_post')->select('user_id','content_text','content_embed_link')->where('content_id',$data['content_id'])->first();
            $prods = DB::table('minimi_product')->select('product_uri','category_id','subcat_id','brand_id')->where('product_id',$data['product_id'])->first();
            $update['category_id'] = $prods->category_id;
            $update['subcat_id'] = $prods->subcat_id;
            $update['product_id'] = $data['product_id'];
            $update['content_type'] = 2;
            $update['purpose_flag'] = 1;
            $update['content_title'] = '';
            $update['content_subtitle'] = '';
            $update['content_text'] = $content->content_text;
            $update['content_embed_link'] = $content->content_embed_link;
            $update['content_thumbnail'] = DB::table('minimi_product_gallery')->where('product_id',$data['product_id'])->value('prod_gallery_picture as pict');
            $date = date('Y-m-d H:i:s');
            $update['updated_at'] = $date;
            DB::table('minimi_content_post')->where('content_id',$data['content_id'])->update($update);

            $notify['user_id'] = $content->user_id;
            $notify['notification_message'] = 'Hai produk yang anda daftarkan sudah selesai kami periksa, yuk bantu orang tua lainnya dengan mengisi review dari produk tersebut';
            $notify['notification_target'] = '/product'.'/'.$prods->product_uri;
            app('App\Http\Controllers\Utility\NotificationController')->saveNotification_exe($notify);

            $return['content_id'] = $data['content_id'];
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'product_assignment_failed']);
		}
    }

    public function showNotification(Request $request){
        $data = $request->all();
        try {
            $limit = (empty($data['limit']))?20:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
            
            $offset_count = $offset*$limit;
            $next_offset_count = ($offset+1)*$limit;
            
            $query = DB::table('minimi_notification_admin')
                ->where([
                    'read_status'=>0
                ])
            ->skip($offset_count)->take($limit)->get();

            $next_offset = 'empty';
		    if(count($query)==$limit){
                $query2 = DB::table('minimi_notification_admin')
                    ->where([
                        'read_status'=>0
                    ])
                ->skip($next_offset_count)->take($limit)->get();

                if(count($query2)>0){
                    $next_offset = $next_offset_count/$limit;
                }
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$query,'offset'=>$next_offset]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'show_notification_failed']);
		}
    }

    public function readNotification(Request $request){
        $data = $request->all();
        try {
            DB::table('minimi_notification_admin')->where([
                'read_status'=>0
            ])->update([
                'read_status'=>1,
                'updated_at'=>date('Y-m-d H:i:s')
            ]);
            return response()->json(['code'=>200,'message'=>'success']);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'read_notification_failed']);
		}
    }

    public function readNotificationContent(Request $request, $mode, $id){
        $data = $request->all();
        try {
            switch ($mode) {
                case 'content':
                    $where['content_id']=$id;
                    break;
                case 'notify':
                    $where['notif_admin_id']=$id;
                    break;
                default:
                    return response()->json(['code'=>4300,'message'=>'invalid_mode']);
                    break;
            }
            DB::table('minimi_notification_admin')->where($where)->update([
                'read_status'=>1,
                'updated_at'=>date('Y-m-d H:i:s')
            ]);
            return response()->json(['code'=>200,'message'=>'success']);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'read_notification_failed']);
		}
    }
}