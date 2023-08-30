<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions as Exceptions;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Mail;
use App\User;

use DB;

class AuthController extends Controller
{
    public function __construct(){
		date_default_timezone_set("Asia/Jakarta");
	}

    public function getAuthenticatedUser(){
		try {
			if(!$user=JWTAuth::parseToken()->authenticate()){
				return response()->json(['code'=>4103,'message'=>'user_not_found']);
			}
            $name = $this->splitName($user->fullname);
            $user->first_name = $name['first_name'];
            $user->last_name = $name['last_name'];
            return response()->json(['code'=>200,'message'=>'valid','data'=>$user]);    
		} catch (Exceptions\TokenExpiredException $e) {
			return response()->json(['code'=>4100,'message'=>'token_expired']);
		} catch (Exceptions\TokenInvalidException $e) {
			return response()->json(['code'=>4101,'message'=>'token_invalid']);
		} catch (Exceptions\JWTException $e) {
			return response()->json(['code'=>4102,'message'=>'token_absent']);
		}
    }

    public function userRegister(Request $request){
        $data = $request->all();
        try {
            $email = strtolower($data['email']);
            $user = User::where('email','=',$email)->first();
            if(empty($user)){
                if(empty($data['fullname'])){
                    return response()->json(['code'=>4210,'message'=>'Fullname is required','message_id'=>'Mohon masukan nama lengkap']);
                }else{
                    if($data['fullname']=="" || $data['fullname']==null){
                        return response()->json(['code'=>4210,'message'=>'Fullname is required','message_id'=>'Mohon masukan nama lengkap']);
                    }
                }
    
                if(empty($data['email'])){
                    return response()->json(['code'=>4211,'message'=>'Email is required','message_id'=>'Mohon masukan email']);
                }else{
                    if($data['email']=="" || $data['email']==null){
                        return response()->json(['code'=>4211,'message'=>'Email is required','message_id'=>'Mohon masukan email']);
                    }
                }
    
                if(empty($data['username'])){
                    return response()->json(['code'=>4212,'message'=>'Username is required','message_id'=>'Mohon masukan username']);
                }else{
                    if($data['username']=="" || $data['username']==null){
                        return response()->json(['code'=>4212,'message'=>'Username is required','message_id'=>'Mohon masukan username']);
                    }
                    $slug = app('App\Http\Controllers\Utility\UtilityController')->slug($data['username']);
                    $checkUsername = app('App\Http\Controllers\Utility\UtilityController')->checkUri2($slug);
                    if($checkUsername=="FALSE"){
                        return response()->json(['code'=>4212,'message'=>'Username already used','message_id'=>'Username sudah digunakan user lain']);
                    }
                }
    
                if(empty($data['password'])){
                    return response()->json(['code'=>4213,'message'=>'Password is required','message_id'=>'Mohon masukan password']);
                }else{
                    if($data['password']=="" || $data['password']==null){
                        return response()->json(['code'=>4213,'message'=>'Password is required','message_id'=>'Mohon masukan password']);
                    }
    
                    if(empty($data['password_conf'])){
                        return response()->json(['code'=>4214,'message'=>'Password confirmation is required','message_id'=>'Mohon masukan konfirmasi password']);
                    }else{
                        if($data['password']!=$data['password_conf']){
                            return response()->json(['code'=>4220,'message'=>'Password does not match','message_id'=>'Password tidak cocok']);
                        }   
                    }
                }


                $date = date('Y-m-d H:i:s');
                $userId = DB::table('minimi_user_data')->insertGetId([
                    'fullname' => $data['fullname'],
                    'email' => strtolower($data['email']),
                    'password' => Hash::make($data['password']),
                    'user_uri' => $slug,
                    'created_at'=>$date,
                    'updated_at'=>$date
                ]);
                
                app('App\Http\Controllers\Utility\UtilityController')->pointCounterContent(null, $userId, 'sign_up');

                $name = $this->splitName($data['fullname']);
                $result['first_name'] = $name['first_name'];
                $result['last_name'] = $name['last_name'];
                $result['user_uri'] = $slug;
                $result['user_id'] = $userId;
                $result['email'] = $data['email'];
                return response()->json(['code'=>200, 'message'=>'success','data'=>$result]);

                /*$credentials['email'] = $data['email'];
                $credentials['password'] = $data['password'];
                // attempt to verify the credentials and create a token for the user
                if (! $token = JWTAuth::attempt($credentials)) {
                    return Response()->json(['code'=>4200, 'message' => 'invalid_credentials']);
                }else{
                    $result['token'] = $token;
                    return response()->json(['code'=>200, 'message'=>'success', 'data'=>$result]);
                }*/
            }else{
                return response()->json(['code'=>4201,'message'=>'Email has been used','message_id'=>'Alamat email sudah pernah digunakan']);
            }
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'user_registration_failed']);
		}
    }

    public function userRegisterReferral(Request $request){
        $data = $request->all();
        try {
            $user_uri = base64_decode($data['code']);
            $check = DB::table('minimi_user_data')->select('user_id')->where(['user_uri'=>$user_uri,'active'=>1])->first();
            if(empty($check)){
                return response()->json(['code'=>4300,'message'=>'invalid_user']);
            }

            $user = User::where('email','=',$data['email'])->where('active','=',1)->first();
            if(empty($user)){
                if(empty($data['fullname'])){
                    return response()->json(['code'=>4210,'message'=>'fullname_is_required']);
                }else{
                    if($data['fullname']=="" || $data['fullname']==null){
                        return response()->json(['code'=>4210,'message'=>'fullname_is_required']);
                    }
                }
    
                if(empty($data['email'])){
                    return response()->json(['code'=>4211,'message'=>'email_is_required']);
                }else{
                    if($data['email']=="" || $data['email']==null){
                        return response()->json(['code'=>4211,'message'=>'email_is_required']);
                    }
                }
    
                if(empty($data['username'])){
                    return response()->json(['code'=>4212,'message'=>'username_is_required']);
                }else{
                    if($data['username']=="" || $data['username']==null){
                        return response()->json(['code'=>4212,'message'=>'username_is_required']);
                    }
                    $slug = app('App\Http\Controllers\Utility\UtilityController')->slug($data['username']);
                    $checkUsername = app('App\Http\Controllers\Utility\UtilityController')->checkUri2($slug);
                    if($checkUsername=="FALSE"){
                        return response()->json(['code'=>4212,'message'=>'username_already_in_used']);
                    }
                }
    
                if(empty($data['password'])){
                    return response()->json(['code'=>4213,'message'=>'password_is_required']);
                }else{
                    if($data['password']=="" || $data['password']==null){
                        return response()->json(['code'=>4213,'message'=>'password_is_required']);
                    }
    
                    if(empty($data['password_conf'])){
                        return response()->json(['code'=>4214,'message'=>'password_confirmation_is_required']);
                    }else{
                        if($data['password']!=$data['password_conf']){
                            return response()->json(['code'=>4220,'message'=>'password_does_not_match']);
                        }   
                    }
                }

                $date = date('Y-m-d H:i:s');
                $userId = DB::table('minimi_user_data')->insertGetId([
                    'fullname' => $data['fullname'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'user_uri' => $slug,
                    'created_at'=>$date,
                    'updated_at'=>$date
                ]);
                
                app('App\Http\Controllers\Utility\UtilityController')->pointCounterContent(null, $userId, 'sign_up', $check->user_id);
                
                app('App\Http\Controllers\Utility\UtilityController')->pointCounterContent(null, $check->user_id, 'invite_friend', $userId);
                
                $name = $this->splitName($data['fullname']);
                $result['first_name'] = $name['first_name'];
                $result['last_name'] = $name['last_name'];
                $result['user_uri'] = $slug;
                $result['user_id'] = $userId;
                $result['email'] = $data['email'];
                
                return response()->json(['code'=>200, 'message'=>'success','data'=>$result]);
            }else{
                return response()->json(['code'=>4201,'message'=>'email_has_been_used']);
            }
        } catch (QueryException $ex){
			return response()->json(['code'=>4050,'message'=>'user_registration_failed']);
		}
    }

    public function checkLoginToken(Request $request){
        $data = $request->all();
        try {
            $result = $data['user']->data;

            $address = DB::table('minimi_user_address')->where('default',1)->first();
            if(empty($address)){
                $address = DB::table('minimi_user_address')->where('status',1)->first();
                if(!empty($address)){
                    DB::table('minimi_user_address')->where('address_id',$address->address_id)->update([
                        'default'=>1
                    ]);
                }
            }
            $result->address = $address;

            $voucher = DB::table('commerce_voucher')
                ->where([
                    'user_id'=>$result->user_id,
                    'promo_type'=>1,
                    'publish'=>1,
                    'status'=>1
                ])
            ->get();
            $result->voucher_count = count($voucher);

            return response()->json(['code'=>200, 'message'=>'success', 'data'=>$result]);
        } catch (JWTException $e) {
            return Response()->json(['code'=>4205,'message' => 'could_not_encode_token']);
        }
    }
    
    public function userLogin(Request $request){
        $data = $request->all();
        try {
            $user = User::where('email','=',$data['email'])->where('active',1)->first();
            if(isset($user)){
                $credentials['email'] = $data['email'];
                $credentials['password'] = $data['password'];
                // attempt to verify the credentials and create a token for the user
                if (! $token = JWTAuth::attempt($credentials)) {
                    return response()->json(['code'=>4200,'message'=>'Invalid credentials','message_id'=>'Password salah']);
                }else{
                    $result['token'] = $token;
                    $name = $this->splitName($user->fullname);
                    $result['first_name'] = $name['first_name'];
                    $result['last_name'] = $name['last_name'];
                    $result['user_uri'] = $user->user_uri;
                    $result['user_id'] = $user->user_id;
                    $result['email'] = $user->email;
                    return response()->json(['code'=>200, 'message'=>'success', 'data'=>$result]);
                }
            }else{
                return response()->json(['code'=>4201,'message'=>'Email not found','message_id'=>'Email tidak ditemukan']);
            }
        } catch (JWTException $e) {
            // something went wrong whilst attempting to create token
            return Response()->json(['code'=>4205,'message' => 'could_not_create_token']);
        }
    }

    public function forgotPassword(Request $request){
        $data = $request->all();
        try {
            $user = User::where('email','=',$data['email'])->where('active',1)->first();
            if(isset($user)){
                $token_length = 20;
                $token = bin2hex(random_bytes($token_length));
                $created_date = date('Y-m-d H:i');
                $expire_date = date('Y-m-d H:i',strtotime("+3 hours"));
                DB::table('minimi_password_recovery')->insert([
                    'user_id' => $user->user_id,
                    'token' => $token,
                    'created_at' => $created_date,
                    'expired_at' => $expire_date
                ]);
                $array['name'] = $user->fullname;
                $array['message_forgot'] = "Kamu telah meminta untuk membuat password baru.";
                $array['email'] = $user->email;
                $array['reset_pass_link'] = env('FRONTEND_URL')."password/reset/".$token;
                $array['expire_date'] = date('d M Y H:i',strtotime($expire_date));
                $array['subject'] = 'Minimi.co.id Reset Password';
                $this->forgot_notification($array);
                // return "success";
                return response()->json(['code'=>200,'message'=>'success']);
            }else{
                return response()->json(['code'=>4201,'message'=>'email_not_found']);
            }
        } catch (QueryException $e) {
            // something went wrong whilst attempting to create token
            return Response()->json(['code'=>4205,'message' => 'could_not_create_token']);
        }
    }

    public function resetPassword(Request $request){
        $data = $request->all();
        try {
            $newPassword = $data['new_password'];
		    $confirmNewPassword = $data['confirm_new_password'];
		    $recoveryToken = $data['recovery_token'];
            $query = DB::table('minimi_password_recovery')->where('token', $recoveryToken)->first();
            if(!empty($query)){
		        if($newPassword === $confirmNewPassword){
                    if($query->expired_at >= date("Y-m-d H:i:s")){
                        DB::table('minimi_user_data')->where('user_id', $query->user_id)->update(['password' => Hash::make($newPassword)]);
                        DB::table('minimi_password_recovery')->where('token', $recoveryToken)->delete();
                        return response()->json(['code'=>200,'message'=>'success']);
                    }else{
                        return response()->json(['code'=>4010,'message'=>'recovery_token_expired']);
                    }
                }else{
                    return response()->json(['code'=>4012,'message'=>'passwords_not_match']);
                }
            }else{
                return response()->json(['code'=>4011,'message'=>'recovery_token_invalid']);
            }
        } catch (QueryException $e) {
            // something went wrong whilst attempting to create token
            return Response()->json(['code'=>4205,'message' => 'could_not_create_token']);
        }
    }

    public function checkVersion(Request $request, $mode='all'){
        $data = $request->all();
        try {
            switch ($mode) {
                case 'all':
                    $query = DB::table('data_app_version')
                        ->where(['android_status'=>1,'ios_status'=>1])
                        ->orderBy('version_date','desc')
                    ->first();
                break;
                case 'android':
                    $query = DB::table('data_app_version')
                        ->where('android_status',1)
                        ->orderBy('version_date','desc')
                    ->first();
                break;
                case 'ios':
                    $query = DB::table('data_app_version')
                        ->where('ios_status',1)
                        ->orderBy('version_date','desc')
                    ->first();
                break;
                default:
                    return response()->json(['code'=>401,'message'=>'undefined']);
                break;
            }
            if(!empty($query)){
                return response()->json(['code'=>200,'message'=>'success','data'=>$query]);
            }else{
                return response()->json(['code'=>400,'message'=>'empty']);
            }
        } catch (QueryException $e) {
            // something went wrong whilst attempting to create token
            return Response()->json(['code'=>4205,'message' => 'check_version_failed']);
        }
    }
    
    public function splitName($name){
        $exp = explode(' ',$name);
        $count = count($exp);
        if($count>1){
            $result['first_name'] = $exp[0];
            unset($exp[0]);
            $result['last_name'] = implode(" ",$exp);
        }else{
            $result['first_name'] = $exp[0];
            $result['last_name'] = $exp[0];
        }

        return $result;
    }

    public function forgot_notification($array){
		$email = $array['email'];
        $subject = $array['subject'];
        $from_address = env('MAIL_FROM_ADDRESS');
        $from_name = env('MAIL_FROM_NAME');
		Mail::send('emails.forgot', $array, function ($message) use ($email,$subject,$from_address,$from_name)
		{
			$message->from($from_address, $from_name);
			$message->to($email);
			$message->subject($subject);
		});

		return response()->json(['message' => 'Request completed']);
	}
}