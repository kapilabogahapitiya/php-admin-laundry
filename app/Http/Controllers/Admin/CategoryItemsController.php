<?php

namespace App\Http\Controllers\Admin;

use Alert;
use App\Category;
use App\CategoryItem;
use App\HotelItem;
use App\Http\Controllers\Controller;
use App\SubCategory;
use Illuminate\Http\Request;
use Session;
use Validator;

class CategoryItemsController extends Controller {
    public function index() {
        $category_items = CategoryItem::where('status','1')->get();
        $item_deleted = CategoryItem::select('category_items.*')
                            ->join('categories','category_items.category_id','categories.id')
                            ->join('sub_categories','category_items.subcategory_id','sub_categories.id')
                            ->where('categories.status','1')
                            ->where('sub_categories.status','1')
                            ->where('category_items.status','0')
                            ->get();
        $data = compact('category_items','item_deleted');
        return view('bsb.category_items.index')->with($data);
    }

    public function create() {
        $categories = Category::where('status','1')->pluck('name', 'id')->all();
        $sub_categories = SubCategory::where('status','1')->pluck('name', 'id')->all();

        $data = compact('categories', 'sub_categories');
        return view('bsb.category_items.create')->with($data);
    }

    public function store(Request $request) {
        $validate = Validator::make($request->all(), CategoryItem::valid());
        if ($validate->fails()) {
            return redirect('admin/category_items/create')
                ->withErrors($validate)
                ->withInput();
        } else {
            // check duplicate
            $found = CategoryItem::where('code', '=', $request->get('code'))
                ->where('category_id', '=', $request->get('category_id'))
                ->where('subcategory_id', '=', $request->get('subcategory_id'))
                ->where('status','=','1')
                ->first();
            if ($found) {
                Alert::error('This item already exist');
                return redirect('admin/category_items/create')
                    ->withInput();
            }
            $request->merge(['status', '1']);
            $category_item = CategoryItem::create($request->all());
            Alert::success('Success Create Category');
            return redirect('admin/category_items');
        }
    }

    public function edit($id) {
        $category_item = CategoryItem::findOrFail($id);
        $categories = Category::where('status','1')->pluck('name', 'id')->all();
        $sub_categories = SubCategory::where('status','1')->pluck('name', 'id')->all();

        /**
         * Determine if item was deleted
         */
        if ($category_item->status == "0") {
            Session::flash('error', 'Item with name: ' . $category_item->name . ' was deleted.');
            return redirect('admin/category_items');
        }

        /**
         * Determine if item category / sub category is alive or not.
         */
        if ($category_item->category->status == "0" || $category_item->sub_category->status == "0") {
            Session::flash('error', 'Category or SubCategory of Item with name: ' . $category_item->name . ' was deleted.');
            return redirect('admin/category_items');
        }

        $data = compact('categories', 'category_item', 'sub_categories');
        return view('bsb.category_items.edit')->with($data);
    }

    public function update(Request $request, $id) {
        $validate = Validator::make($request->all(), CategoryItem::valid($id));
        if ($validate->fails()) {
            return back()
                ->withErrors($validate)
                ->withInput();
        } else {
            $found = CategoryItem::where('code', '=', $request->get('code'))
                ->where('category_id', '=', $request->get('category_id'))
                ->where('subcategory_id', '=', $request->get('subcategory_id'))
                ->where('id', '!=', $id)
                ->first();
            if ($found) {
                Alert::error('This item already exist');
                return back()
                    ->withInput();
            }
            $category_item = CategoryItem::findOrFail($id);
            $category_item->update($request->all());
            Alert::success('Success Update Category Item');
            return redirect('admin/category_items/');
        }
    }

    public function destroy($id) {
        $category_item = CategoryItem::find($id);
        $category_item->delete();
        Alert::success('Success Delete Category Item');
        return redirect('admin/category_items');
    }

    public function update_status($id) {
        $category_item = CategoryItem::find($id);
        $hotel_items = HotelItem::where('item_id', $category_item->id)->get();

        foreach ($hotel_items as $hotelItem) {
            $hotelItem->update(['status' => "0"]);
        }

        $category_item->update(['status' => '0']);
        Alert::success('Success Delete Category Item');
        return redirect('admin/category_items');
    }

    public function activate($id) {
        $category_item = CategoryItem::find($id);
        $hotel_items = HotelItem::where('item_id', $category_item->id)->get();

        foreach ($hotel_items as $hotelItem) {
            $hotelItem->update(['status' => "1"]);
        }

        $category_item->update(['status' => '1']);
        Alert::success('Success Activate Category Item');
        return redirect('admin/category_items');
    }
}
