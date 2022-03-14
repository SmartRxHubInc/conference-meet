<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use App\Models\SelectedNumber;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

class SelectedNumberController extends Controller
{

    public function submit(Request $request)
    {
        $query = SelectedNumber::where('user_id', $request->user_id)->first();
        if (!$query) {
            $query = new SelectedNumber();
            $query->user_id = $request->user_id;
        }
        $query->selected_number = $request->selected_number;
        if ($query->save()) {

            // $data = $this->getUserList($request->selected_number, $request->user_id);
            // $object = new stdClass();
            // $object->success = true;
            // $object->data = $data;
            // $object->selected_number_id =  $query->id;
            // return $object;
            //            print_r($request->selected_number.','. $request->user_id.','. $query->id);exit;
            return $this->autoCheckAndConnect($request->selected_number, $request->user_id, $query->id);
        }
    }

    public function userList(Request $request)
    {

        // $query = $this->getUserList($request->selected_number, $request->user_id);
        $query = $this->getUserRefresh($request->selected_number, $request->user_id);
        if ($query) {
            $object = new stdClass();
            $object->success = true;
            $object->data = $query;
            $object->random_range  = $this->getUserCount();
            return $object;
        }
    }

    public function connectionRequest(Request $request)
    {
        // $query = Connection::where('connections.user_id', '=', $request->user_id)->first();
        $uuid = Str::uuid()->toString();
        // if (!$query) {
        $query = new Connection();
        $query->user_id = $request->user_id;
        $query->selected_number_id = $request->selected_number_id;
        $query->selected_number = $request->selected_number;
        // }
        $query->connection_status = 1;
        $query->batch_code = $uuid;
        $query->from_user_id = (int) $request->from_user_id;

        if ($query->save()) {
            $newReplicate = $query->replicate();
            $newReplicate->by_request = 1;
            $newReplicate->from_user_id = $query->user_id;
            $newReplicate->user_id = $query->from_user_id;
            $newReplicate->selected_number_id = $request->from_selected_number_id;
            $newReplicate->save();
            if (!$newReplicate->save()) {
                $query->delete();
            }
            // $data = $this->getUserList($request->selected_number, $request->from_user_id);
            $data = $this->getUserRefresh($request->selected_number, $request->from_user_id);
            $object = new stdClass();
            $object->success = true;
            $object->data = $data;
            $object->random_range  = $this->getUserCount();
            return $object;
        }
    }

    public function connectionReqAccept(Request $request)
    {
        // $query = Connection::where("batch_code", $request->batch_code)
        //     ->update(array('connection_status' => $request->user_id))->save();
        DB::table('connections')
            ->where("batch_code", $request->batch_code)
            ->update(['connection_status' => 2]);

        // if (!$query) {
        $data = $this->getUserList($request->selected_number, $request->from_user_id);
        $object = new stdClass();
        $object->success = true;
        $object->data = $data;
        $object->random_range  = $this->getUserCount();
        return $object;
        // }
    }

    public function connectionReqReject(Request $request)
    {
        DB::table('connections')
            ->where("batch_code", $request->batch_code)
            ->update(['connection_status' => 3]);

        // if (!$query) {
        $data = $this->getUserList($request->selected_number, $request->from_user_id);
        $object = new stdClass();
        $object->success = true;
        $object->data = $data;
        $object->random_range  = $this->getUserCount();
        return $object;
    }

    public function getUserList($selected_number, $user_id)
    {
        return SelectedNumber::select(
            'c.batch_code',
            DB::raw('if(c.by_request IS NULL, 0,c.by_request) by_request'),
            'c.from_user_id',
            DB::raw('if(c.connection_status IS NULL,0,c.connection_status) connection_status'),
            'selected_numbers.id',
            'selected_numbers.user_id',
            'u.fullname',
            'u.email',
            'u.profession',
            'u.phone',
            'selected_numbers.selected_number'
        )
            ->leftjoin('users as u', 'u.id', '=', 'selected_numbers.user_id')
            ->leftjoin('connections as c', function ($join) {
                $join->on('c.selected_number_id', '=', 'selected_numbers.id');
                $join->whereRaw('( c.connection_status IN (0,1) OR  ( c.connection_status IN (2) AND c.updated_at >= UTC_TIMESTAMP() - INTERVAL 2 MINUTE  ))  ');
                // $join->on('c.updated_at', '>=', DB::raw('UTC_TIMESTAMP() - INTERVAL 2 MINUTE'));
            })
            ->where('selected_numbers.selected_number', $selected_number)
            ->where('selected_numbers.user_id', "!=", $user_id)
            ->whereRaw('( c.connection_status IN (0,1) OR c.connection_status IS NULL OR ( c.connection_status IN (2) AND c.updated_at >= UTC_TIMESTAMP() - INTERVAL 2 MINUTE  ))  ')
            ->groupBy('batch_code')
            ->orderBy('c.connection_status', 'DESC')
            ->limit(1)->get();
    }

    public function leaderBoard()
    {
        $query = User::select('users.id', 'users.fullname', DB::raw('sum(if(c.connection_status=2,1,0))*5 as sumpoint'))
            ->leftjoin('connections as c', 'c.from_user_id', '=', 'users.id')
            ->groupBy('users.id')
            ->orderBy('sumpoint', 'DESC')
            ->get();
        return $query;
    }

    public function getUserRefresh($selected_number, $user_id)
    {
        return $query = Connection::select(
            'connections.batch_code',
            DB::raw('if(connections.by_request IS NULL, 0,connections.by_request) by_request'),
            'connections.from_user_id',
            DB::raw('if(connections.connection_status IS NULL,0,connections.connection_status) connection_status'),
            'connections.selected_number_id',
            'connections.user_id',
            'u.fullname',
            'u.email',
            'u.profession',
            'u.phone',
            'connections.selected_number'
        )
            ->join('users as u', 'u.id', '=', 'connections.user_id')
            ->where('connections.selected_number', '=', $selected_number)
            ->where('connections.from_user_id', '=', $user_id)
            ->whereRaw('connections.updated_at >= UTC_TIMESTAMP() - INTERVAL 2 MINUTE')
            ->orderBy('connections.updated_at', 'asc')->limit(1)->get();
    }

    public function getUserCount()
    {
        $data = Connection::select(DB::raw('COUNT(1) cnt'))
            ->first();
        if ($data) {
            if ($data->cnt <= 10) {
                return 20;
            } else if ($data->cnt > 10 && $data->cnt <= 20) {
                return 40;
            } else if ($data->cnt > 20 && $data->cnt <= 50) {
                return 60;
            } else {
                return 100;
            }
        } else {
            return 10;
        }
    }

    public function autoCheckAndConnect($selected_number, $from_user_id, $from_select_number_id)
    {
        $userData = SelectedNumber::where('selected_number', $selected_number)
            ->where('user_id', "!=", $from_user_id)
            ->orderBy('updated_at', 'DESC')
            ->first();

        if ($userData) {
            $conData = Connection::where('selected_number', $selected_number)
                ->where('user_id', $userData->user_id)
                ->first();
            if (!$conData) {
                $is_connect = $this->autoConnect($from_user_id, $userData->user_id, $selected_number, $from_select_number_id, $userData->id);

                $data = $this->getUserRefresh($selected_number, $from_user_id);
                $object = new stdClass();
                $object->success = true;
                $object->is_connect = $is_connect;
                $object->data = $data;
                $object->random_range  = $this->getUserCount();
                return $object;
            } else {
                $data = $this->getUserRefresh($selected_number, $from_user_id);
                // $data = $this->getUserList($selected_number, $from_user_id);
                $object = new stdClass();
                $object->success = true;
                $object->data = $data;
                $object->random_range  = $this->getUserCount();
                return $object;
            }
        } else {
            $data = $this->getUserRefresh($selected_number, $from_user_id);
            $object = new stdClass();
            $object->success = true;
            $object->data = $data;
            $object->random_range  = $this->getUserCount();
            return $object;
        }
    }

    public function autoConnect($from_user_id, $to_user_id, $selected_number, $from_select_number_id, $to_select_number_id)
    {
        $uuid = Str::uuid()->toString();

        $query = new Connection();
        $query->user_id = $to_user_id;
        $query->selected_number_id = $to_select_number_id;
        $query->selected_number = $selected_number;

        $query->connection_status = 2;
        $query->batch_code = $uuid;
        $query->from_user_id = (int) $from_user_id;

        if ($query->save()) {
            $newReplicate = $query->replicate();
            $newReplicate->by_request = 1;
            $newReplicate->from_user_id = $query->user_id;
            $newReplicate->user_id = $query->from_user_id;
            $newReplicate->selected_number_id = $from_select_number_id;
            $newReplicate->save();
            if (!$newReplicate->save()) {
                $query->delete();
                return 0;
            }
        }
        return 1;
        // $data = $this->getUserList($selected_number, $from_user_id);
        //        $data = $this->getUserRefresh($selected_number, $from_user_id);
        //        $object = new stdClass();
        //        $object->success = true;
        //        $object->data = $data;
        //        return $object;
    }
}
