<?php

namespace App\Http\Controllers\Dash;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

use DB;

class BookingController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }

    public function detailOrder(Request $request, $booking_id){
        $data = $request->all();
        try{
            $query = DB::table('commerce_booking')->select('order_id', 'user_id')
                ->where([
                    'booking_id'=>$booking_id,
                    'paid_status'=>1,
                    'cancel_status'=>0
                ])
                ->wherenotNull('order_id')
            ->first();

            if(empty($query)){
                return response()->json(['code'=>4803,'message'=>'invalid_order']);
            }

            $return = app('App\Http\Controllers\Utility\CartController')->getOrderDetail_exe($query->order_id, $query->user_id);

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        }catch(QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'order_detail_failed']);
		}
    }

    public function detailOrderBulk(Request $request){
        $data = $request->all();
        try{
            $query = DB::table('commerce_booking')->select('booking_id','order_id')
                ->whereIn('booking_id',$data['booking_id'])
                ->wherenotNull('order_id')
            ->get();

            if(count($query)==0){
                return response()->json(['code'=>4803,'message'=>'invalid_order']);
            }

            $coll = collect($query);
            $booking_ids = $coll->pluck('booking_id')->all();

            $return = app('App\Http\Controllers\Utility\CartController')->getOrderDetailBulk_exe($booking_ids);

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        }catch(QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'order_detail_failed']);
		}
    }

    public function searchOrder(Request $request){
        $data = $request->all();
        try{
            $query = DB::table('commerce_booking')->select('order_id', 'booking_id')
                ->where('order_id','like',$data['search_query'].'%');

            if($data['transaction_type']!='all'){
                $query = $query->where('transaction_type',$data['transaction_type']);
            }

            $query = $query->where([
                    'paid_status'=>1,
                    'cancel_status'=>0
                ])
            ->get();

            if(!count($query)){
                return response()->json(['code'=>4803,'message'=>'not_found']);
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$query]);
        }catch(QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'search_order_failed']);
		}
    }

    public function verifyOrder(Request $request){
        $data = $request->all();
        try{
            $query = DB::table('commerce_booking')->select('booking_id', 'transaction_type','user_id', 'cart_id', 'cg_id')
                ->where([
                    'order_id'=>$data['order_id'],
                    'commerce_booking.user_id'=>$data['user_id'],
                    'admin_verified'=>0,
                    'paid_status'=>1,
                    'cancel_status'=>0
                ])
            ->first();

            if(empty($query)){
                return response()->json(['code'=>4803,'message'=>'invalid_order']);
            }

            if($query->transaction_type==2){
                $product_id = DB::table('commerce_shopping_cart_item')->where('cart_id',$query->cart_id)->value('product_id');
                
                $return = app('App\Http\Controllers\Utility\VoucherController')->generateVoucher_exe($data['user_id'],$product_id);
                if($return=='empty'){
                    return response()->json(['code'=>4043,'message'=>'product_invalid']);
                }
            }elseif($query->transaction_type==1){
                app('App\Http\Controllers\Utility\MailController')->sendPaymentVerifiedEmailPhys($data['order_id']);
            }elseif($query->transaction_type==3){
                return response()->json(['code'=>4806,'message'=>'unable_to_verify_groupbuy']);
                /*$verdict = app('App\Http\Controllers\API\GroupBuyController')->validateGroupBuy($query->cg_id);
                if($verdict==FALSE){
                    return response()->json(['code'=>4806,'message'=>'group_buy_requirement_not_met']);
                }*/
            }

            $this->updatePurchaseCount($query->cart_id);

            $update['admin_verified']=1;
            $update['admin_verified_id']=$data['admin_id'];
            $update['verified_at']=date('Y-m-d H:i:s');

            DB::table('commerce_booking')->where('booking_id',$query->booking_id)->update($update);

            if($query->transaction_type==3){
                DB::table('commerce_group_buy')->where('cg_id',$query->cg_id)->update([
                    'status'=>4,
                    'updated_at'=>date('Y-m-d H:i:s')
                ]);
            }

            return response()->json(['code'=>200,'message'=>'success']);
        }catch(QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'order_verification_failed']);
		}
    }

    public function giveVoucher($user_id, $cart_id){
        $product_id = DB::table('commerce_shopping_cart_item')->where('cart_id',$cart_id)->value('product_id');
                
        $return = app('App\Http\Controllers\Utility\VoucherController')->generateVoucher_exe($user_id,$product_id);
        if($return=='empty'){
            return response()->json(['code'=>4043,'message'=>'product_invalid']);
        }

        return response()->json(['code'=>200,'message'=>'success']);
    }

    public function verifyPickupOrder(Request $request){
        $data = $request->all();
        try{
            $query = DB::table('commerce_booking')->select('booking_id', 'transaction_type','user_id', 'cart_id','delivery_vendor','delivery_receipt_number')
                ->where([
                    'order_id'=>$data['order_id'],
                    'commerce_booking.user_id'=>$data['user_id'],
                    'admin_verified'=>1,
                    'delivery_verified'=>0,
                    'paid_status'=>1,
                    'cancel_status'=>0
                ])
                ->whereIn('transaction_type',[1,3])
            ->first();
            
            if(empty($query)){
                return response()->json(['code'=>4803,'message'=>'invalid_order']);
            }else{
                if(strtolower($query->delivery_vendor)=='sicepat' && ($query->delivery_receipt_number==null||$query->delivery_receipt_number=='')){
                    return response()->json(['code'=>4803,'message'=>'invalid_order']);
                }
            }

            $update['delivery_verified']=1;
            $update['delivery_verified_id']=$data['admin_id'];
            $update['delivery_verified_at']=date('Y-m-d H:i:s');

            DB::table('commerce_booking')->where('booking_id',$query->booking_id)->update($update);

            return response()->json(['code'=>200,'message'=>'success']);
        }catch(QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'order_pickup_verification_failed']);
		}
    }

    public function verifyPickupOrderBulk(Request $request){
        $data = $request->all();
        try{
            $query = DB::table('commerce_booking')->select('booking_id', 'transaction_type','user_id', 'cart_id','delivery_vendor','delivery_receipt_number')
                ->whereIn('order_id',$data['order_id'])
                ->where([
                    'admin_verified'=>1,
                    'delivery_verified'=>0,
                    'paid_status'=>1,
                    'cancel_status'=>0
                ])
                ->whereIn('transaction_type',[1,3])
            ->get();
            
            if(empty($query)){
                return response()->json(['code'=>4803,'message'=>'invalid_orders']);
            }else{
                foreach ($query as $key=>$row) {
                    if(strtolower($row->delivery_vendor)=='sicepat' && ($row->delivery_receipt_number==null||$row->delivery_receipt_number=='')){
                        unset($query[$key]);
                    }
                }
                $col_query = collect($query);
                $booking_ids = $col_query->pluck('booking_id')->all();

                if(empty($booking_ids)){
                    return response()->json(['code'=>4803,'message'=>'invalid_orders']);
                }
            }

            $update['delivery_verified']=1;
            $update['delivery_verified_id']=$data['admin_id'];
            $update['delivery_verified_at']=date('Y-m-d H:i:s');

            DB::table('commerce_booking')->whereIn('booking_id',$booking_ids)->update($update);

            return response()->json(['code'=>200,'message'=>'success']);
        }catch(QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'order_pickup_verification_bulk_failed']);
		}
    }

    /**
     * Utility Function
    **/

    public function updatePurchaseCount($cart_id){
        $item = DB::table('commerce_shopping_cart_item')
            ->select('product_id','count')
            ->where([
                'cart_id'=>$cart_id,
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
            $find = $col_item->where('product_id', $row->product_id)->first();
            $update['product_purchase_count'] = intval($row->product_purchase_count)+intval($find->count);
            $update['updated_at'] = $date;
            DB::table('minimi_product')->where('product_id',$row->product_id)->update($update);
        }

        DB::table('commerce_shopping_cart_item')->where('cart_id',$cart_id)->update([
            'count_flag'=>1
        ]);

        return TRUE;
    }
}