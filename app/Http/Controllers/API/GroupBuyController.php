<?php

namespace App\Http\Controllers\API;

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

    public function createGroupBuy(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $valid = app('App\Http\Controllers\API\CommerceController')->checkProductVariant($data['product_id'],$data['variant_id']);
            if($valid['code']!=200){
                return response()->json(['code'=>$valid['code'],'message'=>$valid['message']]);
            }

            $check_res = app('App\Http\Controllers\API\CommerceController')->checkRestriction($data['variant_id'],$data['count']);
            if($check_res['verdict']==FALSE){
                $item_msg = ($check_res['restriction']>1)?'items':'item';
                $tobe = ($check_res['restriction']>1)?'are':'is';
                $return['message_id'] = 'Pembelian maksimal '.$check_res['restriction'].' item';
                $return['message_en'] = 'Maximum purchase '.$tobe.' '.$check_res['restriction'].' '.$item_msg;
                return response()->json(['code'=>4402,'message'=>'item_restriction_count_met','data'=>$return]);
            }

            $date = date('Y-m-d H:i:s');
            $check = $this->checkGroupBuy_exe($currentUser->user_id, $data['product_id'], $data['variant_id'], $date);

            $create_group = 0;

            if($check=='empty'){
                $create_group = 1;
            }else {
                $order = DB::table('commerce_booking')
                    ->where([
                        'user_id'=>$currentUser->user_id,
                        'cg_id'=>$check->cg_id
                    ])
                    ->where('paid_status','!=',2)
                    ->where('cancel_status','!=',1)
                ->first();

                if(!empty($order)){
                    return response()->json(['code'=>41108, 'message'=>'Mohon maaf, silahkan selesaikan transaksi beli bareng yang sebelumnya jika ingin membuat grup lain.', 'message_en'=>'Sorry, please finish your previous group buy transaction if you wish to create another group.']);
                }

                $verdict = $this->groupValidityChecker($check->cg_id);
                switch ($verdict) {
                    case 'empty':
                        $create_group=1;
                    break;
                    case 'expired':
                        $create_group=1;
                    break;
                    case 'verified':
                        $create_group = 1;
                    break;
                    case 'closed':
                        $create_group = 1;
                    break;
                    default:
                        if($check->product_id!=$data['product_id']){
                            return response()->json(['code'=>41106,'message'=>'product_not_match']);
                        }
                        
                        if($check->variant_id!=$data['variant_id']){
                            DB::table('commerce_group_buy')->where('cg_id',$check->cg_id)->update([
                                'variant_id'=>$data['variant_id'],
                                'updated_at'=>$date
                            ]);
                        }
                        
                        $return['cg_id'] = floatval($check->cg_id);
                        $basket = $this->joinGroup_exe($currentUser->user_id, $data['product_id'], $data['variant_id'], $data['count'], $return['cg_id']);
                    break;
                }
            }

            if($create_group==1){
                $limit = DB::table('data_param')->where('param_tag','group_limit')->value('param_value');
                $query = DB::table('commerce_group_buy')
                    ->select('cg_id')    
                    ->where([
                        'product_id'=>$data['product_id'],
                        'show'=>1
                    ])
                    ->whereIn('status',[0,1,2,3])
                    //->where('expire_at','>=',$date)
                ->limit($limit)->get();

                $join_group = 0;

                if(count($query)<$limit){
                    $product = DB::table('minimi_product')->select('gb_minimal','gb_expire_period')->where('product_id',$data['product_id'])->first();
                    $insert['product_id'] = $data['product_id'];
                    $insert['variant_id'] = $data['variant_id'];
                    $insert['user_id'] = $currentUser->user_id;
                    $insert['minimum_participant'] = $product->gb_minimal;
                    $insert['total_participant'] = 1;
                    $insert['show'] = 1;
                    $insert['expire_at'] = date('Y-m-d H:i:s',strtotime($date.' + '.$product->gb_expire_period));
                    $insert['created_at'] = $date;
                    $insert['updated_at'] = $date;
                    $return['cg_id'] = DB::table('commerce_group_buy')->insertGetId($insert);
                    $basket = app('App\Http\Controllers\API\CommerceController')->addToBasket_exe($currentUser->user_id, $data['product_id'], $data['variant_id'], $data['count'], $return['cg_id']);
                }else{
                    $join_group = 1;
                }

                if($join_group==1){
                    $coll_query = collect($query);
                    $cg_ids = $coll_query->pluck('cg_id')->all();

                    $order = DB::table('commerce_booking')
                        ->join('commerce_group_buy','commerce_group_buy.cg_id','=','commerce_booking.cg_id')
                        ->where([
                            'commerce_booking.user_id'=>$currentUser->user_id
                        ])
                        ->where('paid_status','!=',2)
                        ->where('cancel_status','!=',1)
                        ->whereIn('commerce_booking.cg_id',$cg_ids)
                        ->whereIn('commerce_group_buy.status',[0,1,2,3])
                    ->get();

                    if(count($order)>0){
                        return response()->json(['code'=>41108, 'message'=>'Mohon maaf, silahkan selesaikan transaksi beli bareng yang sebelumnya jika ingin membuat grup lain.', 'message_en'=>'Sorry, please finish your previous group buy transaction if you wish to create another group.']);
                    }

                    $firstKey = array_key_first($cg_ids);
                    $return['cg_id'] = floatval($cg_ids[$firstKey]);
                    $basket = $this->joinGroup_exe($currentUser->user_id, $data['product_id'], $data['variant_id'], $data['count'], $return['cg_id']);
                }
            }

            $return['item'] = $basket['data'];
            $return['calculate'] = $basket['calculate'];
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'create_group_buy_failed']);
        }
    }

    public function expiredGroupBuy(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $order = DB::table('commerce_booking')
                ->where([
                    'user_id'=>$currentUser->user_id,
                    'cg_id'=>$data['cg_id']
                ])
            ->first();

            if(empty($order)){
                return response()->json(['code'=>41102,'message'=>'invalid_group_buy']);
            }

            $verdict = $this->groupValidityChecker($data['cg_id']);
            switch ($verdict) {
                case 'empty':
                    return response()->json(['code'=>41102,'message'=>'invalid_group_buy']);
                break;
                default:
                    $return = app('App\Http\Controllers\Utility\CartController')->getOrderListUser_gb_exe_limit($currentUser->user_id,0,3);
                    return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
                break;
            }
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'expire_group_buy_failed']);
        }
    }

    public function joinGroupBuy(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $order = DB::table('commerce_booking')
                ->where([
                    'user_id'=>$currentUser->user_id,
                    'cg_id'=>$data['cg_id']
                ])
            ->first();

            $continue = 1;

            if(!empty($order)){
                if($order->order_id==null){
                    $continue = 1;
                }else{
                    if($order->paid_status==2 && $order->cancel_status==1){
                        $continue = 1;
                    }else{
                        $continue = 0;
                    }
                }
            }

            if($continue==1){
                $verdict = $this->groupValidityChecker($data['cg_id']);
                switch ($verdict) {
                    case 'empty':
                        return response()->json(['code'=>41102,'message'=>'invalid_group_buy']);
                    break;
                    case 'expired':
                        return response()->json(['code'=>41103,'message'=>'group_expired']);
                    break;
                    case 'closed':
                        return response()->json(['code'=>41110,'message'=>'group_closed']);
                    break;
                    case 'verified':
                        return response()->json(['code'=>41109,'message'=>'group_verified']);
                    break;
                    default:
                        $date = date('Y-m-d H:i:s');
                        //check cart for other group id
                        $check = $this->checkGroupBuy_exe($currentUser->user_id, $data['product_id'], $data['variant_id'], $date);
                        if($check != 'empty'){
                            if($check->cg_id != $data['cg_id']){
                                if($check->status==2){
                                    return response()->json(['code'=>41108, 'message'=>'Mohon maaf, silahkan selesaikan transaksi beli bareng yang sebelumnya jika ingin bergabung ke grup lain.', 'message_en'=>'Sorry, please finish your previous group buy transaction if you wish to join another group.']);
                                }
    
                                $data['cg_id'] = $check->cg_id;
                            }
                        }

                        $valid = app('App\Http\Controllers\API\CommerceController')->checkProductVariant($data['product_id'],$data['variant_id']);
                        if($valid['code']!=200){
                            return response()->json(['code'=>$valid['code'],'message'=>$valid['message']]);
                        }

                        $check_res = app('App\Http\Controllers\API\CommerceController')->checkRestriction($data['variant_id'],$data['count']);
                        if($check_res['verdict']==FALSE){
                            $item_msg = ($check_res['restriction']>1)?'items':'item';
                            $tobe = ($check_res['restriction']>1)?'are':'is';
                            $return['message_id'] = 'Pembelian maksimal '.$check_res['restriction'].' item';
                            $return['message_en'] = 'Maximum purchase '.$tobe.' '.$check_res['restriction'].' '.$item_msg;
                            return response()->json(['code'=>4402,'message'=>'item_restriction_count_met','data'=>$return]);
                        }

                        // restrict user to join group --start
                        $query = DB::table('commerce_group_buy')
                            ->select('cg_id')    
                            ->where([
                                'product_id'=>$data['product_id'],
                                'show'=>1
                            ])
                            ->whereIn('status',[0,1,2,3])
                            //->where('expire_at','>=',$date)
                        ->get();

                        $coll_query = collect($query);
                        $cg_ids = $coll_query->pluck('cg_id')->all();

                        $order = DB::table('commerce_booking')
                            ->join('commerce_group_buy','commerce_group_buy.cg_id','=','commerce_booking.cg_id')
                            ->where([
                                'commerce_booking.user_id'=>$currentUser->user_id
                            ])
                            ->where('paid_status','!=',2)
                            ->where('cancel_status','!=',1)
                            ->whereIn('commerce_booking.cg_id',$cg_ids)
                            ->whereIn('commerce_group_buy.status',[0,1,2,3])
                        ->get();

                        if(count($order)>0){
                            return response()->json(['code'=>41108, 'message'=>'Mohon maaf, silahkan selesaikan transaksi beli bareng yang sebelumnya jika ingin bergabung ke grup lain.', 'message_en'=>'Sorry, please finish your previous group buy transaction if you wish to join another group.']);
                        }
                        // --end

                        $basket = $this->joinGroup_exe($currentUser->user_id, $data['product_id'], $data['variant_id'], $data['count'], $data['cg_id']);
                        
                        $return['cg_id'] = floatval($data['cg_id']);
                        $return['item'] = $basket['data'];
                        $return['calculate'] = $basket['calculate'];
                        $return['order_id'] = '';
                    break;
                }
            }else{
                $return['order_id'] = $order->order_id;
                return response()->json(['code'=>41107,'message'=>'Anda sudah bergabung di group ini','message_en'=>'You have already joined in this group','data'=>$return]);
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'join_group_buy_failed']);
        }
    }

    public function recommendGroupBuy(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $return = $this->getRecommendedGroup_exe($currentUser->user_id);
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);

        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'recommend_group_buy_failed']);
        }
    }

    public function showGroupBuy(Request $request, $cg_id){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $order = DB::table('commerce_booking')
                ->where([
                    'user_id'=>$currentUser->user_id,
                    'cg_id'=>$cg_id
                ])
            ->first();

            $continue = 1;

            if(!empty($order)){
                if($order->order_id==null){
                    $continue = 1;
                }else{
                    if($order->paid_status==2 && $order->cancel_status==1){
                        $continue = 1;
                    }else{
                        $continue = 0;
                    }
                }
            }

            if($continue==1){
                $verdict = $this->groupValidityChecker($cg_id);
                $basket = app('App\Http\Controllers\API\CommerceController')->getBasket_exe($currentUser->user_id, $cg_id);
                if($basket['calculate']['item_count']==0){
                    return response()->json(['code'=>41105,'message'=>'restricted_group_access']);
                }
                $return['item'] = $basket['data'];
                
                switch ($verdict) {
                    case 'empty':
                        return response()->json(['code'=>41102,'message'=>'invalid_group_buy','data'=>$return]);
                    break;
                    case 'expired':
                        return response()->json(['code'=>41103,'message'=>'group_expired','data'=>$return]);
                    break;
                    case 'closed':
                        return response()->json(['code'=>41110,'message'=>'group_closed','data'=>$return]);
                    break;
                    case 'verified':
                        return response()->json(['code'=>41109,'message'=>'group_verified','data'=>$return]);
                    break;
                    default:
                        $return['cg_id'] = floatval($cg_id);
                        $return['calculate'] = $basket['calculate'];
                        $return['order_id'] = '';
                    break;
                }
            }else{
                $return['order_id'] = $order[0]->order_id;
                return response()->json(['code'=>41107,'message'=>'Anda sudah bergabung di group ini','message_en'=>'You have already joined in this group','data'=>$return]);
            }

            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);

        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'show_group_buy_failed']);
        }
    }

    public function checkGroupBuy(Request $request, $cg_id){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $order = DB::table('commerce_booking')
                ->where([
                    'user_id'=>$currentUser->user_id,
                    'cg_id'=>$cg_id
                ])
            ->first();

            if(!empty($order)){
                if($order->order_id!=null && $order->paid_status!=2 && $order->cancel_status!=1){
                    return response()->json(['code'=>41101,'message'=>'valid_order_exist']);
                }
            }

            $verdict = $this->groupValidityChecker($cg_id);
            switch ($verdict) {
                case 'empty':
                    return response()->json(['code'=>41102,'message'=>'invalid_group_buy']);
                break;
                case 'expired':
                    return response()->json(['code'=>41103,'message'=>'group_expired']);
                break;
                case 'closed':
                    return response()->json(['code'=>41110,'message'=>'group_closed']);
                break;
                case 'verified':
                    return response()->json(['code'=>41109,'message'=>'group_verified']);
                break;
                default:
                    return response()->json(['code'=>200,'message'=>'success']);
                break;
            }

        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'check_group_buy_failed']);
        }
    }

    public function groupBuyProducts(Request $request){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                $currentUser = 'empty';
            }else{
                $currentUser = $data['user']->data;
            }

            $limit = (empty($data['limit']))?6:$data['limit'];
            $offset = (empty($data['offset']))?0:$data['offset'];

            $product = app('App\Http\Controllers\Utility\UtilityController')->listGroupBuyProducts($limit,$offset);

            $return['product'] = $product['data'];
            $return['offset'] = $product['offset'];
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'list_product_group_buy_failed']);
        }
    }

    public function shareGroupBuy(Request $request, $order_id){
        $data = $request->all();
        try {
            if(empty($data['token'])){
                $currentUser = 'empty';
            }else{
                $currentUser = $data['user']->data;
            }

            $query = DB::table('commerce_booking')
                ->where([
                    'order_id'=>$order_id,
                    'user_id'=>$currentUser->user_id,
                    'cancel_status'=>0
                ])
                ->where('paid_status','!=',2)
            ->first();

            if(empty($query)){
                return response()->json(['code'=>41101,'message'=>'invalid_order']);
            }

            $verdict = $this->groupValidityChecker($query->cg_id);
            switch ($verdict) {
                case 'empty':
                    return response()->json(['code'=>41102,'message'=>'invalid_group']);
                break;
                case 'expired':
                    return response()->json(['code'=>41103,'message'=>'group_expired']);
                break;
                case 'closed':
                    return response()->json(['code'=>41110,'message'=>'group_closed']);
                break;
                case 'verified':
                    return response()->json(['code'=>41109,'message'=>'group_verified']);
                break;
                default:
                    $item = DB::table('commerce_shopping_cart_item')
                        ->select('commerce_shopping_cart_item.count','minimi_product_variant.variant_name', 'minimi_product.product_price', 'minimi_product.product_price_gb', 'minimi_product_variant.stock_price', 'minimi_product_variant.stock_price_gb', 'minimi_product.product_id', 'minimi_product.product_uri', 'minimi_product.product_name', 'minimi_product_gallery.prod_gallery_picture as product_image')
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

                    $product = DB::table('minimi_product')->select('product_name','product_uri','product_price_gb as product_price')->where('product_id',$verdict->product_id)->first();

                    if($product->product_uri==null){
                        return response()->json(['code'=>41104,'message'=>'product_not_found']);
                    }
                    $return['whatsapp_text'] = 'Hai, yuk ikut beli bareng '.$product->product_name.' di minimi. Nanti kamu bisa mendapatkan harga cuma Rp.'.number_format($product->product_price,0,',','.').' lho, murah kan? Klik link dibawah ini : ';
                    $return['copy_link_text'] = 'Hai, yuk ikut beli bareng '.$product->product_name.' di minimi. Nanti kamu bisa mendapatkan harga cuma Rp.'.number_format($product->product_price,0,',','.').' lho, murah kan? Klik link dibawah ini : ';
                    $return['link'] = env('FRONTEND_URL').'beli-bareng/'.$product->product_uri.'?cg_id='.$query->cg_id;
                    $return['cg_id'] = $query->cg_id;
                    $return['cart_type'] = 3;
                    $return['product_uri'] = $product->product_uri;
                    
                    $cart_item = array();
                    foreach ($item as $row) {
                        if($row->product_price==0 && $row->product_price_gb==0){
                            $row->product_price = $row->stock_price;
                            $row->product_price_gb = $row->stock_price_gb;
                        }

                        $arr = array();
                        if($row->product_price_gb>0){
                            if($row->product_price>0){
                                $disc = round((($row->product_price-$row->product_price_gb)/$row->product_price)*100);
                                $arr['discount'] = $disc.'%';
                                $arr['price_before_discount'] = $row->product_price;
                                $arr['product_price'] = $row->product_price_gb;
                            }else{
                                $arr['discount'] = '5%';
                                $arr['price_before_discount'] = (1 + 0.05) * $row->product_price_gb;	
                                $arr['product_price'] = $row->product_price_gb;
                            }
                        }else{
                            $arr['discount'] = '5%';
                            $arr['price_before_discount'] = (1 + 0.05) * $row->product_price;
                            $arr['product_price'] = $row->product_price;
                        }
                        $arr['product_image'] = $row->product_image;
                        $arr['product_name'] = $row->product_name;
                        $arr['count'] = $row->count;
                        array_push($cart_item,$arr);
                    }
                    $return['cart_item'] = $cart_item;
                    return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
                break;
            }

        } catch (QueryException $ex){
            return response()->json(['code'=>4050,'message'=>'share_group_buy_failed']);
        }
    }

    /////////////////////////////////////////////////////////////////////////////////////

    /*
     * Utility Function
    */

    protected function joinGroup_exe($user_id, $product_id, $variant_id, $count, $cg_id){
        $basket = app('App\Http\Controllers\API\CommerceController')->getBasket_exe($user_id, $cg_id);
        
        $date = date('Y-m-d H:i:s');

        if($basket['calculate']['item_count']==0){
            $basket = app('App\Http\Controllers\API\CommerceController')->addToBasket_exe($user_id, $product_id, $variant_id, $count, $cg_id);
        }else{
            $check = app('App\Http\Controllers\API\CommerceController')->checkBasket($user_id, $product_id, null, $cg_id);
            if($check=='empty'){
                return response()->json(['code'=>4040,'message'=>'invalid_basket_record']);
            }

            if($check->product_id!=$product_id){
                return response()->json(['code'=>41106,'message'=>'product_not_match']);
            }

            $basket_id = $check->basket_id;
            $ins = array();
            if($check->variant_id!=$variant_id){
                $ins['variant_id'] = $variant_id;
            }
            
            if($check->count!=$count){
                $ins['count'] = $count;
            }
            
            if(count($ins)>0){
                $ins['updated_at'] = $date;
                app('App\Http\Controllers\API\CommerceController')->updateBasket($basket_id, $ins);
                $basket = app('App\Http\Controllers\API\CommerceController')->getBasket_exe($user_id, $cg_id);
            }
        }

        $cg_user_id = DB::table('commerce_group_buy')->where('cg_id',$cg_id)->value('user_id');
        if($cg_user_id==null){
            $product = DB::table('minimi_product')->select('gb_minimal','gb_expire_period')->where('product_id',$product_id)->first();
            $insert['user_id'] = $user_id;
            $insert['minimum_participant'] = $product->gb_minimal;
            $insert['total_participant'] = 1;
            $insert['show'] = 1;
            $insert['expire_at'] = date('Y-m-d H:i:s',strtotime($date.' + '.$product->gb_expire_period));
            $insert['created_at'] = $date;
            DB::table('commerce_group_buy')->where('cg_id',$cg_id)->update($insert);
        }

        return $basket;
    }

    protected function checkGroupBuy_exe($user_id, $product_id, $variant_id, $date=""){
        if($date==""){
            $date = date('Y-m-d H:i:s');
        }
        $check = DB::table('commerce_group_buy')
            ->where([
                'user_id'=>$user_id,
                'product_id'=>$product_id,
                'variant_id'=>$variant_id,
                'status'=>1
            ])
        ->first();

        if(empty($check)){
            $check = DB::table('commerce_shopping_cart')
                ->select('commerce_group_buy.*')
                ->join('commerce_group_buy','commerce_group_buy.cg_id','=','commerce_shopping_cart.cg_id')
                ->where([
                    'commerce_shopping_cart.user_id'=>$user_id,
                    'commerce_group_buy.product_id'=>$product_id
                ])
                ->where('commerce_shopping_cart.status','!=',0)
                ->whereIn('commerce_group_buy.status',[1,2,3])
                ->where('commerce_group_buy.expire_at','>=',$date)
            ->first();

            if(empty($check)){
                return 'empty';
            }

            return $check;
        }

        return $check;
    }

    protected function getRecommendedGroup_exe($user_id, $cg_ids=null){
        $cg_id = DB::table('commerce_group_buy')
            ->join('commerce_shopping_cart','commerce_group_buy.cg_id','=','commerce_shopping_cart.cg_id')
            ->where('commerce_shopping_cart.user_id','!=',$user_id);
        if($cg_ids!=null){
            $cg_id = $cg_id->whereNotIn('commerce_group_buy.cg_id',$cg_ids);
            $arr_cg = $cg_ids;
        }else{
            $arr_cg = array();
        }
        $cg_id = $cg_id->whereIn('commerce_group_buy.status',[1,2,3])
            ->orderBy('commerce_group_buy.created_at','DESC')
        ->value('commerce_group_buy.cg_id');
        if($cg_id==null){
            return array();
        }
        
        $verdict = $this->groupValidityChecker($cg_id);
        
        switch ($verdict) {
            case 'empty':
                array_push($arr_cg, $cg_id);
            break;
            case 'expired':
                array_push($arr_cg, $cg_id);
            break;
            case 'closed':
                array_push($arr_cg, $cg_id);
            break;
            case 'verified':
                array_push($arr_cg, $cg_id);
            break;
            default:
                $ret = DB::table('minimi_product')
                    ->select('product_name','product_uri','product_price','product_price_gb','minimi_product_gallery.prod_gallery_picture as product_image')
                    ->join('minimi_product_gallery', 'minimi_product_gallery.product_id', '=', 'minimi_product.product_id')
                    ->where([
                        'minimi_product.product_id'=>$verdict->product_id,
                        'minimi_product_gallery.main_poster'=>1,
                        'minimi_product_gallery.status'=>1
                    ])
                ->first();
        
                if($ret->product_price>0){
                    $disc = round((($ret->product_price-$ret->product_price_gb)/$ret->product_price)*100);
                    $verdict->discount = $disc.'%';
                    $verdict->price_before_discount = $ret->product_price;
                    $verdict->product_price = $ret->product_price_gb;
                }else{
                    $verdict->discount = '5%';
                    $verdict->price_before_discount = (1 + 0.05) * $ret->product_price_gb;	
                    $verdict->product_price = $ret->product_price_gb;
                }
                $verdict->product_uri = $ret->product_uri;
                $verdict->product_image = $ret->product_image;
                return $verdict;
            break;
        }

        return $this->getRecommendedGroup_exe($user_id, $arr_cg);
    }

    protected function groupValidityChecker($cg_id){
        $check = DB::table('commerce_group_buy')
            ->where('cg_id',$cg_id)
        ->first();

        if(empty($check)){
            return 'empty';
        }

        if($check->status==0){
            return 'expired';
        }

        if($check->status==4){
            return 'verified';
        }

        if($check->status==5){
            return 'closed';
        }

        if($check->expire_at!=null){
            $date = date('Y-m-d H:i:s');
            if($check->expire_at<=$date){
                if($check->status==1){
                    $update['user_id']=null;
                    $update['minimum_participant']=null;
                    $update['total_participant']=0;
                    $update['expire_at']=null;
                    $update['status']=5;
                }else{
                    $update['status']=0;
                }
                $update['updated_at']=$date;
                
                DB::table('commerce_group_buy')->where('cg_id',$cg_id)->update($update);

                app('App\Http\Controllers\Utility\GroupBuyController')->cancelOrderByGroup($cg_id,$date);
                return 'expired';
            }
        }

        return $check;
    }

    public function getGroupBuy($cg_id){
        $check = DB::table('commerce_group_buy')
            ->where([
                'cg_id'=>$cg_id
            ])
        ->first();

        if(empty($check)){
            return 'empty';
        }

        return $check;
    }

    public function updateGroup($cg_id, $user_id){
        $group = $this->groupValidityChecker($cg_id);

        $order = DB::table('commerce_booking')
            ->where([
                'cg_id'=>$cg_id,
                'paid_status'=>1,
                'cancel_status'=>0
            ])
        ->get();
        
        $count = count($order);
        
        $date = date('Y-m-d H:i:s');
        switch ($group) {
            case 'empty':
                return 'empty';
            break;
            case 'expired':
                return $count;
            break;
            case 'closed':
                return $count;
            break;
            case 'verified':
                return $count;
            break;
            default:
                $update['status']=$group->status;
                if($group->status==1){
                    $update['status']=2;
                }elseif ($group->status==2) {
                    if($group->minimum_participant<=$count){ 
                        $update['status']=3;
                    }
        
                    if($group->expire_at<=$date){
                        $update['status']=0;
                    }
                }
            break;
        }
        
        $update['show']=1;
        $update['total_participant']=$count;
        $update['updated_at']=$date;
        DB::table('commerce_group_buy')->where('cg_id',$cg_id)->update($update);

        if($update['status']==0){
            app('App\Http\Controllers\Utility\GroupBuyController')->cancelOrderByGroup($cg_id,$date);
        }

        return $update['total_participant'];
    }

    public function validateGroupBuy($cg_id){
        $check = DB::table('commerce_group_buy')
            ->where('cg_id',$cg_id)
        ->first();

        if($check->minimum_participant<=$check->total_participant){
            return TRUE;
        }

        $date = date('Y-m-d H:i:s');
        if($check->expire_at<=$date){
            if($check->status==1){
                $update['user_id']=null;
                $update['minimum_participant']=null;
                $update['total_participant']=0;
                $update['expire_at']=null;
                $update['status']=5;
            }else{
                $update['status']=0;
            }
            $update['updated_at']=$date;
            
            DB::table('commerce_group_buy')->where('cg_id',$cg_id)->update($update);

            app('App\Http\Controllers\Utility\GroupBuyController')->cancelOrderByGroup($cg_id,$date);
            return TRUE;
        }

        return FALSE;
    }

    public function getGroupBuyProduct($product_id){
        $date = date('Y-m-d H:i:s');
        $limit = DB::table('data_param')->where('param_tag','group_limit')->value('param_value');
        $result = DB::table('commerce_group_buy')
            ->select('minimi_user_data.photo_profile', 'minimi_user_data.fullname', 'expire_at', 'minimum_participant', 'total_participant', 'cg_id')
            ->leftJoin('minimi_user_data','minimi_user_data.user_id','=','commerce_group_buy.user_id')
            ->where([
                'product_id'=>$product_id,
                'show'=>1
            ])
            ->whereIn('status',[1,2,3])
            ->where('expire_at','>=',$date)
        ->limit($limit)->get();

        foreach ($result as $row) {
            if($row->photo_profile==null){
                $row->photo_profile='empty';
            }
            $row->delta_participant=$row->minimum_participant-$row->total_participant;
        }

        $group = json_decode(json_encode($result), true);

        return $group;
    }

    public function getGroupBuyParticipant_booking($cg_id, $user_id){
        $que = DB::table('commerce_booking')
            ->select(
                'commerce_booking.cart_id', 
                'commerce_booking.booking_id',
                'commerce_booking.order_id',
                'commerce_booking.admin_verified',
                'commerce_booking.delivery_verified',
                'commerce_booking.received',
                'commerce_booking.received_by',
                'commerce_booking.received_at',
                'commerce_booking.paid_status',
                'commerce_booking.cancel_status',
                'commerce_shopping_cart.user_id',
                'commerce_shopping_cart.total_weight',
                'commerce_voucher.voucher_code',
                'minimi_user_data.fullname',
                'minimi_user_data.photo_profile'
            )
            ->join('commerce_shopping_cart','commerce_booking.cart_id','=','commerce_shopping_cart.cart_id')
            ->join('minimi_user_data','minimi_user_data.user_id','=','commerce_shopping_cart.user_id')
            ->leftJoin('commerce_voucher','commerce_shopping_cart.voucher_id','=','commerce_voucher.voucher_id')
            ->whereIn('paid_status',[0,1,3,4])
            ->where('cancel_status','!=',1)
            ->where([
                'commerce_booking.cg_id'=>$cg_id,
                'commerce_booking.user_id'=>$user_id
            ])
        ->get();
        $que = json_decode(json_encode($que), true);
        
        $query = DB::table('commerce_booking')
            ->select(
                'commerce_booking.cart_id', 
                'commerce_booking.booking_id',
                'commerce_booking.order_id',
                'commerce_booking.admin_verified',
                'commerce_booking.delivery_verified',
                'commerce_booking.received',
                'commerce_booking.received_by',
                'commerce_booking.received_at',
                'commerce_booking.paid_status',
                'commerce_booking.cancel_status',
                'commerce_shopping_cart.user_id',
                'commerce_shopping_cart.total_weight',
                'commerce_voucher.voucher_code',
                'minimi_user_data.fullname',
                'minimi_user_data.photo_profile'
            )
            ->join('commerce_shopping_cart','commerce_booking.cart_id','=','commerce_shopping_cart.cart_id')
            ->join('minimi_user_data','minimi_user_data.user_id','=','commerce_shopping_cart.user_id')
            ->leftJoin('commerce_voucher','commerce_shopping_cart.voucher_id','=','commerce_voucher.voucher_id')
            ->where([
                'commerce_booking.cg_id'=>$cg_id
            ])
            ->where('commerce_booking.user_id','!=',$user_id)
            ->whereIn('paid_status',[0,1,3,4])
            ->where('cancel_status','!=',1)
        ->get();
        $query = json_decode(json_encode($query), true);

        $result = array_merge($que, $query);
        $return = array();
        foreach($result as $row){
            $arr = array();
            $arr['booking_id'] = $row['booking_id'];
            $arr['order_id'] = $row['order_id'];
            if($row['photo_profile']==null){
                $arr['photo_profile'] = 'empty';
            }else{
                $arr['photo_profile'] = $row['photo_profile'];
            }
            $arr['fullname'] = $row['fullname'];

            if($row['user_id']==$user_id){
                $arr['fullname'] .= ' (Kamu)';
            }
            
            $name = app('App\Http\Controllers\API\AuthController')->splitName($row['fullname']);
            $arr['first_name'] = $name['first_name'];
            $arr['last_name'] = $name['last_name'];
            if($row['paid_status']!=null){
                switch ($row['paid_status']) {
                    case 0:
                        $arr['status'] = 'unpaid';
                    break;
                    case 1:
                        $arr['status'] = 'paid';
                        if($row['admin_verified']==1){
                            $arr['status'] = 'paid';
                        }
                        if($row['delivery_verified']==1){
                            $arr['status'] = 'paid';
                        }
                        if($row['received']==1){
                            $arr['status'] = 'paid';
                        }
                    break;
                    case 2:
                        $arr['status'] = 'cancelled';
                    break;
                    case 4:
                        $arr['status'] = 'unpaid';
                    break;    
                    default:
                        $arr['status'] = 'unpaid';
                    break;
                }
            }else{
                $arr['status'] = 'inprogress';
            }

            array_push($return, $arr);
        }

        return $return;
    }

    public function getGroupBuyParticipant($cg_id, $user_id){
        $que = DB::table('commerce_shopping_cart')
            ->select(
                'commerce_booking.cart_id', 
                'commerce_booking.booking_id',
                'commerce_booking.order_id',
                'commerce_booking.admin_verified',
                'commerce_booking.delivery_verified',
                'commerce_booking.received',
                'commerce_booking.received_by',
                'commerce_booking.received_at',
                'commerce_booking.paid_status',
                'commerce_booking.cancel_status',
                'commerce_shopping_cart.user_id',
                'commerce_shopping_cart.total_weight',
                'commerce_voucher.voucher_code',
                'minimi_user_data.fullname',
                'minimi_user_data.photo_profile'
            )
            ->join('minimi_user_data','minimi_user_data.user_id','=','commerce_shopping_cart.user_id')
            ->leftJoin('commerce_booking','commerce_booking.cart_id','=','commerce_shopping_cart.cart_id')
            ->leftJoin('commerce_voucher','commerce_shopping_cart.voucher_id','=','commerce_voucher.voucher_id')
            ->where([
                'commerce_shopping_cart.cg_id'=>$cg_id,
                'commerce_shopping_cart.user_id'=>$user_id
            ])
        ->get();
        $que = json_decode(json_encode($que), true);
        
        $query = DB::table('commerce_shopping_cart')
            ->select(
                'commerce_booking.cart_id', 
                'commerce_booking.booking_id',
                'commerce_booking.order_id',
                'commerce_booking.admin_verified',
                'commerce_booking.delivery_verified',
                'commerce_booking.received',
                'commerce_booking.received_by',
                'commerce_booking.received_at',
                'commerce_booking.paid_status',
                'commerce_booking.cancel_status',
                'commerce_shopping_cart.user_id',
                'commerce_shopping_cart.total_weight',
                'commerce_voucher.voucher_code',
                'minimi_user_data.fullname',
                'minimi_user_data.photo_profile'
            )
            ->join('minimi_user_data','minimi_user_data.user_id','=','commerce_shopping_cart.user_id')
            ->leftJoin('commerce_booking','commerce_booking.cart_id','=','commerce_shopping_cart.cart_id')
            ->leftJoin('commerce_voucher','commerce_shopping_cart.voucher_id','=','commerce_voucher.voucher_id')
            ->where([
                'commerce_shopping_cart.cg_id'=>$cg_id
            ])
            ->where('commerce_shopping_cart.user_id','!=',$user_id)
        ->get();
        $query = json_decode(json_encode($query), true);

        $result = array_merge($que, $query);
        $return = array();
        foreach($result as $row){
            if($row['paid_status']!=2 && $row['cancel_status']!=1){
                $arr = array();
                $arr['booking_id'] = $row['booking_id'];
                $arr['order_id'] = $row['order_id'];
                if($row['photo_profile']==null){
                    $arr['photo_profile'] = 'empty';
                }else{
                    $arr['photo_profile'] = $row['photo_profile'];
                }
                $arr['fullname'] = $row['fullname'];

                if($row['user_id']==$user_id){
                    $arr['fullname'] .= ' (Kamu)';
                }
                
                $name = app('App\Http\Controllers\API\AuthController')->splitName($row['fullname']);
                $arr['first_name'] = $name['first_name'];
                $arr['last_name'] = $name['last_name'];
                if($row['paid_status']!=null){
                    switch ($row['paid_status']) {
                        case 0:
                            $arr['status'] = 'unpaid';
                        break;
                        case 1:
                            $arr['status'] = 'paid';
                            if($row['admin_verified']==1){
                                $arr['status'] = 'paid';
                            }
                            if($row['delivery_verified']==1){
                                $arr['status'] = 'paid';
                            }
                            if($row['received']==1){
                                $arr['status'] = 'paid';
                            }
                        break;
                        case 2:
                            $arr['status'] = 'cancelled';
                        break;
                        case 4:
                            $arr['status'] = 'unpaid';
                        break;    
                        default:
                            $arr['status'] = 'unpaid';
                        break;
                    }
                }else{
                    $arr['status'] = 'inprogress';
                }
    
                array_push($return, $arr);
            }
        }

        return $return;
    }
}