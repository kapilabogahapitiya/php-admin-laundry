<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class HotelEmployee extends Model
{
    protected $table = 'hotel_employees';
    protected $fillable = ['name', 'address', 'city', 'province', 'email', 'hp', 'working_since', 'ktp', 'hotel_id', 'status'];
    public $timestamps = false;

    public static function valid($id='') {
        return [
        'name' => 'required',
        'email' => 'required|email|unique:hotel_employees,email'.($id ? ",$id" : ''),
        'ktp' => 'required'
        ];
    }

    public function user() {
        $user = User::where('table_name', '=', 'hotel_employees')->where('table_id', '=', $this->id)->first();
        return $user;
    }

    public function hotel() {
        return $this->belongsTo('App\Hotel');
    }
}
