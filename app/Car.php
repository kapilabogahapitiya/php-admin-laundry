<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    protected $table = 'cars';
    protected $fillable = ['name', 'licence', 'status'];
    public $timestamps = false;

    public static function valid($id='') {
        return [
        'name' => 'required',
        'licence' => 'required|unique:cars,licence'.($id ? ",$id" : ''),
        ];
    }
}
