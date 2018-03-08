<?php

namespace App\Http\Controllers;

use App\Category;
use App\Dummy;
use App\Hotel;
use App\HotelEmployee;
use App\HotelItem;
use App\LaundryForm;
use Auth;
use DB;
use Illuminate\Http\Request;

class DashboardsController extends Controller {
    public function dashboard() {
        $total_cf_1month = LaundryForm::collections()->lastMonth()->get()->count();
        $total_df_1month = LaundryForm::deliveries()->lastMonth()->get()->count();
        $cf_approved = LaundryForm::collections()->lastMonth()->where('status', '!=', 3)->where(function ($query) {
            $query->where('status', '=', 2);
            $query->orWhere('status', '=', 5);
        })->get()->count();
        $df_approved = LaundryForm::deliveries()->lastMonth()->where('status', '!=', 3)->where(function ($query) {
            $query->where('status', '=', 2);
            $query->orWhere('status', '=', 5);
        })->get()->count();
        $cf_unapproved = LaundryForm::collections()->lastMonth()->whereNotIn('status', [2, 3, 5])->get()->count();
        $df_unapproved = LaundryForm::deliveries()->lastMonth()->whereNotIn('status', [2, 3, 5])->get()->count();

        $cf = LaundryForm::collections()->last3Months()->select(DB::raw('count(id) as count'), DB::raw('DATE_FORMAT(created_at, "%m") as month'))->groupBy('month')->get();
        $df = LaundryForm::deliveries()->last3Months()->select(DB::raw('count(id) as count'), DB::raw('DATE_FORMAT(created_at, "%m") as month'))->groupBy('month')->get();

        $labels = [];
        foreach ($cf as $key => $value) {
            array_push($labels, getMonth($value->month));
        }
        $labels = json_encode($labels);

        $chart_cf = json_encode($cf->pluck('count')->all());
        $chart_df = json_encode($df->pluck('count')->all());

        $data = compact('total_cf_1month', 'total_df_1month', 'cf_approved', 'df_approved', 'cf_unapproved', 'df_unapproved', 'labels', 'chart_cf', 'chart_df');
        return view('bsb.dashboards.superadmin')->with($data);
    }

    public function general_report() {

        $user = Auth::user();
        $hotel_id = HotelEmployee::findOrFail($user->table_id)->hotel_id;
        $today = \Carbon\Carbon::today();

        $hotel = Hotel::findOrFail($hotel_id);

        $categories = Category::pluck('name', 'id')->all();

        // Bagian 1 general report
        $collection_form_summary = new Dummy();
        $laundry_forms = [];
        $total_weight = 0;
        foreach ($categories as $key => $value) {
            $category = new Dummy();
            $forms = LaundryForm::collections()
                ->select('number', 'weight')
                ->today()
            // ->approveOnly()
                ->where('category_id', '=', $key)
                ->where('hotel_id', '=', $hotel_id)
                ->get();
            $weight = 0;
            foreach ($forms as $form) {
                $weight += $form->weight;
            }
            $category->forms = $forms;
            $total_weight += $weight;
            $category->weight = $weight;

            $laundry_forms[$value] = $category;
        }

        $collection_form_summary->total_weight = $total_weight;
        $collection_form_summary->categories = $laundry_forms;
        // Akhir bagian 1

        // bagian 2
        $collection_form_weight = new Dummy();
        $form_weight = [];
        $open_date = \Carbon\Carbon::now();
        $close_book = \Carbon\Carbon::createFromDate($open_date->year, $open_date->month, $hotel->opening_date)->startOfDay()->addMonth();
        $open_book = \Carbon\Carbon::createFromDate($open_date->year, $open_date->month, $hotel->opening_date)->startOfDay();
        foreach ($categories as $key => $value) {
            $obj = new Dummy();
            $found = LaundryForm::collections()
                ->select(DB::raw('sum(weight) as weight'))
                ->today()
                ->where('category_id', '=', $key)
                ->where('hotel_id', '=', $hotel_id)
                ->groupBy('category_id')
                ->first();
            if ($found) {
                $w = $found->weight;
            } else {
                $w = 0;
            }
            $obj->weight = $w;

            // get weight to date
            $found_sum = LaundryForm::collections()
                ->select(DB::raw('sum(weight) as weight'))
                ->where('category_id', '=', $key)
                ->where('hotel_id', '=', $hotel_id)
                ->where('created_at', '>=', $open_book)
                ->where('created_at', '<', $close_book)
                ->groupBy('category_id')
                ->first();
            if ($found_sum) {
                $w2 = $found_sum->weight;
            } else {
                $w2 = 0;
            }
            $obj->weight_sum = $w2;

            $form_weight[$value] = $obj;
        }
        $collection_form_weight->summary = $form_weight;

        // akhir bagian 2
        //
        // bagian 3

        $start_date = \Carbon\Carbon::now()->startOfMonth();
        $end_date = \Carbon\Carbon::now()->endOfMonth();
        $dataset = [];
        foreach ($categories as $key => $value) {
            $series = DB::select("SELECT COALESCE(SUM(lf.weight), 0) as weight FROM (SELECT DATE_ADD('" . $start_date->format("Y-m-d") . "', INTERVAL `n`.`id` - 1 DAY) as ts FROM `numbers` `n` WHERE DATE_ADD('" . $start_date->format("Y-m-d") . "', INTERVAL `n`.`id` -1 DAY) <= '" . $end_date->format("Y-m-d") . "' ) X LEFT JOIN (SELECT weight, created_at from laundry_forms where category_id = " . $key . " and hotel_id = " . $hotel_id . ") as lf ON STR_TO_DATE(lf.created_at, '%Y-%m-%d') = X.ts  group by X.ts");
            $dt = [];
            foreach ($series as $val) {
                array_push($dt, $val->weight);
            }

            $seri = new Dummy();
            $seri->label = $value;
            $seri->data = $dt;
            $seri->borderColor = rand_color();
            $seri->fill = false;
            array_push($dataset, $seri);
        }

        $labels = [];
        for ($i = (int) $start_date->format('j'); $i <= $end_date->format('j'); $i++) {
            array_push($labels, $i);
        }

        // akhir bagian 3

        // Bagian 4 general report
        $daily_item_transactions = new Dummy();
        $items = [];

        foreach ($categories as $key => $value) {
            $item = HotelItem::select(DB::raw('h.name as hotel_name, ci.name as item_name, c.name as category_name, CONCAT(h.code, sc.code, ci.code) as SKU, SUM(ft.amount_cf) as collection, SUM(ft.amount_df) as delivery, (SUM(ft.amount_cf) - SUM(ft.amount_df)) as balance'))
                ->leftJoin('form_transactions as ft', 'hotel_items.id', '=', 'ft.item_id')
                ->leftJoin('hotels as h', 'ft.hotel_id', '=', 'h.id')
                ->leftJoin('category_items as ci', 'ci.id', '=', 'hotel_items.item_id')
                ->leftJoin('categories as c', 'c.id', '=', 'ci.category_id')
                ->leftJoin('sub_categories as sc', 'sc.id', '=', 'ci.subcategory_id')
                ->leftJoin('laundry_forms as lf', 'lf.id', '=', 'ft.form_id')
                ->where('h.id', '=', $hotel_id)
                ->where('c.id', '=', $key)
                ->where('lf.created_at', '>=', $today)
                ->groupBy('SKU')
                ->get()->toArray();

            $items[$value] = $item;
        }
        $daily_item_transactions->items = $items;
        // Akhir bagian 4
        $data = compact('collection_form_summary', 'daily_item_transactions', 'collection_form_weight', 'labels', 'dataset');

        return view('bsb.general_report.index')->with($data);
    }

    public function filter(Request $request) {
        $start_date = \Carbon\Carbon::parse($request->get('start_date'));
        $end_date = \Carbon\Carbon::parse($request->get('end_date'));
        
        if ($start_date == $end_date) {
            $end_date = \Carbon\Carbon::parse($request->get('end_date') . ' 23:59');
        }

        $user = Auth::user();
        $hotel_id = HotelEmployee::findOrFail($user->table_id)->hotel_id;
        $today = \Carbon\Carbon::today();

        $hotel = Hotel::findOrFail($hotel_id);

        $categories = Category::pluck('name', 'id')->all();

        // Bagian 1 general report
        $collection_form_summary = new Dummy();
        $laundry_forms = [];
        $total_weight = 0;
        foreach ($categories as $key => $value) {
            $category = new Dummy();
            $forms = LaundryForm::collections()
                ->select('number', 'weight')
            // ->approveOnly()
                ->where('category_id', '=', $key)
                ->where('hotel_id', '=', $hotel_id)
                ->where('created_at', '>=', $start_date)
                ->where('created_at', '<=', $end_date)
                ->get();
            $weight = 0;
            foreach ($forms as $form) {
                $weight += $form->weight;
            }
            $category->forms = $forms;
            $total_weight += $weight;
            $category->weight = $weight;

            $laundry_forms[$value] = $category;
        }

        $collection_form_summary->total_weight += $total_weight;
        $collection_form_summary->categories = $laundry_forms;
        // Akhir bagian 1

        // bagian 2
        $collection_form_weight = new Dummy();
        $form_weight = [];
        foreach ($categories as $key => $value) {
            $obj = new Dummy();
            $found = LaundryForm::collections()
                ->select(DB::raw('sum(weight) as weight'))
                ->where('category_id', '=', $key)
                ->where('hotel_id', '=', $hotel_id)
                ->groupBy('category_id')
                ->where('created_at', '>=', $start_date)
                ->where('created_at', '<=', $end_date)
                ->first();
            if ($found) {
                $w = $found->weight;
            } else {
                $w = 0;
            }
            $obj->weight = $w;

            // get weight to date
            $found_sum = LaundryForm::collections()
                ->select(DB::raw('sum(weight) as weight'))
                ->where('category_id', '=', $key)
                ->where('hotel_id', '=', $hotel_id)
                ->where('created_at', '>=', $start_date)
                ->where('created_at', '<=', $end_date)
                ->groupBy('category_id')
                ->first();
            if ($found_sum) {
                $w2 = $found_sum->weight;
            } else {
                $w2 = 0;
            }
            $obj->weight_sum = $w2;

            $form_weight[$value] = $obj;
        }
        $collection_form_weight->summary = $form_weight;

        // akhir bagian 2
        //
        // bagian 3

        $dataset = [];
        foreach ($categories as $key => $value) {
            $series = DB::select("SELECT COALESCE(SUM(lf.weight), 0) as weight FROM (SELECT DATE_ADD('" . $start_date->format("Y-m-d") . "', INTERVAL `n`.`id` - 1 DAY) as ts FROM `numbers` `n` WHERE DATE_ADD('" . $start_date->format("Y-m-d") . "', INTERVAL `n`.`id` -1 DAY) <= '" . $end_date->format("Y-m-d") . "' ) X LEFT JOIN (SELECT weight, created_at from laundry_forms where category_id = " . $key . " and hotel_id = " . $hotel_id . ") as lf ON STR_TO_DATE(lf.created_at, '%Y-%m-%d') = X.ts  group by X.ts");
            $dt = [];
            foreach ($series as $val) {
                array_push($dt, $val->weight);
            }

            $seri = new Dummy();
            $seri->label = $value;
            $seri->data = $dt;
            $seri->borderColor = rand_color();
            $seri->fill = false;
            array_push($dataset, $seri);
        }

        $labels = [];
        for ($i = (int) $start_date->format('j'); $i <= $end_date->format('j'); $i++) {
            array_push($labels, $i);
        }

        // akhir bagian 3

        // Bagian 4 general report
        $daily_item_transactions = new Dummy();
        $items = [];

        foreach ($categories as $key => $value) {
            $item = HotelItem::select(DB::raw('h.name as hotel_name, ci.name as item_name, c.name as category_name, CONCAT(h.code, sc.code, ci.code) as SKU, SUM(ft.amount_cf) as collection, SUM(ft.amount_df) as delivery, (SUM(ft.amount_cf) - SUM(ft.amount_df)) as balance'))
                ->leftJoin('form_transactions as ft', 'hotel_items.id', '=', 'ft.item_id')
                ->leftJoin('hotels as h', 'ft.hotel_id', '=', 'h.id')
                ->leftJoin('category_items as ci', 'ci.id', '=', 'hotel_items.item_id')
                ->leftJoin('categories as c', 'c.id', '=', 'ci.category_id')
                ->leftJoin('sub_categories as sc', 'sc.id', '=', 'ci.subcategory_id')
                ->leftJoin('laundry_forms as lf', 'lf.id', '=', 'ft.form_id')
                ->where('h.id', '=', $hotel_id)
                ->where('c.id', '=', $key)
                ->where('lf.created_at', '>=', $start_date)
                ->where('lf.created_at', '<=', $end_date)
                ->groupBy('SKU')
                ->get()->toArray();

            $items[$value] = $item;
        }
        $daily_item_transactions->items = $items;
        // Akhir bagian 4
        $data = compact('collection_form_summary', 'daily_item_transactions', 'collection_form_weight', 'labels', 'dataset');

        if ($request->ajax()) {
            $view = (String) view('bsb.general_report.filter')
                ->with($data)
                ->render();
            return response()->json(['view' => $view, 'start_date' => $start_date, 'end_date' => $end_date, 'data' => $data]);
        } else {
            return response()->json($data, 200);
        }
    }
}
