<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CategoryItem extends Model
{
    protected $table = 'category_items';
    protected $fillable = ['category_id', 'subcategory_id', 'name', 'code', 'description', 'status'];
    public $timestamps = false;

    public static function valid($id='') {
        return [
        'category_id' => 'required',
        'name' => 'required',
        'code' => 'required',
        ];
    }

    public function category() {
        return $this->belongsTo('App\Category');
    }

    public function sub_category() {
        return $this->belongsTo('App\SubCategory', 'subcategory_id');
    }

    public function getCompleteNameAttribute()
    {
        return $this->category->name .' : '. $this->sub_category->code . $this->code . ' - ' . $this->name;
    }
}
