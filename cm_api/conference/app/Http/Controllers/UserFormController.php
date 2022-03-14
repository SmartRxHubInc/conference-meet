<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use stdClass;

class UserFormController extends Controller
{
    //
    public function submit(Request $request)
    {
        $validator = validator()->make($request->all(), [
            'email' => ['required'],
            'fullname' => ['required'],
            'phone' => ['required'],
            'fullname' => ['required'],
        ]);
        Log::info("Step 1: check validation which whether valid or not : " . $validator->errors()->first());
        if ($validator->fails()) {
            Log::debug("Step 2:  validation error : " . $validator->errors()->first());
            return;
        }
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            $user = new User();
            $user->email = $request->email;
        }
        $user->fullname = $request->fullname;
        $user->phone = $request->phone;
        $user->profession = $request->proff;
        if ($user->save()) {
            $object = new stdClass();
            $object->success = true;
            $object->user_id = $user->id;
            return $object;
        }
    }
}
