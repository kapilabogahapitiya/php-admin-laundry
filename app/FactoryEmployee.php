<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\User;

class FactoryEmployee extends Model
{
    protected $table = 'factory_employees';
    protected $fillable = ['name', 'address', 'city', 'province', 'email', 'hp', 'working_since', 'ktp', 'factory_id', 'status'];
    public $timestamps = false;

    public static function valid($id='') {
        return [
        'name' => 'required',
        'email' => 'required|email|unique:factory_employees,email'.($id ? ",$id" : ''),
        'ktp' => 'required'
        ];
    }

    public function factory() {
        return $this->belongsTo('App\Factory');
    }

    public function user() {
        $user = User::where('table_name', '=', 'factory_employees')->where('table_id', '=', $this->id)->first();
        return $user;
    }
}
