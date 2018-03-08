<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ZoneRunner extends Model
{
    protected $table = 'zone_runner';
    protected $fillable = ['zone_id', 'runner_id', 'status'];
    public $timestamps = false;
    protected $primaryKey = 'runner_id';

    public static function valid($id='') {
        return [
        'zone_id' => 'required',
        'runner_id' => 'required',
        ];
    }

    public static function updateValid($id='') {
        return [
            'zone_id' => 'required'
        ];
    }

    public function zone() {
        return $this->belongsTo('App\Zone', 'zone_id');
    }

    public function runner() {
        return $this->belongsTo('App\TexcareEmployee', 'runner_id');
    }
}
