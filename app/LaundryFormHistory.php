<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LaundryFormHistory extends Model
{
    protected $table = 'laundry_form_histories';
    protected $fillable = ['id', 'user_id', 'form_id'];
    public $timestamps = true;

    public static function valid($id='') {
        return [
        ];
    }

    public function user() {
        return $this->belongsTo('App\User');
    }

    public function form() {
        return $this->belongsTo('App\LaundryForm', 'form_id');
    }
}
