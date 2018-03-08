<?php

namespace App\Http\Controllers\Admin;

use Alert;
use App\Factory,App\FactoryEmployee;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;

class FactoriesController extends Controller {
    public function index() {
        $factories = Factory::where('status','1')->get();
        $factories_deleted = Factory::where('status','0')->get();
        $data = compact('factories','factories_deleted');
        return view('bsb.factories.index')->with($data);
    }

    public function create() {
        return view('bsb.factories.create');
    }

    public function store(Request $request) {
        $validate = Validator::make($request->all(), Factory::valid());
        if ($validate->fails()) {
            return redirect('admin/factories/create')
                ->withErrors($validate)
                ->withInput();
        } else {
            $request->merge(['status' => '1']);
            $factory = Factory::create($request->all());
            Alert::success('Success Create Category');
            return redirect('admin/factories');
        }
    }

    public function edit($id) {
        $factory = Factory::findOrFail($id);
        return view('bsb.factories.edit')->with('factory', $factory);
    }

    public function update(Request $request, $id) {
        $validate = Validator::make($request->all(), Factory::valid($id));
        if ($validate->fails()) {
            return back()
                ->withErrors($validate)
                ->withInput();
        } else {
            $factory = Factory::findOrFail($id);
            $factory->update($request->all());
            Alert::success('Success Update Factory');
            return redirect('admin/factories/');
        }
    }

    public function destroy($id) {
        $factory = Factory::find($id);
        $factory->delete();
        Alert::success('Success Delete Factory');
        return redirect('admin/factories');
    }

    public function update_status($id) {
        $factory = Factory::find($id);
        $employee = FactoryEmployee::where('factory_id',$factory->id)->get();
            foreach($employee as $data) {
                $delete = FactoryEmployee::find($data->id);
                $delete->update(['status' => '0']);
                $delete->user()->update(['status' => '0']);
            }
        $factory->update(['status' => '0']);
        Alert::success('Success Delete Factory');
        return redirect('admin/factories');
    }
}
