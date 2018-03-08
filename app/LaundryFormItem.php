<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LaundryFormItem extends Model
{
    protected $table = 'laundry_form_items';
    protected $fillable = ['id', 'form_id', 'item_id', 'amount'];
    public $timestamps = false;

    public static function valid($id='') {
        return [
        ];
    }

    public function item() {
        return $this->belongsTo('App\HotelItem', 'item_id');
    }

    public function form() {
        return $this->belongsTo('App\LaundryForm', 'form_id');
    }

    public function sku() {
        $form = $this->form;
        $item = $this->item;

        $sku = $form->hotel->code.$item->sub_category->code.$item->code;

        return $sku;
    }
}
