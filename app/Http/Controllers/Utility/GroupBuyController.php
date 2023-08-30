<?php

namespace App\Http\Controllers\Utility;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

use Faker\Factory as Faker;
use Faker\Generator as Faker_generator;
use Faker\Provider as Faker_provider;

use DB;

class GroupBuyController extends Controller
{
    public function __construct(){
        date_default_timezone_set("Asia/Jakarta");
        $faker = new Faker_generator();
        $faker->addProvider(new Faker_provider\Internet($faker));
    }

    public function addBogusUser(){
        $checks = DB::table('commerce_group_buy')->where('status',2)->get();
        
        if(count($checks)==0){
            return 'not_found';
        }

        foreach ($checks as $check) {
            $date = date('Y-m-d H:i:s');
            switch ($check->status) {
                case 1:
                    if($check->expire_at<=$date){
                        $update['status']=5;
                        $update['expire_at']=null;
                        $update['updated_at']=$date;
                        
                        DB::table('commerce_group_buy')->where('cg_id',$check->cg_id)->update($update);
                        
                        $this->cancelOrderByGroup($check->cg_id,$date);
                    }
                break;
                case 2:
                    $minimum_participant = $check->minimum_participant;
                    $total_participant = $check->total_participant;
                    if($total_participant>=$minimum_participant){
                        DB::table('commerce_group_buy')->where('cg_id',$check->cg_id)->update([
                            'status'=>3,
                            'updated_at'=>$date
                        ]);
                    }
                    if($check->expire_at<=$date){
                        DB::table('commerce_group_buy')->where('cg_id',$check->cg_id)->update([
                            'status'=>0,
                            'updated_at'=>$date
                        ]);
                        $this->cancelOrderByGroup($check->cg_id,$date);
                    }
                break;
            }
    
            /*$delta = $minimum_participant - $total_participant;
            $bogus = array();
            $faker = Faker::create('id_ID');
            $name = $faker->name;
            $update = array();
            if($delta==2){
                $date_6_hrs = date('Y-m-d H:i:s',strtotime($check->created_at.' + 12 Hours'));
                if($date>=$date_6_hrs){
                    $bogs = "empty--".$name."--"."paid";
                    $update['bogus_participant'] = $bogs;
                    $update['updated_at'] = $date;
                }
            }elseif($delta==1){
                $date_1_hrs = date('Y-m-d H:i:s',strtotime($check->expire_at.' - 1 Hours'));
                if($date>=$date_1_hrs){
                    $arr = explode(';',$check->bogus_participant);
                    array_push($arr,"empty--".$name."--"."paid");
                    $update['bogus_participant'] = implode(';',$arr);
                    $update['updated_at'] = $date;
                }
            }
    
            if(!empty($update)){
                DB::table('commerce_group_buy')->where('cg_id',$check->cg_id)->update($update);
            }*/
        }

        return 'success';
    }

    public function createUser(){
        $faker = Faker::create('id_ID');
        return $faker->name;
    }

    public function cancelOrderByGroup($cg_id, $date){
        DB::table('commerce_booking')->where(['cg_id'=>$cg_id,'cancel_status'=>0])->whereIn('paid_status',[0,3])->update([
            'paid_status'=>2,
            'cancel_status'=>1,
            'updated_at'=>$date
        ]);
    }
}