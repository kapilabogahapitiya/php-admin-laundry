<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Factory extends Model
{
    protected $table = 'factories';
    protected $fillable = ['name', 'address', 'city', 'province', 'code', 'status'];
    public $timestamps = false;

    public static function valid($id='') {
        return [
        'name' => 'required',
        'code' => 'required|unique:factories,code'.($id ? ",$id" : '')
        ];
    }

    public function factory_admins() {
        $role = Role::findOrFail(2);
        $admins = $role->users()
                ->select('users.id as user_id', 'factory_employees.*', 'factories.name as factory_name')
                ->leftJoin('factory_employees', 'users.table_id', '=', 'factory_employees.id')
                ->leftJoin('factories', 'factories.id', '=', 'factory_employees.factory_id')
                ->where('factories.id', '=', $this->id)
                ->get();

        return $admins;
    }
}
