<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use Auth, DB, Session, Validator, Alert;
use App\Scale, App\Hotel, App\FactoryEmployee;
use App\Category, App\SubCategory;

class ScalesController extends Controller
{
    public function index() {
        $scales = Scale::all();

        $data = compact('scales');
        return view('bsb.scales.index')->with($data);
    }

    public function create()
    {
        $hotels = Hotel::all()->pluck('name', 'id')->all();
        $factory_id = FactoryEmployee::findOrFail(Auth::user()->table_id)->factory_id;
        $categories = Category::all()->pluck('name', 'id')->all();
        $sub_categories = SubCategory::all()->pluck('name', 'id')->all();

        $data = compact('hotels', 'factory_id', 'categories', 'sub_categories');
        return view('bsb.scales.create')->with($data);
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), Scale::valid());
        if ($validate->fails()) {
            return redirect('scales/create')
            ->withErrors($validate)
            ->withInput();
        } else {
            $scale = Scale::create($request->all());
            Alert::success('Success Create Category');
            return redirect('scales');
        }
    }

    public function edit($id)
    {
        $scale = Scale::findOrFail($id);
        $hotels = Hotel::all()->pluck('name', 'id')->all();
        $factory_id = FactoryEmployee::findOrFail(Auth::user()->table_id)->factory_id;
        $categories = Category::all()->pluck('name', 'id')->all();
        $sub_categories = SubCategory::all()->pluck('name', 'id')->all();

        $data = compact('hotels', 'scale', 'factory_id', 'categories', 'sub_categories');
        return view('bsb.scales.edit')->with($data);
    }

    public function update(Request $request, $id)
    {
        $validate = Validator::make($request->all(), Scale::valid($id));
        if ($validate->fails()) {
            return back()
            ->withErrors($validate)
            ->withInput();
        } else {
            $scale = Scale::findOrFail($id);
            $scale->update($request->all());
            Alert::success('Success Update Scale');
            return redirect('scales/');
        }
    }

    public function destroy($id)
    {
        $scale = Scale::find($id);
        $scale->delete();
        Alert::success('Success Delete Scale');
        return redirect('scales');
    }

    public function report(Request $request) {
        $hotels = Hotel::all()->pluck('name', 'id')->all();

        $data = compact('hotels');
        return view('bsb.scales.report')->with($data);
    }

    public function filter(Request $request) {
        $hotel_id = $request->get('hotel_id');
        $start = $request->get('from');
        $end = $request->get('to');

        $scales = Scale::where('created_at', '>=', $start)
                        ->where('created_at', '<=', $end)
                        ->orderBy('created_at', 'ASC');

        if ($hotel_id > 0) {
            $scales = $scales->where('hotel_id', '=', $hotel_id);
        }

        $scales = $scales->get();

        $data = compact('scales');
        if ($request->ajax()) {
            $view = (String) view('bsb.scales.filter')
              ->with($data)
              ->render();
            return response()->json(['view' => $view]);
        } else {
            return response()->json($data, 200);
        }
    }
}
