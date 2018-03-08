<?php

namespace App;

use Laratrust\LaratrustPermission;

class Permission extends LaratrustPermission
{
    protected $fillable = ['name', 'display_name', 'description', 'created_at', 'updated_at'];

    public static function valid($id='') {
        return [
            'name' => 'required|min:4|unique:permissions,name'.($id ? ",$id" : ''),
            'display_name' => 'required'
        ];
    }
}
