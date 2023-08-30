<?php

namespace App\Http\Controllers\Dash;

use App\Http\Controllers\Controller;
use App\Imports\VariantImport;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Maatwebsite\Excel\Facades\Excel;

use DB;

class ProductController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
	}

    /*
        product begin
    */
    public function saveProduct(Request $request){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            $slug = app('App\Http\Controllers\Utility\UtilityController')->slug($data['product_name']);
            $product_uri = app('App\Http\Controllers\Utility\UtilityController')->uri($slug);
            $product_type = (empty($data['product_type']))?1:$data['product_type'];
            
            if($product_type == 2){
                $insert['product_price'] = $data['product_price'];
                $insert['product_sub_name'] = $data['product_sub_name'];
                $insert['product_short_desc'] = $data['product_short_desc'];
            }elseif($product_type == 1){
                $insert['product_weight'] = $data['product_weight'];
                $insert['brand_id'] = $data['brand_id'];
            }

            $category_alt = (empty($data['category_alt']))?null:$data['category_alt'];
            $subcategory_alt = (empty($data['subcategory_alt']))?null:$data['subcategory_alt'];
            
            if($category_alt != null){
                $insert['alt_category_tag'] = '#'.str_replace(',',';#',$category_alt).';';
            }

            if($subcategory_alt != null){
                $insert['alt_subcategory_tag'] = '#'.str_replace(',',';#',$subcategory_alt).';';
            }

            $insert['category_id'] = $data['category_id'];
            $insert['subcat_id'] = $data['subcat_id'];
            $insert['product_name'] = $data['product_name'];
            $insert['product_uri'] = $product_uri;
            $insert['product_desc'] = $data['product_desc'];
            $insert['product_tags'] = $data['product_tags'];
            $insert['product_feed_desc'] = (empty($data['product_feed_desc']))?null:$data['product_feed_desc'];
            $insert['product_type'] = $product_type;
            $insert['created_at'] = $date;
            $insert['updated_at'] = $date;

            $product_id = DB::table('minimi_product')->insertGetId($insert);

            if($product_type==1){
                DB::table('relation_product_rating')->insert([
                    ['product_id' => $product_id, 'rp_id' => 1, 'created_at' => $date, 'updated_at' => $date],
                    ['product_id' => $product_id, 'rp_id' => 2, 'created_at' => $date, 'updated_at' => $date],
                    ['product_id' => $product_id, 'rp_id' => 3, 'created_at' => $date, 'updated_at' => $date]
                ]);
    
                DB::table('relation_product_trivia')->insert([
                    ['product_id' => $product_id, 'trivia_id' => 1, 'created_at' => $date, 'updated_at' => $date],
                    ['product_id' => $product_id, 'trivia_id' => 2, 'created_at' => $date, 'updated_at' => $date]
                ]);
            }

            $count = app('App\Http\Controllers\Utility\UtilityController')->recapperCount($product_id,1);

            $return['product_id'] = $product_id;
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'save_product_failed']);
		}
    }

    public function editProduct(Request $request){
        $data = $request->all();
        try {
            $product_type = DB::table('minimi_product')->where('product_id',$data['product_id'])->value('product_type');
            $category_alt = (empty($data['category_alt']))?null:$data['category_alt'];
            $subcategory_alt = (empty($data['subcategory_alt']))?null:$data['subcategory_alt'];
            
            if($category_alt != null){
                $category_alt = '#'.str_replace(',',';#',$category_alt).';';
            }

            if($subcategory_alt != null){
                $subcategory_alt = '#'.str_replace(',',';#',$subcategory_alt).';';
            }

            $update = array(
                'category_id'=>$data['category_id'],
                'subcat_id'=>$data['subcat_id'],
                'alt_category_tag'=>$category_alt,
                'alt_subcategory_tag'=>$subcategory_alt,
                'product_name'=>$data['product_name'],
                'product_desc'=>$data['product_desc'],
                'product_tags'=>$data['product_tags'],
                'updated_at'=>date('Y-m-d H:i:s')
            );

            if($product_type == 2){
                $update['product_price'] = $data['product_price'];
                $update['product_sub_name'] = $data['product_sub_name'];
                $update['product_short_desc'] = $data['product_short_desc'];
            }elseif($product_type == 1){
                $update['brand_id'] = $data['brand_id'];
                $update['product_weight'] = $data['product_weight'];
            }

            $update['product_feed_desc'] = (empty($data['product_feed_desc']))?null:$data['product_feed_desc'];
            
            DB::table('minimi_product')->where('product_id',$data['product_id'])->update($update);

            $return['product_type'] = $product_type;
            $return['product_id'] = $data['product_id'];
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'edit_product_failed']);
		}
    }

    public function deleteProduct(Request $request, $product_id){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            
            DB::table('minimi_product')->where('product_id',$product_id)->update([
                'status'=>0,
                'updated_at'=>$date
            ]);

            $return['product_id'] = $product_id;
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'delete_product_failed']);
		}
    }

    public function detailProduct(Request $request, $product_id){
        $data = $request->all();
        try {
            $return = DB::table('minimi_product')
                ->select('minimi_product.*', 'data_category.category_name', 'data_category_sub.subcat_name', 'data_brand.brand_name')
                ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                ->where([
                    'product_id' => $product_id,
                    'minimi_product.status' => 1
                ])
            ->first();

            if($return->alt_subcategory_tag!=null){
                $strip = str_replace('#','',$return->alt_subcategory_tag);
                $exp = explode(';',$strip);
                $subcat_id = array_filter($exp);
                array_push($subcat_id,$return->subcat_id);
                $alt_subcat = DB::table('data_category_sub')->select('data_category_sub.category_id','category_name','subcat_id','subcat_name')->join('data_category','data_category.category_id','=','data_category_sub.category_id')->whereIn('subcat_id',$subcat_id)->get();
                $col_subcat = collect($alt_subcat);
                $cat_ids = $col_subcat->pluck('category_id')->unique()->all();
                $array = array();
                foreach ($cat_ids as $row) {
                    $dat = array();
                    $dat['category_id'] = $row;
                    $dat['subcat_id'] = $col_subcat->where('category_id',$row)->pluck('subcat_id')->unique()->all();;
                    array_push($array,$dat);
                }
                $return->alt=$array;
            }

            $return->review_count = DB::table('minimi_content_rating_tab')->where(['tag'=>'review_count','product_id'=>$product_id])->value('value');

            if(!empty($return)){
                return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
            }else{
                return response()->json(['code'=>400, 'message'=>'not_found']);
            }
        } catch (QueryException $ex){
            dd($ex);
			return response()->json(['code'=>4050,'message'=>'loading_product_failed']);
		}
    }

    public function detailProductDigital(Request $request, $product_id){
        $data = $request->all();
        try {
            $return = DB::table('minimi_product')
                ->select('minimi_product.*', 'data_category.category_name', 'data_category_sub.subcat_name')
                ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->where([
                    'product_id' => $product_id,
                    'product_type' => 2,
                    'minimi_product.status' => 1
                ])
            ->first();
            if(!empty($return)){
                $bundle = DB::table('minimi_product_digital')
                    ->select('digital_id', 'colour_palette', 'voucher_count', 'voucher_duration', 'voucher_minimum', 'voucher_value', 'discount_type', 'voucher_type', 'voucher_name', 'voucher_desc', 'voucher_tnc')
                    ->where('product_id',$product_id)
                ->first();

                $image = DB::table('minimi_product_gallery')
                    ->where([
                        'status'=>1,
                        'product_id' => $product_id
                    ])
                ->orderBy('main_poster')->get();

                $return->bundle = $bundle;
                $return->images = $image;
                return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
            }else{
                return response()->json(['code'=>400, 'message'=>'not_found']);
            }
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'loading_product_failed']);
		}
    }

    public function searchProduct(Request $request){
        $data = $request->all();
        try {
            $search_query = $data['search_query'];
            $limit=$data['limit'];
            $offset_count=$data['offset']*$limit;
            $next_offset_count=($data['offset']+1)*$limit;

            $query = DB::table('minimi_product')
                ->select('minimi_product.product_id','product_uri','product_name','brand_name','category_name','subcat_name')
                ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                ->where('minimi_product.status',1)
                ->where('minimi_product.product_type',1)
                ->where(function ($query) use ($search_query){
                    $query->where('product_name','like','%'.$search_query.'%')
                        ->orWhere('brand_name','like','%'.$search_query.'%')
                        ->orWhere('product_uri','like','%'.$search_query.'%')
                        ->orWhere('product_id','like','%'.$search_query.'%');
                })
            ->skip($offset_count)->take($limit)->get();

            if(count($query)>0){
                $next_offset = 'empty';
                if(count($query)>=$limit){
                    $query2 = DB::table('minimi_product')
                        ->select('product_id')
                        ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                        ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                        ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                        ->where('minimi_product.status',1)
                        ->where(function ($query2) use ($search_query){
                            $query2->where('product_name','like','%'.$search_query.'%')
                                ->orWhere('brand_name','like','%'.$search_query.'%');
                        })
                    ->skip($next_offset_count)->take($limit)->get();
                    
                    if(count($query2)>0){
                        $next_offset = $next_offset_count/$limit;
                    }
                }
                
                return response()->json(['code'=>200,'message'=>'success','data'=>$query,'offset'=>$next_offset,'limit'=>$limit,'search_query'=>$search_query]);
            }else{
                return response()->json(['code'=>400, 'message'=>'not_found']);
            }
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'search_product_failed']);
		}
    }

    /*
        product end
    */

    //////////////////////////////////////////////

    /*
        product variant begin
    */
    public function uploadProductVariant(Request $request){
        $rows = Excel::toArray(new VariantImport, $request->file('filename'));
        $data = $rows[0];
        unset($data[0]);
        $arr = array();
        foreach($data as $row){
            if($row[0]!=""){
                $product_id = $row[0];
                $variant_sku = $row[5];
                
                $variant = DB::table('minimi_product_variant')
                    ->where([
                        'product_id'=>$product_id,
                        'variant_sku'=>$variant_sku
                    ])
                ->first();
    
                $date = date('Y-m-d H:i:s');
                $update['stock_count'] = $row[6];
                $update['stock_weight'] = $row[7];
                $update['stock_price'] = $row[9];
                $update['stock_price_gb'] = $row[10];
                $update['stock_restriction_count'] = $row[11];
                $update['updated_at'] = $date;
    
                if(!empty($variant)){
                    DB::table('minimi_product_variant')->where('variant_id',$variant->variant_id)->update($update);
                }else{
                    $update['product_id'] = $row[0];
                    $update['variant_name'] = $row[4];
                    $update['variant_sku'] = $row[5];
                    $update['stock_agent_price'] = $row[8];
                    $update['publish'] = 1;
                    $update['status'] = 1;
                    $update['created_at'] = $date;
                    DB::table('minimi_product_variant')->insert($update);
                }
                
                if($update['stock_price']>0){
                    App('App\Http\Controllers\Utility\UtilityController')->updateProductPrice($product_id);
                }
    
                if($update['stock_price_gb']>0){
                    App('App\Http\Controllers\Utility\UtilityController')->updateProductPriceGB($product_id);
                }
            }
        }

        return response()->json(['code'=>200, 'message'=>'success']);
    }

    public function saveProductVariant(Request $request){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            $stock_price_gb = empty($data['stock_price_gb'])?0:$data['stock_price_gb'];
            $stock_restriction_count = empty($data['stock_restriction_count'])?null:$data['stock_restriction_count'];
            $variant_id = DB::table('minimi_product_variant')->insertGetId([
                'product_id'=>$data['product_id'],
                'variant_name'=>$data['variant_name'],
                'variant_sku'=>$data['variant_sku'],
                'stock_count'=>$data['stock_count'],
                'stock_weight'=>$data['stock_weight'],
                'stock_price'=>$data['stock_price'],
                'stock_price_gb'=>$stock_price_gb,
                'stock_restriction_count'=>$stock_restriction_count,
                'stock_agent_price'=>$data['stock_agent_price'],
                'created_at'=>$date,
                'updated_at'=>$date
            ]);

            if($data['stock_price']>0){
                App('App\Http\Controllers\Utility\UtilityController')->updateProductPrice($data['product_id']);
            }

            if($stock_price_gb>0){
                App('App\Http\Controllers\Utility\UtilityController')->updateProductPriceGB($data['product_id']);
            }

            $return['variant_id'] = $variant_id;
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'save_product_variant_failed']);
		}
    }

    public function editProductVariant(Request $request){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            
            $update['variant_name'] = $data['variant_name'];
            $update['variant_sku'] = $data['variant_sku'];
            $update['stock_count'] = $data['stock_count'];
            $update['stock_weight'] = $data['stock_weight'];
            $update['stock_price'] = $data['stock_price'];
            $update['stock_price_gb'] = $data['stock_price_gb'];
            $update['stock_agent_price'] = $data['stock_agent_price'];
            $update['stock_restriction_count'] = $data['stock_restriction_count'];
            $update['updated_at'] = $date;

            DB::table('minimi_product_variant')->where('variant_id',$data['variant_id'])->update($update);
            
            $var = DB::table('minimi_product_variant')->select('product_id','stock_price','stock_price_gb')->where('variant_id',$data['variant_id'])->first();

            App('App\Http\Controllers\Utility\UtilityController')->updateProductPrice($var->product_id);
            App('App\Http\Controllers\Utility\UtilityController')->updateProductPriceGB($var->product_id);

            $return['variant_id'] = $data['variant_id'];
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'edit_product_variant_failed']);
		}
    }

    public function detailProductVariant(Request $request, $variant_id){
        $data = $request->all();
        try {
            $return = DB::table('minimi_product_variant')->where('variant_id',$variant_id)->first();
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'loading_product_variant_failed']);
		}
    }

    public function deleteProductVariant(Request $request, $variant_id){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            
            DB::table('minimi_product_variant')->where('variant_id',$variant_id)->update([
                'status'=>0,
                'publish'=>0,
                'updated_at'=>$date
            ]);

            $product_id = DB::table('minimi_product_variant')->where('variant_id',$variant_id)->value('product_id');
            App('App\Http\Controllers\Utility\UtilityController')->updateProductPrice($product_id);
            App('App\Http\Controllers\Utility\UtilityController')->updateProductPriceGB($product_id);

            $return['variant_id'] = $variant_id;
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'delete_product_variant_failed']);
		}
    }

    public function publishProductVariant(Request $request, $variant_id, $status){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            
            DB::table('minimi_product_variant')->where('variant_id',$variant_id)->update([
                'publish'=>$status,
                'updated_at'=>$date
            ]);

            $product_id = DB::table('minimi_product_variant')->where('variant_id',$variant_id)->value('product_id');
            App('App\Http\Controllers\Utility\UtilityController')->updateProductPrice($product_id);
            App('App\Http\Controllers\Utility\UtilityController')->updateProductPriceGB($product_id);

            $return['variant_id'] = $variant_id;
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'publish_product_variant_failed']);
		}
    }

    public function listProductVariant(Request $request, $product_id){
        $data = $request->all();
        try {
            $return = DB::table('minimi_product_variant')->where('product_id',$product_id)->first();
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'loading_product_variant_failed']);
		}
    }

    /*
        product variant end
    */

    //////////////////////////////////////////////

    /*
        product gallery begin
    */
    public function saveProductGallery(Request $request){
        $data = $request->all();
        try {
            $check = DB::table('minimi_product_gallery')->where(['product_id'=>$data['product_id'],'status'=>1])->get();
            $i=count($check);
            $j=0;
            $photos = $data['photo'];
            $poster = array();
            $return['product_id'] = $data['product_id'];
            foreach($photos as $photo){
                $row = array();
                $insert['product_id'] = $data['product_id'];
                if($i==0){
                    $insert['main_poster'] = 1;
                }else{
                    if($photo['main']==1){
                        DB::table('minimi_product_gallery')->where(['product_id'=>$data['product_id'],'main_poster'=>1])->update([
                            'main_poster'=>0
                        ]);
                        $insert['main_poster'] = 1;
                    }else{
                        $insert['main_poster'] = 0;
                    }
                }
                if($_FILES['photo']['size'][$j]['image']>0){
                    $destinationPath = 'public/product';
                    $image = $photo['image'];
                    $image_path = app('App\Http\Controllers\Utility\UtilityController')->upload_image($image,$destinationPath);
                    switch ($image_path) {
                        case 'too_big':
                            return response()->json(['code'=>1001,'message'=>'image_too_big','data'=>$return]);
                            break;
                        case 'not_an_image':
                            return response()->json(['code'=>1002,'message'=>'invalid_image_type','data'=>$return]);
                            break;
                        default:
                            $insert['prod_gallery_picture'] = $image_path;
                            break;
                    }
    
                    $date = date('Y-m-d H:i:s');
                    $insert['prod_gallery_alt'] = $photo['alt'];
                    $insert['prod_gallery_title'] = $photo['title'];
                    $insert['created_at'] = $date;
                    $insert['updated_at'] = $date;
                    DB::table('minimi_product_gallery')->insert($insert);
                    $row['prod_gallery_picture'] = $image_path;
                    $row['prod_gallery_alt'] = $photo['alt'];
                    $row['prod_gallery_title'] = $photo['title'];
                    array_push($poster, $row);
                    $j++;
                }
                $i++;
            }
            if($j==0){
                return response()->json(['code'=>1003,'message'=>'no_image_found','data'=>$return]);
            }else{
                $return['data'] = $poster;
                return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
            }

        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'save_product_image_failed']);
		}
    }

    public function saveProductGallerySingle(Request $request){
        $data = $request->all();
        try {
            $check = DB::table('minimi_product_gallery')->where(['product_id'=>$data['product_id'],'status'=>1])->get();
            $i=count($check);

            $return['product_id'] = $data['product_id'];
            
            if($request->hasFile('image')){
                $poster = array();

                if($i==0){
                    $insert['main_poster'] = 1;
                }else{
                    if($data['main_poster']==1){
                        DB::table('minimi_product_gallery')->where(['product_id'=>$data['product_id'],'main_poster'=>1])->update([
                            'main_poster'=>0
                        ]);
                        $insert['main_poster'] = 1;
                    }else{
                        $insert['main_poster'] = 0;
                    }
                }
                
                $destinationPath = 'public/product';
                $photo = $data['image'];
                $image_path = app('App\Http\Controllers\Utility\UtilityController')->upload_image($photo,$destinationPath);
                switch ($image_path) {
                    case 'too_big':
                        return response()->json(['code'=>1001,'message'=>'image_too_big','data'=>$return]);
                        break;
                    case 'not_an_image':
                        return response()->json(['code'=>1002,'message'=>'invalid_image_type','data'=>$return]);
                        break;
                    default:
                        $insert['prod_gallery_picture'] = $image_path;
                        break;
                }

                $date = date('Y-m-d H:i:s');
                $insert['product_id'] = $data['product_id'];
                $insert['prod_gallery_alt'] = $data['alt'];
                $insert['prod_gallery_title'] = $data['title'];
                $insert['created_at'] = $date;
                $insert['updated_at'] = $date;
                DB::table('minimi_product_gallery')->insert($insert);
                
                $return['prod_gallery_picture'] = $image_path;
                $return['prod_gallery_alt'] = $data['alt'];
                $return['prod_gallery_title'] = $data['title'];
                
                return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
            }
            
            return response()->json(['code'=>1003,'message'=>'no_image_found','data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'save_product_image_failed']);
		}
    }

    public function editProductGallery(Request $request){
        $data = $request->all();
        try {
            if($request->hasFile('image')){
                $destinationPath = 'public/product';
                $image_path = app('App\Http\Controllers\Utility\UtilityController')->upload_image($data['image'],$destinationPath);
                switch ($image_path){
                    case 'too_big':
                        return response()->json(['code'=>1001,'message'=>'image_too_big']);
                        break;
                    case 'not_an_image':
                        return response()->json(['code'=>1002,'message'=>'invalid_image_type']);
                        break;
                    default:
                        $update['prod_gallery_picture'] = $image_path;
                        break;
                }
            }

            $date = date('Y-m-d H:i:s');
            $update['prod_gallery_alt']=$data['alt'];
            $update['prod_gallery_title']=$data['title'];
            if(!empty($data['main_poster'])){
                $update['main_poster']=$data['main_poster'];
            }
            $update['updated_at']=$date;
            DB::table('minimi_product_gallery')->where('prod_gallery_id',$data['prod_gallery_id'])->update($update);

            $return['product_id'] = DB::table('minimi_product_gallery')->where('prod_gallery_id',$data['prod_gallery_id'])->value('product_id');
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'edit_product_image_failed']);
		}
    }

    public function setMainProductGallery(Request $request,$prod_gallery_id){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');

            $return['product_id'] = DB::table('minimi_product_gallery')->where('prod_gallery_id',$prod_gallery_id)->value('product_id');

            DB::table('minimi_product_gallery')->where(['product_id'=>$return['product_id'],'main_poster'=>1])->update([
                'main_poster'=>0
            ]);
            
            DB::table('minimi_product_gallery')->where('prod_gallery_id',$prod_gallery_id)->update([
                'main_poster'=>1,
                'updated_at'=>$date
            ]);

            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'edit_product_iamge_failed']);
		}
    }

    public function deleteProductGallery(Request $request,$prod_gallery_id){
        $data = $request->all();
        try {
            $query = DB::table('minimi_product_gallery')->select('product_id','prod_gallery_picture','main_poster')->where('prod_gallery_id',$prod_gallery_id)->first();
            //$image = app('App\Http\Controllers\Utility\UtilityController')->deleteImage($query->prod_gallery_picture);
            if($query->main_poster==1){
                $update['main_poster']=0;
                $prod = DB::table('minimi_product_gallery')
                    ->select('prod_gallery_id')
                    ->where('product_id',$query->product_id)
                    ->where('status',1)
                    ->where('prod_gallery_id','!=',$prod_gallery_id)
                ->first();

                DB::table('minimi_product_gallery')->where('prod_gallery_id',$prod_gallery_id)->update([
                    'main_poster'=>1,
                    'updated_at'=>date('Y-m-d H:i:s')
                ]);
            }
            $date = date('Y-m-d H:i:s');
            $update['status']=0;
            $update['updated_at']=$date;
            DB::table('minimi_product_gallery')->where('prod_gallery_id',$prod_gallery_id)->update($update);
            
            $return['product_id'] = $query->product_id;
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'edit_product_variant_failed']);
        }
    }

    public function listProductGallery(Request $request, $product_id){
        $data = $request->all();
        try {
            $return = DB::table('minimi_product_gallery')
                ->where([
                    'product_id'=>$product_id,
                    'status'=>1
                ])
                ->orderBy('main_poster','DESC')
            ->get();
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'list_product_iamge_failed']);
		}
    }

    /*
        product gallery end
    */

    ////////////////////////////////////////////////////////////////////////////////////////////

    /*
        product voucher 
    */

    public function saveProductVoucher(Request $request){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            
            $query = DB::table('minimi_product_digital')
                ->where('product_id',$data['product_id'])
            ->first();

            if(!empty($query)){
                return response()->json(['code'=>4030, 'message'=>'bundling_already_existed']);
            }

            $digital_id = DB::table('minimi_product_digital')->insertGetId([
                'product_id'=>$data['product_id'],
                'colour_palette'=>$data['colour_palette'],
                'voucher_count'=>$data['voucher_count'],
                'voucher_duration'=>$data['voucher_duration'],
                'voucher_minimum'=>$data['voucher_minimum'],
                'voucher_value'=>$data['voucher_value'],
                'discount_type'=>$data['discount_type'],
                'voucher_type'=>$data['voucher_type'],
                'voucher_name'=>$data['voucher_name'],
                'voucher_desc'=>$data['voucher_desc'],
                'voucher_tnc'=>$data['voucher_tnc'],
                'created_at'=>$date,
                'updated_at'=>$date
            ]);

            $return['digital_id'] = $digital_id;
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'save_product_variant_failed']);
		}
    }

    public function editProductVoucher(Request $request){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            
            DB::table('minimi_product_digital')->where('digital_id',$data['digital_id'])->update([
                'colour_palette'=>$data['colour_palette'],
                'voucher_count'=>$data['voucher_count'],
                'voucher_duration'=>$data['voucher_duration'],
                'voucher_minimum'=>$data['voucher_minimum'],
                'voucher_value'=>$data['voucher_value'],
                'discount_type'=>$data['discount_type'],
                'voucher_type'=>$data['voucher_type'],
                'voucher_name'=>$data['voucher_name'],
                'voucher_desc'=>$data['voucher_desc'],
                'voucher_tnc'=>$data['voucher_tnc'],
                'updated_at'=>$date
            ]);

            $return['digital_id'] = $data['digital_id'];
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'edit_product_variant_failed']);
		}
    }
}