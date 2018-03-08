<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\HotelItem, App\CategoryItem, App\ItemUnit, App\Hotel;
use Auth, DB, Session, Validator, Alert;

class HotelItemsController extends Controller
{
    public function index()
    {
        $hotel_items = HotelItem::where('status', '1')->get();
        $items_deleted = HotelItem::select('hotel_items.*')
                            ->join('hotels','hotels.id','hotel_items.hotel_id')
                            ->join('category_items','hotel_items.item_id','category_items.id')
                            ->where('hotel_items.status','0')
                            ->where('category_items.status','1')
                            ->where('hotels.status','1')
                            ->get();

        $data = compact('hotel_items','items_deleted');
        return view('bsb.hotel_items.index')->with($data);
    }

    public function create()
    {
        $hotels = Hotel::where('status','1')->pluck('name', 'id')->all();
        $units = ItemUnit::pluck('code', 'id')->all();
        $items = CategoryItem::orderBy('category_id', 'ASC')
                ->orderBy('subcategory_id', 'ASC')
                ->orderBy('code', 'ASC')
                ->where('status','1')
                ->get()->pluck('CompleteName', 'id')->all();

        $data = compact('hotels', 'units', 'items');
        return view('bsb.hotel_items.create')->with($data);
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), HotelItem::valid());
        if ($validate->fails()) {
            return redirect('admin/hotel_items/create')
            ->withErrors($validate)
            ->withInput();
        } else {
            // find duplicate
            $hotel_id = $request->get('hotel_id');
            $item_id = $request->get('item_id');
            $found = HotelItem::where('hotel_id', '=', $hotel_id)
                    ->where('item_id', '=', $item_id)
                    ->where('status', '1')->first();

            if (!$found) {
                $request->merge(['status','1']);
                HotelItem::create($request->all());
                Alert::success('Success Create hotel item');
                return redirect('admin/hotel_items');
            } else {
                Alert::error('This SKU already Exist');
                return back()->withInput();
            }
        }
    }

    public function edit($id)
    {
        $hotel_item = HotelItem::findOrFail($id);
        $hotels = Hotel::where('status','1')->pluck('name', 'id')->all();
        $units = ItemUnit::pluck('code', 'id')->all();
        $items = CategoryItem::where('status','1')->get()->pluck('CompleteName', 'id')->all();

        $data = compact('hotels', 'units', 'items', 'hotel_item');
        return view('bsb.hotel_items.edit')->with($data);
    }

    public function update(Request $request, $id)
    {
        $validate = Validator::make($request->all(), HotelItem::valid());
        if ($validate->fails()) {
            return back()
            ->withErrors($validate)
            ->withInput();
        } else {
            // find duplicate
            $hotel_id = $request->get('hotel_id');
            $item_id = $request->get('item_id');
            $found = HotelItem::where('hotel_id', '=', $hotel_id)
                        ->where('item_id', '=', $item_id)
                        ->where('id', '!=', $id)->first();

            if (!$found) {
                $hotel_item = HotelItem::findOrFail($id);
                $hotel_item->update($request->all());
                Alert::success('Success update hotel item');
                return redirect('admin/hotel_items');
            } else {
                Alert::error('This SKU already Exist');
                return back()->withInput();
            }
        }
    }
    public function destroy($id)
    {
        $item = HotelItem::find($id);
        $item->delete();
        Alert::success('Success Delete Hotel Item');
        return redirect('admin/hotel_items');
    }

    public function update_status($id){
        $item = HotelItem::find($id);
        $item->update(['status' => '0']);
        Alert::success('Success Delete Hotel Item');
        return redirect('admin/hotel_items');
    }

    public function activate($id) {
        $items = HotelItem::select('hotel_items.id')
                        ->join('hotels','hotels.id','hotel_items.hotel_id')
                        ->where('hotel_items.id',$id)
                        ->where('hotels.status','1')
                        ->first();
        // dd($items->id);
        if(!empty($items)){
            $activate = HotelItem::find($items->id);
            // dd($activate);
            $activate->update(['status' => '1']);
            Alert::success('Success Activate Hotel Item');
            return redirect('admin/hotel_items');
        } else {
            Alert::error('Fail Activate Hotel Item');
            return redirect('admin/hotel_items');
        }
    }

    public function copyItem() {
        $hotels = Hotel::pluck('name', 'id')->all();

        $data = compact('hotels');
        return view('bsb.hotel_items.copy')->with($data);
    }

    public function doCopyItem(Request $request) {
        $source_id = $request->get('source_id');
        $destination_id = $request->get('destination_id');

        if ($source_id == $destination_id) {
            Alert::error('Source dan destination tidak boleh sama');
            return back();
        } else {
            DB::beginTransaction();
            try {
                $source = Hotel::findOrFail($source_id);
                $destination = Hotel::findOrFail($destination_id);

                $destination_items = $destination->items->pluck('item_id')->all();
                $source_items = $source->items()->whereNotIn('item_id', $destination_items)->get();
                if ($source_items->count() > 0) {
                    foreach ($source_items as $key => $item) {
                        HotelItem::create(['hotel_id' => $destination_id, 'item_id' => $item->item_id, 'unit_id' => $item->unit_id, 'price' => $item->price]);
                    }
                } else {
                    Alert::error('Semua item pada source sudah terdaftar pada destination');
                    return back();
                }
            } catch (Exception $e) {
                DB::rollback();
                Alert::error('Duplikasi gagal');
                return back();
            }
            DB::commit();
            Alert::success('Berhasil menduplikasi hotel item');
            return redirect('admin/hotel_items');
        }
    }
}
