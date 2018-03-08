<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Scale extends Model
{
    protected $table = 'scales';
    protected $fillable = ['hotel_id', 'factory_id', 'user_id', 'amount', 'category_id', 'subcategory_id'];
    public $timestamps = true;

    public static function valid($id='') {
        return [
        'amount' => 'required'
        ];
    }

    public function hotel() {
        return $this->belongsTo('App\Hotel');
    }

    public function category() {
        return $this->belongsTo('App\Category');
    }

    public function subCategory() {
        return $this->belongsTo('App\SubCategory');
    }
}
