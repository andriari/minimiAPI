<?php

namespace App\Http\Controllers\Dash;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;

use DB;

class DeliveryController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }

    public function sicepatPickupRequest(Request $request){
        $data = $request->all();
        try{
            $query = DB::table('commerce_booking')
                ->select(
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
                    'commerce_booking.sicepat_destination_code',
                    'commerce_booking.cart_id', 
                    'commerce_booking.price_amount', 
                    'commerce_booking.delivery_amount', 
                    'commerce_booking.discount_amount', 
                    'commerce_booking.insurance_amount', 
                    'commerce_booking.total_amount', 
                    'commerce_booking.payment_method', 
                    'commerce_booking.delivery_service', 
                    'commerce_booking.delivery_receipt_number',
                    'commerce_shopping_cart.total_weight'
                )
                ->join('minimi_user_data','minimi_user_data.user_id','=','commerce_booking.user_id')
                ->join('minimi_user_address','minimi_user_address.address_id','=','commerce_booking.address_id')
                ->join('commerce_shopping_cart','commerce_shopping_cart.cart_id','=','commerce_booking.cart_id')
                ->whereIn('transaction_type', [1,3])
                ->where([
                    'order_id'=>$data['order_id'],
                    'commerce_booking.user_id'=>$data['user_id'],
                    'admin_verified'=>1,
                    'delivery_verified'=>0,
                    'paid_status'=>1,
                    'cancel_status'=>0
                ])
            ->first();

            if(empty($query)){
                return response()->json(['code'=>4803,'message'=>'invalid_order']);
            }

            $notes = (empty($data['notes']))?'':$data['notes'];
            $return['auth_key'] = env('SICEPAT_API_PICKUP_KEY');
            $return['reference_number'] = $data['order_id'];
            $return['pickup_request_date'] = date('Y-m-d H:i');
            $return['pickup_merchant_code'] = env('SICEPAT_PICKUP_MERCHANT_CODE');
            $return['pickup_merchant_name'] = env('SICEPAT_PICKUP_MERCHANT_NAME');
            $return['pickup_address'] = env('SICEPAT_PICKUP_MERCHANT_ADDRESS');
            $return['pickup_city'] = env('SICEPAT_PICKUP_CITY');
            $return['pickup_merchant_phone'] = env('SICEPAT_PICKUP_MERCHANT_PHONE');
            $return['pickup_method'] = "PICKUP";
            $return['pickup_merchant_email'] = env('SICEPAT_PICKUP_MERCHANT_EMAIL');
            $return['notes'] = $notes;
            $packageList[0]['receipt_number'] = $data['receipt_number'];
            $packageList[0]['origin_code'] = env('SICEPAT_ORIGIN_CODE');
            $packageList[0]['delivery_type'] = $query->delivery_service;
            $packageList[0]['parcel_category'] = $data['parcel_category'];
            $content = $this->itemCheck($query->cart_id);
            $packageList[0]['parcel_content'] = $content['content'];
            $packageList[0]['parcel_qty'] = $content['qty'];
            $packageList[0]['parcel_uom'] = "Pcs";
            $packageList[0]['parcel_value'] = $query->price_amount;
            $packageList[0]['cod_value'] = 0;
            if($query->insurance_amount>0){
                $delivery_insurance_sicepat = DB::table('data_param')->where('param_tag','delivery_insurance_sicepat')->value('param_value');
                $insurance = floatval(($delivery_insurance_sicepat/100)*$query->price_amount);
            }else{
                $insurance = 0;
            }
            $packageList[0]['insurance_value'] = $insurance;
            $packageList[0]['parcel_length'] = 0;
            $packageList[0]['parcel_width'] = 0;
            $packageList[0]['parcel_height'] = 0;
            $packageList[0]['total_weight'] = $query->total_weight;
            $packageList[0]['shipper_code'] = env('SICEPAT_PICKUP_MERCHANT_CODE');
            $packageList[0]['shipper_name'] = env('SICEPAT_PICKUP_MERCHANT_NAME');
            $packageList[0]['shipper_address'] = env('SICEPAT_PICKUP_MERCHANT_ADDRESS');
            $packageList[0]['shipper_city'] = env('SICEPAT_PICKUP_CITY');
            $packageList[0]['shipper_phone'] = env('SICEPAT_PICKUP_MERCHANT_PHONE');
            $packageList[0]['shipper_province'] = env('SICEPAT_PICKUP_PROVINCE');
            $packageList[0]['shipper_district'] = env('SICEPAT_PICKUP_DISTRICT');
            $packageList[0]['shipper_zip'] = env('SICEPAT_PICKUP_ZIP_CODE');
            $packageList[0]['shipper_longitude'] = null;
            $packageList[0]['shipper_latitude'] = null;
            $packageList[0]['recipient_title'] = $query->address_title;
            $packageList[0]['recipient_name'] = $query->address_pic;
            $packageList[0]['recipient_address'] = $query->address_detail;
            $packageList[0]['recipient_province'] = $query->address_province_name;
            $packageList[0]['recipient_city'] = $query->address_city_name;
            $packageList[0]['recipient_district'] = $query->address_subdistrict_name;
            $packageList[0]['recipient_zip'] = $query->address_postal_code;
            $packageList[0]['recipient_phone'] = $query->address_phone;
            $packageList[0]['recipient_longitude'] = $query->address_long;
            $packageList[0]['recipient_latitude'] = $query->address_lat;
            $packageList[0]['destination_code'] = $query->sicepat_destination_code;
            $packageList[0]['notes'] = $notes;
            $return['PackageList'] = $packageList;
            
            $result = app('App\Http\Controllers\Utility\UtilityController')->curlSicepatPickupRequest($return);
            if($result->status==200){
                if(empty($result->datas)){
                    return response()->json(['code'=>4804,'message'=>'sicepat_request_error']);
                }
                $res['cust_package_id'] = $result->datas[0]->cust_package_id;
                $res['receipt_number'] = $result->datas[0]->receipt_number;
                $res['request_number'] = $result->request_number;
                $res['receipt_datetime'] = $result->receipt_datetime;

                $update['updated_at'] = date('Y-m-d H:i:s', strtotime($res['receipt_datetime']));
                $update['delivery_receipt_number'] = $res['receipt_number'];
                DB::table('commerce_booking')->where('order_id',$data['order_id'])->update($update);
                return response()->json(['code'=>200,'message'=>'success','data'=>$res]);
            }else{
                return response()->json(['code'=>4805,'message'=>$result->error_message]);
            }
        }catch(QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'sicepat_pickup_request_failed']);
		}
    }

    public function sicepatPickupRequestBulk(Request $request){
        $data = $request->all();
        try{
            $collect = collect($data['request']);
            $order_ids = $collect->pluck('order_id')->all();

            $query = DB::table('commerce_booking')
                ->select(
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
                    'commerce_booking.sicepat_destination_code',
                    'commerce_booking.order_id', 
                    'commerce_booking.cart_id', 
                    'commerce_booking.price_amount', 
                    'commerce_booking.delivery_amount', 
                    'commerce_booking.discount_amount', 
                    'commerce_booking.insurance_amount', 
                    'commerce_booking.total_amount', 
                    'commerce_booking.payment_method', 
                    'commerce_booking.delivery_service', 
                    'commerce_booking.delivery_receipt_number',
                    'commerce_shopping_cart.total_weight'
                )
                ->join('minimi_user_data','minimi_user_data.user_id','=','commerce_booking.user_id')
                ->join('minimi_user_address','minimi_user_address.address_id','=','commerce_booking.address_id')
                ->join('commerce_shopping_cart','commerce_shopping_cart.cart_id','=','commerce_booking.cart_id')
                ->whereIn('transaction_type', [1,3])
                ->whereIn('order_id',$order_ids)
                ->where([
                    'admin_verified'=>1,
                    'delivery_verified'=>0,
                    'paid_status'=>1,
                    'cancel_status'=>0
                ])
            ->get();

            if(count($query)==0){
                return response()->json(['code'=>4803,'message'=>'invalid_order']);
            }

            $col_query = collect($query);

            $array = array();
            foreach($data['request'] as $row){
                $return = array();
                $packageList = array();
                $filter = $col_query->where('order_id',$row['order_id'])->first();
                
                $notes = (empty($row['notes']))?'':$row['notes'];
                $return['auth_key'] = env('SICEPAT_API_PICKUP_KEY');
                $return['reference_number'] = $row['order_id'];
                $return['pickup_request_date'] = date('Y-m-d H:i');
                $return['pickup_merchant_code'] = env('SICEPAT_PICKUP_MERCHANT_CODE');
                $return['pickup_merchant_name'] = env('SICEPAT_PICKUP_MERCHANT_NAME');
                $return['pickup_address'] = env('SICEPAT_PICKUP_MERCHANT_ADDRESS');
                $return['pickup_city'] = env('SICEPAT_PICKUP_CITY');
                $return['pickup_merchant_phone'] = env('SICEPAT_PICKUP_MERCHANT_PHONE');
                $return['pickup_method'] = "PICKUP";
                $return['pickup_merchant_email'] = env('SICEPAT_PICKUP_MERCHANT_EMAIL');
                $return['notes'] = $notes;
                $packageList[0]['receipt_number'] = $row['receipt_number'];
                $packageList[0]['origin_code'] = env('SICEPAT_ORIGIN_CODE');
                $packageList[0]['delivery_type'] = $filter->delivery_service;
                $packageList[0]['parcel_category'] = $row['parcel_category'];
                $content = $this->itemCheck($filter->cart_id);
                $packageList[0]['parcel_content'] = $content['content'];
                $packageList[0]['parcel_qty'] = $content['qty'];
                $packageList[0]['parcel_uom'] = "Pcs";
                $packageList[0]['parcel_value'] = $filter->price_amount;
                $packageList[0]['cod_value'] = 0;
                if($filter->insurance_amount>0){
                    $delivery_insurance_sicepat = DB::table('data_param')->where('param_tag','delivery_insurance_sicepat')->value('param_value');
                    $insurance = floatval(($delivery_insurance_sicepat/100)*$filter->price_amount);
                }else{
                    $insurance = 0;
                }
                $packageList[0]['insurance_value'] = $insurance;
                $packageList[0]['parcel_length'] = 0;
                $packageList[0]['parcel_width'] = 0;
                $packageList[0]['parcel_height'] = 0;
                $packageList[0]['total_weight'] = $filter->total_weight;
                $packageList[0]['shipper_code'] = env('SICEPAT_PICKUP_MERCHANT_CODE');
                $packageList[0]['shipper_name'] = env('SICEPAT_PICKUP_MERCHANT_NAME');
                $packageList[0]['shipper_address'] = env('SICEPAT_PICKUP_MERCHANT_ADDRESS');
                $packageList[0]['shipper_city'] = env('SICEPAT_PICKUP_CITY');
                $packageList[0]['shipper_phone'] = env('SICEPAT_PICKUP_MERCHANT_PHONE');
                $packageList[0]['shipper_province'] = env('SICEPAT_PICKUP_PROVINCE');
                $packageList[0]['shipper_district'] = env('SICEPAT_PICKUP_DISTRICT');
                $packageList[0]['shipper_zip'] = env('SICEPAT_PICKUP_ZIP_CODE');
                $packageList[0]['shipper_longitude'] = null;
                $packageList[0]['shipper_latitude'] = null;
                $packageList[0]['recipient_title'] = $filter->address_title;
                $packageList[0]['recipient_name'] = $filter->address_pic;
                $packageList[0]['recipient_address'] = $filter->address_detail;
                $packageList[0]['recipient_province'] = $filter->address_province_name;
                $packageList[0]['recipient_city'] = $filter->address_city_name;
                $packageList[0]['recipient_district'] = $filter->address_subdistrict_name;
                $packageList[0]['recipient_zip'] = $filter->address_postal_code;
                $packageList[0]['recipient_phone'] = $filter->address_phone;
                $packageList[0]['recipient_longitude'] = $filter->address_long;
                $packageList[0]['recipient_latitude'] = $filter->address_lat;
                $packageList[0]['destination_code'] = $filter->sicepat_destination_code;
                $packageList[0]['notes'] = $notes;
                $return['PackageList'] = $packageList;
                $result = app('App\Http\Controllers\Utility\UtilityController')->curlSicepatPickupRequest($return);
                
                if($result->status==200){
                    if(!empty($result->datas)){
                        $res['order_id'] = $row['order_id'];
                        $res['cust_package_id'] = $result->datas[0]->cust_package_id;
                        $res['receipt_number'] = $result->datas[0]->receipt_number;
                        $res['request_number'] = $result->request_number;
                        $res['receipt_datetime'] = $result->receipt_datetime;
        
                        $update['updated_at'] = date('Y-m-d H:i:s', strtotime($res['receipt_datetime']));
                        $update['delivery_receipt_number'] = $res['receipt_number'];
                        DB::table('commerce_booking')->where('order_id',$row['order_id'])->update($update);

                        array_push($array,$res);
                    }
                }
            }
            
            if(empty($array)){
                return response()->json(['code'=>4806,'message'=>'no_request_was_made']);
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$array]);
        }catch(QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'sicepat_pickup_request_failed']);
		}
    }

    /**
     * Utility Function
    **/

    public function receiptNumber($order_id){
        $num = rand(100000000000,999999999999);
        return $num;
    }

    public function itemCheck($cart_id){
        $query = DB::table('commerce_shopping_cart_item')
            ->select('commerce_shopping_cart_item.count','minimi_product_variant.variant_name')
            ->join('minimi_product_variant','minimi_product_variant.variant_id','=','commerce_shopping_cart_item.variant_id')
            ->where([
                'cart_id'=>$cart_id,
                'commerce_shopping_cart_item.status'=>1
            ])
        ->get();

        $count = 0;
        $arr = array();
        foreach ($query as $row) {
            $count +=$row->count;
            $arr[] = $row->variant_name.' ('.$row->count.')';
        }

        $return['qty'] = $count;
        $return['content'] = implode(', ',$arr);

        return $return;
    }
}