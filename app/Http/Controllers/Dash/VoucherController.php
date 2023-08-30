<?php

namespace App\Http\Controllers\Dash;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

use DB;

class VoucherController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
	}

    public function saveVoucher(Request $request){
        $data = $request->all();
        try {
            if($request->hasFile('image')){
                $destinationPath = 'public/voucher';
                $image_path = app('App\Http\Controllers\Utility\UtilityController')->upload_image($data['image'],$destinationPath);
                switch ($image_path) {
                    case 'too_big':
                        return response()->json(['code'=>1001,'message'=>'image_too_big','data'=>$return]);
                        break;
                    case 'not_an_image':
                        return response()->json(['code'=>1002,'message'=>'invalid_image_type','data'=>$return]);
                        break;
                    default:
                        $insert['voucher_image'] = $image_path;
                        break;
                }

                $date = date('Y-m-d H:i:s');

                if(empty($data['voucher_code'])){
                    $voucher_code = app('App\Http\Controllers\Utility\VoucherController')->personalVoucherCodeGenerator();
                }else{
                    $voucher_code = $data['voucher_code'];
                }

                $insert['voucher_code'] = $voucher_code;
                $insert['voucher_name'] = $data['voucher_name'];
                $insert['voucher_desc'] = $data['voucher_desc'];
                $insert['voucher_tnc'] = $data['voucher_tnc'];
                $insert['shown_in_list'] = $data['shown_in_list'];
                $insert['official_voucher'] = 1;
                $insert['publish'] = $data['publish'];
                $insert['limit_usage'] = $data['limit_usage'];
                $insert['usage_period'] = $data['usage_period'];
                $insert['voucher_minimum'] = $data['voucher_minimum'];
                $insert['colour_palette'] = $data['colour_palette'];

                //transaction type
                $transactionType = empty($data['transaction_type'])?0:$data['transaction_type'];
                switch ($transactionType) {
                    case 0:
                        $insert['transaction_type'] = $transactionType;
                        break;
                    case 1:
                        $insert['transaction_type'] = $transactionType;
                        break;
                    case 2:
                        $insert['transaction_type'] = $transactionType;
                        break;
                    case 3:
                        $insert['transaction_type'] = $transactionType;
                        break;
                    default:
                        return response()->json(['code'=>1204,'message'=>'transaction_type_unknown']);
                        break;
                }
                
                //promo type
                $insert['promo_type'] = $data['promo_type'];
                switch ($data['promo_type']) {
                    case 1:
                        $insert['user_id'] = $data['user_id'];
                        break;
                    case 2:
                        $insert['user_id'] = null;
                        break;
                    default:
                        return response()->json(['code'=>1200,'message'=>'promo_type_unknown']);
                        break;
                }

                //voucher type
                $insert['voucher_type'] = $data['voucher_type'];
                switch ($data['voucher_type']) {
                    case 1:
                        $insert['product_id'] = null;
                        break;
                    case 2:
                        $insert['product_id'] = $data['product_id'];
                        break;
                    case 3:
                        $insert['product_id'] = null;
                        break;
                    default:
                        return response()->json(['code'=>1201,'message'=>'voucher_type_unknown']);
                        break;
                }

                //discount type
                $insert['discount_type'] = $data['discount_type'];
                switch ($data['discount_type']) {
                    case 1:
                        $insert['voucher_value'] = trim($data['voucher_value']);
                        break;
                    case 2:
                        if($data['voucher_value']>100){
                            return response()->json(['code'=>1203,'message'=>'voucher_value_bigger_than_100']);
                        }
                        $insert['voucher_value'] = trim($data['voucher_value']);
                        break;
                    case 3:
                        $voucher_value = trim($data['voucher_value']);
                        $exp = explode(';',$voucher_value);
                        if($exp[1]>100){
                            return response()->json(['code'=>1203,'message'=>'voucher_value_bigger_than_100']);
                        }
                        $insert['voucher_value'] = $voucher_value;
                        break;
                    default:
                        return response()->json(['code'=>1202,'message'=>'discount_type_unknown']);
                        break;
                }

                if($data['combined_voucher']==1){
                    $arr[0] = $data['discount_type_2'];
                    $arr[1] = $data['voucher_type_2'];
                    switch ($data['discount_type_2']) {
                        case 1:
                            $arr[2] = trim($data['voucher_value_2']);
                            break;
                        case 2:
                            if($data['voucher_value_2']>100){
                                return response()->json(['code'=>1206,'message'=>'voucher_value_2_bigger_than_100']);
                            }
                            $arr[2] = trim($data['voucher_value_2']);
                            break;
                        case 3:
                            $voucher_value_2 = trim($data['voucher_value_2']);
                            $exp_1 = explode(';',$voucher_value_2);
                            if($exp_1[1]>100){
                                return response()->json(['code'=>1206,'message'=>'voucher_value_2_bigger_than_100']);
                            }
                            $arr[2] = $voucher_value_2;
                            break;
                        default:
                            return response()->json(['code'=>1207,'message'=>'discount_type_2_unknown']);
                            break;
                    }

                    if($data['voucher_type']==$data['voucher_type_2']){
                        return response()->json(['code'=>1205,'message'=>'voucher_type_duplicate']);
                    }

                    if($data['voucher_type_2']==2){
                        $insert['product_id'] = $data['product_id'];
                    }else{
                        $insert['product_id'] = null;
                    }

                    $insert['voucher_value_combined'] = implode('|', $arr);
                    $insert['combined_voucher'] = $data['combined_voucher'];
                }

                $insert['voucher_validity_end'] = date('Y-m-d', strtotime($data['voucher_validity_end']));
                $insert['created_at'] = $date;
                $insert['updated_at'] = $date;

                $voucher_id = DB::table('commerce_voucher')->insertGetId($insert);

                $return['voucher_id'] = $voucher_id;
                return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
            }
            
            return response()->json(['code'=>1003,'message'=>'no_image_found']);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'save_voucher_failed']);
		}
    }

    public function editVoucher(Request $request){
        $data = $request->all();
        try {
            if($request->hasFile('image')){
                $destinationPath = 'public/voucher';
                $image_path = app('App\Http\Controllers\Utility\UtilityController')->upload_image($data['image'],$destinationPath);
                switch ($image_path) {
                    case 'too_big':
                        return response()->json(['code'=>1001,'message'=>'image_too_big','data'=>$return]);
                        break;
                    case 'not_an_image':
                        return response()->json(['code'=>1002,'message'=>'invalid_image_type','data'=>$return]);
                        break;
                    default:
                        $update['voucher_image'] = $image_path;
                        break;
                }
            }

            $date = date('Y-m-d H:i:s');
            
            $update['voucher_code'] = $data['voucher_code'];
            $update['voucher_name'] = $data['voucher_name'];
            $update['voucher_desc'] = $data['voucher_desc'];
            $update['voucher_tnc'] = $data['voucher_tnc'];
            $update['shown_in_list'] = $data['shown_in_list'];
            $update['publish'] = $data['publish'];
            $update['limit_usage'] = $data['limit_usage'];
            $update['usage_period'] = $data['usage_period'];
            $update['voucher_minimum'] = $data['voucher_minimum'];
            $update['colour_palette'] = $data['colour_palette'];

            //transaction type
            if(!empty($data['transaction_type'])){
                switch ($data['transaction_type']) {
                    case 0:
                        $update['transaction_type'] = $data['transaction_type'];
                        break;
                    case 1:
                        $update['transaction_type'] = $data['transaction_type'];
                        break;
                    case 2:
                        $update['transaction_type'] = $data['transaction_type'];
                        break;
                    case 3:
                        $update['transaction_type'] = $data['transaction_type'];
                        break;
                    default:
                        return response()->json(['code'=>1204,'message'=>'transaction_type_unknown']);
                        break;
                }
            }

            //promo type
            $update['promo_type'] = $data['promo_type'];
            switch ($data['promo_type']) {
                case 1:
                    $update['user_id'] = $data['user_id'];
                    break;
                case 2:
                    $update['user_id'] = null;
                    break;
                default:
                    return response()->json(['code'=>1200,'message'=>'promo_type_unknown']);
                    break;
            }

            //voucher type
            $update['voucher_type'] = $data['voucher_type'];
            switch ($data['voucher_type']) {
                case 1:
                    $update['product_id'] = null;
                    break;
                case 2:
                    $update['product_id'] = $data['product_id'];
                    break;
                case 3:
                    $update['product_id'] = null;
                    break;
                default:
                    return response()->json(['code'=>1201,'message'=>'voucher_type_unknown']);
                    break;
            }

            //discount type
            $update['discount_type'] = $data['discount_type'];
            switch ($data['discount_type']) {
                case 1:
                    $update['voucher_value'] = $data['voucher_value'];
                    break;
                case 2:
                    if($data['voucher_value']>100){
                        return response()->json(['code'=>1203,'message'=>'voucher_value_bigger_than_100']);
                    }
                    $update['voucher_value'] = $data['voucher_value'];
                    break;
                case 3:
                    $voucher_value = trim($data['voucher_value']);
                    $exp = explode(';',$voucher_value);
                    if($exp[1]>100){
                        return response()->json(['code'=>1203,'message'=>'voucher_value_bigger_than_100']);
                    }
                    $update['voucher_value'] = $voucher_value;
                    break;
                default:
                    return response()->json(['code'=>1202,'message'=>'discount_type_unknown']);
                    break;
            }

            if($data['combined_voucher']==1){
                $arr[0] = $data['discount_type_2'];
                $arr[1] = $data['voucher_type_2'];
                switch ($data['discount_type_2']) {
                    case 1:
                        $arr[2] = trim($data['voucher_value_2']);
                        break;
                    case 2:
                        if($data['voucher_value_2']>100){
                            return response()->json(['code'=>1206,'message'=>'voucher_value_2_bigger_than_100']);
                        }
                        $arr[2] = trim($data['voucher_value_2']);
                        break;
                    case 3:
                        $voucher_value_2 = trim($data['voucher_value_2']);
                        $exp_1 = explode(';',$voucher_value_2);
                        if($exp_1[1]>100){
                            return response()->json(['code'=>1206,'message'=>'voucher_value_2_bigger_than_100']);
                        }
                        $arr[2] = $voucher_value_2;
                        break;
                    default:
                        return response()->json(['code'=>1207,'message'=>'discount_type_2_unknown']);
                        break;
                }

                if($data['voucher_type']==$data['voucher_type_2']){
                    return response()->json(['code'=>1205,'message'=>'voucher_type_duplicate']);
                }

                if($data['voucher_type_2']==2){
                    $insert['product_id'] = $data['product_id'];
                }else{
                    $insert['product_id'] = null;
                }

                $update['voucher_value_combined'] = implode('|', $arr);
                $update['combined_voucher'] = $data['combined_voucher'];
            }

            $update['voucher_validity_end'] = date('Y-m-d', strtotime($data['voucher_validity_end']));
            $update['updated_at'] = $date;

            DB::table('commerce_voucher')->where('voucher_id',$data['voucher_id'])->update($update);

            $return['voucher_id'] = $data['voucher_id'];
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'edit_voucher_failed']);
		}
    }

    public function publishVoucher(Request $request, $voucher_id, $mode){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            
            if($mode!=0 && $mode!=1){
                return response()->json(['code'=>1202,'message'=>'unknown_status']);
            }

            DB::table('commerce_voucher')->where('voucher_id',$voucher_id)->update([
                'publish'=>$mode,
                'updated_at'=>$date
            ]);

            $return['voucher_id'] = $voucher_id;
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'publish_mode_voucher_failed']);
		}
    }
    
    public function deleteVoucher(Request $request, $voucher_id){
        $data = $request->all();
        try {
            $date = date('Y-m-d H:i:s');
            
            DB::table('commerce_voucher')->where('voucher_id',$voucher_id)->update([
                'status'=>0,
                'publish'=>0,
                'shown_in_list'=>0,
                'updated_at'=>$date
            ]);

            $return['voucher_id'] = $voucher_id;
            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'delete_voucher_failed']);
		}
    }

    public function detailVoucher(Request $request, $voucher_id){
        $data = $request->all();
        try {
            $return = DB::table('commerce_voucher')
                ->where([
                    'voucher_id' => $voucher_id,
                    'status' => 1
                ])
            ->first();

            if(!empty($return)){
                if($return->combined_voucher == 1){
                    $exp = explode('|',$return->voucher_value_combined);
                    $return->discount_type_2 = $exp[0];
                    $return->voucher_type_2 = $exp[1];
                    $return->voucher_value_2 = $exp[2];
                }else{
                    $return->discount_type_2 = null;
                    $return->voucher_type_2 = null;
                    $return->voucher_value_2 = null;
                }
                $query = DB::table('commerce_booking')
                    ->select(
                        'commerce_booking.order_id',
                        'commerce_booking.created_at as updated_at',
                        'minimi_user_data.fullname'
                    )
                    ->join('minimi_user_data','minimi_user_data.user_id','=','commerce_booking.user_id')
                    ->join('commerce_shopping_cart','commerce_shopping_cart.cart_id','=','commerce_booking.cart_id')
                    ->where([
                        'commerce_shopping_cart.voucher_id'=>$voucher_id,
                        'commerce_booking.hide'=>0,
                        'commerce_booking.paid_status'=>1,
                        'commerce_booking.cancel_status'=>0
                    ])
                    ->whereNotNull('order_id')
                    ->orderBy('commerce_booking.created_at','DESC')
                ->get();
                $count = count($query);
                $return->count = $count;
                if($count>0){
                    $return->order = $query;
                }else{
                    $return->order = array();    
                }
                return response()->json(['code'=>200, 'message'=>'success', 'data'=>$return]);
            }else{
                return response()->json(['code'=>400, 'message'=>'not_found']);
            }
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'loading_voucher_failed']);
		}
    }
}