<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\FormTransaction;
use App\HotelEmployee, App\FactoryEmployee, App\ZoneRunner;
use DB, Auth;

class TransactionsController extends Controller
{
    public function index() {
        $transactions = FormTransaction::select('laundry_forms.type as form_type', 'laundry_forms.number as form_number', 'hotels.name as hotel_name', 'hotels.code as hotel_code', 'hotels.status as hotel_status', 'hotels.customer_code as hotel_customer_code', 'sub_categories.code as subcategory_code', 'sub_categories.status as subcategory_status', 'laundry_forms.factory_id', 'category_items.code as item_code', 'category_items.status as item_status', DB::raw('CONCAT(hotels.code, sub_categories.code, category_items.code) as SKU'), 'category_items.name as item_name', 'form_transactions.*')
                        ->leftJoin('laundry_forms', 'laundry_forms.id', '=', 'form_transactions.form_id')
                        ->leftJoin('hotels', 'hotels.id', '=', 'form_transactions.hotel_id')
                        ->leftJoin('hotel_items', 'hotel_items.id', '=', 'form_transactions.item_id')
                        ->leftJoin('category_items', 'category_items.id', '=', 'hotel_items.item_id')
                        ->leftJoin('sub_categories', 'sub_categories.id', '=', 'category_items.subcategory_id');

        $user = Auth::user();
        if ($user->hasRole('hotel_supervisor')) {
            $hotel_id = HotelEmployee::findOrFail($user->table_id)->hotel_id;
            $transactions = $transactions->where('form_transactions.hotel_id', '=', $hotel_id)->get();
        } elseif ($user->hasRole('pabrik_admin')) {
            $factory_id = FactoryEmployee::findOrFail($user->table_id)->factory_id;
            $transactions = $transactions->where('laundry_forms.factory_id', '=', $factory_id)->get();
        } elseif ($user->hasRole('texcare_runner')) {
            $zone = ZoneRunner::where('runner_id', '=', $user->table_id)->firstOrFail()->zone;
            $hotels_id = $zone->hotels()->get()->pluck('id')->all();
            $transactions = $transactions->whereIn('form_transactions.hotel_id', $hotels_id)->get();
        } else {
            $transactions = $transactions->get();
        }


        $data = compact('transactions');
        return view('bsb.transactions.index')->with($data);
    }
}
