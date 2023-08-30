<?php

namespace App\Http\Controllers\Dash;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;

use DB;

class GroupBuyController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }

    public function checkStatusGroupBuy(Request $request){
        $data = $request->all();
        try {
            $query = DB::table('commerce_group_buy')->where('status',2)->get();

            if(count($query)==0){
                return response()->json(['code'=>200,'message'=>'success']);
            }

            foreach($query as $row){
                $date = date('Y-m-d H:i:s');
                $status = $row->status;

                if($row->total_participant>=$row->minimum_participant){
                    $status = 3;
                }
                if($row->expire_at<=$date){
                    $status = 0;
                    app('App\Http\Controllers\Utility\GroupBuyController')->cancelOrderByGroup($row->cg_id,$date);
                }

                if($row->status != $status){
                    DB::table('commerce_group_buy')->where('cg_id',$row->cg_id)->update([
                        'status'=>$status,
                        'updated_at'=>$date
                    ]);
                }
            }

            return response()->json(['code'=>200,'message'=>'success']);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'check_group_buy_failed']);
        }
    }

    public function detailGroupBuy(Request $request, $cg_id){
        $data = $request->all();
        try {
            $query = DB::table('commerce_group_buy')
                ->select('commerce_group_buy.*','minimi_user_data.fullname', 'minimi_product.product_uri', 'minimi_product.product_name', 'minimi_product.product_price_gb as product_price', 'minimi_product.product_rating', 'data_category.category_name', 'data_category_sub.subcat_name', 'data_brand.brand_name', 'prod_gallery_picture as pict', 'minimi_product.status as product_status')
                ->join('minimi_user_data','minimi_user_data.user_id','=','commerce_group_buy.user_id')
                ->join('minimi_product','minimi_product.product_id','=','commerce_group_buy.product_id')
                ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_group_buy.product_id')
                ->join('data_category', 'data_category.category_id', '=', 'minimi_product.category_id')
                ->join('data_category_sub', 'data_category_sub.subcat_id', '=', 'minimi_product.subcat_id')
                ->join('data_brand', 'data_brand.brand_id', '=', 'minimi_product.brand_id')
                ->where([
                    'cg_id' => $cg_id
                ])
                ->orderBy('main_poster','desc')
            ->first();

            if(empty($query)){
                return response()->json(['code'=>400,'message'=>'empty']);
            }

            switch ($query->status) {
                case 0:
                    $query->status_name = "0 : Expired (Waiting for verification)";
                    break;
                case 1:
                    $query->status_name = "1 : Waiting For Participant Payment";
                    break;
                case 2:
                    $query->status_name = "2 : Participant Requirement Not Met";
                    break;
                case 3:
                    $query->status_name = "3 : Participant Met (Waiting for verification)";
                    break;
                case 4:
                    $query->status_name = "4 : Verified";
                    break;
                default:
                    $query->status_name = "5 : Closed";
                    break;
            }

            $query->share_link_url = env('FRONTEND_URL').'beli-bareng/'.$query->product_uri.'?cg_id='.$cg_id;

            $return['group'] = $query;

            $order = DB::table('commerce_booking')
                ->select('booking_id','paid_status','admin_verified')
                ->where([
                    'cg_id'=>$cg_id
                ])
                ->whereIn('paid_status',[0,1,3])
                ->where('cancel_status',0)
            ->get();
            
            if(count($order)>0){
                $col_colls = collect($order);
                $booking_ids = $col_colls->pluck('booking_id')->all();
                $return['order'] = app('App\Http\Controllers\Utility\CartController')->getOrderListBulk_exe($booking_ids,3);
                if($query->status==4){ 
                    $filter_colls = $col_colls->where('admin_verified',1);
                    $booking_ids_2 = $filter_colls->pluck('booking_id')->all();
                    $delt = count($booking_ids) - count($booking_ids_2);
                    $return['group']->verify = ($delt==0)?2:1;
                }else{
                    $return['group']->verify = ($query->status==0||$query->status==3)?1:0;
                }
            }else{
                $return['order'] = array();
                $return['group']->verify = 0;
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'detail_group_buy_failed']);
        }
    }

    public function verifyGroupBuy(Request $request){
        $data = $request->all();
        try {
            $query = DB::table('commerce_group_buy')
                ->whereIn('status',[0,3,4])
                ->where('cg_id',$data['cg_id'])
            ->first();

            if(empty($query)){
                return response()->json(['code'=>400,'message'=>'invalid_group']);
            }

            $date = date('Y-m-d H:i:s');

            $order = DB::table('commerce_booking')
                ->select('booking_id', 'cart_id', 'admin_verified')
                ->where([
                    'cg_id'=>$query->cg_id,
                    'paid_status'=>1,
                    'cancel_status'=>0
                ])
            ->get();

            if(count($order)==0){
                return response()->json(['code'=>400,'message'=>'order_empty']);
            }
            
            $col_colls = collect($order);
            $filter_colls = $col_colls->where('admin_verified',0);
            $cart_ids = $filter_colls->pluck('cart_id')->all();
            $booking_ids = $filter_colls->pluck('booking_id')->all();

            if(empty($booking_ids) && empty($cart_ids) && $query->status==4){
                return response()->json(['code'=>200,'message'=>'success']);    
            }

            $this->updatePurchaseCount($cart_ids);

            DB::table('commerce_booking')->whereIn('booking_id',$booking_ids)->update([
                'admin_verified'=>1,
                'admin_verified_id'=>$data['admin_id'],
                'verified_at'=>$date
            ]);

            $count = count($order);

            DB::table('commerce_group_buy')->where('cg_id',$data['cg_id'])->update([
                'total_participant'=>$count,
                'show'=>0,
                'status'=>4,
                'updated_at'=>$date
            ]);

            app('App\Http\Controllers\Utility\GroupBuyController')->cancelOrderByGroup($data['cg_id'],$date);

            return response()->json(['code'=>200,'message'=>'success']);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'verify_group_buy_failed']);
        }
    }

    public function mergeGroupBuy(Request $request){
        $data = $request->all();
        try {
            $cg_id = array($data['cg_id_to'],$data['cg_id_from']);

            $query = DB::table('commerce_group_buy')
                ->whereIn('cg_id',$cg_id)
                ->whereNotNull('user_id')
            ->get();

            if(empty($query)){
                return response()->json(['code'=>400,'message'=>'invalid_groups']);
            }

            $coll_query = collect($query);

            $find_to = $coll_query->where('cg_id',$data['cg_id_to'])->first();
            $find_from = $coll_query->where('cg_id',$data['cg_id_from'])->first();

            if($find_to==null || $find_from==null){
                return response()->json(['code'=>405,'message'=>'invalid_group']);
            }

            $order = DB::table('commerce_booking')
                ->where([
                    'cg_id' => $find_from->cg_id,
                    'paid_status' => 1,
                    'cancel_status' => 0
                ])
            ->get();
            if(count($order)>0){
                $coll_order = collect($order);
                $booking_ids = $coll_order->pluck('booking_id')->all();
                $cart_ids = $coll_order->pluck('cart_id')->all();
                $update_booking['cg_id'] = $find_to->cg_id;
                DB::table('commerce_booking')->whereIn('booking_id',$booking_ids)->update($update_booking);
                $update['cg_id'] = $find_to->cg_id;
                DB::table('commerce_shopping_cart')->whereIn('cart_id',$cart_ids)->update($update);
            }

            $cart = DB::table('commerce_shopping_cart')->where('cg_id',$find_from->cg_id)->get();
            if(count($cart)>0){
                $coll_cart = collect($cart);
                $cart_ids2 = $coll_cart->pluck('cart_id')->all();
                $update_cart['cg_id'] = $find_to->cg_id;
                DB::table('commerce_shopping_cart')->whereIn('cart_id',$cart_ids2)->update($update_cart);
            }

            $date = date('Y-m-d H:i:s');
            DB::table('commerce_group_buy')->where('cg_id',$find_from->cg_id)->update([
                'status'=>5,
                'expire_at'=>null,
                'updated_at'=>$date
            ]);

            $order_to = DB::table('commerce_booking')
                ->where([
                    'paid_status'=>1,
                    'cancel_status'=>0
                ])
            ->get();
            
            if($find_to->status==4){
                $col_colls = collect($order_to);
                $filter_colls = $col_colls->where('admin_verified',0);
                $cart_ids = $filter_colls->pluck('cart_id')->all();
                $booking_ids = $filter_colls->pluck('booking_id')->all();
                
                $this->updatePurchaseCount($cart_ids);

                DB::table('commerce_booking')->whereIn('booking_id',$booking_ids)->update([
                    'admin_verified'=>1,
                    'admin_verified_id'=>999,
                    'verified_at'=>$date
                ]);
            }
             
            $count = count($order_to);

            if($find_to->minimum_participant<=$count){
                $update_group['status']=3;
            }

            $update_group['total_participant']=$count;
            $update_group['updated_at']=$date;
            DB::table('commerce_group_buy')->where('cg_id',$find_to->cg_id)->update($update_group);

            return response()->json(['code'=>200,'message'=>'success']);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'merge_group_buy_failed']);
        }
    }

    public function searchGroupBuy(Request $request){
        $data = $request->all();
        try {
            $search_query = $data['search_query'];
            $limit=$data['limit'];
            $offset_count=$data['offset']*$limit;
            $next_offset_count=($data['offset']+1)*$limit;

            $query = DB::table('commerce_group_buy')
                ->select('commerce_group_buy.cg_id','product_name','variant_name','fullname','commerce_group_buy.status')
                ->join('minimi_product','commerce_group_buy.product_id','=','minimi_product.product_id')
                ->join('minimi_product_variant','commerce_group_buy.variant_id','=','minimi_product_variant.variant_id')
                ->join('minimi_user_data','commerce_group_buy.user_id','=','minimi_user_data.user_id')
                ->where('commerce_group_buy.status','!=',5)
                ->where(function ($query) use ($search_query){
                    $query->where('product_name','like','%'.$search_query.'%')
                        ->orWhere('fullname','like','%'.$search_query.'%')
                        ->orWhere('variant_name','like','%'.$search_query.'%')
                        ->orWhere('cg_id',$search_query);
                })
            ->skip($offset_count)->take($limit)->get();

            if(count($query)>0){
                foreach ($query as $row) {
                    switch ($row->status) {
                        case 0:
                            $row->status = "0 : Expired (Waiting for verification)";
                            break;
                        case 1:
                            $row->status = "1 : Waiting For Participant Payment";
                            break;
                        case 2:
                            $row->status = "2 : Participant Requirement Not Met";
                            break;
                        case 3:
                            $row->status = "3 : Participant Met (Waiting for verification)";
                            break;
                        case 4:
                            $row->status = "4 : Verified";
                            break;
                        default:
                            $row->status = "5 : Closed";
                            break;
                    }
                }
                $next_offset = 'empty';
                if(count($query)>=$limit){
                    $query2 = DB::table('commerce_group_buy')
                        ->select('commerce_group_buy.cg_id')
                        ->join('minimi_product','commerce_group_buy.product_id','=','minimi_product.product_id')
                        ->join('minimi_product_variant','commerce_group_buy.variant_id','=','minimi_product_variant.variant_id')
                        ->join('minimi_user_data','commerce_group_buy.user_id','=','minimi_user_data.user_id')
                        ->where('commerce_group_buy.status','!=',5)
                        ->where(function ($query2) use ($search_query){
                            $query2->where('product_name','like','%'.$search_query.'%')
                                ->orWhere('fullname','like','%'.$search_query.'%')
                                ->orWhere('variant_name','like','%'.$search_query.'%');
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
            return response()->json(['code'=>4050,'message'=>'search_group_buy_failed']);
        }
    }

    /**
     * Utility Function
    **/

    public function updatePurchaseCount($cart_ids){
        $item = DB::table('commerce_shopping_cart_item')
            ->select('product_id','count')
            ->whereIn('cart_id',$cart_ids)
            ->where([
                'status'=>1,
                'count_flag'=>0
            ])
        ->get();

        if(count($item)==0){
            return FALSE;
        }

        $col_item = collect($item);
        $product_ids = $col_item->pluck('product_id')->unique()->all();
        
        $products = DB::table('minimi_product')
            ->select('product_id','product_purchase_count')
            ->whereIn('product_id',$product_ids)
        ->get();

        $date = date('Y-m-d H:i:s');

        foreach ($products as $row) {
            $finds = $col_item->where('product_id', $row->product_id)->all();
            $count = 0;
            foreach($finds as $find){
                $count += intval($find->count);
            }

            $update['product_purchase_count'] = intval($row->product_purchase_count)+intval($count);
            $update['updated_at'] = $date;
            DB::table('minimi_product')->where('product_id',$row->product_id)->update($update);
        }

        DB::table('commerce_shopping_cart_item')->whereIn('cart_id',$cart_ids)->update([
            'count_flag'=>1
        ]);

        return TRUE;
    }
}