<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class HotelItem extends Model
{
    protected $table = 'hotel_items';
    protected $fillable = ['item_id', 'hotel_id', 'unit_id', 'price', 'status'];
    public $timestamps = true;

    public static function valid($id='') {
        return [
        'item_id' => 'required',
        'hotel_id' => 'required',
        'unit_id' => 'required',
        'price' => 'required'
        ];
    }

    public function hotel() {
        return $this->belongsTo('App\Hotel');
    }

    public function unit() {
        return $this->belongsTo('App\ItemUnit', 'unit_id');
    }

    public function item() {
        return $this->belongsTo('App\CategoryItem', 'item_id');
    }

    public function getSKUAttribute()
    {
        return $this->hotel->code . $this->item->sub_category->code . $this->item->code;
    }
}
