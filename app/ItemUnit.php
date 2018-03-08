<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ItemUnit extends Model
{
    protected $table = 'item_units';
    protected $fillable = ['name', 'code', 'description'];
    public $timestamps = false;

    public static function valid($id='') {
        return [
        'name' => 'required',
        'code' => 'required|unique:zones,code'.($id ? ",$id" : ''),
        ];
    }
}
