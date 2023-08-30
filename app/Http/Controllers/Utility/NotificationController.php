<?php

namespace App\Http\Controllers\Utility;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

use DB;

class NotificationController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
    }
    
    public function checkNotification(Request $request){
        $data = $request->all();
		try{
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $query = DB::table('minimi_notification')
                ->select('notif_id','notification_message','notification_tag','notification_target')
                ->where([
                    'read_status'=>0,
                    'delay_status'=>0,
                    'user_id'=>$currentUser->user_id
                ])
            ->orderBy('updated_at','DESC')->get();

            if(!count($query)){
                return response()->json(['code'=>4046,'message'=>'notification_not_found']);    
            }

            $return['notif_id'] = $query[0]->notif_id;
            $return['notification_text'] = $query[0]->notification_message;
            $return['notification_target'] = $query[0]->notification_target;

            $this->readNotif_exe($query[0]->notif_id);
            return response()->json(['code'=>200,'message'=>'success','data'=>$return]);    
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'check_notification_failed']);
	  	}
    }

    public function checkReminderNotification(Request $request, $mode){
        $data = $request->all();
		try{
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }

            $check = $this->checkReminder_exe($currentUser->user_id, $mode);

            if($check==FALSE){
                return response()->json(['code'=>2001,'message'=>'already_reminded']);
            }

            return response()->json(['code'=>200,'message'=>'success']);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'check_notification_reminder_failed']);
	  	}
    }

    public function postReminder(Request $request, $mode){
        $data = $request->all();
		try{
            if(empty($data['token'])){
                return response()->json(['code'=>4034,'message'=>'login_to_continue']);
            }else{
                $currentUser = $data['user']->data;
            }
            
            switch ($mode) {
                case 'affiliate':
                    $target = '/affiliate';
                    break;
                case 'gamification':
                    $target = '/gamification';
                    break;
                default:
                    return response()->json(['code'=>4047,'message'=>'undefined_mode']);
                    break;
            }

            $verdict = $this->postReminder_exe($currentUser->user_id, $mode, $target);

            if($verdict=='already_reminded'){
                return response()->json(['code'=>2001,'message'=>'already_reminded']);
            }

            return response()->json(['code'=>200,'message'=>'success']);
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'save_notification_reminder_failed']);
	  	}
    }

    /*
     * Utility Function
    */

    public function notifyAdmin($notif){
        $date = date('Y-m-d H:i:s');
        $notif['created_at'] = $date;
        $notif['updated_at'] = $date;
        DB::table('minimi_notification_admin')->insert($notif);
    }

	public function saveNotification_exe($notif){
        $date = date('Y-m-d H:i:s');
        $notif['created_at'] = $date;
        $notif['updated_at'] = $date;
        DB::table('minimi_notification')->insert($notif);
    }
    
    public function readNotif_exe($notif_id){
        $date = date('Y-m-d H:i:s');
        $notif['read_status'] = 1;
        $notif['updated_at'] = $date;
        DB::table('minimi_notification')->where('notif_id',$notif_id)->update($notif);
    }
    
    public function undelayNotif_exe($notif_id){
        $date = date('Y-m-d H:i:s');
        $notif['delay_status'] = 0;
        $notif['updated_at'] = $date;
        DB::table('minimi_notification')->where('delay_status',1)->update($notif);
	}

    public function checkReminder_exe($user_id, $mode){
        $check = DB::table('minimi_notification')->where([
            'user_id' => $user_id,
            'notification_tag' => $mode,
        ])->value('notif_id');

        if($check>0){
            return FALSE;
        }

        return TRUE;
    }

    public function postReminder_exe($user_id, $mode, $target){
        $check = $this->checkReminder_exe($user_id, $mode);

        if($check==FALSE){
            return 'already_reminded';
        }

        $notify['user_id'] = $user_id;
        $notify['notification_tag'] = $mode;
        $notify['notification_target'] = $target;
        $notify['delay_status'] = 1;
        $this->saveNotification_exe($notify);

        return 'success';
    }
}