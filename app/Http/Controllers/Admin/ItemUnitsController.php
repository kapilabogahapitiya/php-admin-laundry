<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use DB, Session, Validator, Alert;
use App\ItemUnit;

class ItemUnitsController extends Controller
{
    public function index()
    {
        $item_units = ItemUnit::all();

        $data = compact('item_units');
        return view('bsb.item_units.index')->with($data);
    }

    public function create()
    {
        return view('bsb.item_units.create');
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), ItemUnit::valid());
        if ($validate->fails()) {
            return redirect('admin/item_units/create')
            ->withErrors($validate)
            ->withInput();
        } else {
            $item_unit = ItemUnit::create($request->all());
            Alert::success('Success Create Category');
            return redirect('admin/item_units');
        }
    }

    public function edit($id)
    {
        $item_unit = ItemUnit::findOrFail($id);
        return view('bsb.item_units.edit')->with('item_unit', $item_unit);
    }

    public function update(Request $request, $id)
    {
        $validate = Validator::make($request->all(), ItemUnit::valid($id));
        if ($validate->fails()) {
            return back()
            ->withErrors($validate)
            ->withInput();
        } else {
            $item_unit = ItemUnit::findOrFail($id);
            $item_unit->update($request->all());
            Alert::success('Success Update ItemUnit');
            return redirect('admin/item_units/');
        }
    }

    public function destroy($id)
    {
        $item_unit = ItemUnit::find($id);
        $item_unit->delete();
        Alert::success('Success Delete ItemUnit');
        return redirect('admin/item_units');
    }
}
