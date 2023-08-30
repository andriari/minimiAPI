<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;

use DB;

class OrderController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }

    public function packageReceived(Request $request, $order_id){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $return = app('App\Http\Controllers\Utility\CartController')->orderReceived_exe($order_id, $currentUser->user_id);
            switch ($return) {
                case 'order_not_found':
                    return response()->json(['code'=>4901,'message'=>$return]);
                break;
                case 'order_invalid':
                    return response()->json(['code'=>4902,'message'=>$return]);
                break;
                case 'package_already_received':
                    return response()->json(['code'=>4903,'message'=>$return]);
                break;
                default:
                    app('App\Http\Controllers\Utility\CartController')->pointTransaction_exe($order_id);
                    app('App\Http\Controllers\Utility\MailController')->sendTransactionCompleteEmailPhys($order_id);
                    return response()->json(['code'=>200,'message'=>'success']);
                break;
            }
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'order_detail_failed']);
        }
    }

    public function trackOrder(Request $request, $order_id){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }
            $order = app('App\Http\Controllers\Utility\CartController')->getOrderTrack_exe($order_id, $currentUser->user_id);
            switch ($order) {
                case 'order_not_found':
                    return response()->json(['code'=>4901,'message'=>$order]);
                break;
                case 'order_invalid':
                    return response()->json(['code'=>4902,'message'=>$order]);
                break;
                default:
                    return response()->json(['code'=>200,'message'=>'success','data'=>$order]);
                break;
            }
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'order_track_failed']);
        }
    }

    public function detailOrder(Request $request, $order_id){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }
            $order = app('App\Http\Controllers\Utility\CartController')->getOrderDetail_exe($order_id, $currentUser->user_id);
            switch ($order) {
                case 'order_not_found':
                    return response()->json(['code'=>4901,'message'=>$order]);
                break;
                case 'order_invalid':
                    return response()->json(['code'=>4902,'message'=>$order]);
                break;
                default:
                    return response()->json(['code'=>200,'message'=>'success','data'=>$order]);
                break;
            }
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'order_detail_failed']);
        }
    }

    public function historyOrder(Request $request,$mode=1){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }
            $order = app('App\Http\Controllers\Utility\CartController')->getOrderListUser_exe($currentUser->user_id,$mode);
            
            switch ($order) {
                case 'order_not_found':
                    return response()->json(['code'=>4901,'message'=>$order]);
                break;
                default:
                    return response()->json(['code'=>200,'message'=>'success','data'=>$order]);
                break;
            }

        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'order_history_failed']);
        }
    }

    public function historyOrderLatest(Request $request,$mode=1){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            if($mode==3){
                $order = app('App\Http\Controllers\Utility\CartController')->getOrderListUser_gb_exe_limit($currentUser->user_id,0,3);
            }else{
                $order = app('App\Http\Controllers\Utility\CartController')->getOrderListUser_exe_limit($currentUser->user_id,$mode,0,3);
            }
            
            switch ($order) {
                case 'order_not_found':
                    return response()->json(['code'=>4901,'message'=>$order]);
                break;
                default:
                    return response()->json(['code'=>200,'message'=>'success','data'=>$order]);
                break;
            }
            
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'order_history_latest_failed']);
        }
    }

    public function cancelOrder(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }
            
            $order = app('App\Http\Controllers\Utility\CartController')->getOrderDetail_exe($data['order_id'], $currentUser->user_id);
            switch ($order){
                case 'order_not_found':
                    return response()->json(['code'=>4901,'message'=>$return]);
                break;
                case 'order_invalid':
                    return response()->json(['code'=>4902,'message'=>$return]);
                break;
                default:
                    if($order->status=='waiting_for_payment'||$order->status=='draft'){
                        $update['paid_status'] = 2;
                        $update['cancel_status'] = 1;
                        $update['updated_at'] = date('Y-m-d H:i:s');
                        DB::table('commerce_booking')->where('order_id',$data['order_id'])->update($update);
                        app('App\Http\Controllers\Utility\PaymentController')->payment_cancel($data['order_id']);
                        //send notification here
                        return response()->json(['code'=>200,'message'=>'success']);
                    }elseif($order->status=='paid'||$order->status=='verified_by_admin'||$order->status=='package_picked_up'||$order->status=='package_received'){
                        return response()->json(['code'=>201,'message'=>'customer_service_needed']);
                    }elseif($order->status=='transaction_cancelled'){
                        return response()->json(['code'=>301,'message'=>'already_cancelled']);
                    }
                break;
            }

        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'order_cancel_failed']);
        }
    }

    public function reviewOrderItem(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }
            
            $order = app('App\Http\Controllers\Utility\CartController')->getOrderItemReview_exe($currentUser->user_id);
            
            switch ($order) {
                case 'order_not_found':
                    return response()->json(['code'=>4901,'message'=>$order]);
                break;
                default:
                    return response()->json(['code'=>200,'message'=>'success','data'=>$order]);
                break;
            }

        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'order_item_review_failed']);
        }
    }
}