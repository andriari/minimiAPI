<?php

namespace App\Http\Controllers\Utility;

use App\Http\Controllers\Controller;
use App\Veritrans\Midtrans;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

use DB;

class PaymentController extends Controller
{
	public function __construct()
	{
		Midtrans::$serverKey = env('MIDTRANS_SERVER_KEY');
		Midtrans::$isProduction = env('PROD');
		date_default_timezone_set("Asia/Jakarta");
	}

	public function listPaymentChannel(Request $request){
		try {
			$data = $request->all();
			if (empty($data['token'])) {
				return response()->json(['code' => 4034, 'message' => 'login_to_continue']);
			}
	
			$return = app('App\Http\Controllers\Utility\FaspayController')->getPaymentChannel_exe();
	
			return response()->json(['code'=>200,'message'=>'success','data'=>$return]);
		} catch (QueryException $ex) {
			return response()->json(['code'=>4050,'message'=>'list_payment_channel_failed']);
		}
	}

	public function midtransToken(Request $request)
	{
		$data = $request->all();
		$order_id = $data['order_id'];
		if (empty($data['token'])) {
			return response()->json(['code' => 4034, 'message' => 'login_to_continue']);
		}
		
		$user = $data['user']->data;

		// Get Order Details
		$order_details = app('App\Http\Controllers\Utility\CartController')->getOrderDetail_exe($order_id, $user->user_id);

		switch ($order_details) {
			case 'order_not_found':
				return response()->json(['code'=>4901,'message'=>$order_details]);
			break;
			case 'order_invalid':
				return response()->json(['code'=>4902,'message'=>$order_details]);
			break;
			default:
				$transaction_type = $order_details->transaction_type;
			break;
		}

		$midtrans = new Midtrans;
		$transaction_details = array(
			'order_id'      => $order_id,
			'gross_amount'  => $order_details->total_amount
		);

		// Populate items
		$items = [];

		if($transaction_type==1 || $transaction_type==3){
			foreach ($order_details->shopping_cart_item as $row) {
				$product_name = $row->product_name;
				if (strlen($row->product_name) > 50) {
					$product_name = substr($row->product_name, 0, 47) . '...';
				}
	
				$item = array(
					'price'     => $row->price,
					'quantity'  => $row->count,
					'name'      => $product_name
				);
				array_push($items, $item);
			}
		}else{
			$row = $order_details->shopping_cart_item[0];
			$item = array(
				'price'     => $row->price,
				'quantity'  => 1,
				'name'      => $row->product_name
			);
			array_push($items, $item);
		}

		// Additional Charges
		$delivery_charges = array(
			'price'     => $order_details->delivery_amount,
			'quantity'  => 1,
			'name'      => "Delivery Charges"
		);
		array_push($items, $delivery_charges);

		$insurance_charges = array(
			'price'     => $order_details->insurance_amount,
			'quantity'  => 1,
			'name'      => "Insurance Charges"
		);
		array_push($items, $insurance_charges);

		// Check if there's discount
		if ($order_details->discount_amount > 0) {
			$discount_amount = -1 * abs($order_details->discount_amount);
			$discount = array(
				'price'     => $discount_amount,
				'quantity'  => 1,
				'name'      => "Discount"
			);
			array_push($items, $discount);
		}

		if ($order_details->delivery_discount_amount > 0) {
			$delivery_discount_amount = -1 * abs($order_details->delivery_discount_amount);
			$discount = array(
				'price'     => $delivery_discount_amount,
				'quantity'  => 1,
				'name'      => "Delivery Discount"
			);
			array_push($items, $discount);
		}

		// Populate customer's billing address
		$billing_address = array(
			'first_name'    => $order_details->first_name,
			'last_name'     => "",
			'address'       => substr($order_details->address_detail, 0, 62) . '...',
			'city'          => $order_details->address_city_name,
			'postal_code'   => $order_details->address_postal_code,
			'phone'         => $order_details->address_phone,
			'country_code'  => 'IDN'
		);

		// Populate customer's shipping address
		$shipping_address = array(
			'first_name'    => $order_details->first_name,
			'last_name'     => $order_details->last_name,
			'address'       => substr($order_details->address_detail, 0, 62) . '...',
			'city'          => $order_details->address_city_name,
			'postal_code'   => $order_details->address_postal_code,
			'phone'         => $order_details->address_phone,
			'country_code'  => 'IDN'
		);

		// Populate customer's Info
		$customer_details = array(
			'first_name'    => $order_details->first_name,
			'last_name'     => $order_details->last_name,
			'email'         => trim($order_details->email),
			'phone'         => $order_details->phone,
			'billing_address' => $billing_address,
			'shipping_address' => $shipping_address
		);

		// Data yang akan dikirim untuk request redirect_url.
		$credit_card = array(
			'secure' => true,
			'installment' => array(
				'required' => false,
				'terms' => array(
					'bni' => [3, 6]
				)
			)
		);
		//ser save_card true to enable oneclick or 2click
		//$credit_card['save_card'] = true;

		$time = time();
		$custom_expiry = array(
			'start_time' => date("Y-m-d H:i:s O", $time),
			'unit'       => 'hour',
			'duration'   => 24
		);

		$transaction_data = array(
			'transaction_details' => $transaction_details,
			'item_details'       => $items,
			'customer_details'   => $customer_details,
			'credit_card'        => $credit_card,
			'expiry'             => $custom_expiry,
			'enabled_payments'   => array("credit_card", "akulaku", "mandiri_clickpay", "cimb_clicks", "bca_klikbca", "bca_klikpay", "bri_epay", "telkomsel_cash", "echannel", "bbm_money", "xl_tunai", "indosat_dompetku", "mandiri_ecash", "permata_va", "bca_va", "other_va", "kioson", "Indomaret", "gopay")
		);
		//return response()->json($transaction_data);

		try {
			// Check payment_data table
			$this->checkPaymentData($order_id, $order_details->total_amount);
			$snap_token = $midtrans->getSnapToken($transaction_data);
			$return['token'] = $snap_token;
			DB::table('commerce_booking')->where('order_id',$order_id)->update([
				'payment_vendor'=>'midtrans',
				'payment_method'=>'midtrans',
				'pg_code'=>'',
				'updated_at'=>date('Y-m-d H:i:s')
			]);
			return response()->json(['code' => 200, 'message' => 'success', 'data' => $return]);
		} catch (Exception $e) {
			return response()->json(['code' => 4075, 'message' => "token_request_failed"]);
			//return $e->getMessage;
		}
	}

	public function midtransNotify()
	{
		$midtrans = new Midtrans;
		$json_result = file_get_contents('php://input');
		$result = json_decode($json_result);
		if ($result) {
			$notif = $midtrans->status($result->order_id);
		}
		if (!in_array($result->status_code, array(200, 201, 202))) {
			return $notif->status_message;
		}
		$transaction = $result->transaction_status;
		$type = $result->payment_type;
		$order_id = $result->order_id;
		$fraud = (empty($result->fraud_status))?null:$result->fraud_status;
		$date = date('Y-m-d H:i:s');

		$update['transaction_time'] = $result->transaction_time;
		$update['transaction_status'] = $result->transaction_status;
		$update['transaction_id'] = $result->transaction_id;
		$update['status_message'] = $result->status_message;
		$update['status_code'] = $result->status_code;
		$update['payment_type'] = $result->payment_type;
		$update['merchant_id'] = $result->merchant_id;
		$update['vt_gross_amount'] = $result->gross_amount;
		$update['store'] = (empty($result->store))?null:$result->store;
		$update['fraud_status'] = (empty($result->fraud_status))?null:$result->fraud_status;
		$update['masked_card'] = (empty($result->masked_card))?null:$result->masked_card;
		$update['biller_code'] = (empty($result->biller_code))?null:$result->biller_code;
		$update['bill_key'] = (empty($result->bill_key))?null:$result->bill_key;
		$update['settlement_time'] = (empty($result->settlement_time))?null:$result->settlement_time;
		$update['signature_key'] = (empty($result->signature_key))?null:$result->signature_key;
		$update['approval_code'] = (empty($result->approval_code))?null:$result->approval_code;
		$update['currency'] = (empty($result->currency))?null:$result->currency;
		if(isset($result->permata_va_number)){
			$update['virtual_account'] = $result->permata_va_number;
			$update['bank'] = 'permata';
		}else{
			$update['virtual_account'] = (empty($result->va_numbers))?null:$result->va_numbers[0]->va_number;
			$update['bank'] = (empty($result->va_numbers))?null:$result->va_numbers[0]->bank;
		}
		$update['eci'] = (empty($result->eci))?null:$result->eci;
		$update['updated_at'] = $date;
		$update['log'] = $json_result;
		
		// Update Payment Data
		DB::table('payment_data')
			->where('order_id', $result->order_id)
			->update($update);

		if ($transaction == 'capture') {
			// For credit card transaction, we need to check whether transaction is challenge by FDS or not
			if ($type == 'credit_card') {
				if ($fraud == 'challenge') {
					// TODO set payment status in merchant's database to 'Challenge by FDS'
					// TODO merchant should decide whether this transaction is authorized or not in MAP
					echo "Transaction order_id: " . $order_id . " is challenged by FDS";
					DB::table('commerce_booking')
						->where('order_id', $result->order_id)
						->update([
							'paid_status' => 2,
							'cancel_status' => 1,
							'updated_at' => $date
						]);
					$this->payment_cancel($result->order_id);
				} else {
					// TODO set payment status in merchant's database to 'Success'
					echo "Transaction order_id: " . $order_id . " successfully captured using " . $type;
					DB::table('commerce_booking')
						->where('order_id', $result->order_id)
						->update([
							'paid_status' => 1,
							'cancel_status' => 0,
							'updated_at' => $date
						]);
					$this->payment_complete($result->order_id);
				}
			}
		} else if ($transaction == 'settlement') {
			// TODO set payment status in merchant's database to 'Settlement'
			DB::table('commerce_booking')
				->where('order_id', $result->order_id)
				->update([
					'paid_status' => 1,
					'cancel_status' => 0,
					'updated_at' => $date
				]);
			$this->payment_complete($result->order_id);
			echo "Transaction order_id: " . $order_id . " successfully transfered using " . $type;
		} else if ($transaction == 'pending') {
			// TODO set payment status in merchant's database to 'Pending'
			echo "Waiting customer to finish transaction order_id: " . $order_id . " using " . $type;
			DB::table('commerce_booking')
				->where('order_id', $result->order_id)
				->update([
					'paid_status' => 0,
					'cancel_status' => 0,
					'updated_at' => $date
				]);
			app('App\Http\Controllers\Utility\MailController')->sendWaitingPaymentEmailPhys($result->order_id,$date);
		} else if ($transaction == 'deny') {
			// TODO set payment status in merchant's database to 'Denied'
			echo "Payment using " . $type . " for transaction order_id: " . $order_id . " is denied.";
			DB::table('commerce_booking')
				->where('order_id', $result->order_id)
				->update([
					'paid_status' => 2,
					'cancel_status' => 1,
					'updated_at' => $date
				]);
			$this->payment_cancel($result->order_id);
		} else if ($transaction == 'expire') {
			echo "Payment using " . $type . " for transaction order_id: " . $order_id . " is expired.";
			DB::table('commerce_booking')
				->where('order_id', $result->order_id)
				->update([
					'paid_status' => 2,
					'cancel_status' => 1,
					'updated_at' => $date
				]);
			$this->payment_cancel($result->order_id);
		}
	}

	public function payment_complete_test($order_id){
		$date = date('Y-m-d H:i:s');
		DB::table('commerce_booking')
			->where('order_id', $order_id)
			->update([
				'paid_status' => 1,
				'cancel_status' => 0,
				'updated_at' => $date
			]);
		$this->payment_complete($order_id);
	}

	public function payment_complete($order_id)
	{
		$order = DB::table('commerce_booking')
			->select('commerce_booking.*', 'fullname', 'email')
			->leftJoin('minimi_user_data', 'commerce_booking.user_id', '=', 'minimi_user_data.user_id')
			->where('commerce_booking.order_id', $order_id)
		->first();
		if (empty($order)) {
			return response()->json(['code' => 404, 'message' => 'Order Not Found']);
		}
		if ($order->cancel_status != 0) {
			return response()->json(['code' => 400, 'message' => 'Order Cancelled']);
		}
		if ($order->paid_status != 1) {
			return response()->json(['code' => 400, 'message' => 'Order not yet paid']);
		}

		$date = date('Y-m-d H:i:s');
		$voucher_id = DB::table('commerce_shopping_cart')->where('cart_id',$order->cart_id)->value('voucher_id');
		if($voucher_id!=null){
			$update['status'] = 1;
			$update['updated_at'] = $date;
			DB::table('commerce_voucher_usage')->where(['voucher_id'=>$voucher_id,'user_id'=>$order->user_id])->update($update);

			$update_voucher['usage'] = app('App\Http\Controllers\Utility\VoucherController')->countVoucherUsage($voucher_id, $order->user_id);
            $update_voucher['updated_at'] = $date;
            DB::table('commerce_voucher')->where('voucher_id',$voucher_id)->update($update_voucher);
		}

		if($order->transaction_type==2){
			$qty = 1;
        }else{
            $item = DB::table('commerce_shopping_cart_item')
                ->select('commerce_shopping_cart_item.count')
                ->where([
                    'cart_id'=>$order->cart_id
                ])
			->get();
			$qty = 0;
			foreach ($item as $row){
				$qty += $row->count;
			}
		}

		// Send email to customer
		$data['data']['name'] = $order->fullname;
		$data['data']['order'] = $order_id;
		$data['receiver_email'] = $order->email;
		$data['template'] = "emails.payment_complete";
		$data['subject'] = "Payment Complete - ".$order_id;
		app('App\Http\Controllers\Utility\UtilityController')->sendMail($data);
		
		// Send email to admin
		$data2['data']['name'] = $order->fullname;
		$data2['data']['order'] = $order_id;
		$data2['receiver_email'] = env('ADMIN_MAIL');
		$data2['template'] = "emails.payment_complete";
		$data2['subject'] = "Customer Payment Complete - ".$order_id;
		app('App\Http\Controllers\Utility\UtilityController')->sendMail($data2);

		$actions = array();
		if($order->transaction_type==3){
			$total_participant = app('App\Http\Controllers\API\GroupBuyController')->updateGroup($order->cg_id,$order->user_id);
			$act['action'] = "bb_purchased";
			$act['attributes']['group_id'] = $order->cg_id;
			$act['attributes']['total_participant'] = $total_participant;
			$act['attributes']['order_id'] = $order_id;
			$act['attributes']['order_status'] = 'paid';
			$act['attributes']['name'] = $order->fullname;
			$act['attributes']['qty'] = $qty;
			$act['attributes']['insurance'] = ($order->insurance_amount>0)?'yes':'no';
			$act['attributes']['grand_total'] = $order->total_amount;
			$act['attributes']['payment_date'] = $date;
			$act['attributes']['delivery'] = $order->delivery_vendor.'-'.$order->delivery_service;
			array_push($actions,$act);
		}else{
			$act['action'] = "ecom_purchased";
			$act['attributes']['order_id'] = $order_id;
			$act['attributes']['order_status'] = 'paid';
			$act['attributes']['name'] = $order->fullname;
			$act['attributes']['qty'] = $qty;
			$act['attributes']['insurance'] = ($order->insurance_amount>0)?'yes':'no';
			$act['attributes']['grand_total'] = $order->total_amount;
			$act['attributes']['payment_date'] = $date;
			$act['attributes']['delivery'] = $order->delivery_vendor.'-'.$order->delivery_service;
			array_push($actions,$act);
		}
		
		app('App\Http\Controllers\Utility\UtilityController')->storeEventMoengage($order->user_id,$actions);
		app('App\Http\Controllers\Utility\UtilityController')->storeEventAppsflyer($order->user_id,$actions);

		return response()->json(['code' => 200, 'message' => 'success']);
	}

	public function cronPaymentCancellation(){
		$date = date('Y-m-d H:i:s');
		$expire_date = date('Y-m-d H:i:s', strtotime($date.'-3 days'));
		
		$order = DB::table('commerce_booking')
			->select('order_id','paid_status','created_at','updated_at')
			->whereIn('paid_status',[0,3,4])
			->where('created_at','<',$expire_date)
		->limit(50)->get();

		foreach ($order as $row) {
			DB::table('commerce_booking')
				->where('order_id', $row->order_id)
			->update([
				'paid_status' => 2,
				'cancel_status' => 1,
				'updated_at' => $date
			]);
			$this->payment_cancel($row->order_id);
		}
	}

	public function payment_cancel($order_id)
	{
		$order = DB::table('commerce_booking')
			->select('commerce_booking.*', 'fullname', 'email' , 'transaction_id')
			->leftJoin('minimi_user_data', 'commerce_booking.user_id', '=', 'minimi_user_data.user_id')
			->leftJoin('payment_data', 'commerce_booking.order_id', '=', 'payment_data.order_id')
			->where('commerce_booking.order_id', $order_id)
		->first();
		if (empty($order)) {
			return response()->json(['code' => 404, 'message' => 'Order Not Found']);
		}

		$voucher_id = DB::table('commerce_shopping_cart')->where('cart_id',$order->cart_id)->value('voucher_id');
		if($voucher_id!=null){
			$update_voucher['usage'] = app('App\Http\Controllers\Utility\VoucherController')->countVoucherUsage($voucher_id, $order->user_id);
            $update_voucher['updated_at'] = date('Y-m-d H:i:s');
            DB::table('commerce_voucher')->where('voucher_id',$voucher_id)->update($update_voucher);
		}

		if($order->payment_vendor == 'midtrans'){
			$midtrans = new Midtrans;
			$verdict = $midtrans->cancel($order_id);
		}elseif($order->payment_vendor == 'faspay'){
			app('App\Http\Controllers\Utility\FaspayController')->paymentCancel($order->transaction_id, $order_id, 'Payment Cancel');
		}

		echo "Transaction order_id: " . $order_id . " cancelled";
	}

	public function midtransCallback(Request $request)
	{
		$data = $request->all();
		try {
			if (empty($data['token'])) {
				return response()->json(['code' => 4034, 'message' => 'login_to_continue']);
			} else {
				$currentUser = $data['user']->data;
			}

			$check = DB::table('commerce_booking')->where([
				'order_id' => $data['order_id'],
				'user_id' => $currentUser->user_id
			])->first();

			if (empty($check)) {
				return response()->json(['code' => 4701, 'message' => 'transaction_not_found']);
			}

			if ($check->paid_status == 3 || $check->paid_status == 0) {
				$date = date('Y-m-d H:i:s');
	
				DB::table('commerce_booking')->where('order_id', $data['order_id'])->update([
					'paid_status' => 4,
					'updated_at' => $date
				]);

				if($check->paid_status == 3){
					DB::table('payment_data')->where('order_id', $data['order_id'])->update([
						'transaction_status' => 'waiting for payment',
						'updated_at' => $date
					]);
				}
			}

			return response()->json(['code' => 200, 'message' => 'success']);
		} catch (Exception $e) {
			return response()->json(['code' => 500, 'message' => $e->getMessage]);
			//return $e->getMessage;
		}
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/**
	 * Utility Function
	 **/

	public function checkPaymentData($order_id, $price, $payment_gateway = 0)
	{
		$query = DB::table('payment_data')->where('order_id', $order_id)->first();
		if (empty($query)) {
			DB::table('payment_data')->insert([
				'order_id'      => $order_id,
				'gross_amount'  => $price,
				'payment_gateway'  => $payment_gateway,
				'updated_at' => date("Y-m-d H:i:s")
			]);
		} else {
			DB::table('payment_data')->where('order_id', $order_id)->update([
				'gross_amount'  => $price,
				'payment_gateway'  => $payment_gateway,
				'updated_at' => date("Y-m-d H:i:s")
			]);
		}
		return 'success';
	}
}
