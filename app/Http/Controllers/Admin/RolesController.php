<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Role, App\Permission, App\RolePermission;
use Auth, DB, Validator, Session, Alert;

class RolesController extends Controller
{
    public function index()
    {
        $roles = Role::all();

        $data = compact('roles');
        return view('bsb.roles.index')->with($data);
    }

    public function create()
    {
        return view('bsb.roles.create');
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), Role::valid());
        if ($validate->fails()) {
            return back()
            ->withErrors($validate)
            ->withInput();
        } else {
            $role = Role::create($request->all());

            Alert::success('Success Create Role');
            return redirect('admin/roles');
        }
    }

    public function edit($id)
    {
        $role = Role::findOrFail($id);
        $permissions = Permission::orderBy('display_name', 'asc')->get();
        $permission_roles = $role->permissions()->where('role_id', '=', $id)
            ->orderBy('permission_id', 'ASC')
            ->pluck('permission_id')->all();

        $data = compact('role', 'permissions', 'permission_roles');
        return view('bsb.roles.edit')->with($data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int                      $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validate = Validator::make($request->all(), Role::valid($id));
        if ($validate->fails()) {
            dd($validate);
            return back()
            ->withErrors($validate)
            ->withInput();
        } else {
            DB::beginTransaction();
            try {
                $role = Role::findOrFail($id);
                $role->update($request->all());

                $permission_roles = $request->get('permission_role');
                if (!is_null($permission_roles)) {
                    $rp = RolePermission::where('role_id', '=', $id)->delete();
                    foreach ($permission_roles as $key => $value) {
                        $permission = Permission::find($value);
                        if ($permission) {
                            $role->attachPermission($permission);
                        }
                    }

                } else {
                    RolePermission::where('role_id', '=', $id)->delete();
                }

            } catch(Exception $e) {
                DB::rollback();
                dd($e);
                Alert::error('Fail updated role and permissions');
                return Redirect::to('admin/roles/' . $id . '/edit')
                ->withInput();
            }
            DB::commit();
            Alert::success('Successfully updated role and permissions');
            return redirect('admin/roles');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $role = Role::find($id);
        $role->delete();
        Alert::success('Success Delete Role');
        return redirect('admin/roles');
    }
}
