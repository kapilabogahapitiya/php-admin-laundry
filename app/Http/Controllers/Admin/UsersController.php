<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use DB, Session, Validator, Hash, Alert;
use App\User, App\Role;

class UsersController extends Controller
{
    public function index()
    {
        $users = User::where('status','1')->orderBy('id', 'ASC')->get();

        $data = compact('users');

        return view('bsb.users.index')->with($data);
    }

    public function create()
    {
        $roles = Role::where('id', '=', 1)->orderBy('id', 'ASC')
                    ->pluck('display_name', 'id')
                    ->all();
        $data = compact('roles');

        return view('bsb.users.create')->with($data);
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), User::valid());
        if ($validate->fails()) {
            return back()
                ->withErrors($validate)
                ->withInput();
        } else {
            DB::beginTransaction();
            try {
                $password = $request['password'];
                $hash_password = Hash::make($request['password']);
                $request->merge(['password' => $hash_password]);
                $user = User::create($request->all());

                $role = Role::findOrFail($request->get('role_id'));
                $user->attachRole($role);


            } catch(\Exception $e) {
                dd($e);
                DB::rollback();
                Alert::error('Fails create user');
                return back();
            }
            DB::Commit();
            Alert::success('Success create user');
            return redirect('admin/users');
        }
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $roles = Role::where('id', '=', 1)->orderBy('id', 'ASC')
                    ->pluck('display_name', 'id')
                    ->all();
        $user_role = $user->roles()->first();
        $user->role_id = $user_role->id;
        $data = compact('roles', 'user');

        return view('bsb.users.edit')->with($data);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $validate = Validator::make($request->all(), User::valid_update($user->id));
        if ($validate->fails()) {
            return back()
                ->withErrors($validate)
                ->withInput();
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            DB::beginTransaction();
            try {
                $password = $request['password'];
                if ($password != '') {
                    $hash_password = Hash::make($request['password']);
                    $request->merge(['password' => $hash_password]);
                }
                $role = Role::findOrFail($request->get('role_id'));
                $user->update($request->all());

                $user->roles()->detach();
                $user->attachRole($role);

            } catch(\Exception $e) {
                DB::rollback();
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
                dd($e);
                Alert::error('Fails update user');
                return back();
            }
            DB::Commit();
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            Alert::success('Success update user');
            return redirect('admin/users');
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($id);
            if ($user->table_name != '') {
                DB::table($user->table_name)
                    ->where('id', '=', $user->table_id)
                    ->delete();
            }
            $user->delete();
        } catch (Exception $e) {
            DB::rollback();
            Alert::error('Fail delete user');
            return back();
        }
        DB::commit();
        Alert::success('Success delete user');
        return back();
    }

    public function update_status($id){
        $user = User::find($id);
        if($user->table_name != ''){
            DB::table($user->table_name)
                ->where('id', $user->table_id)
                ->update(['status' => '0']);
        }
        $user->update(['status' => '0']);
        Alert::success('Success delete user');
        return back();
    }
}
