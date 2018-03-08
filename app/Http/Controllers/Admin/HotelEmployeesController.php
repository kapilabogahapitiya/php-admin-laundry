<?php

namespace App\Http\Controllers\Admin;

use Alert;
use App\Hotel;
use App\HotelEmployee;
use App\Http\Controllers\Controller;
use App\LaundryForm;
use App\Role;
use App\User;
use DB;
use Hash;
use Illuminate\Http\Request;
use Validator;

class HotelEmployeesController extends Controller {
    public function index() {
        $hotel_employees = HotelEmployee::where('status','1')->get();
        $employee_deleted = HotelEmployee::select('hotel_employees.*')
                            ->join('hotels','hotels.id','hotel_employees.hotel_id')
                            ->where('hotel_employees.status','0')
                            ->where('hotels.status','1')
                            ->get();

        $data = compact('hotel_employees','employee_deleted');
        return view('bsb.hotel_employees.index')->with($data);
    }

    public function create() {
        $hotels = Hotel::where('status','1')->pluck('name', 'id')->all();
        $roles = Role::where('name', 'like', 'hotel_%')
            ->pluck('display_name', 'id')
            ->all();
        $data = compact('hotels', 'roles');
        return view('bsb.hotel_employees.create')->with($data);
    }

    public function store(Request $request) {
        $validate = Validator::make($request->all(), HotelEmployee::valid());
        if ($validate->fails()) {
            return redirect('admin/hotel_employees/create')
                ->withErrors($validate)
                ->withInput();
        } else {
            DB::beginTransaction();
            try {
                $request->merge(['status','1']);
                $hotel_employee = HotelEmployee::create($request->all());
                $hash_password = Hash::make($request['password']);
                $request->merge(
                    [
                        'password' => $hash_password,
                        'table_name' => 'hotel_employees',
                        'table_id' => $hotel_employee->id,
                        'status' => '1']
                );
                $user = User::create($request->all());
                $role = Role::findOrFail($request->get('role_id'));
                $user->attachRole($role);
            } catch (Exception $e) {
                DB::rollback();
                Alert::error('Fail create hotel employee');
                return redirect('admin/hotel_employees/create')
                    ->withInput();
            }
            DB::commit();

            Alert::success('Success create hotel employee');
            return redirect('admin/hotel_employees');
        }
    }

    public function edit($id) {
        $hotel_employee = HotelEmployee::findOrFail($id);
        $hotels = Hotel::pluck('name', 'id')->all();
        $roles = Role::where('name', 'like', 'hotel_%')
            ->pluck('display_name', 'id')
            ->all();
        $data = compact('hotels', 'roles', 'hotel_employee');

        $user = $hotel_employee->user();
        $role = $user->roles()->first();
        $hotel_employee->username = $user->username;
        $hotel_employee->role_id = $role->id;
        return view('bsb.hotel_employees.edit')->with($data);
    }

    public function update(Request $request, $id) {
        $validate = Validator::make($request->all(), HotelEmployee::valid($id));
        if ($validate->fails()) {
            return back()
                ->withErrors($validate)
                ->withInput();
        } else {
            DB::beginTransaction();
            try {
                $hotel_employee = HotelEmployee::findOrFail($id);
                $hotel_employee->update($request->all());
                $password = $request['password'];
                if ($password != '') {
                    $hash_password = Hash::make($request['password']);
                    $request->merge(['password' => $hash_password]);
                } else {
                    $request->merge(['password' => $hotel_employee->user()->password]);
                }
                $user = $hotel_employee->user();
                $role = Role::findOrFail($request->get('role_id'));
                $user->update($request->all());

                $user->roles()->detach();
                $user->attachRole($role);
            } catch (\Exception $e) {
                DB::rollback();
                Alert::error('Fail update hotel employee');
                return back()
                    ->withInput();
            }
            DB::commit();

            Alert::success('Success update hotel employee');
            return redirect('admin/hotel_employees');
        }
    }

    public function destroy($id) {
        $hotel_employee = HotelEmployee::find($id);
        $user = $hotel_employee->user();
        $not_used = LaundryForm::where(function ($query) use ($user) {
            $query->where('created_by', '=', $user->id);
            $query->orWhere('approved_by', '=', $user->id);
            $query->orWhere('req_revision_by', '=', $user->id);
            $query->orWhere('approved_revision_by', '=', $user->id);
        })->first();

        if ($not_used != null) {
            Alert::error('Cannot delete this employee, deletion will crash the forms');
            return back();
        }
        $hotel_employee->user()->delete();
        $hotel_employee->delete();
        Alert::success('Success Delete Hotel Employee');
        return redirect('admin/hotel_employees');
    }

    public function update_status($id) {
        $hotel_employee = HotelEmployee::find($id);
        $hotel_employee->update(['status' => '0']);
        $hotel_employee->user()->update(['status' => '0']);
        Alert::success('Success Delete Hotel Employee');
        return redirect('admin/hotel_employees');
    }

    public function activate($id) {
        $employee = HotelEmployee::select('hotel_employees.id')
                        ->join('hotels','hotels.id','hotel_employees.hotel_id')
                        ->where('hotel_employees.id',$id)
                        ->where('hotels.status','1')
                        ->first();
        if(!empty($employee)){
            $activate = HotelEmployee::find($employee->id);
            $activate->update(['status' => '1']);
            $activate->user()->update(['status' => '1']);
            Alert::success('Success Activate Hotel Employee');
            return redirect('admin/hotel_employees');
        } else {
            Alert::error('Fail Activate Hotel Employee');
            return redirect('admin/hotel_employees');
        }
    }
}
