<?php

namespace App\Http\Controllers\Admin;

use Alert;
use App\Category;
use App\CategoryItem;
use App\HotelItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class CategoriesController extends Controller {
    public function index() {
        $categories = Category::where('status','1')->get();
        $categories_deleted = Category::where('status','0')->get();

        $data = compact('categories', 'categories_deleted');
        return view('bsb.categories.index')->with($data);
    }

    public function create() {
        return view('bsb.categories.create');
    }

    public function store(Request $request) {
        $validate = Validator::make($request->all(), Category::valid());
        if ($validate->fails()) {
            return redirect('admin/categories/create')
                ->withErrors($validate)
                ->withInput();
        } else {
            $request->merge(['status', '1']);
            $category = Category::create($request->all());
            Schema::table('form_numbers', function (Blueprint $table) use ($request) {
                $table->integer($request->input('code'))->default(0)->nullable();
            });
            Alert::success('Success Create Category');
            return redirect('admin/categories');
        }
    }

    public function edit($id) {
        $category = Category::findOrFail($id);
        return view('bsb.categories.edit')->with('category', $category);
    }

    public function update(Request $request, $id) {
        $validate = Validator::make($request->all(), Category::valid($id));
        if ($validate->fails()) {
            return back()
                ->withErrors($validate)
                ->withInput();
        } else {
            $category = Category::findOrFail($id);
            $oldCode = $category->code;
            $category->update($request->all());
            Schema::table('form_numbers', function (Blueprint $table) use ($request, $category, $oldCode) {
                $table->renameColumn($oldCode, $request->input('code'));
            });
            Alert::success('Success Update Category');
            return redirect('admin/categories/');
        }
    }

    public function destroy($id) {
        $category = Category::find($id);
        Schema::table('form_numbers', function (Blueprint $table) use ($category) {
                $table->dropColumn($category->code);
        });
        $category->delete();
        Alert::success('Success Delete Category');
        return redirect('admin/categories');
    }

    public function update_status($id) {
        $category = Category::find($id);
        $category_items = CategoryItem::where('category_id', $category->id)->get();

        foreach ($category_items as $item) {
            $hotel_items = HotelItem::where('item_id', $item->id)->get();

            foreach ($hotel_items as $hotelItem) {
                $hotelItem->update(['status' => "0"]);
            }

            $item->update(['status' => "0"]);
        }

        $category->update(['status'=> '0']);
        Alert::success('Success Delete Category');
        return redirect('admin/categories');
    }

    public function activate($id){
        $category = Category::find($id);
        $category_items = CategoryItem::where('category_id', $category->id)->get();

        foreach ($category_items as $item) {
            $hotel_items = HotelItem::where('item_id', $item->id)->get();

            foreach ($hotel_items as $hotelItem) {
                $hotelItem->update(['status' => "1"]);
            }

            $item->update(['status' => "1"]);
        }
        $category->update(['status' => '1']);
        Alert::success('Success Activate Category');
        return redirect('admin/categories');
    }
}
