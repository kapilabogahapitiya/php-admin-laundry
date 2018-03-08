<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Category;
use App\Dummy;
use App\Http\Requests;
use App\FormTransaction;
use App\LaundryForm;
use App\Hotel;
use App\Scale;
use App\HotelEmployee;
use App\HotelItem;
use Auth, DB, Session;

class ReportsController extends Controller
{
    public function daily(Request $request) {
        $type = $request->get('type');
        $hotel_id = $request->get('hotel_id');
        $hotel = null;

        $today = \Carbon\Carbon::today();
        $start = \Carbon\Carbon::now()->subDay()->startOfDay();
        $end = \Carbon\Carbon::now()->startOfDay();

        if ($hotel_id != null) {
            $hotel = Hotel::findOrFail($hotel_id);
        } else {
            $hotel = Hotel::all();

            // $collection_form_summary = new Dummy();
            // $daily_item_transactions = new Dummy();
            // $collection_form_weight = new Dummy();

            $collection_form_summary = [];
            $collection_form_summary['total_weight'] = 0;
            $daily_item_transactions = [];
            $daily_item_transactions['items'] = [];
            $collection_form_weight = [];
            $collection_form_weight['summary'] = [];
            $labels = [];
            $items = [];

            foreach ($hotel as $hotel) {
                $hotel_id = $hotel->id;
                $categories = Category::pluck('name', 'id')->all();
                // Bagian 1 general report
                $laundry_forms = [];
                $total_weight = 0;
                foreach ($categories as $key => $value) {
                    $category = new Dummy();
                    $forms = LaundryForm::collections()
                        ->select('number', 'weight')
                        ->where('category_id', '=', $key)
                        ->where('hotel_id', '=', $hotel_id)
                        ->where('created_at', '>=', $today)
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

                $collection_form_summary['total_weight'] += $total_weight;

                if (! empty($collection_form_summary['categories'])) {
                    foreach ($laundry_forms as $laundry_forms_key => $laundry_forms_each) {

                        if (array_key_exists($laundry_forms_key, $collection_form_summary['categories'])) {
                            for ($i=0; $i < count($laundry_forms_each->forms); $i++) { 
                                $collection_form_summary['categories'][$laundry_forms_key]->forms->push($laundry_forms_each->forms[$i]);
                            }
                        } else {
                            if (is_array($laundry_forms_each->forms)) {
                                $collection_form_summary['categories'] = array_merge($collection_form_summary['categories'], [$laundry_forms_key => $laundry_forms_each]);
                            }
                        }

                        $collection_form_summary['categories'][$laundry_forms_key]->weight += $laundry_forms_each->weight;
                    }
                } else {
                    $collection_form_summary['categories'] = $laundry_forms;
                }


                // Akhir bagian 1

                // bagian 2
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
                        ->where('created_at', '>=', $today)
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
                        ->where('created_at', '>=', $today)
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

                if (! empty($collection_form_weight['summary'])) {
                    foreach ($form_weight as $form_weight_key => $form_weight_each) {
                        if (array_key_exists($form_weight_key, $collection_form_weight['summary'])) {
                            if (count($form_weight_each->weight) > 0 && count($form_weight_each->weight_sum) > 0) {

                                $collection_form_weight['summary'][$form_weight_key]->weight += $form_weight_each->weight;
                                $collection_form_weight['summary'][$form_weight_key]->weight_sum += $form_weight_each->weight_sum;
                            }
                        } else {
                            if (is_array($form_weight_each->forms)) {
                                $collection_form_weight['summary'] = array_merge($collection_form_weight_array['summary'], [$form_weight_key, $form_weight_each]);
                            }
                        }
                    }
                } else {
                    $collection_form_weight['summary'] = $form_weight;
                }

                // akhir bagian 2
                //
                // bagian 3
                // akhir bagian 3

                // Bagian 4 general report

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

                if (! empty($daily_item_transactions['items'])) {
                    foreach ($items as $items_key => $items_each) {
                        if (array_key_exists($items_key, $daily_item_transactions['items'])) {
                            
                            if (count($items_each) > 0 && count($items_each) == 1) {
                                for ($i=0; $i < count($items_each); $i++) {
                                    $daily_item_transactions['items'][$items_key] = array_merge($daily_item_transactions['items'][$items_key], $items_each);
                                }
                            } else {
                                for ($i=0; $i < count($items_each) - 1; $i++) {
                                    $daily_item_transactions['items'][$items_key] = array_merge($daily_item_transactions['items'][$items_key], $items_each);
                                }
                            }
                        } else {
                            if (is_array($form_weight_each->forms)) {
                                $daily_item_transactions['items'] = array_merge($daily_item_transactions['items'], [$items_key, $items_each]);
                            }
                        }
                    }
                } else {
                    $daily_item_transactions['items'] = $items;
                }

                // Akhir bagian 4

                $template = 'bsb.reports.daily';
                switch ($type) {
                    case 'plain':
                        $template = 'bsb.reports.daily_plain';
                        break;

                    default:
                        # code...
                        break;
                }
            }

            /**
             * Success List
             */
            // dd($collection_form_weight);
            // dd($collection_form_summary);
            // dd($daily_item_transactions);

            $data = compact('collection_form_summary', 'daily_item_transactions', 'collection_form_weight', 'today');
            return view($template)->with($data);
        }

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
            $seri->backgroundColor = rand_color();
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

        $template = 'bsb.reports.daily';
        switch ($type) {
            case 'plain':
                $template = 'bsb.reports.daily_plain';
                break;

            default:
                # code...
                break;
        }

        $data = compact('collection_form_summary', 'daily_item_transactions', 'collection_form_weight', 'labels', 'dataset', 'today');

        return view($template)->with($data);
    }
}
