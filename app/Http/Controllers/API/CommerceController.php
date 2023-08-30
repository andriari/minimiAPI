<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;

use DB;

class CommerceController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }

    /*
     * Wishlist 
    */

    public function getWishlist(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $limit = (empty($data['limit']))?20:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];
            $wish = $this->getWishlist_exe($currentUser->user_id, $limit, $offset);
            $return['product'] = $wish['data'];
            $return['offset'] = $wish['offset'];
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'wishlist_load_failed']);
        }
    }

    public function addWishlist(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $check = $this->checkWishlist($currentUser->user_id, $data['product_id']);
            $date = date('Y-m-d H:i:s');
            $insert['status'] = 1;
            $insert['updated_at'] = $date;
            if($check=='empty'){
                $insert['product_id'] = $data['product_id'];
                $insert['user_id'] = $currentUser->user_id;
                $insert['created_at'] = $date;
                DB::table('minimi_product_wishlist')->insert($insert);
            }else{
                $this->updateWishlist($check->wish_id, $insert);
            }
            return response()->json(['code'=>200,'message'=>'success']);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'add_wishlist_failed']);
        }
    }

    public function removeWishlist(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $check = $this->checkWishlist($currentUser->user_id, $data['product_id']);
            $date = date('Y-m-d H:i:s');
            $insert['status'] = 0;
            $insert['updated_at'] = $date;
            if($check=='empty'){
                return response()->json(['code'=>4040,'message'=>'invalid_wishlist_record']);    
            }
            $this->updateWishlist($check->wish_id, $insert);
            return response()->json(['code'=>200,'message'=>'success']);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'remove_wishlist_failed']);
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////
    
    /*
     * Commerce Basket 
    */
    public function getBasketCount(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $return['count'] = $this->getBasketCount_exe($currentUser->user_id);
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'basket_load_failed']);
        }
    }

    public function getBasket(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $limit = (empty($data['limit']))?5:$data['limit'];
            $wish = $this->getWishlist_exe($currentUser->user_id, $limit, 0);
            $basket = $this->getBasket_exe($currentUser->user_id);
            $return['item'] = $basket['data'];
            $return['calculate'] = $basket['calculate'];
            return response()->json(['code'=>200,'message'=>'success','data'=>$return,'wishlist'=>$wish]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'basket_load_failed']);
        }
    }

    public function addBasket(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $valid = $this->checkProductVariant($data['product_id'],$data['variant_id']);
            if($valid['code']!=200){
                return response()->json(['code'=>$valid['code'],'message'=>$valid['message']]);
            }

            $basket = $this->addToBasket_exe($currentUser->user_id, $data['product_id'], $data['variant_id'], $data['count']);
            if($basket['verdict']=='item_restriction_count_met'){
                $return['message_id'] = $basket['message_id'];
                $return['message_en'] = $basket['message_en'];
                return response()->json(['code'=>4402,'message'=>'item_restriction_count_met','data'=>$return]);
            }

            $check_wishlist = $this->checkWishlist($currentUser->user_id, $data['product_id']);
            $date = date('Y-m-d H:i:s');
            if($check_wishlist!='empty'){
                $insert_wishlist['status'] = 0;
                $insert_wishlist['updated_at'] = $date;
                $this->updateWishlist($check_wishlist->wish_id, $insert_wishlist);
            }
            $limit = (empty($data['limit']))?5:$data['limit'];
            $wish = $this->getWishlist_exe($currentUser->user_id, $limit, 0);

            $return['item'] = $basket['data'];
            $return['calculate'] = $basket['calculate'];
            return response()->json(['code'=>200,'message'=>'success','data'=>$return,'wishlist'=>$wish]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'add_basket_failed']);
        }
    }

    public function editBasket(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $valid = $this->checkProductVariant($data['product_id'],$data['variant_id']);
            if($valid['code']!=200){
                return response()->json(['code'=>$valid['code'],'message'=>$valid['message']]);
            }

            $basket = $this->editBasket_exe($currentUser->user_id, $data['product_id'], $data['variant_id'], $data['count']);
            
            $limit = (empty($data['limit']))?5:$data['limit'];
            $wish = $this->getWishlist_exe($currentUser->user_id, $limit, 0);
            $return['item'] = $basket['data'];
            $return['calculate'] = $basket['calculate'];

            if($basket['verdict']=='invalid_basket_record'){
                return response()->json(['code'=>4040,'message'=>'invalid_basket_record']);
            }elseif($basket['verdict']=='item_restriction_count_met'){
                $return['message_id'] = $basket['message_id'];
                $return['message_en'] = $basket['message_en'];
                return response()->json(['code'=>4402,'message'=>'item_restriction_count_met','data'=>$return,'wishlist'=>$wish]);
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$return,'wishlist'=>$wish]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'edit_basket_failed']);
        }
    }

    public function removeBasket(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $basket = $this->editBasket_exe($currentUser->user_id, $data['product_id'], $data['variant_id'], 0, null, null, 1);
            if($basket['verdict']=='invalid_basket_record'){
                return response()->json(['code'=>4040,'message'=>'invalid_basket_record']);
            }elseif($basket['verdict']=='item_restriction_count_met'){
                $return['message_id'] = $basket['message_id'];
                $return['message_en'] = $basket['message_en'];
                return response()->json(['code'=>4402,'message'=>'item_restriction_count_met','data'=>$return]);
            }

            $limit = (empty($data['limit']))?5:$data['limit'];
            $wish = $this->getWishlist_exe($currentUser->user_id, $limit, 0);
            $return['item'] = $basket['data'];
            $return['calculate'] = $basket['calculate'];
            return response()->json(['code'=>200,'message'=>'success','data'=>$return,'wishlist'=>$wish]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'remove_basket_failed']);
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////
    
    /*
     * Commerce Basket Group Buy
    */
    public function getBasketGB(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $limit = (empty($data['limit']))?5:$data['limit'];
            $basket = $this->getBasket_exe($currentUser->user_id,$data['cg_id']);
            $return['cg_id'] = floatval($data['cg_id']);
            $return['item'] = $basket['data'];
            $return['calculate'] = $basket['calculate'];
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'basket_group_buy_load_failed']);
        }
    }

    public function addBasketGB(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $valid = $this->checkProductVariant($data['product_id'],$data['variant_id']);
            if($valid['code']!=200){
                return response()->json(['code'=>$valid['code'],'message'=>$valid['message']]);
            }

            $basket = $this->addToBasket_exe($currentUser->user_id, $data['product_id'], $data['variant_id'], $data['count'], $data['cg_id']);
            if($basket['verdict']=='item_restriction_count_met'){
                $return['message_id'] = $basket['message_id'];
                $return['message_en'] = $basket['message_en'];
                return response()->json(['code'=>4402,'message'=>'item_restriction_count_met','data'=>$return]);
            }
            $return['cg_id'] = floatval($data['cg_id']);
            $return['item'] = $basket['data'];
            $return['calculate'] = $basket['calculate'];
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'add_basket_group_buy_failed']);
        }
    }

    public function editBasketGB(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $valid = $this->checkProductVariant($data['product_id'],$data['variant_id']);
            if($valid['code']!=200){
                return response()->json(['code'=>$valid['code'],'message'=>$valid['message']]);
            }

            $basket = $this->editBasket_exe($currentUser->user_id, $data['product_id'], $data['variant_id'], $data['count'], $data['cg_id'], $data['basket_id']);
            $return['cg_id'] = floatval($data['cg_id']);
            $return['item'] = $basket['data'];
            $return['calculate'] = $basket['calculate'];

            if($basket['verdict']=='invalid_basket_record'){
                return response()->json(['code'=>4040,'message'=>'invalid_basket_record']);
            }elseif($basket['verdict']=='item_restriction_count_met'){
                $return['message_id'] = $basket['message_id'];
                $return['message_en'] = $basket['message_en'];
                return response()->json(['code'=>4402,'message'=>'item_restriction_count_met','data'=>$return]);
            }
            
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'edit_basket_group_buy_failed']);
        }
    }

    public function removeBasketGB(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $item = DB::table('commerce_basket')->select('product_id','variant_id')->where('basket_id',$data['basket_id'])->first();
            $basket = $this->editBasket_exe($currentUser->user_id, $item->product_id, $item->variant_id, 0, $data['cg_id'], $data['basket_id'], 1);
            if($basket['verdict']=='invalid_basket_record'){
                return response()->json(['code'=>4040,'message'=>'invalid_basket_record']);
            }elseif($basket['verdict']=='item_restriction_count_met'){
                $return['message_id'] = $basket['message_id'];
                $return['message_en'] = $basket['message_en'];
                return response()->json(['code'=>4402,'message'=>'item_restriction_count_met','data'=>$return]);
            }

            $return['cg_id'] = floatval($data['cg_id']);
            $return['item'] = $basket['data'];
            $return['calculate'] = $basket['calculate'];
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'remove_basket_group_buy_failed']);
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////
    
    /*
     * Physical Product Function
    */

    public function addToCartBasket(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $return['cart_id'] = $this->addToCartBasket_exe($data['basket_id'], $currentUser->user_id);

            if($return['cart_id']=='no_item_valid'){
                return response()->json(['code'=>4410,'message'=>'no_item_valid']);
            }
            
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'add_to_basket_cart_failed']);
        }
    }

    public function previewBasketCheckout(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $voucher_code = (!empty($data['voucher_code']))?$data['voucher_code']:null;
            $address_id = (!empty($data['address_id']))?$data['address_id']:null;
            $delivery = (!empty($data['delivery']))?$data['delivery']:null;
            $delivery_tariff = (!empty($data['delivery_tariff']))?floatval($data['delivery_tariff']):null;
            $delivery_vendor = (!empty($data['delivery_vendor']))?$data['delivery_vendor']:null;
            $insurance_sicepat = (!empty($data['insurance_sicepat']))?$data['insurance_sicepat']:0;

            $return = $this->previewBasketCart_exe($data['cart_id'], $currentUser->user_id, $voucher_code, $address_id, $delivery, $delivery_tariff, $delivery_vendor, $insurance_sicepat);

            switch ($return) {
                case 'cart_invalid':
                    return response()->json(['code'=>4411,'message'=>$return]);
                    break;
                case 'voucher_not_found':
                    return response()->json(['code'=>4412,'message'=>$return]);
                    break;
                case 'voucher_expired':
                    return response()->json(['code'=>4413,'message'=>$return]);
                    break;
                case 'price_must_be_bigger_than_zero':
                    return response()->json(['code'=>4414,'message'=>$return]);
                    break;
                case 'minimum_value_did_not_meet':
                    return response()->json(['code'=>4415,'message'=>$return]);
                    break;
                case 'invalid_voucher':
                    return response()->json(['code'=>4416,'message'=>$return]);
                    break;
                default:
                    return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
                break;
            }

        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'preview_basket_checkout_failed']);
        }
    }

    public function bookBasketCheckout(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $payment_vendor = empty($data['payment_vendor'])?'midtrans':$data['payment_vendor'];
            $pg_code = empty($data['pg_code'])?'':$data['pg_code'];
            if($payment_vendor=='faspay' && $pg_code==''){
                return response()->json(['code'=>405,'message'=>'invalid_payment_method']);
            }
            $return = $this->bookBasketCheckout_exe($data['cart_id'], $currentUser->user_id, $data['payment_method'], $payment_vendor, $pg_code);

            switch ($return['verdict']) {
                case 'cart_invalid':
                    return response()->json(['code'=>4411,'message'=>$return]);
                    break;
                case 'voucher_not_found':
                    return response()->json(['code'=>4412,'message'=>$return]);
                    break;
                case 'voucher_expired':
                    return response()->json(['code'=>4413,'message'=>$return]);
                    break;
                case 'price_must_be_bigger_than_zero':
                    return response()->json(['code'=>4414,'message'=>$return]);
                    break;
                case 'minimum_value_did_not_meet':
                    return response()->json(['code'=>4415,'message'=>$return]);
                    break;
                case 'invalid_voucher':
                    return response()->json(['code'=>4416,'message'=>$return]);
                    break;
                case 'invalid_address':
                    return response()->json(['code'=>4417,'message'=>$return]);
                    break;
                case 'invalid_delivery_service':
                    return response()->json(['code'=>4418,'message'=>$return]);
                    break;
                case 'success':
                    return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
                    break;
                default:
                    return response()->json(['code'=>4419,'message'=>$return]);
                break;
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'book_basket_checkout_failed']);
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////
    
    /*
     * Digital Product Function
    */

    public function addToCartDigital(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $return['cart_id'] = $this->addToCartDigital_exe($data['product_id'], $currentUser->user_id);

            if($return['cart_id']=='product_not_found'){
                return response()->json(['code'=>4400,'message'=>'product_not_found']);
            }
            
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'add_to_digital_cart_failed']);
        }
    }

    public function previewDigitalCheckout(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $return = $this->previewDigitalCart_exe($data['cart_id'], $currentUser->user_id);
            
            if($return=='cart_invalid'){
                return response()->json(['code'=>4401,'message'=>'cart_invalid']);
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'preview_digital_checkout_failed']);
        }
    }

    public function bookDigitalCheckout(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $payment_vendor = empty($data['payment_vendor'])?'midtrans':$data['payment_vendor'];
            $pg_code = empty($data['pg_code'])?'':$data['pg_code'];
            if($payment_vendor=='faspay' && $pg_code==''){
                return response()->json(['code'=>405,'message'=>'invalid_payment_method']);
            }
            $return = $this->bookDigitalCheckout_exe($data['cart_id'], $currentUser->user_id, $data['payment_method'], $payment_vendor, $pg_code);
            
            if($return['verdict']=='cart_invalid'){
                return response()->json(['code'=>4401,'message'=>'cart_invalid']);
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'book_digital_checkout_failed']);
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////
    
    /*
     * Group Buy Cart Function
    */

    public function addToCartGroupBuy(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $return['cart_id'] = $this->addToCartGroupBuy_exe($data['cg_id'], $currentUser->user_id);

            if($return['cart_id']=='no_item_valid'){
                return response()->json(['code'=>4410,'message'=>'no_item_valid']);
            }
            
            $return['cg_id'] = floatval($data['cg_id']);
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'add_to_group_buy_cart_failed']);
        }
    }

    public function previewGroupBuyCheckout(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $voucher_code = (!empty($data['voucher_code']))?$data['voucher_code']:null;
            $address_id = (!empty($data['address_id']))?$data['address_id']:null;
            $delivery = (!empty($data['delivery']))?$data['delivery']:null;
            $delivery_tariff = (!empty($data['delivery_tariff']))?floatval($data['delivery_tariff']):null;
            $delivery_vendor = (!empty($data['delivery_vendor']))?$data['delivery_vendor']:null;
            $insurance_sicepat = (!empty($data['insurance_sicepat']))?$data['insurance_sicepat']:0;

            $return = $this->previewBasketCart_exe($data['cart_id'], $currentUser->user_id, $voucher_code, $address_id, $delivery, $delivery_tariff, $delivery_vendor, $insurance_sicepat, $data['cg_id']);

            switch ($return) {
                case 'cart_invalid':
                    return response()->json(['code'=>4411,'message'=>$return]);
                    break;
                case 'voucher_not_found':
                    return response()->json(['code'=>4412,'message'=>$return]);
                    break;
                case 'voucher_expired':
                    return response()->json(['code'=>4413,'message'=>$return]);
                    break;
                case 'price_must_be_bigger_than_zero':
                    return response()->json(['code'=>4414,'message'=>$return]);
                    break;
                case 'minimum_value_did_not_meet':
                    return response()->json(['code'=>4415,'message'=>$return]);
                    break;
                case 'invalid_voucher':
                    return response()->json(['code'=>4416,'message'=>$return]);
                    break;
                default:
                    $return['participant'] = app('App\Http\Controllers\API\GroupBuyController')->getGroupBuyParticipant($data['cg_id'], $currentUser->user_id);
                    $return['cg_id'] = floatval($data['cg_id']);
                    return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
                break;
            }
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'preview_group_buy_checkout_failed']);
        }
    }

    public function bookGroupBuyCheckout(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $payment_vendor = empty($data['payment_vendor'])?'midtrans':$data['payment_vendor'];
            $pg_code = empty($data['pg_code'])?'':$data['pg_code'];
            if($payment_vendor=='faspay' && $pg_code==''){
                return response()->json(['code'=>405,'message'=>'invalid_payment_method']);
            }
            $return = $this->bookBasketCheckout_exe($data['cart_id'], $currentUser->user_id, $data['payment_method'], $payment_vendor, $pg_code, $data['cg_id']);

            switch ($return['verdict']) {
                case 'cart_invalid':
                    return response()->json(['code'=>4411,'message'=>$return]);
                    break;
                case 'voucher_not_found':
                    return response()->json(['code'=>4412,'message'=>$return]);
                    break;
                case 'voucher_expired':
                    return response()->json(['code'=>4413,'message'=>$return]);
                    break;
                case 'price_must_be_bigger_than_zero':
                    return response()->json(['code'=>4414,'message'=>$return]);
                    break;
                case 'minimum_value_did_not_meet':
                    return response()->json(['code'=>4415,'message'=>$return]);
                    break;
                case 'invalid_voucher':
                    return response()->json(['code'=>4416,'message'=>$return]);
                    break;
                case 'invalid_address':
                    return response()->json(['code'=>4417,'message'=>$return]);
                    break;
                case 'invalid_delivery_service':
                    return response()->json(['code'=>4418,'message'=>$return]);
                    break;
                case 'success':
                    $group_user = DB::table('commerce_group_buy')->where('cg_id',$data['cg_id'])->value('user_id');
                    if($group_user==null){
                        $date = date('Y-m-d H:i:s');
                        $product = DB::table('minimi_product')->select('gb_minimal','gb_expire_period')->where('product_id',$verdict->product_id)->first();
                        DB::table('commerce_group_buy')->where('cg_id',$data['cg_id'])->update([
                            'user_id'=>$currentUser->user_id,
                            'minimum_participant'=>$product->gb_minimal,
                            'total_participant'=>1,
                            'expire_at'=>date('Y-m-d H:i:s',strtotime($date.' + '.$product->gb_expire_period)),
                            'created_at'=>$date,
                            'updated_at'=>$date
                        ]);
                    }
                    $return['cg_id'] = floatval($data['cg_id']);
                    return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
                    break;
                default:
                    return response()->json(['code'=>4419,'message'=>$return]);
                break;
            }
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'book_group_buy_checkout_failed']);
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////

    /*
     * Delivery Function
    */

    public function deliverySicepat(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $origin = (!empty($data['origin']))?$data['origin']:'CGK';
            $destination = $data['destination'];
            $weight = $data['weight'];

            $return = app('App\Http\Controllers\Utility\SicepatController')->sicepatTariff_exe($origin, $destination, $weight);

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'delivery_sicepat_failed']);
        }
    }

    public function deliverySicepat2(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $origin = (!empty($data['origin']))?$data['origin']:'CGK';
            $destination = $data['destination'];
            $weight = $data['weight'];
            $price_amount = $data['price_amount'];
            $delivery_discount = (!empty($data['delivery_discount']))?$data['delivery_discount']:0;

            $return = app('App\Http\Controllers\Utility\SicepatController')->sicepatTariff_exe($origin, $destination, $weight, $price_amount, $delivery_discount);

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'delivery_sicepat_failed']);
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////

    /*
     * Utility Function
    */

    protected function getWishlist_exe($user_id, $limit, $offset){
        $offset_count = $offset*$limit;
		$next_offset_count = ($offset+1)*$limit;
        
        $query = DB::table('minimi_product_wishlist')
			->select('minimi_product_wishlist.product_id','product_uri','product_name','product_price','product_price_gb','product_rating','brand_name','category_name','subcat_name','minimi_product_wishlist.updated_at')
            ->join('minimi_product','minimi_product.product_id','=','minimi_product_wishlist.product_id')
            ->join('data_category','data_category.category_id','=','minimi_product.category_id')
			->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
			->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
			->where([
				'minimi_product_wishlist.user_id' => $user_id,
				'minimi_product_wishlist.status' => 1
			])
        ->skip($offset_count)->take($limit)->distinct()->orderBy('minimi_product_wishlist.updated_at','DESC')->get();
        
        $col_prod = collect($query);
		$product_ids = $col_prod->pluck('product_id')->all();
		
		$images = DB::table('minimi_product_gallery')
			->select('product_id','prod_gallery_picture as pict','prod_gallery_alt as alt','prod_gallery_title as title')
			->whereIn('product_id',$product_ids)
			->where('main_poster',1)
		->get();
        $col_image = collect($images);
        
        $rating = DB::table('minimi_content_rating_tab')
			->select('product_id', 'value')
			->whereIn('product_id', $product_ids)
			->where('tag', 'review_count')
		->get();
		$col_rating = collect($rating);

		foreach ($query as $row) {
            if($row->product_price>0){
                if($row->product_price_gb>0){
                    $product_buyable = 2;
                }else{
                    $product_buyable = 1;
                }
            }else{
                if($row->product_price_gb>0){
                    $product_buyable = 3;
                }else{
                    $product_buyable = 0;
                }
            }
            $row->product_buyable = $product_buyable;
            if($product_buyable==1){
                $row->discount = '5%';
                $row->price_before_discount = (1+0.05)*$row->product_price;
            }elseif($product_buyable==2||$product_buyable==3){
                if($product_buyable==2){
                    $row->price_before_discount = $row->product_price;
                    $row->product_price = $row->product_price_gb;
                }
            }
			$find = $col_image->where('product_id',$row->product_id)->first();
			if($find==null){
				$row->pict = "";
				$row->alt = "";
				$row->title = "";
			}else{
				$row->pict = $find->pict;
				$row->alt = $find->alt;
				$row->title = $find->title;
            }
            
            $rating = $col_rating->where('product_id', $row->product_id)->first();
			if ($rating == null) {
				$row->review_count = 0;
			} else {
				$row->review_count = $rating->value;
			}
		}

		$next_offset = 'empty';
		if(count($query)>$limit){
			$query2 = DB::table('minimi_product_wishlist')
                ->select('minimi_product_wishlist.product_id','minimi_product_wishlist.updated_at')
                ->join('minimi_product','minimi_product.product_id','=','minimi_product_wishlist.product_id')
                ->leftJoin('minimi_content_rating_tab','minimi_content_rating_tab.product_id','=','minimi_product.product_id')
                ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                ->where([
                    'minimi_product_wishlist.user_id' => $user_id,
                    'minimi_product_wishlist.status' => 1,
                    'minimi_content_rating_tab.tag'=>'review_count'
                ])
			->skip($next_offset_count)->take($limit)->distinct()->orderBy('minimi_product_wishlist.updated_at','DESC')->get();
			
			if(count($query2)>0){
				$next_offset = $next_offset_count/$limit;
			}
		}

		$result['data'] = $query;
		$result['offset'] = $next_offset;
		return $result;
    }

    public function checkWishlist($user_id, $product_id){
        $query = DB::table('minimi_product_wishlist')
            ->where([
                'user_id'=>$user_id,
                'product_id'=>$product_id
            ])
        ->first();

        if(!empty($query)){
            return $query;
        }

        return 'empty';
    }

    public function updateWishlist($wish_id, $insert){
        DB::table('minimi_product_wishlist')
            ->where('wish_id',$wish_id)
        ->update($insert);
    }

    ///---Commerce Basket---///

    public function getBasketCount_exe($user_id, $cg_id=null){
        if($cg_id!=null){
            $query = DB::table('commerce_basket')
                ->select('basket_id','commerce_basket.product_id','commerce_basket.variant_id','product_uri','category_name','subcat_name','brand_name','product_name','variant_name','commerce_basket.count','stock_price_gb as stock_price','commerce_basket.updated_at')
                ->join('commerce_group_buy','commerce_group_buy.cg_id','=','commerce_basket.cg_id')
                ->join('minimi_product','minimi_product.product_id','=','commerce_basket.product_id')
                ->join('minimi_product_variant','minimi_product_variant.variant_id','=','commerce_basket.variant_id')
                ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                ->where([
                    'commerce_basket.cg_id' => $cg_id,
                    'commerce_basket.user_id' => $user_id,
                    'commerce_basket.status' => 1,
                    'commerce_group_buy.status' => 1
                ])
                ->where('minimi_product_variant.stock_price_gb','>',0)
            ->orderBy('commerce_basket.created_at','DESC')->get();
        }else{    
            $query = DB::table('commerce_basket')
                ->select('basket_id','commerce_basket.product_id','commerce_basket.variant_id','product_uri','category_name','subcat_name','brand_name','product_name','variant_name','commerce_basket.count','stock_price','commerce_basket.updated_at')
                ->join('minimi_product','minimi_product.product_id','=','commerce_basket.product_id')
                ->join('minimi_product_variant','minimi_product_variant.variant_id','=','commerce_basket.variant_id')
                ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                ->where([
                    'commerce_basket.user_id' => $user_id,
                    'commerce_basket.status' => 1
                ])
                ->whereNull('commerce_basket.cg_id')
                ->where('minimi_product_variant.stock_price','>',0)
            ->orderBy('commerce_basket.created_at','DESC')->get();
        }
        
        $item_count = 0;
        foreach ($query as $row) {
			$item_count += $row->count;
		}

		return $item_count;
    }

    public function getBasket_exe($user_id, $cg_id=null){
        if($cg_id!=null){
            $query = DB::table('commerce_basket')
                ->select('basket_id','commerce_basket.product_id','commerce_basket.variant_id','product_uri','category_name','subcat_name','brand_name','product_name','variant_name','commerce_basket.count','stock_price_gb as stock_price','stock_restriction_count','commerce_basket.updated_at')
                ->join('commerce_group_buy','commerce_group_buy.cg_id','=','commerce_basket.cg_id')
                ->join('minimi_product','minimi_product.product_id','=','commerce_basket.product_id')
                ->join('minimi_product_variant','minimi_product_variant.variant_id','=','commerce_basket.variant_id')
                ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                ->where([
                    'commerce_basket.cg_id' => $cg_id,
                    'commerce_basket.user_id' => $user_id,
                    'commerce_basket.status' => 1
                ])
                ->where('commerce_group_buy.status','!=',0)
                ->where('minimi_product_variant.stock_price_gb','>',0)
            ->orderBy('commerce_basket.created_at','DESC')->get();
        }else{    
            $query = DB::table('commerce_basket')
                ->select('basket_id','commerce_basket.product_id','commerce_basket.variant_id','product_uri','category_name','subcat_name','brand_name','product_name','variant_name','commerce_basket.count','stock_price','stock_restriction_count','commerce_basket.updated_at')
                ->join('minimi_product','minimi_product.product_id','=','commerce_basket.product_id')
                ->join('minimi_product_variant','minimi_product_variant.variant_id','=','commerce_basket.variant_id')
                ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                ->where([
                    'commerce_basket.user_id' => $user_id,
                    'commerce_basket.status' => 1
                ])
                ->whereNull('commerce_basket.cg_id')
                ->where('minimi_product_variant.stock_price','>',0)
            ->orderBy('commerce_basket.created_at','DESC')->get();
        }
        
        $col_prod = collect($query);
		$product_ids = $col_prod->pluck('product_id')->all();
		
		$images = DB::table('minimi_product_gallery')
			->select('product_id','prod_gallery_picture as pict','prod_gallery_alt as alt','prod_gallery_title as title')
			->whereIn('product_id',$product_ids)
			->where('main_poster',1)
		->get();
		$col_image = collect($images);
        
        $item_count = 0;
        $price_count = 0;
		foreach ($query as $row) {
			$find = $col_image->where('product_id',$row->product_id)->first();
			if($find==null){
				$row->pict = "";
				$row->alt = "";
				$row->title = "";
			}else{
				$row->pict = $find->pict;
				$row->alt = $find->alt;
				$row->title = $find->title;
            }
            $row->total_amount = floatval($row->count*$row->stock_price);
            $item_count += $row->count;
            $price_count += $row->total_amount;
            $row->limit_met = 0;
            if($row->stock_restriction_count!=null && $row->count==$row->stock_restriction_count){
                $row->limit_met = 1;
            }
		}

		$result['data'] = $query;
		$result['calculate']['item_count'] = $item_count;
		$result['calculate']['total_amount'] = $price_count;
		$result['verdict'] = 'success';
		return $result;
    }

    public function addToBasket_exe($user_id, $product_id, $variant_id, $count, $cg_id=null){
        $check = $this->checkBasket($user_id, $product_id, $variant_id, $cg_id);
        $date = date('Y-m-d H:i:s');
        $insert['status'] = 1;
        $insert['created_at'] = $date;
        $insert['updated_at'] = $date;
        if($check=='empty'){
            $check_res = $this->checkRestriction($variant_id,$count);
            if($check_res['verdict']==TRUE){
                $insert['product_id'] = $product_id;
                $insert['variant_id'] = $variant_id;
                $insert['count'] = $count;
                $insert['user_id'] = $user_id;
                $insert['cg_id'] = $cg_id;
                DB::table('commerce_basket')->insert($insert);
            }else{
                $item_msg = ($check_res['restriction']>1)?'items':'item';
                $tobe = ($check_res['restriction']>1)?'are':'is';
                $return['verdict'] = 'item_restriction_count_met';
                $return['message_id'] = 'Pembelian maksimal '.$check_res['restriction'].' item';
                $return['message_en'] = 'Maximum purchase '.$tobe.' '.$check_res['restriction'].' '.$item_msg;
                return $return;
            }
        }else{
            $count_update = $check->count+$count;
            $check_res = $this->checkRestriction($variant_id,$count_update);
            if($check_res['verdict']==TRUE){
                $insert['count'] = $count_update;
                $this->updateBasket($check->basket_id, $insert);
            }else{
                $item_msg = ($check_res['restriction']>1)?'items':'item';
                $tobe = ($check_res['restriction']>1)?'are':'is';
                $return['verdict'] = 'item_restriction_count_met';
                $return['message_id'] = 'Pembelian maksimal '.$check_res['restriction'].' item';
                $return['message_en'] = 'Maximum purchase '.$tobe.' '.$check_res['restriction'].' '.$item_msg;
                return $return;
            }
        }

        $basket = $this->getBasket_exe($user_id, $cg_id);
        $basket['verdict'] = 'success';
        return $basket;
    }

    public function editBasket_exe($user_id, $product_id, $variant_id, $count, $cg_id=null, $basket_id=null, $remove=0){
        $check = $this->checkBasket($user_id, $product_id, $variant_id, $cg_id,$basket_id);
        $date = date('Y-m-d H:i:s');
        if($basket_id==null){
            $insert['count'] = $count;
            if($remove==1){
                $insert['status'] = 2;
            }else{
                $insert['status'] = 1;
            }
            $insert['updated_at'] = $date;
            if($check=='empty'){
                $return['verdict'] = 'invalid_basket_record';
                return $return;
            }
            $basket_id = $check->basket_id;
        }else{
            $insert['count'] = $count;
            if($remove==1){
                $insert['status'] = 2;
            }else{
                $insert['product_id'] = $product_id;
                $insert['variant_id'] = $variant_id;
                $insert['status'] = 1;
            }
            $insert['updated_at'] = $date;
            if($check=='empty'){
                $return['verdict'] = 'invalid_basket_record';
                return $return;
            }
        }
        $check_res = $this->checkRestriction($variant_id,$count);
        if($check_res['verdict']==TRUE){
            $this->updateBasket($basket_id, $insert);
        }else {
            $insert['count'] = $check_res['restriction'];
            $this->updateBasket($basket_id, $insert);
            $item_msg = ($check_res['restriction']>1)?'items':'item';
            $tobe = ($check_res['restriction']>1)?'are':'is';
            $basket = $this->getBasket_exe($user_id, $cg_id);
            $basket['verdict'] = 'item_restriction_count_met';
            $basket['message_id'] = 'Pembelian maksimal '.$check_res['restriction'].' item';
            $basket['message_en'] = 'Maximum purchase '.$tobe.' '.$check_res['restriction'].' '.$item_msg;
            return $basket;
        }

        $basket = $this->getBasket_exe($user_id, $cg_id);
        $basket['verdict'] = 'success';
        return $basket;
    }

    public function checkBasket($user_id, $product_id, $variant_id=null, $cg_id=null,$basket_id=null){
        $where['user_id']=$user_id;
        if($basket_id==null){
            $where['product_id']=$product_id;
            if($variant_id!=null){
                $where['variant_id']=$variant_id;
            }else{
                $where['status']=1;
            }
        }else{
            $where['basket_id']=$basket_id;
        }
        $query = DB::table('commerce_basket')
            ->where($where);
        if($cg_id!=null){
            $query = $query->where('cg_id',$cg_id);
        }else{
            $query = $query->whereNull('cg_id');
        }
        $query = $query->first();

        if(!empty($query)){
            return $query;
        }

        return 'empty';
    }

    public function updateBasket($basket_id, $insert){
        DB::table('commerce_basket')
            ->where('basket_id',$basket_id)
        ->update($insert);
    }

    ///---Physical Product---///

    protected function addToCartBasket_exe($item_array, $user_id){
        $query = DB::table('commerce_basket')
			->select('basket_id','commerce_basket.product_id','commerce_basket.variant_id','commerce_basket.count','stock_count','stock_price','stock_restriction_count')
            ->join('minimi_product','minimi_product.product_id','=','commerce_basket.product_id')
            ->join('minimi_product_variant','minimi_product_variant.variant_id','=','commerce_basket.variant_id')
            ->join('data_category','data_category.category_id','=','minimi_product.category_id')
			->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
			->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
			->where([
				'commerce_basket.user_id' => $user_id,
				'commerce_basket.status' => 1
            ])
            ->whereIn('commerce_basket.basket_id',$item_array)
            ->where('minimi_product_variant.stock_price','>',0)
        ->get();

        if(!count($query)){
            return 'no_item_valid';
        }

        $col_prod = collect($query);
        $basket_ids = $col_prod->pluck('basket_id')->all();
        //$diff = array_diff($item_array, $basket_ids);

        $date = date('Y-m-d H:i:s');

        $cart_id = DB::table('commerce_shopping_cart')->where(['user_id'=>$user_id,'cart_type'=>1,'status'=>1])->value('cart_id');
        if($cart_id==null){
            $cart['user_id'] = $user_id;
            $cart['price_amount'] = 0;
            $cart['total_amount'] = 0;
            $cart['cart_type'] = 1;
            $cart['created_at'] = $date;
            $cart['updated_at'] = $date;
            $cart_id = DB::table('commerce_shopping_cart')->insertGetId($cart);
            
        }else{
            DB::table('commerce_shopping_cart_item')->where('cart_id',$cart_id)->delete();
        }

        $item = array();
        $i = 0;
        $price = 0;
        foreach ($query as $row) {
            /*if($row->stock_count==0){
                return 'one_or_more_item_invalid';
            }*/

            if($row->stock_price==0){
                DB::table('commerce_basket')->where('basket_id',$row->basket_id)->update([
                    'count' => 0,
                    'status' => 2,
                    'updated_at' => $date
                ]);
                return 'item_invalid';
            }

            if($row->stock_restriction_count!=null && $row->count>$row->stock_restriction_count){
                DB::table('commerce_basket')->where('basket_id',$row->basket_id)->update([
                    'count' => $row->stock_restriction_count,
                    'updated_at' => $date
                ]);
                return 'restriction_met';
            }

            $item[$i]['cart_id'] = $cart_id;
            $item[$i]['basket_id'] = $row->basket_id;
            $item[$i]['product_id'] = $row->product_id;
            $item[$i]['variant_id'] = $row->variant_id;
            $item[$i]['count'] = $row->count;
            $item[$i]['price_amount'] = $row->stock_price;
            $item[$i]['discount_amount'] = 0;
            $item[$i]['total_amount'] = floatval($row->count * $row->stock_price);
            $item[$i]['created_at'] = $date;
            $item[$i]['updated_at'] = $date;
            $price += $item[$i]['total_amount'];
            $i++;
        }
        DB::table('commerce_shopping_cart_item')->insert($item);

        $update['price_amount'] = $price;
        $update['total_weight'] = 0;
        $update['voucher_id'] = null;
        $update['delivery_service'] = null;
        $update['delivery_vendor'] = null;
        $update['discount_amount'] = 0;
        $update['delivery_discount_amount'] = 0;
        $update['delivery_amount'] = 0;
        $update['insurance_amount'] = 0;
        $update['total_amount'] = $price;
        DB::table('commerce_shopping_cart')->where('cart_id',$cart_id)->update($update);

        return $cart_id;
    }

    protected function previewBasketCart_exe($cart_id, $user_id, $voucher_code=null, $address_id=null, $delivery=null, $delivery_tariff=null, $delivery_vendor=null, $insurance_sicepat=0, $cg_id=null){
        if($cg_id==null){
            $transactionType = 1;
            $where['status']=1;
            $where['cart_id']=$cart_id;
            $where['user_id']=$user_id;
            $cart_type = array(1,4);
        }else{
            $transactionType = 3;
            $where['status']=1;
            $where['cg_id']=$cg_id;
            $where['cart_id']=$cart_id;
            $where['user_id']=$user_id;
            $cart_type = array(3);
        }

        $query = DB::table('commerce_shopping_cart')
            ->where($where)
            ->whereIn('cart_type',$cart_type)
        ->first();

        if(empty($query)){
            return 'cart_invalid';
        }

        if($cg_id==null){
            $item = DB::table('commerce_shopping_cart_item')
                ->select('commerce_shopping_cart_item.product_id','product_name','product_price','product_weight','stock_weight','category_name','subcat_name','brand_name','count','price_amount','discount_amount','total_amount','variant_name')
                ->join('minimi_product','commerce_shopping_cart_item.product_id','=','minimi_product.product_id')
                ->join('minimi_product_variant','commerce_shopping_cart_item.variant_id','=','minimi_product_variant.variant_id')
                ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                ->where('minimi_product.status',1)
                ->where('minimi_product.product_type',1)
                ->where('minimi_product.product_price','>',0)
                ->where('cart_id',$cart_id)
            ->get();
        }else{
            $item = DB::table('commerce_shopping_cart_item')
                ->select('commerce_shopping_cart_item.product_id','product_name','product_price','product_weight','stock_weight','category_name','subcat_name','brand_name','count','price_amount','discount_amount','total_amount','variant_name')
                ->join('minimi_product','commerce_shopping_cart_item.product_id','=','minimi_product.product_id')
                ->join('minimi_product_variant','commerce_shopping_cart_item.variant_id','=','minimi_product_variant.variant_id')
                ->join('data_category','data_category.category_id','=','minimi_product.category_id')
                ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
                ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
                ->where('minimi_product.status',1)
                ->where('minimi_product.product_type',1)
                ->where('minimi_product.product_price_gb','>',0)
                ->where('cart_id',$cart_id)
            ->get();
        }

        $col_prod = collect($item);
		$product_ids = $col_prod->pluck('product_id')->all();
		
		$images = DB::table('minimi_product_gallery')
			->select('product_id','prod_gallery_picture as pict','prod_gallery_alt as alt','prod_gallery_title as title')
			->whereIn('product_id',$product_ids)
			->where('main_poster',1)
		->get();
		$col_image = collect($images);

        $weight = 0;
        $total_amount = 0;
		foreach ($item as $row) {
            $weight_item = ($row->stock_weight>0)?$row->stock_weight:$row->product_weight;
            $weight += ($row->count * $weight_item);
            $total_amount += $row->total_amount;
			$find = $col_image->where('product_id',$row->product_id)->first();
			if($find==null){
				$row->pict = "";
				$row->alt = "";
				$row->title = "";
			}else{
				$row->pict = $find->pict;
				$row->alt = $find->alt;
				$row->title = $find->title;
            }
            $arr = explode($row->product_name.' ',$row->variant_name);
            if(count($arr)>1){
                $row->variant_name = $arr[1];
            }
        }

        $return['voucher_code'] = '';
        $return['voucher_verdict'] = 'empty';
        $date = date('Y-m-d H:i:s');
        $update_voucher = array();

        if($query->total_weight!=$weight){
            $total_weight = $weight/1000;
            $update_voucher['total_weight'] = $total_weight;
            $update_voucher['updated_at'] = $date;
            $query->total_weight = $total_weight;
        }

        if($query->price_amount!=$total_amount){
            $update_voucher['price_amount'] = $total_amount;
            $update_voucher['updated_at'] = $date;
            $query->price_amount = $total_amount;
        }

        if($voucher_code!=null){
            $cur_date = date('Y-m-d',strtotime($date));
            $voucher = app('App\Http\Controllers\Utility\VoucherController')->calculatePrice($voucher_code, $query->price_amount, $cur_date, $user_id, $cart_id, $query->delivery_amount, $transactionType);
            $return['voucher_code'] = $voucher_code;

            if($voucher['voucher_verdict'] != 'success'){
                if($voucher['voucher_verdict'] != 'voucher_not_found'){
                    if($voucher['voucher_type']==3){
                        $query->delivery_discount_amount = 0;
                        $update_voucher['delivery_discount_amount'] = 0;
                    }
                }
                $query->voucher_id = null;
                $query->discount_amount = 0;
                $total_amount = floatval($query->price_amount - $query->discount_amount + $query->delivery_amount - $query->delivery_discount_amount + $query->insurance_amount);
                $query->total_amount = $total_amount;
                $update_voucher['total_amount'] = $total_amount;
                $update_voucher['voucher_id'] = null;
                $update_voucher['discount_amount'] = 0;
                $update_voucher['updated_at'] = $date;

                $return['voucher_verdict'] = $voucher['voucher_verdict'];
            }else{
                if($query->voucher_id != $voucher['voucher_id']){
                    if($voucher['voucher_type']==3){
                        if($query->delivery_discount_amount<$voucher['delivery_discount']){
                            $query->delivery_discount_amount = $voucher['delivery_discount'];
                            $update_voucher['delivery_discount_amount'] = $voucher['delivery_discount'];
                        }
                    }
                    $query->voucher_id = $voucher['voucher_id'];
                    $query->discount_amount = $voucher['rabat'];
                    $total_amount = floatval($query->price_amount - $query->discount_amount + $query->delivery_amount - $query->delivery_discount_amount + $query->insurance_amount);
                    $query->total_amount = $total_amount;
                    $update_voucher['voucher_id'] = $voucher['voucher_id'];
                    $update_voucher['discount_amount'] = $voucher['rabat'];
                    $update_voucher['total_amount'] = $total_amount;
                    $update_voucher['updated_at'] = $date;
                }

                $return['voucher_verdict'] = 'success';
            }   
        }else{
            if($query->discount_amount>0){
                $total_amount = floatval($query->price_amount - 0 + $query->delivery_amount - $query->delivery_discount_amount + $query->insurance_amount);
                $query->total_amount = $total_amount;
                $update_voucher['total_amount'] = $total_amount;
            }

            $query->voucher_id = null;
            $query->discount_amount = 0;
            $update_voucher['voucher_id'] = null;
            $update_voucher['discount_amount'] = 0;
            $update_voucher['updated_at'] = $date;
        }
        
        $return['address'] = array();

        if($address_id!=null){
            $return['address'] = DB::table('minimi_user_address')->where('address_id',$address_id)->first();
            
            $param = DB::table('data_param')
                ->select('param_tag','param_value')
                ->whereIn('param_tag',['free_delivery_region','free_delivery_amount','free_delivery_weight','free_delivery_discount_threshold','free_delivery_region_2','free_delivery_amount_2','free_delivery_weight_2','free_delivery_discount_threshold_2'])
            ->get();
            $col_param = collect($param);

            $free_delivery_region = $col_param->firstWhere('param_tag', 'free_delivery_region')->param_value;
            $free_delivery_amount = floatval($col_param->firstWhere('param_tag', 'free_delivery_amount')->param_value);
            $free_delivery_weight = floatval($col_param->firstWhere('param_tag', 'free_delivery_weight')->param_value);
            $free_delivery_discount_threshold = floatval($col_param->firstWhere('param_tag', 'free_delivery_discount_threshold')->param_value);
            $free_delivery_region_2 = $col_param->firstWhere('param_tag', 'free_delivery_region_2')->param_value;
            $free_delivery_amount_2 = floatval($col_param->firstWhere('param_tag', 'free_delivery_amount_2')->param_value);
            $free_delivery_weight_2 = floatval($col_param->firstWhere('param_tag', 'free_delivery_weight_2')->param_value);
            $free_delivery_discount_threshold_2 = floatval($col_param->firstWhere('param_tag', 'free_delivery_discount_threshold_2')->param_value);
            
            $pos = substr($return['address']->sicepat_destination_code, 0, 3);
            $delivery_promo = 0;
            $delivery_discount_threshold = 0;
            $region = 0;
            if($pos == $free_delivery_region){
                if($free_delivery_amount>0 && $query->price_amount>=$free_delivery_amount){
                    $region = 1;
                }
            }else{
                $arr_region = explode(';',$free_delivery_region_2);
                if(in_array($pos, $arr_region)){
                    $region = 1;
                    $free_delivery_amount = $free_delivery_amount_2;
                    $free_delivery_weight = $free_delivery_weight_2;
                    $free_delivery_discount_threshold = $free_delivery_discount_threshold_2;
                }
            }

            if($region==1){
                if($free_delivery_amount>0 && $query->price_amount>=$free_delivery_amount){
                    if($free_delivery_weight!=0){
                        if($weight>=$free_delivery_weight){
                            $delivery_discount_threshold = 1;
                        }
                    }else{
                        $delivery_discount_threshold = 1;
                    }
                }
            }
            
            if($delivery_discount_threshold == 1){
                if($query->cart_type==4){
                    $delivery_promo = 1;
                    $discount_message = 0;
                }else{
                    if($free_delivery_discount_threshold>0){
                        $delivery_promo = 1;
                        $discount_message = $free_delivery_discount_threshold;
                    }else{
                        $delivery_promo = 1;
                        $discount_message = 0;
                    }
                }
            }

            $return['delivery_promo'] = $delivery_promo;
            if($delivery_promo==1){
                $message_id['greetings'] = 'SELAMAT ANDA BERHASIL';
                $message_en['greetings'] = 'CONGRATULATIONS';

                if($free_delivery_amount>0){
                    $message_id['message'] = 'Mencapai transaksi Rp. '.number_format($free_delivery_amount,0,'.',',').' dan mendapatkan';
                    $message_en['message'] = 'Transaction amount exceed IDR '.number_format($free_delivery_amount,0,'.',',').' you\'re eligible for';
                }else{
                    $message_id['message'] = 'Mendapatkan potongan';
                    $message_en['message'] = 'You\'re eligible for';
                }

                if($discount_message==0){
                    $message_id['discount'] = 'Gratis Ongkir';
                    $message_en['discount'] = 'Free Delivery Discount';
                }else{
                    $message_id['discount'] = 'Rp. '.number_format($free_delivery_discount_threshold,0,'.',',');
                    $message_en['discount'] = 'IDR '.number_format($free_delivery_discount_threshold,0,',','.');
                }
            }else{
                $message_id = array();
                $message_en = array();
            }

            $return['delivery_promo_message_id'] = $message_id;
            $return['delivery_promo_message_en'] = $message_en;

            if($address_id!=$query->address_id){
                $query->address_id = $address_id;
                $query->sicepat_destination_code = $return['address']->sicepat_destination_code;
                $delivery=null;
                $delivery_tariff=null;
                $delivery_vendor=null;
                $query->delivery_service = null;
                $query->delivery_vendor = null;
                $query->delivery_amount = 0;
                $query->delivery_discount_amount = 0;
                $total_amount = floatval($query->price_amount - $query->discount_amount + 0 - 0 + $query->insurance_amount);
                $query->total_amount = $total_amount;
                $update_voucher['delivery_vendor'] = null;
                $update_voucher['delivery_service'] = null;
                $update_voucher['delivery_amount'] = 0;
                $update_voucher['delivery_discount_amount'] = 0;
                $update_voucher['total_amount'] = $total_amount;
                $update_voucher['sicepat_destination_code'] = $return['address']->sicepat_destination_code;
                $update_voucher['address_id'] = $address_id;
                $update_voucher['updated_at'] = $date;
            }

            if($delivery!=null && $delivery_tariff!=null && $delivery_vendor!=null){
                $delivery_vendor = ($delivery=='MIX')?'Minimi':$delivery_vendor;
                if($query->delivery_amount!=$delivery_tariff || $query->delivery_service!=$delivery || $query->delivery_vendor!=$delivery_vendor){
                    $continue = 1;
                    if($query->voucher_id!=NULL && $voucher['voucher_type']==3){
                        $continue = 0;
                    }
                }else{
                    if($query->voucher_id==NULL){
                        $continue = 1;
                    }else{
                        $continue = 0;
                    }
                }

                if($continue == 1){
                    $delivery_discount = 0;
                    if($delivery_discount_threshold == 1){
                        if($query->cart_type==4){
                            $delivery_discount = $delivery_tariff;
                        }else{
                            if($free_delivery_discount_threshold>0){
                                $delivery_discount = $free_delivery_discount_threshold;
                            }else{
                                $delivery_discount = $delivery_tariff;
                            }
                        }
                    }

                    $query->delivery_amount = $delivery_tariff;
                    $query->delivery_discount_amount = $delivery_discount;
                    $query->delivery_service = $delivery;
                    $query->delivery_vendor = $delivery_vendor;
                    $total_amount = floatval($query->price_amount - $query->discount_amount + $delivery_tariff - $delivery_discount + $query->insurance_amount);
                    $query->total_amount = $total_amount;
                    $update_voucher['delivery_vendor'] = $delivery_vendor;
                    $update_voucher['delivery_service'] = $delivery;
                    $update_voucher['delivery_amount'] = $delivery_tariff;
                    $update_voucher['delivery_discount_amount'] = $delivery_discount;
                    $update_voucher['total_amount'] = $total_amount;
                    $update_voucher['updated_at'] = $date;
                }else{
                    $query->delivery_amount = $delivery_tariff;
                    $query->delivery_service = $delivery;
                    $query->delivery_vendor = $delivery_vendor;
                    $total_amount = floatval($query->price_amount - $query->discount_amount + $query->delivery_amount - $query->delivery_discount_amount + $query->insurance_amount);
                    $query->total_amount = $total_amount;
                    $update_voucher['delivery_vendor'] = $delivery_vendor;
                    $update_voucher['delivery_service'] = $delivery;
                    $update_voucher['delivery_amount'] = $delivery_tariff;
                    $update_voucher['total_amount'] = $total_amount;
                    $update_voucher['updated_at'] = $date;
                }
            }
        }else{
            $return['delivery_promo'] = 0;
            $return['delivery_promo_message_id'] = array();
            $return['delivery_promo_message_en'] = array();
            if($query->delivery_amount>0){
                $query->delivery_amount = 0;
                $query->delivery_discount_amount = 0;
                $total_amount = floatval($query->price_amount - $query->discount_amount + 0 - 0 + $query->insurance_amount);
                $query->total_amount = $total_amount;
                $update_voucher['delivery_amount'] = 0;
                $update_voucher['delivery_discount_amount'] = 0;
                $update_voucher['total_amount'] = $total_amount;
            }
            $query->address_id = null;
            $query->sicepat_destination_code = null;
            $update_voucher['sicepat_destination_code'] = null;
            $update_voucher['address_id'] = null;
            $update_voucher['updated_at'] = $date;
        }

        $return['insurance_available'] = 0;
        if(strtolower($query->delivery_vendor)=='sicepat'){
            $return['insurance_verdict'] = 'insurance_not_set';
            $delivery_insurance_sicepat = DB::table('data_param')->where('param_tag','delivery_insurance_sicepat')->value('param_value');
            
            if($query->price_amount>=500000){
                $return['insurance_available'] = 1;
            }else{
                $return['insurance_verdict'] = 'minimum_price_amount_not_met';
            }
            
            if($insurance_sicepat==1){
                $insurance = floatval(($delivery_insurance_sicepat/100)*$query->price_amount);
                $ceil = ceil($insurance/100);
                $insurance_val = $ceil*100;
                $total_amount = floatval($query->price_amount - $query->discount_amount + $query->delivery_amount - $query->delivery_discount_amount + $insurance_val);
                $query->insurance_amount = $insurance_val;
                $query->total_amount = $total_amount;
                $update_voucher['insurance_amount'] = $insurance_val;
                $update_voucher['total_amount'] = $total_amount;
                $update_voucher['updated_at'] = $date;
                $return['insurance_verdict'] = 'success';
            }else{
                if($query->insurance_amount>0){
                    $total_amount = floatval($query->price_amount - $query->discount_amount + $query->delivery_amount - $query->delivery_discount_amount + 0);
                    $query->insurance_amount = 0;
                    $query->total_amount = $total_amount;
                    $update_voucher['insurance_amount'] = 0;
                    $update_voucher['total_amount'] = $total_amount;
                    $update_voucher['updated_at'] = $date;
                }
            }
        }else{
            $return['insurance_verdict'] = 'insurance_not_available_with_vendor';
            if($query->insurance_amount>0){
                $total_amount = floatval($query->price_amount - $query->discount_amount + $query->delivery_amount - $query->delivery_discount_amount + 0);
                $query->insurance_amount = 0;
                $query->total_amount = $total_amount;
                $update_voucher['insurance_amount'] = 0;
                $update_voucher['total_amount'] = $total_amount;
                $update_voucher['updated_at'] = $date;
            }
        }

        $point = app('App\Http\Controllers\Utility\UtilityController')->countTransactionPoint($query->total_amount);
        $query->estimated_point = floatval($point);
        $update_voucher['estimated_point'] = floatval($point);
        $update_voucher['updated_at'] = $date;

        if(!empty($update_voucher)){
            DB::table('commerce_shopping_cart')->where('cart_id',$cart_id)->update($update_voucher);
        }

        $query->item = $item;
        $return['cart'] = $query;
        return $return;
    }

    protected function bookBasketCheckout_exe($cart_id, $user_id, $payment_method, $payment_vendor='', $pg_code='', $cg_id=null){
        if($cg_id==null){
            $where['status']=1;
            $where['cart_id']=$cart_id;
            $where['user_id']=$user_id;
            $cart_type = array(1,4);
            $transactionType = 1;
        }else{
            $where['status']=1;
            $where['cg_id']=$cg_id;
            $where['cart_id']=$cart_id;
            $where['user_id']=$user_id;
            $cart_type = array(3);
            $transactionType = 3;
        }

        $query = DB::table('commerce_shopping_cart')
            ->where($where)
            ->whereIn('cart_type',$cart_type)
        ->first();

        if(empty($query)){
            $return['verdict'] = 'cart_invalid';
            return $return;
        }

        $date = date('Y-m-d H:i:s');

        $voucher_code = DB::table('commerce_voucher')->where('voucher_id',$query->voucher_id)->value('voucher_code');

        if($voucher_code!=null){
            $cur_date = date('Y-m-d',strtotime($date));
            $voucher = app('App\Http\Controllers\Utility\VoucherController')->calculatePrice($voucher_code, $query->price_amount, $cur_date, $user_id, $cart_id, $query->delivery_amount, $transactionType);
            if($voucher['voucher_verdict'] != 'success'){
                $return['verdict'] = 'voucher_problem_'.$voucher['voucher_verdict'];
                return $return;
            }

            $update_voucher['usage'] = $voucher['current_usage']+1;
            $update_voucher['updated_at'] = $date;
            DB::table('commerce_voucher')->where('voucher_id',$voucher['voucher_id'])->update($update_voucher);

            $insert['voucher_id'] = $voucher['voucher_id'];
            $insert['user_id'] = $user_id;
            $insert['created_at'] = $date;
            $insert['updated_at'] = $date;
            DB::table('commerce_voucher_usage')->insert($insert);
        }

        if($query->address_id==null){
            $return['verdict'] = 'invalid_address';
            return $return;
        }

        $check_address = DB::table('minimi_user_address')->where(['address_id'=>$query->address_id,'status'=>1])->first();
        if(empty($check_address)){
            $return['verdict'] = 'invalid_address';
            return $return;
        }

        if($query->delivery_vendor==null || $query->delivery_service==null){
            $return['verdict'] = 'invalid_delivery_service';
            return $return;
        }

        $book['cart_id'] = $cart_id;
        $book['user_id'] = $user_id;
        $book['cg_id'] = $cg_id;
        $book['address_id'] = $query->address_id;
        $book['sicepat_destination_code'] = $query->sicepat_destination_code;
        $book['price_amount'] = $query->price_amount;
        $book['delivery_amount'] = $query->delivery_amount;
        $book['discount_amount'] = $query->discount_amount;
        $book['delivery_discount_amount'] = $query->delivery_discount_amount;
        $book['insurance_amount'] = $query->insurance_amount;
        $book['total_amount'] = $query->total_amount;
        $book['delivery_service'] = $query->delivery_service;
        $book['delivery_vendor'] = $query->delivery_vendor;
        $book['payment_vendor'] = $payment_vendor;
        $book['payment_method'] = $payment_method;
        $book['pg_code'] = $pg_code;
        $book['transaction_type'] = $transactionType;
        $book['order_id'] = $this->invoiceNumberGenerator($book['transaction_type']);
        
        if($book['order_id']==FALSE){
            $return['verdict'] = 'cart_invalid';
            return $return;
        }
        
        $book['created_at'] = $date;
        $book['updated_at'] = $date;

        $booking_id = DB::table('commerce_booking')->insertGetId($book);

        if($cg_id!=null){
            $cg = DB::table('commerce_group_buy')->where('cg_id',$cg_id)->first();
            if($cg->user_id == null){
                $product = DB::table('minimi_product')->select('gb_minimal','gb_expire_period')->where('product_id',$cg->product_id)->first();
                $update_cg['user_id'] = $user_id;
                $update_cg['minimum_participant'] = $product->gb_minimal;
                $update_cg['total_participant'] = 1;
                $update_cg['expire_at'] = date('Y-m-d H:i:s',strtotime($date.' + '.$product->gb_expire_period));
                $update_cg['updated_at'] = $date;
                DB::table('commerce_group_buy')->where('cg_id',$cg_id)->update($update_cg);
            } 
        }

        $update['status'] = 2;
        $update['updated_at'] = $date;
        DB::table('commerce_shopping_cart')->where('cart_id',$cart_id)->update($update);

        $items = DB::table('commerce_shopping_cart_item')->select('basket_id','product_id','variant_id')->where('cart_id',$cart_id)->get();
        $col_item = collect($items);
        $basket_ids = $col_item->pluck('basket_id')->all();

        DB::table('commerce_basket')->whereIn('basket_id',$basket_ids)->update([
            'count' => 0,
            'status' => 2,
            'updated_at' => $date
        ]);

        $return['verdict'] = 'success';
        $return['booking_id'] = $booking_id;
        $return['order_id'] = $book['order_id'];
        $return['payment_vendor'] = $payment_vendor;
        $return['payment_method'] = $payment_method;
        $return['pg_code'] = $pg_code;
        return $return;
    }

    ///---Group Buy---///
    protected function addToCartGroupBuy_exe($cg_id, $user_id){
        $query = DB::table('commerce_basket')
            ->select('basket_id','commerce_basket.product_id','commerce_basket.variant_id','product_uri','category_name','subcat_name','brand_name','product_name','variant_name','commerce_basket.count','stock_price_gb as stock_price','commerce_basket.updated_at')
            ->join('commerce_group_buy','commerce_group_buy.cg_id','=','commerce_basket.cg_id')
            ->join('minimi_product','minimi_product.product_id','=','commerce_basket.product_id')
            ->join('minimi_product_variant','minimi_product_variant.variant_id','=','commerce_basket.variant_id')
            ->join('data_category','data_category.category_id','=','minimi_product.category_id')
            ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
            ->join('data_brand','data_brand.brand_id','=','minimi_product.brand_id')
            ->where([
                'commerce_basket.cg_id' => $cg_id,
                'commerce_basket.user_id' => $user_id,
                'commerce_basket.status' => 1
            ])
            ->where('commerce_group_buy.status','!=',0)
            ->where('minimi_product_variant.stock_price_gb','>',0)
        ->get();

        if(!count($query)){
            return 'no_item_valid';
        }

        $col_prod = collect($query);
        $basket_ids = $col_prod->pluck('basket_id')->all();
        //$diff = array_diff($item_array, $basket_ids);

        $date = date('Y-m-d H:i:s');

        $cart_id = DB::table('commerce_shopping_cart')->where(['user_id'=>$user_id, 'cg_id'=>$cg_id,'cart_type'=>3,'status'=>1])->value('cart_id');
        if($cart_id==null){
            $cart['user_id'] = $user_id;
            $cart['cg_id'] = $cg_id;
            $cart['price_amount'] = 0;
            $cart['total_amount'] = 0;
            $cart['cart_type'] = 3;
            $cart['created_at'] = $date;
            $cart['updated_at'] = $date;
            $cart_id = DB::table('commerce_shopping_cart')->insertGetId($cart);

            $cg = DB::table('commerce_group_buy')->where('cg_id',$cg_id)->first();

            if($cg->user_id == null){
                $product = DB::table('minimi_product')->select('gb_minimal','gb_expire_period')->where('product_id',$cg->product_id)->first();
                $update_cg['user_id'] = $user_id;
                $update_cg['minimum_participant'] = $product->gb_minimal;
                $update_cg['total_participant'] = 1;
                $update_cg['expire_at'] = date('Y-m-d H:i:s',strtotime($date.' + '.$product->gb_expire_period));
                $update_cg['updated_at'] = $date;
                DB::table('commerce_group_buy')->where('cg_id',$cg_id)->update($update_cg);
            }
        }else{
            DB::table('commerce_shopping_cart_item')->where('cart_id',$cart_id)->delete();
        }

        $item = array();
        $i = 0;
        $price = 0;
        foreach ($query as $row) {
            $item[$i]['cart_id'] = $cart_id;
            $item[$i]['basket_id'] = $row->basket_id;
            $item[$i]['product_id'] = $row->product_id;
            $item[$i]['variant_id'] = $row->variant_id;
            $item[$i]['count'] = $row->count;
            $item[$i]['price_amount'] = $row->stock_price;
            $item[$i]['discount_amount'] = 0;
            $item[$i]['total_amount'] = floatval($row->count * $row->stock_price);
            $item[$i]['created_at'] = $date;
            $item[$i]['updated_at'] = $date;
            $price += $item[$i]['total_amount'];
            $i++;
        }
        DB::table('commerce_shopping_cart_item')->insert($item);

        $update['price_amount'] = $price;
        $update['total_weight'] = 0;
        $update['voucher_id'] = null;
        $update['delivery_service'] = null;
        $update['delivery_vendor'] = null;
        $update['discount_amount'] = 0;
        $update['delivery_discount_amount'] = 0;
        $update['delivery_amount'] = 0;
        $update['insurance_amount'] = 0;
        $update['total_amount'] = $price;
        DB::table('commerce_shopping_cart')->where('cart_id',$cart_id)->update($update);

        return $cart_id;
    }

    ///---Digital Product---///

    protected function addToCartDigital_exe($product_id, $user_id){
        $query = DB::table('minimi_product')
            ->select('product_id','product_price','category_name','subcat_name')
            ->join('data_category','data_category.category_id','=','minimi_product.category_id')
            ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
            ->where('minimi_product.status',1)
            ->where('minimi_product.product_type',2)
            ->where('minimi_product.product_price','>',0)
            ->where('product_id',$product_id)
        ->first();

        if(empty($query)){
            return 'product_not_found';
        }

        $date = date('Y-m-d H:i:s');

        $cart['user_id'] = $user_id;
        $cart['price_amount'] = 0;
        $cart['total_amount'] = 0;
        $cart['cart_type'] = 2;
        $cart['created_at'] = $date;
        $cart['updated_at'] = $date;
        $cart_id = DB::table('commerce_shopping_cart')->insertGetId($cart);

        $item['cart_id'] = $cart_id;
        $item['product_id'] = $product_id;
        $item['count'] = 1;
        $item['price_amount'] = $query->product_price;
        $item['discount_amount'] = 0;
        $item['total_amount'] = $query->product_price;
        $item['created_at'] = $date;
        $item['updated_at'] = $date;
        DB::table('commerce_shopping_cart_item')->insert($item);

        $update['price_amount'] = $query->product_price;
        $update['discount_amount'] = 0;
        $update['total_amount'] = $query->product_price;
        DB::table('commerce_shopping_cart')->where('cart_id',$cart_id)->update($update);

        return $cart_id;
    }

    protected function previewDigitalCart_exe($cart_id, $user_id){
        $query = DB::table('commerce_shopping_cart')
            ->where([
                'status'=>1,
                'cart_type'=>2,
                'cart_id'=>$cart_id,
                'user_id'=>$user_id
            ])
        ->first();

        if(empty($query)){
            return 'cart_invalid';
        }

        $item = DB::table('commerce_shopping_cart_item')
            ->select('commerce_shopping_cart_item.product_id','product_name','product_price','category_name','subcat_name','count','price_amount','discount_amount', 'total_amount','prod_gallery_picture as pict','prod_gallery_alt as alt','prod_gallery_title as title')
            ->join('minimi_product','commerce_shopping_cart_item.product_id','=','minimi_product.product_id')
            ->join('minimi_product_gallery','commerce_shopping_cart_item.product_id','=','minimi_product_gallery.product_id')
            ->join('data_category','data_category.category_id','=','minimi_product.category_id')
            ->join('data_category_sub','data_category_sub.subcat_id','=','minimi_product.subcat_id')
            ->where('minimi_product.status',1)
            ->where('minimi_product.product_type',2)
            ->where('minimi_product.product_price','>',0)
            ->where('minimi_product_gallery.main_poster',1)
            ->where('minimi_product_gallery.status',1)
            ->where('cart_id',$cart_id)
        ->first();

        $query->item = $item;
        $return['cart'] = $query;
        return $return;
    }

    protected function bookDigitalCheckout_exe($cart_id,$user_id,$payment_method,$payment_vendor='',$pg_code=''){
        $query = DB::table('commerce_shopping_cart')
            ->where([
                'status'=>1,
                'cart_type'=>2,
                'cart_id'=>$cart_id,
                'user_id'=>$user_id
            ])
        ->first();

        if(empty($query)){
            $return['verdict'] = 'cart_invalid';
            return $return;
        }

        $date = date('Y-m-d H:i:s');

        $book['cart_id'] = $cart_id;
        $book['user_id'] = $user_id;
        $book['price_amount'] = $query->price_amount;
        $book['discount_amount'] = $query->discount_amount;
        $book['total_amount'] = $query->total_amount;
        $book['payment_vendor'] = $payment_vendor;
        $book['payment_method'] = $payment_method;
        $book['pg_code'] = $pg_code;
        $book['transaction_type'] = 2;
        $book['order_id'] = $this->invoiceNumberGenerator($book['transaction_type']);
        
        if($book['order_id']==FALSE){
            return 'cart_invalid';
        }
        
        $book['created_at'] = $date;
        $book['updated_at'] = $date;

        $booking_id = DB::table('commerce_booking')->insertGetId($book);

        $update['status'] = 2;
        $update['updated_at'] = $date;
        DB::table('commerce_shopping_cart')->where('cart_id',$cart_id)->update($update);

        $return['verdict'] = 'success';
        $return['booking_id'] = $booking_id;
        $return['order_id'] = $book['order_id'];
        $return['payment_vendor'] = $payment_vendor;
        $return['payment_method'] = $payment_method;
        $return['pg_code'] = $pg_code;
        return $return;
    }

    function invoiceNumberGenerator($transactionType){
        switch ($transactionType) {
            case 1: //physical
                $code = "PH";
                break;
            case 2: //digital
                $code = "DG";
                break;
            case 3: //group buy
                $code = "GB";
                break;            
            default:
                return FALSE;
            break;
        }
        $string = strtoupper(Str::random(4));
        $inv_num = $code.date("my").$string.env('ORDER_ID_IDENTIFIER');
		$check = $this->checkInvoiceCode($inv_num);
		if($check=="TRUE"){
			return $inv_num;
		}else{
			return $this->invoiceNumberGenerator($user_id);
		}
    }
    
    function checkInvoiceCode($code){
        $return = DB::table('commerce_booking')
            ->where([
                'order_id'=>$code
            ])
        ->first();
		return (empty($return))?"TRUE":$return;
    }

    function checkProductVariant($product_id, $variant_id){
        $product = DB::table('minimi_product_variant')->select('product_id','stock_count','stock_price','stock_price_gb','stock_agent_price')->where('variant_id',$variant_id)->first();

        if(empty($product)){
            $return['code'] = 4143;
            $return['message'] = 'product_not_found';
            return $return;
        }

        $return['code'] = 200;
        $return['message'] = 'success';

        if($product_id!=$product->product_id){
            $return['code'] = 4140;
            $return['message'] = 'invalid_variant';
        }

        $price = DB::table('minimi_product')->select('product_price','product_price_gb')->where('product_id', $product_id)->first();
        
        if($price->product_price==0 && $price->product_price_gb==0){
            $return['code'] = 4141;
            $return['message'] = 'product_not_buyable';
        }

        if(($product->stock_price==null || $product->stock_price==0) && ($product->stock_price_gb==null || $product->stock_price_gb==0)){
            $return['code'] = 4142;
            $return['message'] = 'variant_not_buyable';
        }

        return $return;
    }

    function checkRestriction($variant_id, $count){
        $restriction = DB::table('minimi_product_variant')->where('variant_id',$variant_id)->value('stock_restriction_count');
        if($restriction==null){
            $return['verdict']=TRUE;
            $return['restriction']=0;
            return $return;
        }
        $return['restriction']=$restriction;
        if($count>$restriction){
            $return['verdict']=FALSE;
            return $return;
        }
        $return['verdict']=TRUE;
        return $return;
    }
}