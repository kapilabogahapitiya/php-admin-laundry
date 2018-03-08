<?php

namespace App\Http\Controllers\Admin;

use Alert;
use App\Driver;
use App\Http\Controllers\Controller;
use DB;
use Illuminate\Http\Request;
use Validator;

class DriversController extends Controller {
    public function index() {
        $drivers = Driver::where('status','1')->get();
        $drivers_deleted = Driver::where('status','0')->get();
        $data = compact('drivers','drivers_deleted');
        return view('bsb.drivers.index')->with($data);
    }

    public function create() {
        return view('bsb.drivers.create');
    }

    public function store(Request $request) {
        $validate = Validator::make($request->all(), Driver::valid());
        if ($validate->fails()) {
            return redirect('admin/drivers/create')
                ->withErrors($validate)
                ->withInput();
        } else {
            DB::beginTransaction();
            try {
                $request->merge(['status' => '1']);
                $driver = Driver::create($request->all());
            } catch (Exception $e) {
                DB::rollback();
                Alert::error('Success create driver');
                return redirect('admin/drivers/create')
                    ->withInput();
            }
            DB::commit();

            Alert::success('Success create driver');
            return redirect('admin/drivers');
        }
    }

    public function edit($id) {
        $driver = Driver::findOrFail($id);
        $data = compact('driver');
        return view('bsb.drivers.edit')->with($data);
    }

    public function update(Request $request, $id) {
        $validate = Validator::make($request->all(), Driver::valid($id));
        if ($validate->fails()) {
            return back()
                ->withErrors($validate)
                ->withInput();
        } else {
            DB::beginTransaction();
            try {
                $driver = Driver::findOrFail($id);
                $driver->update($request->all());

            } catch (Exception $e) {
                DB::rollback();
                Alert::error('Success update driver');
                return back()
                    ->withInput();
            }
            DB::commit();

            Alert::success('Success update driver');
            return redirect('admin/drivers');
        }
    }

    public function destroy($id) {
        $driver = Driver::find($id);
        $driver->delete();
        Alert::success('Success Delete Driver');
        return redirect('admin/drivers');
    }

    public function update_status($id) {
        $driver = Driver::find($id);
        $driver->update(['status' => '0']);
        Alert::success('Success Delete Driver');
        return redirect('admin/drivers');
    }

    public function activate($id){
        $driver = Driver::find($id);
        $driver->update(['status' => '1']);
        Alert::success('Success Activate Driver');
        return redirect('admin/drivers');
    }
}
