<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FormNumber extends Model
{
    protected $table = 'form_numbers';
    protected $fillable = ['type', 'year', 'GL', 'HK', 'SP', 'UN', 'FB'];
    public $timestamps = false;

    public static function valid($id='') {
        return [
        'year' => 'numeric',
        'GL' => 'numeric',
        'HK' => 'numeric',
        'SP' => 'numeric',
        'UN' => 'numeric',
        'FB' => 'numeric'
        ];
    }
}
