<?php

namespace App\Http\Controllers\Utility;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

use DB;

class VoucherController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }

    /*
     * Personal Voucher Generator
    */

    public function generateVoucher_exe($user_id, $product_id){
        $product = DB::table('minimi_product_digital')->where('product_id',$product_id)->first();
        $image = DB::table('minimi_product_gallery')->where(['product_id'=>$product_id,'status'=>1])->orderBy('main_poster','ASC')->orderBy('prod_gallery_id')->first();

        if(empty($product)){
            return "empty";
        }

        $date_issued = date('Y-m-d');
        $insert = array();
        for ($i=0; $i<$product->voucher_count; $i++) { 
            $date = date('Y-m-d H:i:s');
            $insert[$i]['user_id'] = $user_id;
            $insert[$i]['product_id'] = $product_id;
            $insert[$i]['voucher_image'] = $image->prod_gallery_picture;
            $insert[$i]['colour_palette'] = $product->colour_palette;
            $insert[$i]['voucher_code'] = $this->personalVoucherCodeGenerator();
            $insert[$i]['voucher_value'] = $product->voucher_value;
            $insert[$i]['voucher_minimum'] = $product->voucher_minimum;
            $insert[$i]['discount_type'] = $product->discount_type;
            $insert[$i]['voucher_type'] = $product->voucher_type;
            $insert[$i]['voucher_name'] = $product->voucher_name;
            $insert[$i]['voucher_desc'] = $product->voucher_desc;
            $insert[$i]['voucher_tnc'] = $product->voucher_tnc;
            $insert[$i]['voucher_validity_end'] = date('Y-m-d',strtotime($date_issued.' + '.$product->voucher_duration));
            $insert[$i]['shown_in_list'] = 1;
            $insert[$i]['publish'] = 1;
            $insert[$i]['created_at'] = $date;
            $insert[$i]['updated_at'] = $date;
        }

        DB::table('commerce_voucher')->insert($insert);

        return "success";
    }

    function personalVoucherCodeGenerator(){
		$string = strtoupper(Str::random(8));
		$check = $this->checkPersonalCode($string);
		if($check=="TRUE"){
			return $string;
		}else{
			return $this->personalVoucherCodeGenerator();
		}
    }
    
    function checkPersonalCode($code){
        $return = DB::table('commerce_voucher')
            ->where([
                'voucher_code'=>$code,
                'status'=>1
            ])
        ->first();
		return (empty($return))?"TRUE":$return;
    }
    
    ////////////////////////////////////////////////////////////////////////////////////////////

    /*
     * Voucher Usage
    */

    public function listVoucherReguler($user_id,$limit, $offset){
        $offset_count=$offset*$limit;
        $next_offset_count=($offset+1)*$limit;

        $date = date('Y-m-d');
        $future_date = date('Y-m-d',strtotime($date.' +2 days'));
        $query = DB::table('commerce_voucher')
            ->where([
                'promo_type'=>2,
                'status'=>1,
                'publish'=>1,
                'shown_in_list'=>1
            ])
            ->where('voucher_validity_end','>=',$future_date)
        ->skip($offset_count)->take($limit)->get();

        if(count($query)>0){
            foreach ($query as $row) {
                $status = 'available';
                
                if($row->usage >= $row->limit_usage){
                    $status = 'unavailable';
                }

                if($row->voucher_validity_end < $date){
                    $status = 'unavailable';
                }
                
                if($row->limit_usage>1){
                    $real_date = date('Y-m-d H:i:s');
                    $check = DB::table('commerce_voucher_usage')->where(['voucher_id'=>$row->voucher_id,'user_id'=>$user_id])->orderBy('updated_at','DESC')->first();
                    if(!empty($check)){
                        if($row->usage_period=='ONCE'){
                            $status = 'unavailable';
                        }else{
                            $expire = date('Y-m-d H:i:s',strtotime($check->updated_at.' + '.$row->usage_period));
                            if($real_date<$expire){
                                $status = 'unavailable';
                            }
                        }
                    }
                }
                $row->voucher_validity_end = date('d-m-Y',strtotime($row->voucher_validity_end));
                $row->status = $status;
            }
        }

        $next_offset = 'empty';
		if(count($query)==$limit){
            $query2 = DB::table('commerce_voucher')
                ->select('voucher_id')
                ->where([
                    'promo_type'=>2,
                    'status'=>1,
                    'publish'=>1,
                    'shown_in_list'=>1
                ])
                ->where('voucher_validity_end','>=',$future_date)
			->skip($next_offset_count)->take($limit)->get();

			if(count($query2)>0){
				$next_offset = $next_offset_count/$limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
    }

    public function listVoucherUser($user_id, $limit, $offset){
        $offset_count=$offset*$limit;
        $next_offset_count=($offset+1)*$limit;
        $date = date('Y-m-d');
        $future_date = date('Y-m-d',strtotime($date.' -2 days'));
        $query = DB::table('commerce_voucher')
            ->where([
                'user_id'=>$user_id,
                'promo_type'=>1,
                'publish'=>1,
                'status'=>1
            ])
            ->where('voucher_validity_end','>=',$future_date)
        ->skip($offset_count)->take($limit)->get();

        if(count($query)>0){
            foreach ($query as $row) {
                $status = 'available';

                if($row->usage >= $row->limit_usage){
                    $status = 'unavailable';
                }

                if($row->voucher_validity_end < $date){
                    $status = 'unavailable';
                }
                
                if($row->limit_usage>1){
                    $real_date = date('Y-m-d H:i:s');
                    $check = DB::table('commerce_voucher_usage')->where(['voucher_id'=>$row->voucher_id,'user_id'=>$user_id])->orderBy('updated_at','DESC')->first();
                    if(!empty($check)){
                        if($row->usage_period=='ONCE'){
                            $status = 'unavailable';
                        }else{
                            $expire = date('Y-m-d H:i:s',strtotime($check->updated_at.' + '.$row->usage_period));
                            if($real_date<$expire){
                                $status = 'unavailable';
                            }
                        }
                    }
                }
                $row->voucher_validity_end = date('d-m-Y',strtotime($row->voucher_validity_end));
                $row->status = $status;
            }
        }

        $next_offset = 'empty';
		if(count($query)==$limit){
            $query2 = DB::table('commerce_voucher')
                ->select('voucher_id')
                ->where([
                    'user_id'=>$user_id,
                    'promo_type'=>1,
                    'publish'=>1,
                    'status'=>1
                ])
                ->where('voucher_validity_end','>=',$future_date)
			->skip($next_offset_count)->take($limit)->get();

			if(count($query2)>0){
				$next_offset = $next_offset_count/$limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
    }

    public function searchVoucherUser($user_id, $search_query, $limit, $offset){
        $offset_count=$offset*$limit;
        $next_offset_count=($offset+1)*$limit;

        $query = DB::table('commerce_voucher')
            ->where([
                'voucher_code'=>$search_query,
                'status'=>1,
                'publish'=>1
            ])
        ->skip($offset_count)->take($limit)->get();

        $return = array();
        if(count($query)>0){
            $i = 0;
            foreach ($query as $row) {
                $return[$i]['voucher_id'] = $row->voucher_id;
                $return[$i]['voucher_image'] = $row->voucher_image;
                $return[$i]['colour_palette'] = $row->colour_palette;
                $return[$i]['voucher_code'] = $row->voucher_code;
                $return[$i]['voucher_name'] = $row->voucher_name;
                $return[$i]['voucher_desc'] = $row->voucher_desc;
                $return[$i]['voucher_tnc'] = $row->voucher_tnc;
                $return[$i]['voucher_validity_end'] = date('d-m-Y',strtotime($row->voucher_validity_end));
                $return[$i]['official_voucher'] = $row->official_voucher;

                $status = 'available';
                
                if($row->usage >= $row->limit_usage){
                    $status = 'unavailable';
                }

                if($row->limit_usage>1){
                    $real_date = date('Y-m-d H:i:s');
                    $check = DB::table('commerce_voucher_usage')->where(['voucher_id'=>$row->voucher_id,'user_id'=>$user_id])->orderBy('updated_at','DESC')->first();
                    if(!empty($check)){
                        if($row->usage_period=='ONCE'){
                            $status = 'unavailable';
                        }else{
                            $expire = date('Y-m-d H:i:s',strtotime($check->updated_at.' + '.$row->usage_period));
                            if($real_date<$expire){
                                $status = 'unavailable';
                            }
                        }
                    }
                }

                $return[$i]['status'] = $status;
                if($row->promo_type==1&&$row->user_id==$user_id){
                    $i++;
                }elseif($row->promo_type!=1){
                    $i++;
                }
            }
        }

        $next_offset = 'empty';
		if(count($query)==$limit){
            $query2 = DB::table('commerce_voucher')
                ->select('voucher_id')
                ->where([
                    'user_id'=>$user_id,
                    'voucher_code'=>$search_query,
                    'status'=>1,
                    'publish'=>1
                ])
			->skip($next_offset_count)->take($limit)->get();

			if(count($query2)>0){
				$next_offset = $next_offset_count/$limit;
			}
		}

		$result['data'] = $return;
		$result['offset'] = $next_offset;
		return $result;
    }

    public function calculatePrice($voucher_code, $price, $date, $user_id=null, $cart_id=null, $delivery_price=null, $transactionType){
        $query = DB::table('commerce_voucher')
            ->where([
                'voucher_code'=>$voucher_code,
                'status'=>1,
                'publish'=>1
            ])
        ->first();

        $return['rabat'] = 0;
        $return['delivery_discount'] = 0;
        $return['new_price'] = $price;
        $return['old_price'] = $price;
        $return['voucher_id'] = null;
        $return['current_usage'] = 0;
        $return['voucher_status'] = 0;
        $return['voucher_verdict'] = '';
        
        if(empty($query)){
            $return['voucher_status'] = 1;
            $return['voucher_verdict'] = 'voucher_not_found';
            return $return;
        }

        $return['voucher_type'] = $query->voucher_type;
        $return['promo_type'] = $query->promo_type;

        if($query->transaction_type!=0){ 
            if($query->transaction_type!=$transactionType){
                $return['voucher_status'] = 3;
                $return['voucher_verdict'] = 'voucher_invalid';
                return $return;
            }
        }
        
        if($query->usage >= $query->limit_usage){
            $return['voucher_status'] = 2;
            $return['voucher_verdict'] = 'voucher_usage_has_met';
            return $return;
        }

        if($query->promo_type==1){
            if($query->user_id != $user_id){
                $return['voucher_status'] = 3;
                $return['voucher_verdict'] = 'voucher_invalid';
                return $return;
            }
        }

        if($query->voucher_validity_end<$date){
            $return['voucher_status'] = 4;
            $return['voucher_verdict'] = 'voucher_expired';
            return $return;
        }

        if($query->usage_period!=NULL){
            $real_date = date('Y-m-d H:i:s');
            $check = DB::table('commerce_voucher_usage')->where(['voucher_id'=>$query->voucher_id,'user_id'=>$user_id,'status'=>1])->orderBy('updated_at','DESC')->first();
            if(!empty($check)){
                if($query->usage_period=='ONCE'){
                    $return['voucher_status'] = 5;
                    $return['voucher_verdict'] = 'voucher_used';
                    return $return;
                }else{
                    $expire = date('Y-m-d H:i:s',strtotime($check->updated_at.' + '.$query->usage_period));
                    if($real_date<$expire){
                        $return['voucher_status'] = 6;
                        $return['voucher_verdict'] = 'voucher_in_cooldown_period';
                        return $return;
                    }
                }
            }
        }

        if($price==0){
            $return['voucher_status'] = 7;
            $return['voucher_verdict'] = 'price_must_be_bigger_than_zero';
            return $return;
        }

        if($query->voucher_minimum>$price){
            $return['voucher_status'] = 8;
            $return['voucher_verdict'] = 'minimum_value_did_not_meet';
            return $return;
        }

        $delivery_discount = 0;
        if($query->voucher_type==1){
            switch ($query->discount_type){
                case 1: //fixed value
                    $rabat = floatval($query->voucher_value);
                    break;
                case 2: //percentage
                    $value = floatval($query->voucher_value);
                    $rabat = floatval($price*($query->voucher_value/100));
                    break;
                case 3: //hybrid
                    $voucher_value = explode(';',$query->voucher_value);
                    $threshold = floatval($voucher_value[0]);
                    $pct = floatval($voucher_value[1]);
                    $rabat = floatval($price*($pct/100));
                    if($rabat>$threshold){
                        $rabat = $threshold;
                    }
                    break;
                default:
                    $return['voucher_status'] = 9;
                    $return['voucher_verdict'] = 'invalid_value';
                    return $return;
                    break;
            }
    
            $new_price = $price-$rabat;
        }elseif($query->voucher_type==2){
            if($cart_id!=null){
                $items = DB::table('commerce_shopping_cart_item')->where(['cart_id'=>$cart_id,'product_id'=>$query->product_id,'status'=>1])->get();
                if(!empty($items)){
                    $item_price = 0;
                    foreach ($items as $key=>$row){
                        $item_price += floatval($row->total_amount);
                    }
                    switch ($query->discount_type){
                        case 1: //fixed value
                            $rabat = floatval($query->voucher_value);
                            break;
                        case 2: //percentage
                            $value = floatval($query->voucher_value);
                            $rabat = floatval($item_price*($query->voucher_value/100));
                            break;
                        case 3: //hybrid
                            $voucher_value = explode(';',$query->voucher_value);
                            $threshold = floatval($voucher_value[0]);
                            $pct = floatval($voucher_value[1]);
                            $rabat = floatval($item_price*($pct/100));
                            if($rabat>$threshold){
                                $rabat = $threshold;
                            }
                            break;
                        default:
                            $return['voucher_status'] = 9;
                            $return['voucher_verdict'] = 'invalid_value';
                            return $return;
                            break;
                    }
                }else{
                    $return['voucher_status'] = 10;
                    $return['voucher_verdict'] = 'promo_item_does_not_exist_in_cart';
                    return $return;
                }
            }else{
                $return['voucher_status'] = 10;
                $return['voucher_verdict'] = 'promo_item_does_not_exist_in_cart';
                return $return;
            }

            $new_price = $price-$rabat;
        }elseif($query->voucher_type==3){
            $new_price = 0;
            $rabat = 0;
            if($delivery_price==null){
                $return['voucher_status'] = 11;
                $return['voucher_verdict'] = 'delivery_price_undefined';
                return $return;
            }
            switch ($query->discount_type){
                case 1: //fixed value
                    if($delivery_price<=$query->voucher_value){
                        $delivery_discount = floatval($delivery_price);
                    }else{
                        $delivery_discount = floatval($query->voucher_value);
                    }
                    break;
                case 2: //percentage
                    $delivery_discount = floatval($delivery_price*($query->voucher_value/100));
                    break;
                case 3: //hybrid
                    $voucher_value = explode(';',$query->voucher_value);
                    $threshold = floatval($voucher_value[0]);
                    $pct = floatval($voucher_value[1]);
                    $delivery_discount = floatval($delivery_price*($pct/100));
                    if($delivery_discount>$threshold){
                        $delivery_discount = $threshold;
                    }
                    break;
                default:
                    $return['voucher_status'] = 9;
                    $return['voucher_verdict'] = 'invalid_value';
                    return $return;
                    break;
            }
        }

        if($query->combined_voucher==1){ //combined voucher
            $exp = explode('|',$query->voucher_value_combined);

            if($exp[1]==1){
                switch ($exp[0]){
                    case 1: //fixed value
                        $rabat2 = floatval($exp[2]);
                        break;
                    case 2: //percentage
                        $value = floatval($exp[2]);
                        $rabat2 = floatval($price*($exp[2]/100));
                        break;
                    case 3: //hybrid
                        $voucher_value = explode(';',$exp[2]);
                        $threshold = floatval($voucher_value[0]);
                        $pct = floatval($voucher_value[1]);
                        $rabat2 = floatval($price*($pct/100));
                        if($rabat2>$threshold){
                            $rabat2 = $threshold;
                        }
                        break;
                    default:
                        $return['voucher_status'] = 9;
                        $return['voucher_verdict'] = 'invalid_value';
                        return $return;
                        break;
                }

                $new_price = $new_price-$rabat2;
            }elseif($exp[1]==2){
                if($cart_id!=null){
                    $items = DB::table('commerce_shopping_cart_item')->where(['cart_id'=>$cart_id,'product_id'=>$query->product_id,'status'=>1])->get();
                    if(!empty($items)){
                        $item_price = 0;
                        foreach ($items as $key=>$row){
                            $item_price += floatval($row->total_amount);
                        }
                        switch ($exp[0]){
                            case 1: //fixed value
                                $rabat2 = floatval($exp[2]);
                                break;
                            case 2: //percentage
                                $value = floatval($exp[2]);
                                $rabat2 = floatval($item_price*($exp[2]/100));
                                break;
                            case 3: //hybrid
                                $voucher_value = explode(';',$exp[2]);
                                $threshold = floatval($voucher_value[0]);
                                $pct = floatval($voucher_value[1]);
                                $rabat2 = floatval($item_price*($pct/100));
                                if($rabat2>$threshold){
                                    $rabat2 = $threshold;
                                }
                                break;
                            default:
                                $return['voucher_status'] = 9;
                                $return['voucher_verdict'] = 'invalid_value';
                                return $return;
                                break;
                        }
                    }else{
                        $return['voucher_status'] = 10;
                        $return['voucher_verdict'] = 'promo_item_does_not_exist_in_cart';
                        return $return;
                    }
                }else{
                    $return['voucher_status'] = 10;
                    $return['voucher_verdict'] = 'promo_item_does_not_exist_in_cart';
                    return $return;
                }
    
                $new_price = $new_price-$rabat2;
            }elseif($exp[1]==3){
                $rabat2 = 0;
                if($delivery_price==null){
                    $return['voucher_status'] = 11;
                    $return['voucher_verdict'] = 'delivery_price_undefined';
                    return $return;
                }
                switch ($exp[0]){
                    case 1: //fixed value
                        if($delivery_price<=$exp[2]){
                            $delivery_discount = floatval($delivery_price);
                        }else{
                            $delivery_discount = floatval($exp[2]);
                        }
                        break;
                    case 2: //percentage
                        $delivery_discount = floatval($delivery_price*($exp[2]/100));
                        break;
                    case 3: //hybrid
                        $voucher_value = explode(';',$exp[2]);
                        $threshold = floatval($voucher_value[0]);
                        $pct = floatval($voucher_value[1]);
                        $delivery_discount = floatval($delivery_price*($pct/100));
                        if($delivery_discount>$threshold){
                            $delivery_discount = $threshold;
                        }
                        break;
                    default:
                        $return['voucher_status'] = 9;
                        $return['voucher_verdict'] = 'invalid_value';
                        return $return;
                        break;
                }
            }

            $rabat = $rabat + $rabat2;
        }


        $return['rabat'] = $rabat;
        $return['delivery_discount'] = $delivery_discount;
        $return['new_price'] = $new_price;
        $return['old_price'] = $price;
        $return['voucher_id'] = $query->voucher_id;
        $return['current_usage'] = $query->usage;
        $return['voucher_verdict'] = 'success';
        $return['voucher_status'] = 12;
        return $return;
    }

    public function countVoucherUsage($voucher_id,$user_id=""){
        $promo_type = DB::table('commerce_voucher')->where('voucher_id',$voucher_id)->value('promo_type');

        $where['voucher_id'] = $voucher_id;
        $where['status'] = 1;
        
        if($promo_type==1){
            $where['user_id'] = $user_id;  
        }
        
        $check = DB::table('commerce_voucher_usage')->where($where)->get();

        return count($check);
    }
}