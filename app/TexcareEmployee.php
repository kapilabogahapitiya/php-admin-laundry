<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TexcareEmployee extends Model
{
    protected $table = 'texcare_employees';
    protected $fillable = ['name', 'address', 'city', 'province', 'email', 'hp', 'working_since', 'ktp', 'status'];
    public $timestamps = false;

    public static function valid($id='') {
        return [
        'name' => 'required',
        'email' => 'required|email|unique:texcare_employees,email'.($id ? ",$id" : ''),
        'ktp' => 'required'
        ];
    }

    public function user() {
        $user = User::where('table_name', '=', 'texcare_employees')->where('table_id', '=', $this->id)->first();
        return $user;
    }

    public function assigned_zone() {
        $zone = '';
        $user = $this->user();
        if ($user->hasRole('texcare_runner')) {
            $zone_runner = \App\ZoneRunner::where('runner_id', '=', $user->table_id)->first();
            if ($zone_runner) {
                if($zone_runner->zone->status=='1'){
                    $zone = $zone_runner->zone->name;
                } else {
                    $zone = $zone_runner->zone->name.' [ deleted ]';
                }
            }
        } elseif ($user->hasRole('texcare_zona_leader')) {
            $zone_leader = \App\Zone::where('fe_id', '=', $user->table_id)->first();
            if ($zone_leader) {
                if($zone_leader->status == '1'){
                    $zone = $zone_leader->name;
                } else {
                    $zone = $zone_leader->name.' [ deleted ]';
                }
            }
        }

        return $zone;
    }
}
