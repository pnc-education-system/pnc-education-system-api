<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PasswordReset;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'email'=> 'required|email|exists:users,email',
        ]);
        if($validator->fails()){
            return response()->json([
                'message' =>'Validation failed',
                'errors'   => $validator->errors(),
            ],422);
        }
        PasswordReset::where('email', $request->email)->delete();
        $token = Str::random(64);
        PasswordReset::create([
            'email' =>$request->email,
            'token' =>Hash::make($token),
            'expires_at' =>Carbon::now()->addMinutes(60),
        ]);
        $resetLink = url("/auth/password/reset/confirm?token={$token}&email={$request->email}");
        Log::info('Password reset link:' .$resetLink);
        return response()->json([
            'message'=> 'Password reset link has been sent to your email'
        ],200);
    }

    public function confirm(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);
        if($validator->fails()){
            return response()->json([
                'message' =>'Validation failed',
                'errors' => $validator->errors(),
            ],422);
        }
        $resetRecords = PasswordReset::where('email', $request->email)
        ->whereNull('used_at')
        ->get();

        $reset = null;
        foreach ($resetRecords as $record){
            if(Hash::check($request->token, $record->token)){
                $reset = $record;
                break;
            }
        }
        if(!$reset){
            return response()->json([
                'message'=>'Invalid or expired reset token',
            ],400);
        }
        if($reset->expires_at && Carbon::now()->gt($reset->expires_at)){
            $reset->delete();
            return response()->json([
                'message'=>'Reset token has expired. Please request a new one.'
            ],400);
        }
        $user = User::where('email', $request->email)->first();
        $user->update([
            'password'=> Hash::make($request->password),
        ]);
        $reset->update([
            'used_at'=> Carbon::now(),
        ]);
        PasswordReset::where('email', $request->email)->delete();
        return response()->json([
            'message'=> 'Password has been reset successfully',
        ],200);
    }
}