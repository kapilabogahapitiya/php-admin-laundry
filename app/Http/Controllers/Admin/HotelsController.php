<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use DB, Session, Validator, Alert;
use App\Hotel, App\Zone, App\Dummy, App\HotelEmployee, App\HotelItem;

class HotelsController extends Controller
{
    public function index()
    {
        $hotels = Hotel::where('status','1')->get();
        $hotelsdeleted = Hotel::where('status','0')->get();

        $data = compact('hotels','hotelsdeleted');
        return view('bsb.hotels.index')->with($data);
    }

    public function create()
    {
        $zones = Zone::where('status','1')->pluck('name', 'id')->all();
        $data = compact('zones');
        return view('bsb.hotels.create')->with($data);
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), Hotel::valid());
        if ($validate->fails()) {
            return redirect('admin/hotels/create')
            ->withErrors($validate)
            ->withInput();
        } else {
            $request->merge(['status' => '1']);
            $hotel = Hotel::create($request->all());
            Alert::success('Success Create Category');
            return redirect('admin/hotels');
        }
    }

    public function edit($id)
    {
        $hotel = Hotel::findOrFail($id);
        $zones = Zone::where('status','1')->pluck('name', 'id')->all();
        $data = compact('zones', 'hotel');
        return view('bsb.hotels.edit')->with($data);
    }

    public function update(Request $request, $id)
    {
        $validate = Validator::make($request->all(), Hotel::valid($id));
        if ($validate->fails()) {
            return back()
            ->withErrors($validate)
            ->withInput();
        } else {
            $hotel = Hotel::findOrFail($id);
            $hotel->update($request->all());
            Alert::success('Success Update Hotel');
            return redirect('admin/hotels/');
        }
    }

    public function destroy($id)
    {
        $hotel = Hotel::find($id);
        $hotel->delete();
        Alert::success('Success Delete Hotel');
        return redirect('admin/hotels');
    }

    public function items(Request $request, $id)
    {
        $hotel = Hotel::findOrFail($id);
        $cat_id = $request->get('cat_id');
        $data = $hotel->items_array($cat_id);

        if ($request->ajax()) {
            $view = (String) view('bsb.hotel_items.row')
              ->with('data', $data)
              ->render();
            return response()->json(['view' => $view]);
        } else {
            return response()->json(['items' => $data]);
        }
    }

    public function update_status($id) {
        $hotel = Hotel::find($id);
        $item = HotelItem::where('hotel_id',$id)->get();
        $employee = HotelEmployee::where('hotel_id',$id)->get();
            foreach($employee as $data){
                $delete = HotelEmployee::find($data->id);
                $delete->update(['status' => '0']);
                $delete->user()->update(['status' => '0']);
            }
            foreach($item as $data){
                $delete = HotelItem::find($data->id);
                $delete->update(['status' => '0']);
            }
        $hotel->update(['status' => '0']);
        Alert::success('Success Delete Hotel');
        return redirect('admin/hotels');
    }

    public function activate($id) {
        $hotel = Hotel::find($id);
        $item = HotelItem::where('hotel_id',$id)->get();
        $employee = HotelEmployee::where('hotel_id',$id)->get();
            foreach($employee as $data){
                $activate = HotelEmployee::find($data->id);
                $activate->update(['status' => '1']);
                $activate->user()->update(['status' => '1']);
            }
            foreach($item as $data){
                $delete = HotelItem::find($data->id);
                $delete->update(['status' => '1']);
            }
        $hotel->update(['status' => '1']);
        Alert::success('Success Activate Hotel');
        return redirect('admin/hotels');
    }
}
