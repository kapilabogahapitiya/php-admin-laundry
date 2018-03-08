<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use DB, Session, Validator, Hash, Alert;
use App\TexcareEmployee, App\User, App\Role, App\LaundryForm;

class TexcareEmployeesController extends Controller
{
    public function index()
    {
        $texcare_employees = TexcareEmployee::where('status','1')->get();
        $employees_deleted = TexcareEmployee::where('status','0')->get();

        $data = compact('texcare_employees','employees_deleted');
        return view('bsb.texcare_employees.index')->with($data);
    }

    public function create()
    {
        $roles = Role::where('name', 'like', 'texcare_%')
                    ->pluck('display_name', 'id')
                    ->all();
        $data = compact('roles');
        return view('bsb.texcare_employees.create')->with($data);
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), TexcareEmployee::valid());
        if ($validate->fails()) {
            return redirect('admin/texcare_employees/create')
            ->withErrors($validate)
            ->withInput();
        } else {
            DB::beginTransaction();
            try {
                $request->merge(['status' => '1']);
                $texcare_employee = TexcareEmployee::create($request->all());
                $hash_password = Hash::make($request['password']);
                $request->merge(
                    [
                                'password' => $hash_password,
                                'table_name' => 'texcare_employees',
                    'table_id' => $texcare_employee->id,
                    'status' => '1']
                );
                $user = User::create($request->all());
                $role = Role::findOrFail($request->get('role_id'));
                $user->attachRole($role);
            } catch(Exception $e) {
                DB::rollback();
                Alert::error('Fail create TexCare employee');
                return redirect('admin/texcare_employees/create')
                    ->withInput();
            }
            DB::commit();

            Alert::success('Success create TexCare employee');
            return redirect('admin/texcare_employees');
        }
    }

    public function edit($id)
    {
        $texcare_employee = TexcareEmployee::findOrFail($id);
        $roles = Role::where('name', 'like', 'texcare_%')
                    ->pluck('display_name', 'id')
                    ->all();
        $data = compact('roles', 'texcare_employee');

        $user = $texcare_employee->user();
        $role = $user->roles()->first();
        $texcare_employee->username = $user->username;
        $texcare_employee->role_id = $role->id;
        return view('bsb.texcare_employees.edit')->with($data);
    }

    public function update(Request $request, $id)
    {
        $validate = Validator::make($request->all(), TexcareEmployee::valid($id));
        if ($validate->fails()) {
            return back()
            ->withErrors($validate)
            ->withInput();
        } else {
            DB::beginTransaction();
            try {
                $texcare_employee = TexcareEmployee::findOrFail($id);
                $texcare_employee->update($request->all());
                $password = $request['password'];
                if ($password != '') {
                    $hash_password = Hash::make($request['password']);
                    $request->merge(['password' => $hash_password]);
                } else {
                    $request->merge(['password' => $texcare_employee->user()->password]);
                }
                $user = $texcare_employee->user();
                $role = Role::findOrFail($request->get('role_id'));
                $user->update($request->all());

                $user->roles()->detach();
                $user->attachRole($role);
            } catch(\Exception $e) {
                DB::rollback();
                Alert::error('Fail update TexCare employee');
                return back()
                    ->withInput();
            }
            DB::commit();

            Alert::success('Success update TexCare employee');
            return redirect('admin/texcare_employees');
        }
    }

    public function destroy($id)
    {
        $texcare_employee = TexcareEmployee::find($id);
        $user = $texcare_employee->user();
        $not_used = LaundryForm::where(function($query) use ($user) {
            $query->where('created_by', '=', $user->id);
            $query->orWhere('approved_by', '=', $user->id);
            $query->orWhere('req_revision_by', '=', $user->id);
            $query->orWhere('approved_revision_by', '=', $user->id);
        })->first();

        if ($not_used != null) {
            Session::flash('error', 'Cannot delete this employee, deletion will crash the forms');
            return back();
        }
        $texcare_employee->user()->delete();
        $texcare_employee->delete();
        Alert::success('Success Delete TexCare Employee');
        return redirect('admin/texcare_employees');
    }

    public function update_status($id) {
        $texcare_employees = TexcareEmployee::find($id);
        $texcare_employees->update(['status' => '0']);
        $texcare_employees->user()->update(['status' => '0']);
        Alert::success('Success Delete TexCare Employee');
        return redirect('admin/texcare_employees');
    }

    public function activate($id){
        $texcare_employees = TexcareEmployee::find($id);
        $texcare_employees->update(['status' => '1']);
        $texcare_employees->user()->update(['status' => '1']);
        Alert::success('Success Activate TexCare Employee');
        return redirect('admin/texcare_employees');
    }
}
