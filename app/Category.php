<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';
    protected $fillable = ['name', 'code', 'description', 'status'];
    public $timestamps = false;

    public static function valid($id='') {
        return [
        'name' => 'required',
        'code' => 'required|unique:categories,code'.($id ? ",$id" : ''),
        ];
    }

    public function items() {
        return $this->hasMany('App\CategoryItem');
    }
}
