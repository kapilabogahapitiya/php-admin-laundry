<?php

namespace App\Http\Controllers\Admin;

use Alert;
use App\Factory;
use App\FactoryEmployee;
use App\Http\Controllers\Controller;
use App\LaundryForm;
use App\Role;
use App\User;
use DB;
use Hash;
use Illuminate\Http\Request;
use Validator;

class FactoryEmployeesController extends Controller {
    public function index() {
        $factory_employees = FactoryEmployee::where('status', '1')->get();
        $employees_deleted = FactoryEmployee::select('factory_employees.*')
                                        ->join('factories','factories.id','factory_employees.factory_id')
                                        ->where('factory_employees.status','0')
                                        ->where('factories.status','1')
                                        ->get();
        $data = compact('factory_employees','employees_deleted');
        return view('bsb.factory_employees.index')->with($data);
    }

    public function create() {
        $factories = Factory::where('status','1')->pluck('name', 'id')->all();
        $roles = Role::where('name', 'like', 'pabrik_admin%')
            ->pluck('display_name', 'id')
            ->all();
        $data = compact('factories', 'roles');
        return view('bsb.factory_employees.create')->with($data);
    }

    public function store(Request $request) {
        $validate = Validator::make($request->all(), FactoryEmployee::valid());
        if ($validate->fails()) {
            return redirect('admin/factory_employees/create')
                ->withErrors($validate)
                ->withInput();
        } else {
            DB::beginTransaction();
            try {
                $request->merge(['status' => '1']);
                $factory_employee = FactoryEmployee::create($request->all());
                $hash_password = Hash::make($request['password']);
                $request->merge(
                    ['password' => $hash_password,
                        'table_name' => 'factory_employees',
                        'table_id' => $factory_employee->id,
                        'status' => '1'
                    ]
                );
                $user = User::create($request->all());
                $role = Role::findOrFail($request->get('role_id'));
                $user->attachRole($role);
            } catch (Exception $e) {
                DB::rollback();
                Alert::error('Success create factory employee');
                return redirect('admin/factory_employees/create')
                    ->withInput();
            }
            DB::commit();

            Alert::success('Success create factory employee');
            return redirect('admin/factory_employees');
        }
    }

    public function edit($id) {
        $factory_employee = FactoryEmployee::findOrFail($id);
        $factories = Factory::pluck('name', 'id')->all();
        $roles = Role::where('name', 'like', 'pabrik_%')
            ->pluck('display_name', 'id')
            ->all();

        $user = $factory_employee->user();
        $role = null;
        if ($user) {
            $role = $user->roles()->first();
            $factory_employee->username = $user->username;
            $factory_employee->role_id = $role->id;
        }
        $data = compact('factories', 'roles', 'factory_employee');
        return view('bsb.factory_employees.edit')->with($data);
    }

    public function update(Request $request, $id) {
        $validate = Validator::make($request->all(), FactoryEmployee::valid($id));
        if ($validate->fails()) {
            return back()
                ->withErrors($validate)
                ->withInput();
        } else {
            DB::beginTransaction();
            try {
                $factory_employee = FactoryEmployee::findOrFail($id);
                $factory_employee->update($request->all());
                $password = $request['password'];
                if ($password != '') {
                    $hash_password = Hash::make($request['password']);
                    $request->merge(['password' => $hash_password]);
                } else {
                    $request->merge(['password' => $factory_employee->user()->password]);
                }
                $user = $factory_employee->user();
                $role = Role::findOrFail($request->get('role_id'));
                $user->update($request->all());

                $user->roles()->detach();
                $user->attachRole($role);
            } catch (\Exception $e) {
                DB::rollback();
                Alert::error('Success update factory employee');
                return back()
                    ->withInput();
            }
            DB::commit();

            Alert::success('Success update factory employee');
            return redirect('admin/factory_employees');
        }
    }

    public function destroy($id) {
        $factory_employee = FactoryEmployee::find($id);
        $user = $factory_employee->user();
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
        $factory_employee->user()->delete();
        $factory_employee->delete();
        Alert::success('Success Delete Factory Employee');
        return redirect('admin/factory_employees');
    }

    public function update_status($id){
        $factory_employee = FactoryEmployee::find($id);
        $factory_employee->update(['status' => '0']);
        $factory_employee->user()->update(['status' => '0']);
        Alert::success('Success Delete Factory Employee');
        return redirect('admin/factory_employees');
    }

    public function activate($id){
        $employee = FactoryEmployee::select('factory_employees.id')
                        ->join('factories','factory_employees.factory_id','factories.id')
                        ->where('factory_employees.id',$id)
                        ->where('factories.status','1')
                        ->first();
        if(!empty($employee)) {
            $activate = FactoryEmployee::find($employee->id);
            $activate->update(['status' => '1']);
            $activate->user()->update(['status' => '1']);
            Alert::success('Success Activate Factory Employee');
            return redirect('admin/factory_employees');
        } else {
            Alert::error('Fail Activate Factory Employee');
            return redirect('admin/factory_employees');
        }
    }
}
