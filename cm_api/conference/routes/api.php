<?php

use App\Http\Controllers\SelectedNumberController;
use App\Http\Controllers\UserFormController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post("submitform", [UserFormController::class, 'submit']);

Route::post("generatenumber", [SelectedNumberController::class, 'submit']);
Route::post("userlist", [SelectedNumberController::class, 'userList']);


Route::post("ConnectionRequest", [SelectedNumberController::class, 'connectionRequest']);
Route::post("ConnectionReqAccept", [SelectedNumberController::class, 'connectionReqAccept']);
Route::post("ConnectionReqReject", [SelectedNumberController::class, 'connectionReqReject']);

Route::get("LeaderBoard", [SelectedNumberController::class, 'leaderBoard']);

Route::post("deletetable", [SelectedNumberController::class, 'truncatedata']);
