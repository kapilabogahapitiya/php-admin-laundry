<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laratrust\Traits\LaratrustUserTrait;
use DB;

class User extends Authenticatable
{
    use LaratrustUserTrait;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'username', 'email', 'password', 'forgot_token', 'table_name', 'table_id', 'status'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public static function valid_update_forgot() {
        return [
            'password' => 'required|min: 3|confirmed'
        ];
    }

    public static function valid($id='') {
        return [
        'name' => 'required',
        'username' => 'required|min:4|regex:/(^[A-Za-z][A-Za-z0-9!@#$%^&_*]*$)+/|unique:users,username'.($id ? ",$id" : ''),
        'email' => 'required|email|unique:users,email'.($id ? ",$id" : ''),
        'password' => 'required|min:3'
        ];
    }
    public static function valid_update($id='') {
        return [
        'name' => 'required',
        'username' => 'required|min:4|regex:/(^[A-Za-z][A-Za-z0-9!@#$%^&_*]*$)+/|unique:users,username'.($id ? ",$id" : ''),
        'email' => 'required|email|unique:users,email'.($id ? ",$id" : ''),
        'password' => 'min:3'
        ];
    }

    public function assigned() {
        if ($this->hasRole('super_admin')) {
            $this->hp = $this->phone;
            return $this;
        } else {
            $table = $this->table_name;
            $id = $this->table_id;
            return DB::table($table)->where('id', '=', $id)->first();
        }
    }
}
