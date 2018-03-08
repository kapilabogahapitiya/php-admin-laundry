<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\User;
use Alert, DB, Auth, Hash;

class ProfileController extends Controller
{
    public function changePassword() {
        $user = Auth::user();
        $data = compact('user');
        return view('bsb.personals.change_password')->with($data);
    }

    public function doChangePassword(Request $request) {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            if (Hash::check($request->get('current_password'), $user->password)) {
                $password = Hash::make($request->get('password'));
                $user->password = $password;
                $user->save();
            } else {
                Alert::error('Your current password not match');
                return back();
            }
        } catch (Exception $e) {
            DB::rollback();
            Alert::error('We cannot update your password');
            return back();
        }
        DB::commit();
        Alert::success('Password updated');
        return back();
    }
}
