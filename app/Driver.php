<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $table = 'drivers';
    protected $fillable = ['name', 'address', 'city', 'province', 'email', 'hp', 'working_since', 'ktp', 'status'];
    public $timestamps = false;

    public static function valid($id='') {
        return [
        'name' => 'required',
        'ktp' => 'required'
        ];
    }
}
