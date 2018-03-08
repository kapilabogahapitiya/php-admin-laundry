<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use DB, Session, Validator, Alert;
use App\Zone, App\User;

class ZonesController extends Controller
{
    public function index()
    {
        $zones = Zone::where('status','1')->get();

        $data = compact('zones');
        return view('bsb.zones.index')->with($data);
    }

    public function create()
    {
        $employees = User::select('texcare_employees.*', 'users.id as user_id')
                ->where('users.table_name', '=', 'texcare_employees')
                ->where('role_user.role_id', '=', 5)
                ->where('texcare_employees.status','1')
                ->where('users.status','1')
                ->leftJoin('texcare_employees', 'texcare_employees.id', '=', 'users.table_id')
                ->leftJoin('role_user', 'role_user.user_id', '=', 'users.id')->get()
                ->pluck('name', 'id')->all();
        $data = compact('employees');
        return view('bsb.zones.create')->with($data);
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), Zone::valid());
        if ($validate->fails()) {
            return redirect('admin/zones/create')
            ->withErrors($validate)
            ->withInput();
        } else {
            $request->merge(['status','1']);
            $zone = Zone::create($request->all());
            Alert::success('Success Create Zone');
            return redirect('admin/zones');
        }
    }

    public function edit($id)
    {
        $zone = Zone::findOrFail($id);
        $employees = User::select('texcare_employees.*', 'users.id as user_id')
                ->where('users.table_name', '=', 'texcare_employees')
                ->where('role_user.role_id', '=', 5)
                ->where('texcare_employees.status','1')
                ->where('users.status','1')
                ->leftJoin('texcare_employees', 'texcare_employees.id', '=', 'users.table_id')
                ->leftJoin('role_user', 'role_user.user_id', '=', 'users.id')->get()
                ->pluck('name', 'id')->all();
        $data = compact('employees', 'zone');
        return view('bsb.zones.edit')->with($data);
    }

    public function update(Request $request, $id)
    {
        $validate = Validator::make($request->all(), Zone::valid($id));
        if ($validate->fails()) {
            return back()
            ->withErrors($validate)
            ->withInput();
        } else {
            $zone = Zone::findOrFail($id);
            $zone->update($request->all());
            Alert::success('Success Update Zone');
            return redirect('admin/zones/');
        }
    }

    public function destroy($id)
    {
        $zone = Zone::find($id);
        $zone->delete();
        Alert::success('Success Delete Zone');
        return redirect('admin/zones');
    }

    public function update_status($id){
        $zone = Zone::find($id);
        $zone->update(['status' => '0']);
        Alert::success('Success Delete Zone');
        return redirect('admin/zones');
    }
}
