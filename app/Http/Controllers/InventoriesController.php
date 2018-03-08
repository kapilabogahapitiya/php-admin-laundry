<?php

namespace App\Http\Controllers;

use App\FormTransaction;
use App\HotelEmployee;
use Auth;
use DB;

class InventoriesController extends Controller {
    public function index() {
        $inventories = FormTransaction::select('hotels.name as hotel_name',
            DB::raw('SUM(amount_cf) as amount_cf'),
            DB::raw('SUM(amount_df) as amount_df'),
            'hotels.customer_code as hotel_customer_code',
            'hotels.status as hotel_status',
            'sub_categories.code as subcategory_code',
            'sub_categories.status as subcategory_status',
            'category_items.code as item_code',
            'category_items.status as item_status',
            DB::raw('CONCAT(hotels.code, sub_categories.code, category_items.code) as SKU'),
            'category_items.name as item_name',
            'categories.code as category_code',
            'categories.status as category_status',
            'form_transactions.created_at')
            ->leftJoin('hotels', 'hotels.id', '=', 'form_transactions.hotel_id')
            ->leftJoin('hotel_items', 'hotel_items.id', '=', 'form_transactions.item_id')
            ->leftJoin('category_items', 'category_items.id', '=', 'hotel_items.item_id')
            ->leftJoin('sub_categories', 'sub_categories.id', '=', 'category_items.subcategory_id')
            ->leftJoin('categories', 'categories.id', '=', 'category_items.category_id')
            ->groupBy('SKU');

        $user = Auth::user();
        if ($user->hasRole('hotel_supervisor') || $user->hasRole('hotel_employee')) {
            $hotel_id = HotelEmployee::findOrFail($user->table_id)->hotel_id;
            $inventories = $inventories->where('form_transactions.hotel_id', '=', $hotel_id)->get();
        } elseif ($user->hasRole('texcare_runner')) {
            abort(403, 'Unauthorized action.');
        } else {
            $inventories = $inventories->get();
        }
        $data = compact('inventories');
        return view('bsb.inventories.index')->with($data);
    }
}
