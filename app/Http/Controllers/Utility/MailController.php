<?php

namespace App\Http\Controllers\Utility;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

use DB;

class MailController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }

    public function sendWaitingPaymentEmailPhys($order_id, $order_date=""){
        $query = DB::table('commerce_booking')
            ->select('cart_id','user_id','created_at','price_amount', 'delivery_amount', 'discount_amount', 'delivery_discount_amount', 'insurance_amount', 'total_amount', 'payment_method', 'delivery_vendor', 'delivery_service', 'delivery_receipt_number')
            ->where([
                'order_id'=>$order_id
            ])
        ->first();
            
        if($order_date==""){
            $order_date = $query->created_at;
        }

        $date = date('Y-m-d H:i:s', strtotime($order_date));

        $user = DB::table('minimi_user_data')->where('user_id', $query->user_id)->first();

        $items = DB::table('commerce_shopping_cart_item')
            ->select('variant_name','product_name','brand_name','count','price_amount','total_amount','prod_gallery_picture as pict','prod_gallery_alt as alt')
            ->join('minimi_product_variant','commerce_shopping_cart_item.variant_id','=','minimi_product_variant.variant_id')
            ->join('minimi_product','minimi_product_variant.product_id','=','minimi_product.product_id')
            ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
            ->leftJoin('minimi_product_gallery','minimi_product_gallery.product_id','=','minimi_product.product_id')
            ->where(['cart_id'=>$query->cart_id,'main_poster'=>1])
        ->get();
        
        foreach ($items as $row){
            $arr = explode($row->product_name.' ',$row->variant_name);
            if(count($arr)>1){
                $row->variant_name = $arr[1];
            }
            $row->price_amount = 'Rp. '.number_format($row->price_amount,0,',','.');
            $row->total_amount = 'Rp. '.number_format($row->total_amount,0,',','.');
        }

        // Send email to customer
		$data['data']['name'] = $user->fullname;
        $data['data']['order_date'] = app('App\Http\Controllers\Utility\UtilityController')->dateInBahasa($date).' WIB';
        $exp_date = date('Y-m-d H:i:s',strtotime('+24 hours '.$date));
		$data['data']['expire_date'] = app('App\Http\Controllers\Utility\UtilityController')->dateInBahasa($exp_date).' WIB';
        $data['data']['order'] = $order_id;
        $data['data']['items'] = $items;
        $data['data']['price_amount'] = 'Rp. '.number_format($query->price_amount,0,',','.');
        $data['data']['delivery_amount'] = 'Rp. '.number_format($query->delivery_amount,0,',','.');
        $data['data']['discount_amount'] = 'Rp. '.number_format($query->discount_amount,0,',','.');
        $data['data']['delivery_discount_amount'] = 'Rp. '.number_format($query->delivery_discount_amount,0,',','.');
        $data['data']['insurance_amount'] = 'Rp. '.number_format($query->insurance_amount,0,',','.');
        $data['data']['total_amount'] = 'Rp. '.number_format($query->total_amount,0,',','.');
        $data['data']['payment_method'] = $query->payment_method;
		$data['data']['link'] = env('FRONTEND_URL').'history';
		$data['data']['logo'] = "https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/email_icon/Group+431.png";
		$data['data']['call_support'] = "https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/email_icon/support+1.png";
		$data['data']['whatsapp_support'] = "https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/email_icon/whatsapp+(2)+1.png";
		$data['data']['email_support'] = "https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/email_icon/envelope+1.png";
		$data['receiver_email'] = $user->email;
		$data['template'] = "emails.notif.physical_waiting_for_payment";
        $data['subject'] = "Menunggu Pembayaran - ".$order_id;
        
        //return view($data['template'], $data['data']);
        app('App\Http\Controllers\Utility\UtilityController')->sendMail($data);
    }

    public function sendPaymentVerifiedEmailPhys($order_id){
        $query = DB::table('commerce_booking')
            ->select('cart_id','user_id','created_at','price_amount', 'delivery_amount', 'discount_amount', 'delivery_discount_amount', 'insurance_amount', 'total_amount', 'payment_method', 'delivery_vendor', 'delivery_service', 'delivery_receipt_number')
            ->where([
                'order_id'=>$order_id
            ])
        ->first();

        $user = DB::table('minimi_user_data')->where('user_id', $query->user_id)->first();

        $items = DB::table('commerce_shopping_cart_item')
            ->select('variant_name','product_name','brand_name','count','price_amount','total_amount','prod_gallery_picture as pict','prod_gallery_alt as alt')
            ->join('minimi_product_variant','commerce_shopping_cart_item.variant_id','=','minimi_product_variant.variant_id')
            ->join('minimi_product','minimi_product_variant.product_id','=','minimi_product.product_id')
            ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
            ->leftJoin('minimi_product_gallery','minimi_product_gallery.product_id','=','minimi_product.product_id')
            ->where(['cart_id'=>$query->cart_id,'main_poster'=>1])
        ->get();
        
        foreach ($items as $row){
            $arr = explode($row->product_name.' ',$row->variant_name);
            if(count($arr)>1){
                $row->variant_name = $arr[1];
            }
            $row->price_amount = 'Rp. '.number_format($row->price_amount,0,',','.');
            $row->total_amount = 'Rp. '.number_format($row->total_amount,0,',','.');
        }

        $payment = $this->composePaymentMethod($order_id);

        $date = date('Y-m-d H:i:s', strtotime($payment->settlement_time));

        // Send email to customer
		$data['data']['name'] = $user->fullname;
		$data['data']['settlement_date'] = app('App\Http\Controllers\Utility\UtilityController')->dateInBahasa($date).' WIB';
        $data['data']['order'] = $order_id;
        $data['data']['items'] = $items;
        $data['data']['price_amount'] = 'Rp. '.number_format($query->price_amount,0,',','.');
        $data['data']['delivery_amount'] = 'Rp. '.number_format($query->delivery_amount,0,',','.');
        $data['data']['discount_amount'] = 'Rp. '.number_format($query->discount_amount,0,',','.');
        $data['data']['delivery_discount_amount'] = 'Rp. '.number_format($query->delivery_discount_amount,0,',','.');
        $data['data']['insurance_amount'] = 'Rp. '.number_format($query->insurance_amount,0,',','.');
        $data['data']['total_amount'] = 'Rp. '.number_format($query->total_amount,0,',','.');
        $data['data']['payment_method'] = $payment->payment_method;
		$data['data']['link'] = env('FRONTEND_URL').'history';
		$data['data']['logo'] = "https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/email_icon/Group+431.png";
		$data['data']['call_support'] = "https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/email_icon/support+1.png";
		$data['data']['whatsapp_support'] = "https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/email_icon/whatsapp+(2)+1.png";
		$data['data']['email_support'] = "https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/email_icon/envelope+1.png";
		$data['receiver_email'] = $user->email;
		$data['template'] = "emails.notif.physical_payment_verified";
        $data['subject'] = "Pembayaran Terverifikasi - ".$order_id;
        
        //return view($data['template'], $data['data']);
        app('App\Http\Controllers\Utility\UtilityController')->sendMail($data);
    }

    public function sendTransactionCompleteEmailPhys($order_id){
        $query = DB::table('commerce_booking')
            ->select('cart_id','address_id','user_id','created_at','received_at','price_amount', 'delivery_amount', 'discount_amount', 'delivery_discount_amount', 'insurance_amount', 'total_amount', 'payment_method', 'delivery_vendor', 'delivery_service', 'delivery_receipt_number')
            ->where([
                'order_id'=>$order_id
            ])
        ->first();

        $user = DB::table('minimi_user_data')->where('user_id', $query->user_id)->first();

        $items = DB::table('commerce_shopping_cart_item')
            ->select('variant_name','product_name','brand_name','count','price_amount','total_amount','prod_gallery_picture as pict','prod_gallery_alt as alt')
            ->join('minimi_product_variant','commerce_shopping_cart_item.variant_id','=','minimi_product_variant.variant_id')
            ->join('minimi_product','minimi_product_variant.product_id','=','minimi_product.product_id')
            ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
            ->leftJoin('minimi_product_gallery','minimi_product_gallery.product_id','=','minimi_product.product_id')
            ->where(['cart_id'=>$query->cart_id,'main_poster'=>1])
        ->get();
        
        foreach ($items as $row){
            $arr = explode($row->product_name.' ',$row->variant_name);
            if(count($arr)>1){
                $row->variant_name = $arr[1];
            }
            $row->price_amount = 'Rp. '.number_format($row->price_amount,0,',','.');
            $row->total_amount = 'Rp. '.number_format($row->total_amount,0,',','.');
        }

        $date = date('Y-m-d H:i:s', strtotime($query->received_at));

        $address = DB::table('minimi_user_address')->where('address_id',$query->address_id)->first();

        // Send email to customer
		$data['data']['name'] = $user->fullname;
		$data['data']['receive_date'] = app('App\Http\Controllers\Utility\UtilityController')->dateInBahasa($date, 2);
        $data['data']['order'] = $order_id;
        $data['data']['items'] = $items;
        $data['data']['price_amount'] = 'Rp. '.number_format($query->price_amount,0,',','.');
        $data['data']['delivery_amount'] = 'Rp. '.number_format($query->delivery_amount,0,',','.');
        $data['data']['discount_amount'] = 'Rp. '.number_format($query->discount_amount,0,',','.');
        $data['data']['delivery_discount_amount'] = 'Rp. '.number_format($query->delivery_discount_amount,0,',','.');
        $data['data']['insurance_amount'] = 'Rp. '.number_format($query->insurance_amount,0,',','.');
        $data['data']['total_amount'] = 'Rp. '.number_format($query->total_amount,0,',','.');
        $data['data']['delivery_vendor'] = $query->delivery_vendor;
        switch ($query->delivery_service) {
            case 'BEST':
                $data['data']['delivery_service'] = ' - Besok Sampai Tujuan';
                break;
            case 'GOKIL':
                $data['data']['delivery_service'] = ' - Cargo Kilat';
                break;
            case 'SIUNT':
                $data['data']['delivery_service'] = ' - SiUntung';
                break;
            default:
                $data['data']['delivery_service'] = '';
                break;
        }
        $data['data']['delivery_receipt_number'] = $query->delivery_receipt_number;
        $data['data']['recipient_name'] = $address->address_pic;
        $data['data']['recipient_phone'] = $address->address_phone;
        $data['data']['recipient_address'] = $address->address_detail;
		$data['data']['link'] = env('FRONTEND_URL').'history';
		$data['data']['logo'] = "https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/email_icon/Group+431.png";
		$data['data']['call_support'] = "https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/email_icon/support+1.png";
		$data['data']['whatsapp_support'] = "https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/email_icon/whatsapp+(2)+1.png";
		$data['data']['email_support'] = "https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/email_icon/envelope+1.png";
		$data['receiver_email'] = $user->email;
		$data['template'] = "emails.notif.physical_transaction_complete";
        $data['subject'] = "Transaksi Selesai - ".$order_id;
        
        //return view($data['template'], $data['data']);
        app('App\Http\Controllers\Utility\UtilityController')->sendMail($data);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * Utility Function
    **/

    public function composePaymentMethod($order_id){
        $payment_data = DB::table('payment_data')->where('order_id',$order_id)->first();
        if($payment_data!=null){
            $order = DB::table('commerce_booking')
                ->select('payment_vendor','payment_method')
                ->where('order_id',$order_id)
            ->first();
            if($payment_data->payment_gateway==5){
                $payment_method = strtoupper($order->payment_method);
            }else {
                switch ($payment_data->payment_type) {
                    case 'echannel':
                        $payment_method = 'Mandiri E-Channel';
                        break;
                    case 'cimb_clicks':
                        $payment_method = 'CIMB Clicks';
                        break;
                    case 'bca_klikpay':
                        $payment_method = 'BCA Klikpay';
                        break;
                    case 'cstore':
                        $payment_method = strtoupper($payment_data->store);
                        break;
                    case 'gopay':
                        $payment_method = 'GoPay';
                        break;
                    case 'credit_card':
                        $payment_method = 'Credit Card';
                        break;
                    case 'akulaku':
                        $payment_method = 'Akulaku';
                        break;
                    case 'bank_transfer':
                        $payment_method = 'Bank Transfer';
                        if($payment_data->bank!=null){
                            $payment_method .= '-'.strtoupper($payment_data->bank);
                        }
                        break;
                    default:
                        $payment_method = 'Unknown Type';
                        break;
                }
            }
    
            $payment_data->payment_method = $payment_method;
            $payment_data->payment_vendor = strtoupper($order->payment_vendor);
        }
        return $payment_data;
    }
}