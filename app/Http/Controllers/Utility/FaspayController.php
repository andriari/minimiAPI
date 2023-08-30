<?php

namespace App\Http\Controllers\Utility;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

use DB;

class FaspayController extends Controller
{
	public function __construct()
	{
		date_default_timezone_set("Asia/Jakarta");
	}

	public function faspayToken(Request $request)
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
				return response()->json(['code' => 4901, 'message' => $order_details]);
				break;
			case 'order_invalid':
				return response()->json(['code' => 4902, 'message' => $order_details]);
				break;
			default:
				$transaction_type = $order_details->transaction_type;
				break;
		}

		$now = date('Y-m-d H:i:s');
		// Mandatory Parameter
		// Populate items
		$transaction_data = $this->populateItem($order_details, $data['pg_code']);
		// Misc
		$signature = $this->createSignature($order_details->order_id);
		$transaction_data['request'] = "Transmisi Info Detil Pembelian";
		$transaction_data['merchant_id'] = env('FASPAY_MERCHANT_ID');
		$transaction_data['merchant'] = "Minimi.co.id";
		$transaction_data['payment_channel'] = $data['pg_code'];
		$transaction_data['pay_type'] = 01;
		$transaction_data['terminal'] = 10;
		$transaction_data['signature'] = $signature;

		// Populate customer's billing info
		$transaction_data['bill_no'] = $order_details->order_id;
		$transaction_data['bill_date'] = $order_details->created_at;
		$transaction_data['bill_expired'] = date("Y-m-d H:i:s", strtotime($now . "+1 day"));
		$transaction_data['bill_desc'] = "Pembayaran " . $order_details->order_id;
		$transaction_data['bill_currency'] = "IDR";
		// $transaction_data['bill_total'] = (string) number_format($order_details->total_amount,2,"","");

		// Populate customer's Info
		$transaction_data['cust_no'] = $user->user_id;
		$transaction_data['cust_name'] = $order_details->fullname;
		$transaction_data['msisdn'] = $order_details->address_phone;
		$transaction_data['email'] = trim($order_details->email);

		// Optional Parameter
		// Populate customer's billing address
		$transaction_data['billing_address'] = strlen($order_details->address_detail > 200) ? substr($order_details->address_detail, 0, 196) . '...' : $order_details->address_detail;
		$transaction_data['billing_address_city'] = $order_details->address_city_name;
		$transaction_data['billing_address_poscode'] = $order_details->address_postal_code;
		$transaction_data['billing_address_country_code'] = $order_details->address_country_code;

		// Populate customer's shipping address
		$transaction_data['receiver_name_for_shipping'] = $order_details->fullname;
		$transaction_data['shipping_address'] = strlen($order_details->address_detail > 200) ? substr($order_details->address_detail, 0, 196) . '...' : $order_details->address_detail;
		$transaction_data['shipping_address_city'] = $order_details->address_city_name;
		$transaction_data['shipping_address_poscode'] = $order_details->address_postal_code;
		$transaction_data['shipping_address_country_code'] = $order_details->address_country_code;

		// return response()->json($transaction_data);

		try {
			// Check payment_data table
			app('App\Http\Controllers\Utility\PaymentController')->checkPaymentData($order_id, $order_details->total_amount, 5);
			$options['json'] = $transaction_data;

			$endpoint = env('FASPAY_API_ENDPOINT') . 'cvr/300011/10'; // URL Endpoint Post Data
			$return = app('App\Http\Controllers\Utility\UtilityController')->sendReqFaspay('POST', $endpoint, $options);

			if ($return->response_code != 00)
				return response()->json(['code'=>400, 'message'=>'Tidak bisa melanjutkan proses pembayaran. Mohon lapor kepada admin.']);

			$this->setFaspayLink($order_id, $return->redirect_url);
			$payment_method = (empty($data['payment_method']))?$this->getPaymentMethod($data['pg_code']):$data['payment_method'];
			DB::table('commerce_booking')->where('order_id',$order_id)->update([
				'payment_vendor'=>'faspay',
				'payment_method'=>$payment_method,
				'pg_code'=>$data['pg_code'],
				'updated_at'=>date('Y-m-d H:i:s')
			]);
			return response()->json(['code' => 200, 'message' => 'success', 'data' => $return]);
		} catch (Exception $e) {
			return response()->json(['code' => 4075, 'message' => "token_request_failed"]);
			//return $e->getMessage;
		}
	}

	public function setFaspayLink($order_id, $link)
	{
		DB::table('payment_data')->where('order_id', $order_id)->update([
			'faspay_link'  => $link
		]);
		return 'success';
	}

	public function createSignature($order_id = "")
	{
		$faspay_user_id = env('FASPAY_USER_ID');
		$faspay_password = env('FASPAY_PASSWORD');

		return sha1(md5($faspay_user_id . $faspay_password . $order_id));
	}

	public function paymentCancel($transaction_id, $order_id, $desc){
		$signature = $this->createSignature();

		$transaction_data['request'] = "Canceling Payment";
		$transaction_data['trx_id_id'] = $transaction_id;
		$transaction_data['merchant_id'] = env('FASPAY_MERCHANT_ID');
		$transaction_data['merchant'] = "Minimi.co.id";
		$transaction_data['bill_no'] = $order_id;
		$transaction_data['payment_cancel'] = $desc;
		$transaction_data['signature'] = $signature;

		$options['json'] = $transaction_data;
		$endpoint = env('FASPAY_API_ENDPOINT') . 'cvr/100005/10'; // URL Endpoint Payment Cancel
		$return = app('App\Http\Controllers\Utility\UtilityController')->sendReqFaspay('POST', $endpoint, $options);
		if ($return->response_error[0]->response_code == 84 || $return->response_error[0]->response_code == 85) return 'not_found';
	}

	public function getPaymentChannel_exe()
	{
		$channel = array();
		$channel[0]['payment_vendor'] = 'midtrans';
		$channel[0]['payment_method'] = 'midtrans';
		$channel[0]['pg_code'] = '';
		$channel[0]['pg_name'] = 'Midtrans';
		$channel[0]['pg_desc'] = 'BCA, Kartu Kredit';
		$channel[0]['pg_group'] = 'other';
		$channel[0]['pg_icon'] = 'https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/bank_icon/midtrans_icon_med.png';
		$channel[0]['pg_icon_sml'] = 'https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/bank_icon/midtrans_icon_sml.png';
		$return = $this->curlFaspay();
		if ($return != 'not_found') {
			foreach ($return as $row){
				$data = array();
				if($row->pg_code=='801' || $row->pg_code=='708' || $row->pg_code=='801' || $row->pg_code=='408' || $row->pg_code=='402'){
					$exp = explode(' ', $row->pg_name);
					$payment_method = strtolower(str_replace(" ", "_", trim($exp[0])));
					$data['payment_vendor'] = 'faspay';
					$data['payment_method'] = $payment_method;
					$data['pg_code'] = $row->pg_code;
					$data['pg_name'] = 'Virtual Account '.$exp[0];
					$data['pg_desc'] = '';
					$data['pg_group'] = 'va';
					$data['pg_icon'] = 'https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/bank_icon/'.$payment_method.'_icon_med.png';
					$data['pg_icon_sml'] = 'https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/bank_icon/'.$payment_method.'_icon_sml.png';
					array_push($channel, $data);
				}elseif($row->pg_code=='812' || $row->pg_code=='819'){
					$exp = explode(' ', $row->pg_name);
					$payment_method = strtolower(str_replace(" ", "_", trim($exp[0])));
					$data['payment_vendor'] = 'faspay';
					$data['payment_method'] = $payment_method;
					$data['pg_code'] = $row->pg_code;
					$data['pg_name'] = $exp[0];
					$data['pg_desc'] = '';
					$data['pg_group'] = 'wallet';
					$data['pg_icon'] = 'https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/bank_icon/'.$payment_method.'_icon_med.png';
					$data['pg_icon_sml'] = 'https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/bank_icon/'.$payment_method.'_icon_sml.png';
					array_push($channel, $data);
				}elseif($row->pg_code=='820'){
					$exp = explode(' ', $row->pg_name);
					$payment_method = strtolower(str_replace(" ", "_", trim($exp[0])));
					$data['payment_vendor'] = 'faspay';
					$data['payment_method'] = $payment_method;
					$data['pg_code'] = $row->pg_code;
					$data['pg_name'] = $exp[0];
					$data['pg_desc'] = '';
					$data['pg_group'] = 'other';
					$data['pg_icon'] = 'https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/bank_icon/'.$payment_method.'_icon_med.png';
					$data['pg_icon_sml'] = 'https://minimi-bucket.s3-ap-southeast-1.amazonaws.com/public/bank_icon/'.$payment_method.'_icon_sml.png';
					array_push($channel, $data);
				}
			}
		}

		return $channel;
	}

	public function getPaymentMethod($pg_code){
		switch ($pg_code) {
			case 708:
				return 'danamon';
				break;
			case 408:
				return 'maybank';
				break;
			case 812:
				return 'ovo';
				break;
			case 402:
				return 'permata';
				break;
			case 819:
				return 'dana';
				break;
			case 820:
				return 'indodana';
				break;
			case 801:
				return 'bni';
				break;
			default:
				return 'midtrans';
				break;
		}
	}

	public function curlFaspay(){
		$signature = $this->createSignature();
		$transaction_data['request'] = "Daftar Payment Channel";
		$transaction_data['merchant_id'] = env('FASPAY_MERCHANT_ID');
		$transaction_data['merchant'] = "Minimi.co.id";
		$transaction_data['signature'] = $signature;

		$options['json'] = $transaction_data;
		$endpoint = env('FASPAY_API_ENDPOINT') . 'cvr/100001/10'; // URL Endpoint Payment Channel Inquiry
		$return = app('App\Http\Controllers\Utility\UtilityController')->sendReqFaspay('POST', $endpoint, $options);

		if ($return == 'connection_exception') return 'not_found';
		if ($return->response_code != 00) return 'not_found';

		return $return->payment_channel;
	}

	public function populateItem($order_details, $pg_code)
	{
		$bill_total = $order_details->total_amount;

		switch ($pg_code) {
				// case 812: // OVO
				// case 819: // DANA
				// 	break;
			case 820: // INDODANA
				$items = $this->populateItemIndodana($order_details);
				break;
			default:
				$items = $this->populateItemOvo($order_details);
				break;
		}

		$transaction_data['bill_total'] = (string) number_format($bill_total, 2, "", "");
		$transaction_data['item'] = $items;

		return $transaction_data;
	}

	public function populateItemOvo($order_details)
	{
		$items = [];
		$transaction_type = $order_details->transaction_type;

		if ($transaction_type == 1) {
			foreach ($order_details->shopping_cart_item as $row) {
				$product_name = $row->product_name;
				if (strlen($row->product_name) > 50) {
					$product_name = substr($row->product_name, 0, 47) . '...';
				}

				$item = array(
					'product'      => $product_name,
					'amount'     => (string) number_format($row->price, 2, "", ""),
					'qty'  => $row->count,
					'payment_plan'  => "01",
					'tenor'  => "00",
				);
				array_push($items, $item);
			}
		} else {
			$row = $order_details->shopping_cart_item[0];
			$item = array(
				'product'      => $row->product_name,
				'amount'     => (string) number_format($row->price, 2, "", ""),
				'qty'  => $row->count,
				'payment_plan'  => "01",
				'tenor'  => "00",
			);
			array_push($items, $item);
		}

		return $items;
	}

	public function populateItemIndodana($order_details)
	{
		$items = [];
		$transaction_type = $order_details->transaction_type;

		if ($transaction_type == 1) {
			foreach ($order_details->shopping_cart_item as $row) {
				$product_name = $row->product_name;
				if (strlen($row->product_name) > 50) {
					$product_name = substr($row->product_name, 0, 47) . '...';
				}

				$item = array(
					'product'      => $product_name,
					'amount'     => (string) number_format($row->price, 2, "", ""),
					'qty'  => $row->count,
					'payment_plan'  => "01",
					'tenor'  => "00",
				);
				array_push($items, $item);
			}
		} else {
			$row = $order_details->shopping_cart_item[0];
			$item = array(
				'product'      => $row->product_name,
				'amount'     => (string) number_format($row->price, 2, "", ""),
				'qty'  => $row->count,
				'payment_plan'  => "01",
				'tenor'  => "00",
			);
			array_push($items, $item);
		}

		// Additional Charges
		$delivery_charges = array(
			'id'      => "shippingfee",
			'product'      => "Delivery Charges",
			'amount'     => (string) number_format($order_details->delivery_amount, 2, "", ""),
			'qty'  => 1,
		);
		array_push($items, $delivery_charges);

		$insurance_charges = array(
			'id'      => "insurancefee",
			'product'      => "Insurance Charges",
			'amount'     => (string) number_format($order_details->insurance_amount, 2, "", ""),
			'qty'  => 1,
		);
		array_push($items, $insurance_charges);

		// Check if there's discount
		if ($order_details->discount_amount > 0) {
			$discount = array(
				'id'      => "discount",
				'amount'     => (string) number_format($order_details->discount_amount, 2, "", ""),
				'qty'  => 1,
				'product'      => "Discount"
			);
			array_push($items, $discount);
		}

		if ($order_details->delivery_discount_amount > 0) {
			$discount = array(
				'id'      => "discount",
				'amount'     => (string) number_format($order_details->delivery_discount_amount, 2, "", ""),
				'qty'  => 1,
				'product'      => "Delivery Discount"
			);
			array_push($items, $discount);
		}

		return $items;
	}

	public function paymentNotify()
	{
		$json_result = file_get_contents('php://input');
		$result = json_decode($json_result);

		$payment_status_code = $result->payment_status_code;
		$type = $result->payment_channel;
		$order_id = $result->bill_no;
		$date = date('Y-m-d H:i:s');

		$res['response'] = 'Payment Notification';
		$res['trx_id'] = $result->trx_id;
		$res['merchant_id'] = $result->merchant_id;
		$res['merchant'] = $result->merchant;
		$res['bill_no'] = $result->bill_no;

		if ($payment_status_code == 5) { // Invoice Not Found
			$res['response_code'] = '07';
			$res['response_desc'] = 'Invoice Not Found';
			$res['response_date'] = $date;
			return response()->json($res);
		} else if ($payment_status_code == 9) { // unknown
			$res['response_code'] = '08';
			$res['response_desc'] = 'Unknown Request';
			$res['response_date'] = $date;
			return response()->json($res);
		}

		$selfSignature = $this->createSignature($order_id . $payment_status_code);
		$signature = $result->signature;

		if ($selfSignature !== $signature)
			return "Invalid Signature";

		$update['transaction_time'] = $result->payment_date;
		$update['transaction_status'] = $result->payment_status_desc;
		$update['transaction_id'] = $result->trx_id;
		$update['status_message'] = $result->request;
		$update['status_code'] = $payment_status_code;
		$update['payment_type'] = $result->payment_channel;
		$update['merchant_id'] = $result->merchant_id;
		$update['vt_gross_amount'] = $result->bill_total;
		$update['store'] = (empty($result->store)) ? null : $result->store;
		$update['fraud_status'] = (empty($result->fraud_status)) ? null : $result->fraud_status;
		$update['masked_card'] = (empty($result->masked_card)) ? null : $result->masked_card;
		$update['biller_code'] = (empty($result->biller_code)) ? null : $result->biller_code;
		$update['bill_key'] = (empty($result->bill_key)) ? null : $result->bill_key;
		$update['settlement_time'] = (empty($result->settlement_time)) ? null : $result->settlement_time;
		$update['signature_key'] = (empty($result->signature_key)) ? null : $result->signature_key;
		$update['approval_code'] = (empty($result->approval_code)) ? null : $result->approval_code;
		$update['currency'] = (empty($result->currency)) ? null : $result->currency;
		$update['virtual_account'] = (empty($result->va_numbers)) ? null : $result->va_numbers[0]->va_number;
		$update['bank'] = (empty($result->va_numbers)) ? null : $result->va_numbers[0]->bank;
		$update['updated_at'] = $date;
		$update['log'] = $json_result;

		// Update Payment Data
		DB::table('payment_data')
			->where('order_id', $result->bill_no)
			->update($update);
		
		if ($payment_status_code == 2) { // Payment Sukses
			// TODO set payment status in merchant's database to 'Settlement'
			DB::table('commerce_booking')
				->where('order_id', $order_id)
				->update([
					'paid_status' => 1,
					'cancel_status' => 0,
					'updated_at' => $date
				]);
			app('App\Http\Controllers\Utility\PaymentController')->payment_complete($order_id);
			$res['response_code'] = '00';
			$res['response_desc'] = 'Payment Success';
		} else if ($payment_status_code == 0) { // Belum diproses
			// TODO set payment status in merchant's database to 'Pending'
			DB::table('commerce_booking')
				->where('order_id', $order_id)
				->update([
					'paid_status' => 0,
					'cancel_status' => 0,
					'updated_at' => $date
				]);
			$res['response_code'] = '01';
			$res['response_desc'] = 'Waiting for Payment';
		} else if ($payment_status_code == 3) { // Payment Gagal
			// TODO set payment status in merchant's database to 'Denied'
			DB::table('commerce_booking')
				->where('order_id', $order_id)
				->update([
					'paid_status' => 2,
					'cancel_status' => 1,
					'updated_at' => $date
				]);
			app('App\Http\Controllers\Utility\PaymentController')->payment_cancel($result->order_id);
			$res['response_code'] = '02';
			$res['response_desc'] = 'Payment Failed';
		} else if ($payment_status_code == 7) { // Payment Expired
			DB::table('commerce_booking')
				->where('order_id', $order_id)
				->update([
					'paid_status' => 2,
					'cancel_status' => 1,
					'updated_at' => $date
				]);
			app('App\Http\Controllers\Utility\PaymentController')->payment_cancel($result->order_id);
			$res['response_code'] = '03';
			$res['response_desc'] = 'Payment Expired';
		} else if ($payment_status_code == 8) { // Payment Cancelled
			DB::table('commerce_booking')
				->where('order_id', $order_id)
				->update([
					'paid_status' => 2,
					'cancel_status' => 1,
					'updated_at' => $date
				]);
			app('App\Http\Controllers\Utility\PaymentController')->payment_cancel($order_id);
			$res['response_code'] = '04';
			$res['response_desc'] = 'Payment Canceled';
		} else if ($payment_status_code == 1) { // In Progress
			DB::table('commerce_booking')
				->where('order_id', $order_id)
				->update([
					'paid_status' => 4,
					'cancel_status' => 0,
					'updated_at' => $date
				]);

			$res['response_code'] = '05';
			$res['response_desc'] = 'Payment In Progress';
		} else if ($payment_status_code == 4) { // Refund
			DB::table('commerce_booking')
				->where('order_id', $order_id)
				->update([
					'paid_status' => 5,
					'cancel_status' => 1,
					'updated_at' => $date
				]);

			$res['response_code'] = '06';
			$res['response_desc'] = 'Refund';
		}

		$res['response_date'] = $date;
		return response()->json($res);
	}
}
