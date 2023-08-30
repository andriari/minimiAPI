<?php

namespace App\Http\Controllers\Utility;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

use DB;

class SicepatController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }
    
    public function scrapperOrigin(Request $request){
        $data = $request->all();
		try{
            $return = $this->scrapperOrigin_exe();
            if($return == 'empty'){
                return response()->json(['code'=>201,'message'=>'no_new_data_given']);
            }
            return response()->json(['code'=>200,'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'sicepat_scrapper_origin_failed']);
	  	}
    }

    public function scrapperDestination(Request $request){
        $data = $request->all();
		try{
            $return = $this->scrapperDestination_exe();
            if($return == 'empty'){
                return response()->json(['code'=>201,'message'=>'no_new_data_given']);
            }
            return response()->json(['code'=>200,'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'sicepat_scrapper_origin_failed']);
	  	}
    }

    public function getProvince(Request $request){
        $data = $request->all();
		try{
            $query = DB::table('sicepat_destination_data')
                ->select('province')
                ->where('province','!=','INT')
                ->where('province','!=','Pending')
                ->orderBy('province','ASC')
                ->groupBy('province')
            ->get();

            if(!count($query)){
                return response()->json(['code'=>4500,'message'=>'not_found']);
            }

            $col_query = collect($query);
            $provinces = $col_query->pluck('province')->all();
            $return['province'] = $provinces;
            return response()->json(['code'=>200,'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'load_province_failed']);
	  	}
    }

    public function getCity(Request $request){
        $data = $request->all();
		try{
            $query = DB::table('sicepat_destination_data')
                ->select('city')
                ->where('province',$data['province'])
                ->orderBy('city','ASC')
                ->groupBy('city')
            ->get();

            if(!count($query)){
                return response()->json(['code'=>4500,'message'=>'not_found']);
            }

            $col_query = collect($query);
            $cities = $col_query->pluck('city')->all();
            $return['province'] = $data['province'];
            $return['city'] = $cities;
            return response()->json(['code'=>200,'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'load_city_failed']);
	  	}
    }

    public function getSubdistrict(Request $request){
        $data = $request->all();
		try{
            $query = DB::table('sicepat_destination_data')
                ->select('subdistrict')
                ->where('province',$data['province'])
                ->where('city',$data['city'])
                ->orderBy('subdistrict','ASC')
                ->groupBy('subdistrict')
            ->get();

            if(!count($query)){
                return response()->json(['code'=>4500,'message'=>'not_found']);
            }

            $col_query = collect($query);
            $subdistricts = $col_query->pluck('subdistrict')->all();
            $return['province'] = $data['province'];
            $return['city'] = $data['city'];
            $return['subdistrict'] = $subdistricts;
            return response()->json(['code'=>200,'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'load_subdistrict_failed']);
	  	}
    }

    public function getDestinationCode(Request $request){
        $data = $request->all();
		try{
            $query = DB::table('sicepat_destination_data')
                ->select('destination_code')
                ->where('province',$data['province'])
                ->where('city',$data['city'])
                ->where('subdistrict',$data['subdistrict'])
            ->first();

            if(empty($query)){
                return response()->json(['code'=>4500,'message'=>'not_found']);
            }

            $return['province'] = $data['province'];
            $return['city'] = $data['city'];
            $return['subdistrict'] = $data['subdistrict'];
            $return['destination_code'] = $query->destination_code;
            return response()->json(['code'=>200,'message'=>'success', 'data'=>$return]);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'load_destination_code_failed']);
	  	}
    }

    public function sicepatTariff_exe($origin, $destination, $weight, $total_amount=0, $diskon_ongkir=0){
        $weight = floatval(str_replace(',','.',$weight));
        $weight = ceil($weight);
        $tariff = app('App\Http\Controllers\Utility\UtilityController')->curlTariffSicepat($origin, $destination, $weight);
        
        $param = DB::table('data_param')
            ->select('param_tag','param_value')
            ->whereIn('param_tag',['free_delivery_region','free_delivery_amount','free_delivery_weight','free_delivery_discount_threshold','free_delivery_region_2','free_delivery_amount_2','free_delivery_weight_2','free_delivery_discount_threshold_2'])
        ->get();

        $col_param = collect($param);
        $free_delivery_region = $col_param->firstWhere('param_tag', 'free_delivery_region')->param_value;
        $free_delivery_region_2 = $col_param->firstWhere('param_tag', 'free_delivery_region_2')->param_value;
        $pos = substr($destination, 0, 3);

        if($tariff!=null){
            $result = $tariff->sicepat->results;
            $return = array();
            $price_miex = 0;
            foreach ($result as $row) {
                if($row->service=="BEST" || $row->service=="SIUNT" || $row->service=="GOKIL"){
                    $data = array();
                    $data['vendor'] = 'SiCepat';
                    $data['service'] = $row->service;
                    if($row->service=="GOKIL"){
                        $data['description'] = "Cargo Kilat";
                        $data['weight_restriction'] = "";
                    }else{
                        $data['description'] = $row->description;
                        $data['weight_restriction'] = "";
                    }
                    if($row->tariff < $row->minPrice){
                        $price = $row->minPrice;
                    }elseif($row->tariff >= $row->minPrice){
                        $price = $row->tariff;
                    }
                    if($row->service=="BEST"){
                        $price_miex = $price;
                    }
                    $data['tariff'] = $price;
                    $data['weight'] = floatval($weight);
                    if($total_amount>0){
                        $data['discount'] = floatval($this->deliveryDiscount($destination, $param, $total_amount, $price, $diskon_ongkir));
                        $disc_tariff = floatval($data['tariff']-$data['discount']);
                        $data['disc_tariff'] = ($disc_tariff>0)?$disc_tariff:0;
                    }
                    $data['estimated'] = $row->etd;
                    if($data['disc_tariff']>0 || $pos != $free_delivery_region){
                        if($pos != $free_delivery_region && $row->service=="GOKIL"){
                            array_push($return, $data);
                        }elseif($pos == $free_delivery_region){
                            array_push($return, $data);
                        }
                    }
                }
            }
        }else{
            $price_miex = 10000;
        }
        
        if($pos == $free_delivery_region){
            $data['vendor'] = 'Minimi';
            $data['service'] = 'MIX';
            $data['description'] = 'Pengiriman Reguler oleh Minimi';
            $data['weight_restriction'] = '';
            $data['tariff'] = $price_miex;
            $data['weight'] = floatval($weight);
            if($total_amount>0){
                $data['discount'] = floatval($this->deliveryDiscount($destination, $param, $total_amount, $price_miex, $diskon_ongkir));
                $disc_tariff = floatval($data['tariff']-$data['discount']);
                $data['disc_tariff'] = ($disc_tariff>0)?$disc_tariff:0;
            }
            $data['estimated'] = '1 Hari';
            array_push($return, $data);
        }

        return $return;
    }

    /*
     * Utility Function
    */

    protected function deliveryDiscount($destination, $param, $price, $delivery_tariff, $diskon_ongkir=0){
        $col_param = collect($param);

        $free_delivery_region = $col_param->firstWhere('param_tag', 'free_delivery_region')->param_value;
        $free_delivery_amount = floatval($col_param->firstWhere('param_tag', 'free_delivery_amount')->param_value);
        $free_delivery_weight = floatval($col_param->firstWhere('param_tag', 'free_delivery_weight')->param_value);
        $free_delivery_discount_threshold = floatval($col_param->firstWhere('param_tag', 'free_delivery_discount_threshold')->param_value);
        $free_delivery_region_2 = $col_param->firstWhere('param_tag', 'free_delivery_region_2')->param_value;
        $free_delivery_amount_2 = floatval($col_param->firstWhere('param_tag', 'free_delivery_amount_2')->param_value);
        $free_delivery_weight_2 = floatval($col_param->firstWhere('param_tag', 'free_delivery_weight_2')->param_value);
        $free_delivery_discount_threshold_2 = floatval($col_param->firstWhere('param_tag', 'free_delivery_discount_threshold_2')->param_value);
        
        $pos = substr($destination, 0, 3);
        $delivery_discount = 0;
        $delivery_discount_threshold = 0;
        $region = 0;
        if($pos == $free_delivery_region){
            if($free_delivery_amount>0 && $price>=$free_delivery_amount){
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
            if($free_delivery_amount>0 && $price>=$free_delivery_amount){
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
            if($free_delivery_discount_threshold>0){
                $delivery_discount = $free_delivery_discount_threshold;
            }else{
                $delivery_discount = $delivery_tariff;
            }
        }

        if($diskon_ongkir>$delivery_discount){
            $delivery_discount = $diskon_ongkir;
        }

        return $delivery_discount;
    }

    public function scrapperOrigin_exe(){
        $origin = app('App\Http\Controllers\Utility\UtilityController')->curlOriginSicepat();
        $result = $origin->sicepat->results;
        $col_result = collect($result);
        $origin_codes = $col_result->pluck('origin_code')->all();

        $query = DB::table('sicepat_origin_data')->whereIn('origin_code',$origin_codes)->get();
        
        if(count($query)>0){
            $col_query = collect($query);
            $origin_codes_exist = $col_query->pluck('origin_code')->all();

            $diff = array_diff($origin_codes, $origin_codes_exist);

            if(count($diff)==0){
                return 'empty';
            }

            $insert = array();
            foreach ($diff as $row) {
                $arr = array();
                $find = $col_result->where('origin_code',$row)->first();
                if($find!=null){
                    $arr['origin_code'] = $find->origin_code;
                    $arr['origin_name'] = $find->origin_name;
                    array_push($insert, $arr);
                }
            }
        }else{
            $insert = array();
            foreach ($result as $row){
                $arr = array();
                $arr['origin_code'] = $row->origin_code;
                $arr['origin_name'] = $row->origin_name;
                array_push($insert, $arr);
            }
        }
        
        DB::table('sicepat_origin_data')->insert($insert);

        return $insert;
    }

    public function scrapperDestination_exe(){
        $origin = app('App\Http\Controllers\Utility\UtilityController')->curlDestinationSicepat();
        $result = $origin->sicepat->results;
        $col_result = collect($result);
        $destination_codes = $col_result->pluck('destination_code')->all();

        $query = DB::table('sicepat_destination_data')->whereIn('destination_code',$destination_codes)->get();
        
        if(count($query)>0){
            $col_query = collect($query);
            $destination_codes_exist = $col_query->pluck('destination_code')->all();

            $diff = array_diff($destination_codes, $destination_codes_exist);

            if(count($diff)==0){
                return 'empty';
            }

            $insert = array();
            foreach ($diff as $row) {
                $arr = array();
                $find = $col_result->where('destination_code',$row)->first();
                if($find!=null){
                    $arr['destination_code'] = $find->destination_code;
                    $arr['subdistrict'] = $find->subdistrict;
                    $arr['city'] = $find->city;
                    $arr['province'] = $find->province;
                    array_push($insert, $arr);
                }
            }
        }else{
            $insert = array();
            foreach ($result as $row){
                $arr = array();
                $arr['destination_code'] = $row->destination_code;
                $arr['subdistrict'] = $row->subdistrict;
                $arr['city'] = $row->city;
                $arr['province'] = $row->province;
                array_push($insert, $arr);
            }
        }
        
        DB::table('sicepat_destination_data')->insert($insert);

        return $insert;
    }

    public function trackingSicepat_exe($receipt_number){
        $track = app('App\Http\Controllers\Utility\UtilityController')->curlTrackingWaybillSicepat($receipt_number);
        $code = $track->sicepat->status->code;
        
        if($code!=200){
            return 'not_found';
        }

        $track_history = $track->sicepat->result->track_history;
        $last_status = $track->sicepat->result->last_status;

        $sign='';
        $array = array();
        foreach ($track_history as $row) {
            if(!empty($row->city)){
                $desc = $row->city;
            }else{
                $desc = $row->receiver_name;
            }
            $sign_2 = $row->date_time.'-'.$row->status.'-'.$desc;

            if($sign!=$sign_2){
                $sign = $sign_2;
                $daten = array();
                $daten['date_time'] = $row->date_time;
                $daten['status'] = $row->status;
                $daten['desc'] = $desc;
                array_push($array,$daten);
            }
        }

        $return['tracker'] = $array;
        $count = count($array);
        $return['last_status'] = $array[$count-1];

        return $return;
    }
}