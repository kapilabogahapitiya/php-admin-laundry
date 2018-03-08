<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SubCategory extends Model
{
    protected $table = 'sub_categories';
    protected $fillable = ['name', 'code', 'description', 'status'];
    public $timestamps = false;

    public static function valid($id='') {
        return [
        'name' => 'required',
        'code' => 'required|unique:sub_categories,code'.($id ? ",$id" : ''),
        ];
    }

    public function items() {
        return $this->hasMany('App\CategoryItem');
    }
}
