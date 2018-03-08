<?php

namespace App\Http\Controllers\Admin;

use Alert;
use App\Car;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;

class CarsController extends Controller {
    public function index() {
        $cars = Car::where('status','1')->get();
        $cars_deleted = Car::where('status','0')->get();

        $data = compact('cars','cars_deleted');
        return view('bsb.cars.index')->with($data);
    }

    public function create() {
        return view('bsb.cars.create');
    }

    public function store(Request $request) {
        $validate = Validator::make($request->all(), Car::valid());
        if ($validate->fails()) {
            return redirect('admin/cars/create')
                ->withErrors($validate)
                ->withInput();
        } else {
            $request->merge(['status' => '1']);
            $car = Car::create($request->all());
            Alert::success('Success Create Category');
            return redirect('admin/cars');
        }
    }

    public function edit($id) {
        $car = Car::findOrFail($id);
        $data = compact('car');
        return view('bsb.cars.edit')->with($data);
    }

    public function update(Request $request, $id) {
        $validate = Validator::make($request->all(), Car::valid($id));
        if ($validate->fails()) {
            return back()
                ->withErrors($validate)
                ->withInput();
        } else {
            $car = Car::findOrFail($id);
            $car->update($request->all());
            Alert::success('Success Update Car');
            return redirect('admin/cars/');
        }
    }

    public function destroy($id) {
        $car = Car::find($id);
        $car->delete();
        Alert::success('Success Delete Car');
        return redirect('admin/cars');
    }

    public function update_status($id) {
        $car = Car::find($id);
        $car->update(['status' => '0']);
        Alert::success('Success Delete Car');
        return redirect('admin/cars');
    }

    public function activate($id){
        $car = Car::find($id);
        $car->update(['status' => '1']);
        Alert::success('Success Activate Car');
        return redirect('admin/cars');
    }
}
