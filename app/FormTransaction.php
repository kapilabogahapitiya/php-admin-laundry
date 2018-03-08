<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FormTransaction extends Model
{
    protected $table = 'form_transactions';
    protected $fillable = ['id', 'form_id', 'hotel_id', 'item_id', 'amount_cf', 'amount_df', 'price'];
    public $timestamps = true;

    public static function valid($id='') {
        return [
        'form_id' => 'numeric|required',
        'hotel_id' => 'numeric|required',
        'item_id' => 'numeric|required',
        'amount_cf' => 'numeric',
        'amount_df' => 'numeric',
        'price' => 'numeric'
        ];
    }

    public function hotel() {
        return $this->belongsTo('App\Hotel');
    }

    public function form() {
        return $this->belongsTo('App\LaundryForm', 'form_id');
    }

    public function item() {
        return $this->belongsTo('App\HotelItem', 'item_id');
    }
}
