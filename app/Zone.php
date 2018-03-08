<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    protected $table = 'zones';
    protected $fillable = ['name', 'code', 'description', 'fe_id', 'status'];
    public $timestamps = false;

    public static function valid($id='') {
        return [
        'name' => 'required',
        'code' => 'required|unique:zones,code'.($id ? ",$id" : ''),
        ];
    }

    public function leader() {
        return $this->belongsTo('App\TexcareEmployee', 'fe_id');
    }

    public function hotels() {
        return $this->hasMany('App\Hotel');
    }
}
