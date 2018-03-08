<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\HotelItem;
use App\CategoryItem;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\SubCategory;
use Auth, DB, Session, Validator, Alert;

class SubCategoriesController extends Controller
{
    public function index()
    {
        $sub_categories = SubCategory::where('status','1')->get();
        $sub_categories_deleted = SubCategory::where('status','0')->get();

        $data = compact('sub_categories', 'sub_categories_deleted');
        return view('bsb.sub_categories.index')->with($data);
    }

    public function create()
    {
        return view('bsb.sub_categories.create');
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), SubCategory::valid());
        if ($validate->fails()) {
            return redirect('admin/sub_categories/create')
            ->withErrors($validate)
            ->withInput();
        } else {
            $request->merge(['status','1']);
            $sub_category = SubCategory::create($request->all());
            Alert::success('Success Create SubCategory');
            return redirect('admin/sub_categories');
        }
    }

    public function edit($id)
    {
        $sub_category = SubCategory::findOrFail($id);
        return view('bsb.sub_categories.edit')->with('sub_category', $sub_category);
    }

    public function update(Request $request, $id)
    {
        $validate = Validator::make($request->all(), SubCategory::valid($id));
        if ($validate->fails()) {
            return back()
            ->withErrors($validate)
            ->withInput();
        } else {
            $sub_category = SubCategory::findOrFail($id);
            $sub_category->update($request->all());
            Alert::success('Success Update SubCategory');
            return redirect('admin/sub_categories/');
        }
    }

    public function destroy($id)
    {
        $sub_category = SubCategory::find($id);
        $sub_category->delete();
        Alert::success('Success Delete SubCategory');
        return redirect('admin/sub_categories');
    }

    public function update_status($id){
        $sub_category = SubCategory::find($id);
        $sub_category_items = CategoryItem::where('subcategory_id', $sub_category->id)->get();

        foreach ($sub_category_items as $item) {
            $hotel_items = HotelItem::where('item_id', $item->id)->get();

            foreach ($hotel_items as $hotelItem) {
                $hotelItem->update(['status' => "0"]);
            }

            $item->update(['status' => "0"]);
        }

        $sub_category->update(['status' => '0']);
        Alert::success('Success Delete SubCategory');
        return redirect('admin/sub_categories');
    }

    public function activate($id){
        $sub_category = SubCategory::find($id);
        $sub_category_items = CategoryItem::where('subcategory_id', $sub_category->id)->get();

        foreach ($sub_category_items as $item) {
            $hotel_items = HotelItem::where('item_id', $item->id)->get();

            foreach ($hotel_items as $hotelItem) {
                $hotelItem->update(['status' => "1"]);
            }

            $item->update(['status' => "1"]);
        }

        $sub_category->update(['status' => '1']);
        Alert::success('Success Activate SubCategory');
        return redirect('admin/sub_categories');
    }
}
