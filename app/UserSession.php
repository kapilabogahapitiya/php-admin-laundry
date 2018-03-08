<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserSession extends Model {
    protected $table = 'user_session';
    protected $fillable = ['user_id', 'session_id', 'created_at', 'updated_at'];

    public static function valid() {
        return [
            'user_id' => 'required',
            'session_id' => 'required',
        ];
    }
}
