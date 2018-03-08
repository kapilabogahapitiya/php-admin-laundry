<?php

namespace App\Http\Controllers;

use App\CategoryItem;
use App\FormTransaction;
use App\Hotel;
use App\HotelEmployee;
use App\ZoneRunner;
use Auth;
use DB;
use Illuminate\Http\Request;

class BalancesController extends Controller {
    public function index() {
        $user = Auth::user();
        if ($user->hasRole(['hotel_supervisor', 'hotel_employee'])) {
            $hotel_id = HotelEmployee::findOrFail($user->table_id)->hotel_id;
            $hotels = Hotel::where('id', '=', $hotel_id)->get()->pluck('name', 'id')->all();
        } elseif ($user->hasRole(['super_admin', 'pabrik_admin', 'texcare_zona_leader', 'texcare_supervisor'])) {
            $hotels = Hotel::all()->pluck('name', 'id')->all();
            $hotels = [0 => 'All hotels'] + $hotels;
        } else {
            abort(403, 'You are not authorized');
        }

        $items = CategoryItem::all()->pluck('name', 'id')->all();

        $data = compact('hotels', 'items');
        return view('bsb.balances.index')->with($data);
    }

    public function filter(Request $request) {
        $hotel_id = $request->get('hotel_id');
        $item_id = $request->get('item_id');
        $start = $request->get('from');
        $end = $request->get('to');

        $transactions = FormTransaction::select('laundry_forms.type as form_type', 'laundry_forms.number as form_number', 'hotels.name as hotel_name', 'hotels.code as hotel_code', 'hotels.status as hotel_status', 'hotels.customer_code as hotel_customer_code', 'sub_categories.code as subcategory_code', 'sub_categories.status as subcategory_status', 'laundry_forms.factory_id', 'category_items.code as item_code', 'category_items.status as item_status', DB::raw('CONCAT(hotels.code, sub_categories.code, category_items.code) as SKU'), 'category_items.name as item_name', 'form_transactions.*', DB::raw('DATE(form_transactions.created_at) as created_at'))
            ->leftJoin('laundry_forms', 'laundry_forms.id', '=', 'form_transactions.form_id')
            ->leftJoin('hotels', 'hotels.id', '=', 'form_transactions.hotel_id')
            ->leftJoin('hotel_items', 'hotel_items.id', '=', 'form_transactions.item_id')
            ->leftJoin('category_items', 'category_items.id', '=', 'hotel_items.item_id')
            ->leftJoin('sub_categories', 'sub_categories.id', '=', 'category_items.subcategory_id')
            ->where('form_transactions.created_at', '>=', $start)
            ->where('form_transactions.created_at', '<=', $end)
            ->orderBy('created_at', 'ASC');

        $sum = FormTransaction::select(DB::raw('SUM(amount_cf) as amount_cf'),
            DB::raw('SUM(amount_df) as amount_df'))
            ->leftJoin('laundry_forms', 'laundry_forms.id', '=', 'form_transactions.form_id')
            ->leftJoin('hotels', 'hotels.id', '=', 'form_transactions.hotel_id')
            ->leftJoin('hotel_items', 'hotel_items.id', '=', 'form_transactions.item_id')
            ->leftJoin('category_items', 'category_items.id', '=', 'hotel_items.item_id')
            ->leftJoin('sub_categories', 'sub_categories.id', '=', 'category_items.subcategory_id')
            ->where('form_transactions.created_at', '<', $start);

        if ($hotel_id > 0) {
            $transactions = $transactions->where('laundry_forms.hotel_id', '=', $hotel_id);
            $sum = $sum->where('laundry_forms.hotel_id', '=', $hotel_id);
        }

        if ($item_id > 0) {
            $transactions = $transactions->where('category_items.id', '=', $item_id);
            $sum = $sum->where('category_items.id', '=', $item_id);
        }

        $sum = $sum->first();
        $saldo_awal = $sum->amount_cf - $sum->amount_df;

        $user = Auth::user();
        if ($user->hasRole('hotel_supervisor')) {
            $hotel_id = HotelEmployee::findOrFail($user->table_id)->hotel_id;
            $transactions = $transactions->where('form_transactions.hotel_id', '=', $hotel_id)->get();
        } elseif ($user->hasRole('texcare_runner')) {
            $zone = ZoneRunner::where('runner_id', '=', $user->table_id)->firstOrFail()->zone;
            $hotels_id = $zone->hotels()->get()->pluck('id')->all();
            $transactions = $transactions->whereIn('form_transactions.hotel_id', $hotels_id)->get();
        } else {
            $transactions = $transactions->get();
        }

        $data = compact('transactions', 'saldo_awal');
        if ($request->ajax()) {
            $view = (String) view('bsb.balances.row')
                ->with($data)
                ->render();
            return response()->json(['view' => $view, 'saldo_awal' => $saldo_awal]);
        } else {
            return response()->json($data, 200);
        }
    }
}
