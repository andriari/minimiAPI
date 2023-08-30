<?php

namespace App\Http\Controllers\Dash;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;

use DB;

class CollectionController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }
    
    public function saveCollection(Request $request){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            $insert['collection_name'] = $data['collection_name'];
            $insert['collection_desc'] = $data['collection_desc'];
            if(empty($data['collection_uri'])){
                $slug = app('App\Http\Controllers\Utility\UtilityController')->slug($data['collection_name']);
                $insert['collection_uri'] = $this->uri($slug);
            }else{
                $insert['collection_uri'] = $data['collection_uri'];
            }
            $insert['created_at'] = $date;
            $insert['updated_at'] = $date;
            $collection_id = DB::table('minimi_product_collection')->insertGetId($insert);

            $return['collection_id'] = $collection_id;
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'save_collection_failed']);
		}
    }

    public function editCollection(Request $request){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            $update['collection_name'] = $data['collection_name'];
            if(!empty($data['collection_uri'])){
                $update['collection_uri'] = $data['collection_uri'];
            }
            $update['collection_desc'] = $data['collection_desc'];
            $update['updated_at'] = $date;
            DB::table('minimi_product_collection')->where('collection_id',$data['collection_id'])->update($update);

            $return['collection_id'] = $data['collection_id'];
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'edit_collection_failed']);
		}
    }

    public function detailCollection(Request $request,$collection_id){
        $data = $request->all();
        try {
            $query = DB::table('minimi_product_collection')->select('collection_id','collection_name','collection_desc')
                ->where(['status'=>1,'collection_id'=>$collection_id])
            ->first();
            
            if(empty($query)){
                return response()->json(['code'=>400, 'message'=>'not_found']);
            }

            //$limit = (empty($data['limit']))?10:$data['limit'];
            //$offset = (empty($data['offset']))?0:$data['offset'];
            //$offset_count = $offset * $limit;
		    //$next_offset_count = ($offset + 1)*$limit;

            $item = DB::table('minimi_product_collection_item')
                ->select('minimi_product.product_id','minimi_product.product_name', 'data_category.category_name', 'data_category_sub.subcat_name', 'data_brand.brand_name','prod_gallery_picture as pict')
                ->join('minimi_product','minimi_product.product_id','=','minimi_product_collection_item.product_id')
                ->join('minimi_product_gallery','minimi_product_gallery.product_id','=','minimi_product_collection_item.product_id')
                ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                ->where([
                    'collection_id' => $collection_id,
                    'minimi_product_collection_item.status' => 1,
                    'minimi_product.status' => 1,
                    'minimi_product_gallery.main_poster' => 1
                ])
            ->get();
            //->skip($offset_count)->take($limit)->get();

            /*$next_offset = 'empty';
            if (count($item) == $limit) {
                $item2 = DB::table('minimi_product_collection_item')
					->select('minimi_product_collection_item.product_id', 'minimi_product.product_uri')
					->join('minimi_product', 'minimi_product.product_id', '=', 'minimi_product_collection_item.product_id')
					->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'minimi_product_collection_item.product_id')
					->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
					->join('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
					->join('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
					->where([
						'minimi_product_collection_item.collection_id' => $collection_id,
						'minimi_product_collection_item.status' => 1,
						'minimi_product.status' => 1,
                        'minimi_product_gallery.main_poster' => 1
					])
                ->skip($next_offset_count)->take($limit)->get();

                if (count($item2) > 0) {
					$next_offset = $next_offset_count / $limit;
				}
            }*/

            $return['collection'] = $query;
            $return['item'] = $item;
            //$return['offset'] = $next_offset;
            return response()->json(['code'=>200, 'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'detail_collection_failed']);
        }
    }

    public function assignProductCollection(Request $request){
        $data = $request->all();
        try {
            $check = DB::table('minimi_product_collection_item')
                ->where([
                    'collection_id' => $data['collection_id'],
                    'product_id' => $data['product_id']
                ])
            ->first();
            
            $date = date('Y-m-d H:i:s');

            if(!empty($check)){
                if($check->status==1){
                    return response()->json(['code'=>201, 'message'=>'already']);    
                }
                $update['status'] = 1;
                $update['updated_at'] = $date;
                DB::table('minimi_product_collection_item')->where([
                    'collection_id' => $data['collection_id'],
                    'product_id' => $data['product_id']
                ])->update($update);
            }else{
                $insert['collection_id'] = $data['collection_id'];
                $insert['product_id'] = $data['product_id'];
                $insert['status'] = 1;
                $insert['created_at'] = $date;
                $insert['updated_at'] = $date;
                DB::table('minimi_product_collection_item')->insert($insert);
            }

            $return['collection_id'] = $data['collection_id'];
            $return['product_id'] = $data['product_id'];
            
            return response()->json(['code'=>200, 'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'assign_product_to_collection_failed']);
        }
    }

    public function removeProductCollection(Request $request){
        $data = $request->all();
        try {
            $check = DB::table('minimi_product_collection_item')
                ->where([
                    'collection_id' => $data['collection_id'],
                    'product_id' => $data['product_id'],
                    'status' => 1
                ])
            ->first();
            
            if(empty($check)){
                return response()->json(['code'=>400, 'message'=>'not_found']);
            }
            
            $date = date('Y-m-d H:i:s');

            $update['status'] = 0;
            $update['updated_at'] = $date;
            DB::table('minimi_product_collection_item')->where([
                'collection_id' => $data['collection_id'],
                'product_id' => $data['product_id']
            ])->update($update);

            return response()->json(['code'=>200, 'message'=>'success']);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'remove_product_from_collection_failed']);
        }
    }

    public function deleteCollection(Request $request,$collection_id){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            $update['show']=0;
            $update['status']=0;
            $update['updated_at']=$date;
            DB::table('minimi_product_collection')->where('collection_id',$collection_id)->update($update);
            
            return response()->json(['code'=>200, 'message'=>'success']);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'delete_collection_failed']);
        }
    }

    public function showStatusCollection(Request $request,$collection_id){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            $show = DB::table('minimi_product_collection')->where('collection_id',$collection_id)->value('show');
            $update['show']=($show==1)?0:1;
            $update['updated_at']=$date;
            DB::table('minimi_product_collection')->where('collection_id',$collection_id)->update($update);
            
            return response()->json(['code'=>200, 'message'=>'success']);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'delete_collection_failed']);
        }
    }

    /*
     * Utility Function 
    */

    function checkUri($uri){
		$return = DB::table('minimi_product_collection')->where('collection_uri',$uri)->first();
		return (empty($return))?"TRUE":"FALSE";
	}

	function uri($slug){
		$string = Str::random(5);
		$uri = $slug."-".$string;
		$check = $this->checkUri($uri);
		if($check=="TRUE"){
			return $uri;
		}else{
			return $this->uri($slug);
		}
	}
}