<?php

namespace App;

use App\Role;
use Illuminate\Database\Eloquent\Model;

class Hotel extends Model {
    protected $table = 'hotels';
    protected $fillable = ['customer_code', 'name', 'address', 'city', 'province', 'customer_id', 'phone', 'fax', 'email', 'customer_since', 'zone_id', 'code', 'opening_date', 'status'];
    public $timestamps = false;

    public static function valid($id = '') {
        return [
            'name' => 'required',
            'customer_code' => 'required|min:4|unique:hotels,customer_code' . ($id ? ",$id" : ''),
            'code' => 'required|min:2|unique:hotels,code' . ($id ? ",$id" : ''),
            'opening_date' => 'required|numeric|min:1|max:31',
        ];
    }

    public function zone() {
        return $this->belongsTo('App\Zone');
    }

    public function full_address() {
        $arr = [];
        if ($this->address) {
            array_push($arr, $this->address);
        }

        if ($this->city) {
            array_push($arr, $this->city);
        }

        if ($this->province) {
            array_push($arr, $this->province);
        }

        return implode(', ', $arr);
    }

    public function hotelPics() {
        $role = Role::findOrFail(6);
        $pics = $role->users()
            ->select('users.id as user_id', 'hotel_employees.*', 'hotels.name as hotel_name')
            ->leftJoin('hotel_employees', 'users.table_id', '=', 'hotel_employees.id')
            ->leftJoin('hotels', 'hotels.id', '=', 'hotel_employees.hotel_id')
            ->where('hotels.id', '=', $this->id)
            ->get();

        return $pics;
    }

    public function items() {
        return $this->hasMany('App\HotelItem');
    }

    public function items_array($cat_id = null) {
        if ($cat_id == null) {
            $items = $this->items;
        } else {
            $items = $this->items()
                ->select('category_items.name', 'category_items.code', 'hotel_items.*')
                ->leftJoin('category_items', 'category_items.id', '=', 'hotel_items.item_id')
                ->where('category_items.category_id', '=', $cat_id)
                ->get();
        }

        $data = [];
        foreach ($items as $key => $item) {
            $data[$item->id] = $item->item->category->code . ' - ' . $item->unit->code . ' - ' . $item->SKU . ' - ' . $item->item->name;
        }

        return $data;
    }

    public function employees() {
        return $this->hasMany('App\HotelEmployee');
    }
}
