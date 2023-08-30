<?php

namespace App\Http\Controllers\Utility;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

use DB;

class CartController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }

    public function getOrderTrack_exe($order_id, $user_id){
        $query = DB::table('commerce_booking')
            ->select(
                'commerce_booking.order_id', 
                'commerce_booking.user_id', 
                'commerce_booking.paid_status', 
                'commerce_booking.cancel_status', 
                'commerce_booking.delivery_vendor', 
                'commerce_booking.delivery_service', 
                'commerce_booking.delivery_receipt_number',
                'commerce_booking.admin_verified',
                'commerce_booking.verified_at',
                'commerce_booking.delivery_verified',
                'commerce_booking.delivery_verified_at',
                'commerce_booking.received',
                'commerce_booking.received_at',
                'commerce_booking.created_at',
                'commerce_booking.updated_at'
            )
            ->where([
                'order_id'=>$order_id
                //'order_id'=>$order_id,
                //'user_id'=>$user_id
            ])
            ->whereIn('transaction_type',[1,3])
        ->first();

        if(empty($query)){
            return 'order_not_found';
        }

        switch ($query->delivery_service) {
            case 'MIX':
                $return['delivery_name'] = 'Minimi Express';
                break;
            case 'BEST':
                $return['delivery_name'] = 'SiCepat BEST (Besok Sampai Tujuan)';
                break;
            case 'SIUNT':
                $return['delivery_name'] = 'SiCepay SIUNT (Siuntung)';
                break;
            case 'GOKIL':
                $return['delivery_name'] = 'SiCepat GOKIL (Cargo Kilat)';
                break;
            default:
                return 'order_invalid';
                break;
        }
        $return['delivery_receipt_number'] = $query->delivery_receipt_number;
        $return['track_history'] = $this->historyTrack_exe($query);

        return $return;
    }

    public function getOrderDetail_exe($order_id, $user_id){
        $query = DB::table('commerce_booking')
            ->select(
                'commerce_booking.cart_id', 
                'commerce_booking.order_id', 
                'commerce_booking.cg_id', 
                'commerce_booking.price_amount', 
                'commerce_booking.delivery_amount', 
                'commerce_booking.discount_amount', 
                'commerce_booking.delivery_discount_amount', 
                'commerce_booking.insurance_amount',  
                'commerce_booking.total_amount', 
                'commerce_booking.payment_vendor', 
                'commerce_booking.payment_method', 
                'commerce_booking.pg_code', 
                'commerce_booking.delivery_vendor', 
                'commerce_booking.delivery_service', 
                'commerce_booking.delivery_receipt_number',
                'commerce_booking.transaction_type',
                'commerce_booking.admin_verified',
                'commerce_booking.verified_at',
                'commerce_booking.delivery_verified',
                'commerce_booking.received',
                'commerce_booking.received_by',
                'commerce_booking.received_at',
                'commerce_booking.paid_status',
                'commerce_booking.cancel_status',
                'commerce_booking.created_at',
                'commerce_booking.updated_at',
                'commerce_group_buy.expire_at',
                'commerce_shopping_cart.total_weight',
                'commerce_voucher.voucher_code',
                'minimi_user_data.user_id', 
                'minimi_user_data.fullname', 
                'minimi_user_data.email', 
                'minimi_user_data.phone', 
                'minimi_user_address.address_title',
                'minimi_user_address.address_pic',
                'minimi_user_address.address_phone',
                'minimi_user_address.address_detail',
                'minimi_user_address.address_postal_code',
                'minimi_user_address.address_subdistrict_name',
                'minimi_user_address.address_city_name',
                'minimi_user_address.address_province_name',
                'minimi_user_address.address_country_code',
                'minimi_user_address.address_lat',
                'minimi_user_address.address_long',
                'minimi_user_address.sicepat_destination_code'
            )
            ->join('minimi_user_data','minimi_user_data.user_id','=','commerce_booking.user_id')
            ->join('commerce_shopping_cart','commerce_shopping_cart.cart_id','=','commerce_booking.cart_id')
            ->leftJoin('commerce_group_buy','commerce_group_buy.cg_id','=','commerce_booking.cg_id')
            ->leftJoin('minimi_user_address','minimi_user_address.address_id','=','commerce_booking.address_id')
            ->leftJoin('commerce_voucher','commerce_shopping_cart.voucher_id','=','commerce_voucher.voucher_id')
            ->where([
                'order_id'=>$order_id,
                'commerce_booking.user_id'=>$user_id
            ])
        ->first();

        if(empty($query)){
            return 'order_not_found';
        }

        $point_potential = 0;
        if($query->transaction_type==1 || $query->transaction_type==3){
            $item = DB::table('commerce_shopping_cart_item')
                ->select('cart_id', 'commerce_shopping_cart_item.item_id', 'commerce_shopping_cart_item.count', 'minimi_product_variant.variant_name', 'commerce_shopping_cart_item.price_amount as price', 'minimi_product.product_id', 'minimi_product.product_uri', 'minimi_product.product_name', 'minimi_product_gallery.prod_gallery_picture as product_image')
                ->join('minimi_product','minimi_product.product_id','=','commerce_shopping_cart_item.product_id')
                ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_shopping_cart_item.product_id')
                ->join('minimi_product_variant','minimi_product_variant.variant_id','=','commerce_shopping_cart_item.variant_id')
                ->where([
                    'cart_id'=>$query->cart_id,
                    'commerce_shopping_cart_item.status'=>1,
                    'minimi_product_gallery.main_poster'=>1,
                    'minimi_product_gallery.status'=>1
                ])
            ->get();

            $task = DB::table('point_task')->select('task_id', 'task_name', 'task_value', 'task_type', 'task_limit')->where('content_tag', 'post_2')->first();
            foreach ($item as $it) {
                $point_potential += floatval($task->task_value);
            }
        }elseif($query->transaction_type==2){
            $item = DB::table('commerce_shopping_cart_item')
                ->select('minimi_product.product_id', 'commerce_shopping_cart_item.item_id', 'minimi_product.product_uri', 'minimi_product.product_name', 'commerce_shopping_cart_item.price_amount as price', 'minimi_product_digital.voucher_count as count', 'minimi_product_digital.voucher_desc as variant_name', 'minimi_product_digital.voucher_tnc', 'minimi_product_gallery.prod_gallery_picture as product_image')
                ->join('minimi_product','minimi_product.product_id','=','commerce_shopping_cart_item.product_id')
                ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_shopping_cart_item.product_id')
                ->join('minimi_product_digital','minimi_product_digital.product_id','=','minimi_product.product_id')
                ->where([
                    'cart_id'=>$query->cart_id,
                    'commerce_shopping_cart_item.status'=>1,
                    'minimi_product_gallery.main_poster'=>1,
                    'minimi_product_gallery.status'=>1
                ])
            ->get();
        }

        if($query->transaction_type==1 || $query->transaction_type==2){
            $query->expire_at = date('Y-m-d H:i:s',strtotime($query->created_at.' + 1 days'));
        }

        if(!count($item)){
            return 'order_invalid';
        }

        $name = app('App\Http\Controllers\API\AuthController')->splitName($query->fullname);
        $query->first_name = $name['first_name'];
        $query->last_name = $name['last_name'];

        switch ($query->paid_status) {
            case 0:
                $query->status = 'waiting_for_payment';
            break;
            case 1:
                $query->status = 'paid';
                if($query->admin_verified==1){
                    $query->status = 'verified_by_admin';
                }
                if($query->delivery_verified==1){
                    $query->status = 'package_picked_up';
                }
                if($query->received==1){
                    $query->status = 'package_received';
                }
            break;
            case 2:
                $query->status = 'transaction_cancelled';
            break;
            case 4:
                $query->status = 'waiting_for_payment';
            break;    
            default:
                $query->status = 'draft';
            break;
        }
        $query->shopping_cart_item = $item;

        $payment_arr = array();
        $payment_arr['payment_type'] = '';
        $payment_arr['payment_method'] = '';
        $payment_arr['biller_code'] = '';
        $payment_arr['bill_key'] = '';
        $payment_arr['virtual_account'] = '';
        $payment_arr['bank'] = '';
        $payment_arr['faspay_link'] = '';
        $payment_arr['pg_group'] = '';

        if($query->status!='draft'){
            $payment_data = app('App\Http\Controllers\Utility\MailController')->composePaymentMethod($order_id);
            if($payment_data!=null){
                $payment_arr['payment_type'] = $payment_data->payment_type;
                $payment_arr['payment_vendor'] = $payment_data->payment_vendor;
                $payment_arr['payment_method'] = $payment_data->payment_method;
                $payment_arr['biller_code'] = $payment_data->biller_code;
                $payment_arr['bill_key'] = $payment_data->bill_key;
                $payment_arr['virtual_account'] = $payment_data->virtual_account;
                $payment_arr['bank'] = $payment_data->bank;
                $payment_arr['faspay_link'] = $payment_data->faspay_link;
                if($query->pg_code=='801' || $query->pg_code=='708' || $query->pg_code=='408' || $query->pg_code=='402'){
					$payment_arr['pg_group'] = 'va';
				}elseif($query->pg_code=='812' || $query->pg_code=='819'){
					$payment_arr['pg_group'] = 'wallet';
				}elseif($query->pg_code=='820'){
					$payment_arr['pg_group'] = 'other';
				}
            }
            
        }
        
        $query->actual_point = $this->getReviewPoint_order_id($order_id);
        $query->potential_point = $point_potential;
        
        $query->payment_data = $payment_arr;

        if($query->transaction_type==3){
            $query->participant = app('App\Http\Controllers\API\GroupBuyController')->getGroupBuyParticipant_booking($query->cg_id, $user_id);
        }

        return $query;
    }

    public function getReviewPoint_order_id($order_id){
        $post = DB::table('minimi_content_post')->where('order_id',$order_id)->get();
        $task = DB::table('point_task')->select('task_id', 'task_name', 'task_value', 'task_type', 'task_limit')->where('content_tag', 'post_2')->first();
        $point = 0;
        foreach ($post as $row){
            if($row->content_curated==1){
                $point += floatval($task->task_value);
            }
        }

        return $point;
    }

    public function getOrderDetailBulk_exe($booking_id){
        $query = DB::table('commerce_booking')
            ->select(
                'commerce_booking.booking_id', 
                'commerce_booking.cart_id', 
                'commerce_booking.order_id', 
                'commerce_booking.cg_id', 
                'commerce_booking.price_amount', 
                'commerce_booking.delivery_amount', 
                'commerce_booking.discount_amount', 
                'commerce_booking.delivery_discount_amount', 
                'commerce_booking.insurance_amount',  
                'commerce_booking.total_amount', 
                'commerce_booking.payment_vendor', 
                'commerce_booking.payment_method', 
                'commerce_booking.pg_code', 
                'commerce_booking.delivery_vendor', 
                'commerce_booking.delivery_service', 
                'commerce_booking.delivery_receipt_number',
                'commerce_booking.transaction_type',
                'commerce_booking.admin_verified',
                'commerce_booking.verified_at',
                'commerce_booking.delivery_verified',
                'commerce_booking.received',
                'commerce_booking.received_by',
                'commerce_booking.received_at',
                'commerce_booking.paid_status',
                'commerce_booking.cancel_status',
                'commerce_booking.created_at',
                'commerce_booking.updated_at',
                'commerce_group_buy.expire_at',
                'commerce_shopping_cart.total_weight',
                'commerce_voucher.voucher_code',
                'minimi_user_data.user_id', 
                'minimi_user_data.fullname', 
                'minimi_user_data.email', 
                'minimi_user_data.phone', 
                'minimi_user_address.address_title',
                'minimi_user_address.address_pic',
                'minimi_user_address.address_phone',
                'minimi_user_address.address_detail',
                'minimi_user_address.address_postal_code',
                'minimi_user_address.address_subdistrict_name',
                'minimi_user_address.address_city_name',
                'minimi_user_address.address_province_name',
                'minimi_user_address.address_country_code',
                'minimi_user_address.address_lat',
                'minimi_user_address.address_long',
                'minimi_user_address.sicepat_destination_code'
            )
            ->join('minimi_user_data','minimi_user_data.user_id','=','commerce_booking.user_id')
            ->join('commerce_shopping_cart','commerce_shopping_cart.cart_id','=','commerce_booking.cart_id')
            ->leftJoin('commerce_group_buy','commerce_group_buy.cg_id','=','commerce_booking.cg_id')
            ->leftJoin('minimi_user_address','minimi_user_address.address_id','=','commerce_booking.address_id')
            ->leftJoin('commerce_voucher','commerce_shopping_cart.voucher_id','=','commerce_voucher.voucher_id')
            ->whereIn('commerce_booking.booking_id',$booking_id)
        ->get();

        if(count($query)==0){
            return 'order_not_found';
        }

        $coll = collect($query);
        $filter_non_digital = $coll->whereIn('transaction_type', [1,3]);
        $cart_ids_non_digital = $filter_non_digital->pluck('cart_id')->all();
        $filter_digital = $coll->where('transaction_type', 2);
        $cart_ids_digital = $filter_digital->pluck('cart_id')->all();

        $item_non_digital = DB::table('commerce_shopping_cart_item')
            ->select('cart_id', 'commerce_shopping_cart_item.count', 'minimi_product_variant.variant_name', 'commerce_shopping_cart_item.price_amount as price', 'minimi_product.product_id', 'minimi_product.product_uri', 'minimi_product.product_name', 'minimi_product_gallery.prod_gallery_picture as product_image')
            ->join('minimi_product','minimi_product.product_id','=','commerce_shopping_cart_item.product_id')
            ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_shopping_cart_item.product_id')
            ->join('minimi_product_variant','minimi_product_variant.variant_id','=','commerce_shopping_cart_item.variant_id')
            ->where([
                'commerce_shopping_cart_item.status'=>1,
                'minimi_product_gallery.main_poster'=>1,
                'minimi_product_gallery.status'=>1
            ])
            ->whereIn('cart_id',$cart_ids_non_digital)
        ->get();
        $col_nd = collect($item_non_digital);
        
        $item_digital = DB::table('commerce_shopping_cart_item')
            ->select('minimi_product.product_id', 'minimi_product.product_uri', 'minimi_product.product_name', 'commerce_shopping_cart_item.price_amount as price', 'minimi_product_digital.voucher_count as count', 'minimi_product_digital.voucher_desc as variant_name', 'minimi_product_digital.voucher_tnc', 'minimi_product_gallery.prod_gallery_picture as product_image')
            ->join('minimi_product','minimi_product.product_id','=','commerce_shopping_cart_item.product_id')
            ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_shopping_cart_item.product_id')
            ->join('minimi_product_digital','minimi_product_digital.product_id','=','minimi_product.product_id')
            ->where([
                'commerce_shopping_cart_item.status'=>1,
                'minimi_product_gallery.main_poster'=>1,
                'minimi_product_gallery.status'=>1
            ])
            ->whereIn('cart_id',$cart_ids_non_digital)
        ->get();
        $col_d = collect($item_digital);

        foreach ($query as $row) {
            $name = app('App\Http\Controllers\API\AuthController')->splitName($row->fullname);
            $row->first_name = $name['first_name'];
            $row->last_name = $name['last_name'];
    
            switch ($row->paid_status) {
                case 0:
                    $row->status = 'waiting_for_payment';
                break;
                case 1:
                    $row->status = 'paid';
                    if($row->admin_verified==1){
                        $row->status = 'verified_by_admin';
                    }
                    if($row->delivery_verified==1){
                        $row->status = 'package_picked_up';
                    }
                    if($row->received==1){
                        $row->status = 'package_received';
                    }
                break;
                case 2:
                    $row->status = 'transaction_cancelled';
                break;
                case 4:
                    $row->status = 'waiting_for_payment';
                break;    
                default:
                    $row->status = 'draft';
                break;
            }

            if($row->transaction_type==1||$row->transaction_type==3){
                $item = $col_nd->where('cart_id',$row->cart_id)->all();
            }elseif($row->transaction_type==2){
                $item = $col_d->where('cart_id',$row->cart_id)->all();
            }

            $item = array_values($item);

            $i=0;
            $items = array();
            foreach($item as $fin){
                $items[$i]['count'] = $fin->count;
                $items[$i]['variant_name'] = $fin->variant_name;
                $items[$i]['price'] = $fin->price;
                $items[$i]['product_name'] = $fin->product_name;
                $items[$i]['product_image'] = $fin->product_image;
                if($row->transaction_type==2){
                    $items[$i]['tnc'] = $fin->voucher_tnc;
                }
                $i++;
            }

            $row->shopping_cart_item = $items;
    
            $payment_arr = array();
            $payment_arr['payment_type'] = '';
            $payment_arr['payment_method'] = '';
            $payment_arr['biller_code'] = '';
            $payment_arr['bill_key'] = '';
            $payment_arr['virtual_account'] = '';
            $payment_arr['bank'] = '';
    
            if($row->status!='draft'){
                $payment_data = app('App\Http\Controllers\Utility\MailController')->composePaymentMethod($row->order_id);
                if($payment_data!=null){
                    $payment_arr['payment_type'] = $payment_data->payment_type;
                    $payment_arr['payment_vendor'] = $payment_data->payment_vendor;
                    $payment_arr['payment_method'] = $payment_data->payment_method;
                    $payment_arr['biller_code'] = $payment_data->biller_code;
                    $payment_arr['bill_key'] = $payment_data->bill_key;
                    $payment_arr['virtual_account'] = $payment_data->virtual_account;
                    $payment_arr['bank'] = $payment_data->bank;
                    $payment_arr['faspay_link'] = $payment_data->faspay_link;
                }
            }
            
            $row->payment_data = $payment_arr;
    
            if($row->transaction_type==3){
                $row->participant  = app('App\Http\Controllers\API\GroupBuyController')->getGroupBuyParticipant_booking($row->cg_id, $row->user_id);
            }
        }

        return $query;
    }

    public function getOrderListUser_exe($user_id,$mode){
        $query = DB::table('commerce_booking')
            ->select(
                'commerce_booking.cart_id', 
                'commerce_booking.order_id', 
                'commerce_booking.price_amount', 
                'commerce_booking.delivery_amount', 
                'commerce_booking.discount_amount', 
                'commerce_booking.delivery_discount_amount', 
                'commerce_booking.insurance_amount', 
                'commerce_booking.total_amount', 
                'commerce_booking.payment_method', 
                'commerce_booking.delivery_service', 
                'commerce_booking.delivery_receipt_number',
                'commerce_booking.transaction_type',
                'commerce_booking.admin_verified',
                'commerce_booking.delivery_verified',
                'commerce_booking.received',
                'commerce_booking.received_by',
                'commerce_booking.received_at',
                'commerce_booking.paid_status',
                'commerce_booking.cancel_status',
                'commerce_booking.created_at',
                'commerce_shopping_cart.total_weight',
                'commerce_voucher.voucher_code',
                'minimi_user_data.fullname', 
                'minimi_user_data.email', 
                'minimi_user_data.phone', 
                'minimi_user_address.address_title',
                'minimi_user_address.address_pic',
                'minimi_user_address.address_phone',
                'minimi_user_address.address_detail',
                'minimi_user_address.address_postal_code',
                'minimi_user_address.address_subdistrict_name',
                'minimi_user_address.address_city_name',
                'minimi_user_address.address_province_name',
                'minimi_user_address.address_country_code',
                'minimi_user_address.address_lat',
                'minimi_user_address.address_long',
                'minimi_user_address.sicepat_destination_code'
            )
            ->join('minimi_user_data','minimi_user_data.user_id','=','commerce_booking.user_id')
            ->join('commerce_shopping_cart','commerce_shopping_cart.cart_id','=','commerce_booking.cart_id')
            ->leftJoin('minimi_user_address','minimi_user_address.address_id','=','commerce_booking.address_id')
            ->leftJoin('commerce_voucher','commerce_shopping_cart.voucher_id','=','commerce_voucher.voucher_id')
            ->where([
                'commerce_booking.user_id'=>$user_id,
                'commerce_booking.hide'=>0
            ]);
        if($mode==1){
            $query = $query->whereIn('commerce_booking.transaction_type',[1]);
        }elseif($mode==2||$mode==3){
            $query = $query->where('commerce_booking.transaction_type',$mode);
        }else{
            $query = $query->whereIn('commerce_booking.transaction_type',[1,2,3]);
        }

        $query = $query->whereNotNull('order_id')->orderBy('commerce_booking.updated_at','DESC')
        ->get();

        if(empty($query)){
            return 'order_not_found';
        }

        $collect = collect($query);
        $cart_ids = $collect->pluck('cart_id')->all();

        if($mode==1 || $mode==3){
            $item = DB::table('commerce_shopping_cart_item')
                ->select('cart_id','commerce_shopping_cart_item.count', 'minimi_product_variant.variant_name', 'commerce_shopping_cart_item.price_amount as price','minimi_product.product_id','minimi_product.product_uri', 'minimi_product.product_name', 'minimi_product_gallery.prod_gallery_picture as product_image')
                ->join('minimi_product','minimi_product.product_id','=','commerce_shopping_cart_item.product_id')
                ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_shopping_cart_item.product_id')
                ->join('minimi_product_variant','minimi_product_variant.variant_id','=','commerce_shopping_cart_item.variant_id')
                ->whereIn('cart_id',$cart_ids)
                ->where([
                    'commerce_shopping_cart_item.status'=>1,
                    'minimi_product_gallery.main_poster'=>1,
                    'minimi_product_gallery.status'=>1
                ])
            ->get();
        }elseif($mode==2){
            $item = DB::table('commerce_shopping_cart_item')
                ->select('cart_id','minimi_product.product_id','minimi_product.product_uri','minimi_product.product_name', 'minimi_product_digital.voucher_count as count', 'minimi_product_digital.voucher_desc as variant_name','commerce_shopping_cart_item.price_amount as price', 'minimi_product_digital.voucher_tnc', 'minimi_product_gallery.prod_gallery_picture as product_image')
                ->join('minimi_product','minimi_product.product_id','=','commerce_shopping_cart_item.product_id')
                ->join('minimi_product_digital','minimi_product_digital.product_id','=','minimi_product.product_id')
                ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_shopping_cart_item.product_id')
                ->whereIn('cart_id',$cart_ids)
                ->where([
                    'commerce_shopping_cart_item.status'=>1,
                    'minimi_product_gallery.main_poster'=>1,
                    'minimi_product_gallery.status'=>1
                ])
            ->get();
        }else{
            $item = DB::table('commerce_shopping_cart_item')
                ->select('cart_id','commerce_shopping_cart_item.count', 'minimi_product_variant.variant_name', 'commerce_shopping_cart_item.price_amount as price','minimi_product.product_id','minimi_product.product_uri', 'minimi_product.product_name', 'minimi_product_gallery.prod_gallery_picture as product_image')
                ->join('minimi_product','minimi_product.product_id','=','commerce_shopping_cart_item.product_id')
                ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_shopping_cart_item.product_id')
                ->join('minimi_product_variant','minimi_product_variant.variant_id','=','commerce_shopping_cart_item.variant_id')
                ->whereIn('cart_id',$cart_ids)
                ->where([
                    'commerce_shopping_cart_item.status'=>1,
                    'minimi_product_gallery.main_poster'=>1,
                    'minimi_product_gallery.status'=>1
                ])
            ->get();

            $item2 = DB::table('commerce_shopping_cart_item')
                ->select('cart_id','minimi_product.product_id','minimi_product.product_uri','minimi_product.product_name', 'minimi_product_digital.voucher_count as count', 'minimi_product_digital.voucher_desc as variant_name','commerce_shopping_cart_item.price_amount as price', 'minimi_product_digital.voucher_tnc', 'minimi_product_gallery.prod_gallery_picture as product_image')
                ->join('minimi_product','minimi_product.product_id','=','commerce_shopping_cart_item.product_id')
                ->join('minimi_product_digital','minimi_product_digital.product_id','=','minimi_product.product_id')
                ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_shopping_cart_item.product_id')
                ->whereIn('cart_id',$cart_ids)
                ->where([
                    'commerce_shopping_cart_item.status'=>1,
                    'minimi_product_gallery.main_poster'=>1,
                    'minimi_product_gallery.status'=>1
                ])
            ->get();
            $col_item2 = collect($item2);
        }

        $col_item = collect($item);
        
        foreach($query as $key=>$row){
            if($mode=='all'){
                if($row->transaction_type==1||$row->transaction_type==3){
                    $find = $col_item->where('cart_id',$row->cart_id)->all();
                }elseif($row->transaction_type==2){
                    $find = $col_item2->where('cart_id',$row->cart_id)->all();
                }
            }else{
                $find = $col_item->where('cart_id',$row->cart_id)->all();
            }
            $items = array();
            $i=0;
            foreach($find as $fin){
                $items[$i]['count'] = $fin->count;
                $items[$i]['variant_name'] = $fin->variant_name;
                $items[$i]['price'] = $fin->price;
                $items[$i]['product_name'] = $fin->product_name;
                $items[$i]['product_image'] = $fin->product_image;
                if($row->transaction_type==2){
                    $items[$i]['tnc'] = $fin->voucher_tnc;
                }
                $i++;
            }

            switch ($row->paid_status) {
                case 0:
                    $row->status = 'waiting_for_payment';
                break;
                case 1:
                    $row->status = 'paid';
                    if($row->transaction_type==1 || $row->transaction_type==3){
                        if($row->admin_verified==1){
                            $row->status = 'verified_by_admin';
                        }
                        if($row->delivery_verified==1){
                            $row->status = 'package_picked_up';
                        }
                        if($row->received==1){
                            $row->status = 'package_received';
                        }
                    }elseif($row->transaction_type==2){
                        if($row->admin_verified==1){
                            $row->status = 'package_received';
                        }
                    }
                break;
                case 2:
                    $row->status = 'transaction_cancelled';
                break;
                case 4:
                    $row->status = 'waiting_for_payment';
                break;    
                default:
                    $row->status = 'draft';
                break;
            }

            if(empty($items)){
                unset($query[$key]);
            }
            $row->shopping_cart_item = $items;
            $name = app('App\Http\Controllers\API\AuthController')->splitName($row->fullname);
            $row->first_name = $name['first_name'];
            $row->last_name = $name['last_name'];
        }

        $return = json_decode(json_encode($query), true);
        $return = array_values($return);
        return $return;
    }

    public function getOrderItemReview_exe($user_id){
        $exp_period = DB::table('data_param')->where('param_tag','order_review_expire_period')->value('param_value');
        $date = date('Y-m-d H:i:s',strtotime("-".$exp_period));  //expire_review
        $query = DB::table('commerce_booking')
            ->select(
                'commerce_booking.cart_id','commerce_booking.received_at','commerce_booking.order_id'
            )
            ->where([
                'commerce_booking.user_id'=>$user_id,
                'commerce_booking.hide'=>0,
                'commerce_booking.paid_status'=>1,
                'commerce_booking.received'=>1
            ])
            ->where('received_at','>=',$date)
            ->where('commerce_booking.transaction_type','!=',2)
            ->whereNotNull('order_id')->orderBy('commerce_booking.updated_at','DESC')
        ->get();

        if(empty($query)){
            return 'order_not_found';
        }

        $collect = collect($query);
        $cart_ids = $collect->pluck('cart_id')->all();

		$task = DB::table('point_task')->select('task_id', 'task_name', 'task_value', 'task_type', 'task_limit')->where('content_tag', 'post_2')->first();

        $item = DB::table('commerce_shopping_cart_item')
            ->select('cart_id','item_id','reviewed','commerce_shopping_cart_item.count', 'data_brand.brand_name', 'minimi_product_variant.variant_name', 'commerce_shopping_cart_item.price_amount as price','minimi_product.product_id','minimi_product.product_uri', 'minimi_product.product_name', 'minimi_product_gallery.prod_gallery_picture as product_image')
            ->join('minimi_product','minimi_product.product_id','=','commerce_shopping_cart_item.product_id')
            ->join('data_brand','minimi_product.brand_id','=','data_brand.brand_id')
            ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_shopping_cart_item.product_id')
            ->join('minimi_product_variant','minimi_product_variant.variant_id','=','commerce_shopping_cart_item.variant_id')
            ->whereIn('cart_id',$cart_ids)
            ->where([
                'commerce_shopping_cart_item.status'=>1,
                'minimi_product_gallery.main_poster'=>1,
                'minimi_product_gallery.status'=>1
            ])
        ->get();

        foreach ($item as $row) {
            $find = $collect->where('cart_id',$row->cart_id)->first();
            $date_exp = date('Y-m-d H:i:s',strtotime($find->received_at."+".$exp_period)); //expire_review
            $row->order_id = $find->order_id;
            $row->expire_date = $date_exp;
            $row->point = $task->task_value;
        }

        return $item;
    }

    public function getOrderListUser_exe_limit($user_id,$mode,$offset,$limit){
        $query = DB::table('commerce_booking')
            ->select(
                'commerce_booking.cart_id', 
                'commerce_booking.cg_id', 
                'commerce_booking.order_id', 
                'commerce_booking.price_amount', 
                'commerce_booking.delivery_amount', 
                'commerce_booking.discount_amount', 
                'commerce_booking.delivery_discount_amount', 
                'commerce_booking.insurance_amount', 
                'commerce_booking.total_amount', 
                'commerce_booking.payment_method', 
                'commerce_booking.delivery_service', 
                'commerce_booking.delivery_receipt_number',
                'commerce_booking.transaction_type',
                'commerce_booking.admin_verified',
                'commerce_booking.delivery_verified',
                'commerce_booking.received',
                'commerce_booking.received_by',
                'commerce_booking.received_at',
                'commerce_booking.paid_status',
                'commerce_booking.cancel_status',
                'commerce_booking.created_at',
                'commerce_shopping_cart.total_weight',
                'commerce_voucher.voucher_code',
                'minimi_user_data.fullname', 
                'minimi_user_data.email', 
                'minimi_user_data.phone', 
                'minimi_user_address.address_title',
                'minimi_user_address.address_pic',
                'minimi_user_address.address_phone',
                'minimi_user_address.address_detail',
                'minimi_user_address.address_postal_code',
                'minimi_user_address.address_subdistrict_name',
                'minimi_user_address.address_city_name',
                'minimi_user_address.address_province_name',
                'minimi_user_address.address_country_code',
                'minimi_user_address.address_lat',
                'minimi_user_address.address_long',
                'minimi_user_address.sicepat_destination_code'
            )
            ->join('minimi_user_data','minimi_user_data.user_id','=','commerce_booking.user_id')
            ->join('commerce_shopping_cart','commerce_shopping_cart.cart_id','=','commerce_booking.cart_id')
            ->leftJoin('minimi_user_address','minimi_user_address.address_id','=','commerce_booking.address_id')
            ->leftJoin('commerce_voucher','commerce_shopping_cart.voucher_id','=','commerce_voucher.voucher_id')
            ->where([
                'commerce_booking.user_id'=>$user_id,
                'commerce_booking.hide'=>0,
                'commerce_booking.paid_status'=>1,
                'commerce_booking.admin_verified'=>0
            ]);
        if($mode==1){
            $query = $query->whereIn('commerce_booking.transaction_type',[1]);
        }elseif($mode==2||$mode==3){
            $query = $query->where('commerce_booking.transaction_type',$mode);
        }else{
            $query = $query->whereIn('commerce_booking.transaction_type',[1,2,3]);
        }

        $query = $query->whereNotNull('order_id')->orderBy('commerce_booking.updated_at','DESC')
        ->skip($offset)->take($limit)->get();

        if(empty($query)){
            return 'order_not_found';
        }

        $collect = collect($query);
        $cart_ids = $collect->pluck('cart_id')->all();

        if($mode==1 || $mode==3){
            $item = DB::table('commerce_shopping_cart_item')
                ->select('cart_id','commerce_shopping_cart_item.count', 'data_brand.brand_name', 'minimi_product.product_uri', 'minimi_product_variant.variant_name', 'commerce_shopping_cart_item.price_amount as price','minimi_product.product_id','minimi_product.product_uri', 'minimi_product.product_name', 'minimi_product_gallery.prod_gallery_picture as product_image')
                ->join('minimi_product','minimi_product.product_id','=','commerce_shopping_cart_item.product_id')
                ->join('data_brand','minimi_product.brand_id','=','data_brand.brand_id')
                ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_shopping_cart_item.product_id')
                ->join('minimi_product_variant','minimi_product_variant.variant_id','=','commerce_shopping_cart_item.variant_id')
                ->whereIn('cart_id',$cart_ids)
                ->where([
                    'commerce_shopping_cart_item.status'=>1,
                    'minimi_product_gallery.main_poster'=>1,
                    'minimi_product_gallery.status'=>1
                ])
            ->get();
        }elseif($mode==2){
            $item = DB::table('commerce_shopping_cart_item')
                ->select('cart_id','minimi_product.product_id','minimi_product.product_uri','minimi_product.product_name', 'minimi_product_digital.voucher_count as count', 'minimi_product_digital.voucher_desc as variant_name','commerce_shopping_cart_item.price_amount as price', 'minimi_product_digital.voucher_tnc', 'minimi_product_gallery.prod_gallery_picture as product_image')
                ->join('minimi_product','minimi_product.product_id','=','commerce_shopping_cart_item.product_id')
                ->join('minimi_product_digital','minimi_product_digital.product_id','=','minimi_product.product_id')
                ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_shopping_cart_item.product_id')
                ->whereIn('cart_id',$cart_ids)
                ->where([
                    'commerce_shopping_cart_item.status'=>1,
                    'minimi_product_gallery.main_poster'=>1,
                    'minimi_product_gallery.status'=>1
                ])
            ->get();
        }else{
            $item = DB::table('commerce_shopping_cart_item')
                ->select('cart_id','commerce_shopping_cart_item.count', 'data_brand.brand_name', 'minimi_product.product_uri', 'minimi_product_variant.variant_name', 'commerce_shopping_cart_item.price_amount as price','minimi_product.product_id','minimi_product.product_uri', 'minimi_product.product_name', 'minimi_product_gallery.prod_gallery_picture as product_image')
                ->join('minimi_product','minimi_product.product_id','=','commerce_shopping_cart_item.product_id')
                ->join('data_brand','minimi_product.brand_id','=','data_brand.brand_id')
                ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_shopping_cart_item.product_id')
                ->join('minimi_product_variant','minimi_product_variant.variant_id','=','commerce_shopping_cart_item.variant_id')
                ->whereIn('cart_id',$cart_ids)
                ->where([
                    'commerce_shopping_cart_item.status'=>1,
                    'minimi_product_gallery.main_poster'=>1,
                    'minimi_product_gallery.status'=>1
                ])
            ->get();

            $item2 = DB::table('commerce_shopping_cart_item')
                ->select('cart_id','minimi_product.product_id','minimi_product.product_uri','minimi_product.product_name', 'minimi_product_digital.voucher_count as count', 'minimi_product_digital.voucher_desc as variant_name','commerce_shopping_cart_item.price_amount as price', 'minimi_product_digital.voucher_tnc', 'minimi_product_gallery.prod_gallery_picture as product_image')
                ->join('minimi_product','minimi_product.product_id','=','commerce_shopping_cart_item.product_id')
                ->join('minimi_product_digital','minimi_product_digital.product_id','=','minimi_product.product_id')
                ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_shopping_cart_item.product_id')
                ->whereIn('cart_id',$cart_ids)
                ->where([
                    'commerce_shopping_cart_item.status'=>1,
                    'minimi_product_gallery.main_poster'=>1,
                    'minimi_product_gallery.status'=>1
                ])
            ->get();
            $col_item2 = collect($item2);
        }

        $col_item = collect($item);
        
        foreach($query as $key=>$row){
            if($mode=='all'){
                if($row->transaction_type==1||$row->transaction_type==3){
                    $find = $col_item->where('cart_id',$row->cart_id)->all();
                }elseif($row->transaction_type==2){
                    $find = $col_item2->where('cart_id',$row->cart_id)->all();
                }
            }else{
                $find = $col_item->where('cart_id',$row->cart_id)->all();
            }
            $items = array();
            $i=0;
            foreach($find as $fin){
                $items[$i]['count'] = $fin->count;
                $items[$i]['product_uri'] = $fin->product_uri;
                $items[$i]['variant_name'] = $fin->variant_name;
                $items[$i]['price'] = $fin->price;
                $items[$i]['product_name'] = $fin->product_name;
                $items[$i]['product_image'] = $fin->product_image;
                if($row->transaction_type==2){
                    $items[$i]['tnc'] = $fin->voucher_tnc;
                }else{
                    $items[$i]['brand_name'] = $fin->brand_name;
                }
                $i++;
            }

            switch ($row->paid_status) {
                case 0:
                    $row->status = 'waiting_for_payment';
                break;
                case 1:
                    $row->status = 'paid';
                    if($row->transaction_type==1 || $row->transaction_type==3){
                        if($row->admin_verified==1){
                            $row->status = 'verified_by_admin';
                        }
                        if($row->delivery_verified==1){
                            $row->status = 'package_picked_up';
                        }
                        if($row->received==1){
                            $row->status = 'package_received';
                        }
                    }elseif($row->transaction_type==2){
                        if($row->admin_verified==1){
                            $row->status = 'package_received';
                        }
                    }
                break;
                case 2:
                    $row->status = 'transaction_cancelled';
                break;
                case 4:
                    $row->status = 'waiting_for_payment';
                break;    
                default:
                    $row->status = 'draft';
                break;
            }

            if(empty($items)){
                unset($query[$key]);
            }
            $row->shopping_cart_item = $items;
            $name = app('App\Http\Controllers\API\AuthController')->splitName($row->fullname);
            $row->first_name = $name['first_name'];
            $row->last_name = $name['last_name'];
            if($row->transaction_type==3){
                $row->group_buy = app('App\Http\Controllers\API\GroupBuyController')->getGroupBuy($row->cg_id);
            }
        }

        $return = json_decode(json_encode($query), true);
        $return = array_values($return);
        return $return;
    }

    public function getOrderListUser_gb_exe_limit($user_id,$offset,$limit){
        $date = date('Y-m-d H:i:s');
        $query = DB::table('commerce_booking')
            ->select(
                'commerce_booking.cart_id', 
                'commerce_booking.cg_id', 
                'commerce_booking.order_id', 
                'commerce_booking.price_amount', 
                'commerce_booking.delivery_amount', 
                'commerce_booking.discount_amount', 
                'commerce_booking.delivery_discount_amount', 
                'commerce_booking.insurance_amount', 
                'commerce_booking.total_amount', 
                'commerce_booking.payment_method', 
                'commerce_booking.delivery_service', 
                'commerce_booking.delivery_receipt_number',
                'commerce_booking.transaction_type',
                'commerce_booking.admin_verified',
                'commerce_booking.delivery_verified',
                'commerce_booking.received',
                'commerce_booking.received_by',
                'commerce_booking.received_at',
                'commerce_booking.paid_status',
                'commerce_booking.cancel_status',
                'commerce_booking.created_at',
                'commerce_shopping_cart.total_weight',
                'commerce_voucher.voucher_code',
                'minimi_user_data.fullname', 
                'minimi_user_data.email', 
                'minimi_user_data.phone', 
                'minimi_user_address.address_title',
                'minimi_user_address.address_pic',
                'minimi_user_address.address_phone',
                'minimi_user_address.address_detail',
                'minimi_user_address.address_postal_code',
                'minimi_user_address.address_subdistrict_name',
                'minimi_user_address.address_city_name',
                'minimi_user_address.address_province_name',
                'minimi_user_address.address_country_code',
                'minimi_user_address.address_lat',
                'minimi_user_address.address_long',
                'minimi_user_address.sicepat_destination_code'
            )
            ->join('commerce_group_buy','commerce_group_buy.cg_id','=','commerce_booking.cg_id')
            ->join('minimi_user_data','minimi_user_data.user_id','=','commerce_booking.user_id')
            ->join('commerce_shopping_cart','commerce_shopping_cart.cart_id','=','commerce_booking.cart_id')
            ->leftJoin('minimi_user_address','minimi_user_address.address_id','=','commerce_booking.address_id')
            ->leftJoin('commerce_voucher','commerce_shopping_cart.voucher_id','=','commerce_voucher.voucher_id')
            ->where([
                'commerce_booking.user_id'=>$user_id,
                'commerce_booking.hide'=>0,
                'commerce_booking.cancel_status'=>0,
                'commerce_booking.admin_verified'=>0,
                'commerce_booking.transaction_type'=>3,
            ])
            ->where('commerce_booking.paid_status', '!=', 2)
            ->whereIn('commerce_group_buy.status',[1,2,3])
            ->where('commerce_group_buy.expire_at','>=',$date)
            ->whereNotNull('order_id')
            ->orderBy('commerce_booking.updated_at','DESC')
        ->skip($offset)->take($limit)->get();

        if(count($query)==0){
            return 'order_not_found';
        }

        $collect = collect($query);
        $cart_ids = $collect->pluck('cart_id')->all();

        $item = DB::table('commerce_shopping_cart_item')
            ->select('cart_id','commerce_shopping_cart_item.count', 'data_brand.brand_name', 'minimi_product.product_uri', 'minimi_product_variant.variant_name', 'commerce_shopping_cart_item.price_amount as price','minimi_product.product_id','minimi_product.product_uri', 'minimi_product.product_name', 'minimi_product_gallery.prod_gallery_picture as product_image')
            ->join('minimi_product','minimi_product.product_id','=','commerce_shopping_cart_item.product_id')
            ->join('data_brand','minimi_product.brand_id','=','data_brand.brand_id')
            ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_shopping_cart_item.product_id')
            ->join('minimi_product_variant','minimi_product_variant.variant_id','=','commerce_shopping_cart_item.variant_id')
            ->whereIn('cart_id',$cart_ids)
            ->where([
                'commerce_shopping_cart_item.status'=>1,
                'minimi_product_gallery.main_poster'=>1,
                'minimi_product_gallery.status'=>1
            ])
        ->get();

        $col_item = collect($item);
        
        foreach($query as $key=>$row){
            $find = $col_item->where('cart_id',$row->cart_id)->all();
            $items = array();
            $i=0;
            foreach($find as $fin){
                $items[$i]['count'] = $fin->count;
                $items[$i]['product_uri'] = $fin->product_uri;
                $items[$i]['variant_name'] = $fin->variant_name;
                $items[$i]['price'] = $fin->price;
                $items[$i]['product_name'] = $fin->product_name;
                $items[$i]['product_image'] = $fin->product_image;
                if($row->transaction_type==2){
                    $items[$i]['tnc'] = $fin->voucher_tnc;
                }else{
                    $items[$i]['brand_name'] = $fin->brand_name;
                }
                $i++;
            }

            switch ($row->paid_status) {
                case 0:
                    $row->status = 'waiting_for_payment';
                break;
                case 1:
                    $row->status = 'paid';
                    if($row->transaction_type==1 || $row->transaction_type==3){
                        if($row->admin_verified==1){
                            $row->status = 'verified_by_admin';
                        }
                        if($row->delivery_verified==1){
                            $row->status = 'package_picked_up';
                        }
                        if($row->received==1){
                            $row->status = 'package_received';
                        }
                    }elseif($row->transaction_type==2){
                        if($row->admin_verified==1){
                            $row->status = 'package_received';
                        }
                    }
                break;
                case 2:
                    $row->status = 'transaction_cancelled';
                break;
                case 4:
                    $row->status = 'waiting_for_payment';
                break;    
                default:
                    $row->status = 'draft';
                break;
            }

            if(empty($items)){
                unset($query[$key]);
            }
            $row->shopping_cart_item = $items;
            $name = app('App\Http\Controllers\API\AuthController')->splitName($row->fullname);
            $row->first_name = $name['first_name'];
            $row->last_name = $name['last_name'];
            $row->group_buy = app('App\Http\Controllers\API\GroupBuyController')->getGroupBuy($row->cg_id);
        }

        $return = json_decode(json_encode($query), true);
        $return = array_values($return);
        return $return;
    }

    public function getOrderListBulk_exe($booking_ids,$mode){
        $query = DB::table('commerce_booking')
            ->select(
                'commerce_booking.cart_id', 
                'commerce_booking.order_id', 
                'commerce_booking.price_amount', 
                'commerce_booking.delivery_amount', 
                'commerce_booking.discount_amount', 
                'commerce_booking.delivery_discount_amount', 
                'commerce_booking.insurance_amount', 
                'commerce_booking.total_amount', 
                'commerce_booking.payment_method', 
                'commerce_booking.delivery_service', 
                'commerce_booking.delivery_receipt_number',
                'commerce_booking.transaction_type',
                'commerce_booking.admin_verified',
                'commerce_booking.delivery_verified',
                'commerce_booking.received',
                'commerce_booking.received_by',
                'commerce_booking.received_at',
                'commerce_booking.paid_status',
                'commerce_booking.cancel_status',
                'commerce_booking.created_at',
                'commerce_shopping_cart.total_weight',
                'commerce_voucher.voucher_code',
                'minimi_user_data.fullname', 
                'minimi_user_data.email', 
                'minimi_user_data.phone', 
                'minimi_user_address.address_title',
                'minimi_user_address.address_pic',
                'minimi_user_address.address_phone',
                'minimi_user_address.address_detail',
                'minimi_user_address.address_postal_code',
                'minimi_user_address.address_subdistrict_name',
                'minimi_user_address.address_city_name',
                'minimi_user_address.address_province_name',
                'minimi_user_address.address_country_code',
                'minimi_user_address.address_lat',
                'minimi_user_address.address_long',
                'minimi_user_address.sicepat_destination_code'
            )
            ->join('minimi_user_data','minimi_user_data.user_id','=','commerce_booking.user_id')
            ->join('commerce_shopping_cart','commerce_shopping_cart.cart_id','=','commerce_booking.cart_id')
            ->leftJoin('minimi_user_address','minimi_user_address.address_id','=','commerce_booking.address_id')
            ->leftJoin('commerce_voucher','commerce_shopping_cart.voucher_id','=','commerce_voucher.voucher_id')
            ->whereIn('commerce_booking.booking_id', $booking_ids);
        if($mode==1){
            $query = $query->whereIn('commerce_booking.transaction_type',[1,4]);
        }else{
            $query = $query->where('commerce_booking.transaction_type',$mode);
        }

        $query = $query->whereNotNull('order_id')->orderBy('commerce_booking.created_at','DESC')
        ->get();

        if(empty($query)){
            return 'order_not_found';
        }

        $collect = collect($query);
        $cart_ids = $collect->pluck('cart_id')->all();

        if($mode==1 || $mode==3){
            $item = DB::table('commerce_shopping_cart_item')
                ->select('cart_id','commerce_shopping_cart_item.count', 'minimi_product_variant.variant_name', 'commerce_shopping_cart_item.price_amount as price','minimi_product.product_id','minimi_product.product_uri', 'minimi_product.product_name', 'minimi_product_gallery.prod_gallery_picture as product_image')
                ->join('minimi_product','minimi_product.product_id','=','commerce_shopping_cart_item.product_id')
                ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_shopping_cart_item.product_id')
                ->join('minimi_product_variant','minimi_product_variant.variant_id','=','commerce_shopping_cart_item.variant_id')
                ->whereIn('cart_id',$cart_ids)
                ->where([
                    'commerce_shopping_cart_item.status'=>1,
                    'minimi_product_gallery.main_poster'=>1,
                    'minimi_product_gallery.status'=>1
                ])
            ->get();
        }elseif($mode==2){
            $item = DB::table('commerce_shopping_cart_item')
                ->select('cart_id','minimi_product.product_id','minimi_product.product_uri','minimi_product.product_name', 'minimi_product_digital.voucher_count as count', 'minimi_product_digital.voucher_desc as variant_name','commerce_shopping_cart_item.price_amount as price', 'minimi_product_digital.voucher_tnc', 'minimi_product_gallery.prod_gallery_picture as product_image')
                ->join('minimi_product','minimi_product.product_id','=','commerce_shopping_cart_item.product_id')
                ->join('minimi_product_digital','minimi_product_digital.product_id','=','minimi_product.product_id')
                ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'commerce_shopping_cart_item.product_id')
                ->whereIn('cart_id',$cart_ids)
                ->where([
                    'commerce_shopping_cart_item.status'=>1,
                    'minimi_product_gallery.main_poster'=>1,
                    'minimi_product_gallery.status'=>1
                ])
            ->get();
        }

        $col_item = collect($item);
        
        foreach($query as $key=>$row){
            $find = $col_item->where('cart_id',$row->cart_id)->all();
            $items = array();
            $i=0;
            foreach($find as $fin){
                $items[$i]['count'] = $fin->count;
                $items[$i]['variant_name'] = $fin->variant_name;
                $items[$i]['price'] = $fin->price;
                $items[$i]['product_name'] = $fin->product_name;
                $items[$i]['product_image'] = $fin->product_image;
                if($mode==2){
                    $items[$i]['tnc'] = $fin->voucher_tnc;
                }
                $i++;
            }

            switch ($row->paid_status) {
                case 0:
                    $row->status = 'waiting_for_payment';
                break;
                case 1:
                    $row->status = 'paid';
                    if($row->transaction_type==1 || $row->transaction_type==3){
                        if($row->admin_verified==1){
                            $row->status = 'verified_by_admin';
                        }
                        if($row->delivery_verified==1){
                            $row->status = 'package_picked_up';
                        }
                        if($row->received==1){
                            $row->status = 'package_received';
                        }
                    }elseif($row->transaction_type==2){
                        if($row->admin_verified==1){
                            $row->status = 'package_received';
                        }
                    }
                break;
                case 2:
                    $row->status = 'transaction_cancelled';
                break;
                case 4:
                    $row->status = 'waiting_for_payment';
                break;    
                default:
                    $row->status = 'draft';
                break;
            }

            if(empty($items)){
                unset($query[$key]);
            }
            $row->shopping_cart_item = $items;
            $name = app('App\Http\Controllers\API\AuthController')->splitName($row->fullname);
            $row->first_name = $name['first_name'];
            $row->last_name = $name['last_name'];
        }

        $return = json_decode(json_encode($query), true);
        $return = array_values($return);
        return $return;
    }

    public function orderReceived_exe($order_id, $user_id){
        $query = DB::table('commerce_booking')
            ->select(
                'commerce_booking.admin_verified',
                'commerce_booking.affiliate_code',
                'commerce_booking.price_amount',
                'commerce_booking.total_amount',
                'commerce_booking.delivery_verified',
                'commerce_booking.received'
            )
            ->where([
                'order_id'=>$order_id,
                'commerce_booking.user_id'=>$user_id
            ])
        ->first();

        if(empty($query)){
            return 'order_not_found';
        }

        if($query->received==0){
            if($query->admin_verified==1 && $query->delivery_verified==1){
                if($query->affiliate_code!=null){
                    app('App\Http\Controllers\Utility\AffiliateController')->affiliateRedeem($query->affiliate_code, $user_id, $query->price_amount, $order_id);
                }
                
                DB::table('commerce_booking')->where('order_id',$order_id)->update([
                    'received'=>1,
                    'received_by'=>'user',
                    'received_at'=>date('Y-m-d H:i:s')
                ]);

                return 'success';
            }else{
                return 'order_invalid';
            }
        }else{
            return 'package_already_received';
        }
    }

    public function pointTransaction_exe($order_id){
        $order = DB::table('commerce_booking')
            ->where([
                'order_id'=>$order_id,
                'delivery_verified'=>1,
                'received'=>1
            ])
        ->first();
        
		$point = app('App\Http\Controllers\Utility\UtilityController')->countTransactionPoint($order->total_amount);

        $point_amount = floatval($point);

        $message = 'Point dari transaksi dengan order id: '.$order_id;

        app('App\Http\Controllers\Utility\UtilityController')->addPoint($order->user_id, $point_amount, $message, null, $order->booking_id);

        DB::table('commerce_booking')->where('order_id',$order_id)->update([
            'point_given'=>$point_amount,
            'point_given_at'=>date('Y-m-d H:i:s')
        ]);
    }

    public function historyTrack_exe($array){
        $return = array();
        $daten = array();
        $daten['date_time'] = date('Y-m-d H:i:s', strtotime($array->created_at));
        $daten['status'] = 'WAITING_FOR_PAYMENT';
        $daten['desc'] = 'Menunggu pembayaran selesai';
        array_push($return, $daten);

        if($array->paid_status==2 && $array->cancel_status==1){
            $daten['date_time'] = date('Y-m-d H:i:s', strtotime($array->updated_at));
            $daten['status'] = 'PAYMENT_CANCELED';
            $daten['desc'] = 'Transaksi dibatalkan';
            array_push($return, $daten);
            return $return;
        }elseif ($array->paid_status==3||$array->paid_status==0||$array->paid_status==4) {
            return $return;
        }

        $daten['date_time'] = date('Y-m-d H:i:s', strtotime($array->updated_at));
        $daten['status'] = 'PAYMENT_COMPLETE';
        $daten['desc'] = 'Pembayaran berhasil';
        array_push($return, $daten);

        if($array->admin_verified==1){
            $daten['date_time'] = date('Y-m-d H:i:s', strtotime($array->verified_at));
            $daten['status'] = 'ADMIN_VERIFIED';
            $daten['desc'] = 'Pembayaran Anda telah diverifikasi';
            array_push($return, $daten);
        }

        if(strtolower($array->delivery_vendor)=='minimi'){
            if($array->delivery_verified==1){
                $daten['date_time'] = date('Y-m-d H:i:s', strtotime($array->delivery_verified_at));
                $daten['status'] = 'DELIVERY_VERIFIED';
                $daten['desc'] = 'Paket Anda dalam perjalanan';
                array_push($return, $daten);
            }

            if($array->received==1){
                $daten['date_time'] = date('Y-m-d H:i:s', strtotime($array->received_at));
                $daten['status'] = 'PACKAGE_RECEIVED';
                $daten['desc'] = 'Paket telah diterima';
                array_push($return, $daten);
            }else{
                $date = date('Y-m-d H:i:s');
                $date_cut_off = date('Y-m-d H:i:s',strtotime($array->delivery_verified_at.' + 3 Days'));
                if($date>=$date_cut_off){
                    $order = $this->orderReceived_exe($array->order_id, $array->user_id);
                    if($order=='success'){
                        $this->pointTransaction_exe($array->order_id);
                        app('App\Http\Controllers\Utility\MailController')->sendTransactionCompleteEmailPhys($array->order_id);
                        $daten['date_time'] = $date;
                        $daten['status'] = 'PACKAGE_RECEIVED';
                        $daten['desc'] = 'Paket telah diterima';
                        array_push($return, $daten);
                    }
                }
            }
        }elseif(strtolower($array->delivery_vendor)=='sicepat'){
            $track = app('App\Http\Controllers\Utility\SicepatController')->trackingSicepat_exe($array->delivery_receipt_number);
            $return = array_merge($return, $track['tracker']);
            if($array->received==0){
                if($track['last_status']['status']=='DELIVERED'){
                    $order = $this->orderReceived_exe($array->order_id, $array->user_id);
                    if($order=='success'){
                        $this->pointTransaction_exe($array->order_id);
                        app('App\Http\Controllers\Utility\MailController')->sendTransactionCompleteEmailPhys($array->order_id);
                    }
                }
            }
        }

        return $return;
    }
}