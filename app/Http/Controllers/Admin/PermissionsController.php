<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use DB, Session, Validator, Alert;
use App\Permission;

class PermissionsController extends Controller
{
    public function index()
    {
        $permissions = Permission::all();

        $data = compact('permissions');
        return view('bsb.permissions.index')->with($data);
    }

    public function create()
    {
        return view('bsb.permissions.create');
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), Permission::valid());
        if ($validate->fails()) {
            return redirect('admin/permissions/create')
            ->withErrors($validate)
            ->withInput();
        } else {
            $permission = Permission::create($request->all());
            Alert::success('Success Create Permission');
            return redirect('admin/permissions');
        }
    }

    public function edit($id)
    {
        $permission = Permission::findOrFail($id);
        return view('bsb.permissions.edit')->with('permission', $permission);
    }

    public function update(Request $request, $id)
    {
        $validate = Validator::make($request->all(), Permission::valid($id));
        if ($validate->fails()) {
            return back()
            ->withErrors($validate)
            ->withInput();
        } else {
            $permission = Permission::findOrFail($id);
            $permission->update($request->all());
            Alert::success('Success Update Permission');
            return redirect('admin/permissions/');
        }
    }

    public function destroy($id)
    {
        $permission = Permission::find($id);
        $permission->delete();
        Alert::success('Success Delete Permission');
        return redirect('admin/permissions');
    }
}

