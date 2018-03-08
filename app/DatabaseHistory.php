<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DatabaseHistory extends Model
{
    protected $table = 'database_history';

    protected $fillable = ['type', 'user_id', 'created_at'];

    public function user() {
        return $this->belongsTo('App\User', 'user_id');
    }

    public function scopeBackup($query) {
        return $query->where('type', '=', 'backup');
    }

    public function scopeRestore($query) {
        return $query->where('type', '=', 'restore');
    }
}
