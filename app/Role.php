<?php

namespace App;

use Laratrust\LaratrustRole;

class Role extends LaratrustRole
{
    protected $fillable = ['name', 'display_name', 'description', 'created_at', 'updated_at'];

    public static function valid($id='') {
        return [
            'name' => 'required|min:4|unique:roles,name'.($id ? ",$id" : ''),
            'display_name' => 'required'
        ];
    }

    public function user_roles() {
        return $this->hasMany('App\RoleUser');
    }
}
