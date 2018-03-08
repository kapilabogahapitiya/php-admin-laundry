<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LaundryForm extends Model {
    protected $table = 'laundry_forms';
    protected $fillable = ['id', 'type', 'number', 'category_id', 'hotel_id', 'weight', 'speed', 'service', 'allocation', 'driver_id', 'car_id', 'created_at', 'created_by', 'approved_at', 'approved_by', 'req_revision_at', 'req_revision_by', 'req_revision_runner_by','rev_rejected_by', 'rev_rejected_at', 'rev_cancelled_by', 'rev_cancelled_at', 'approved_revision_at', 'approved_revision_by', 'factory_id', 'status', 'room', 'note', 'is_balancing'];
    public $timestamps = true;

    public static function valid($id = '') {
        return [
        ];
    }

    public function scopeFull($query) {
        return $query->where('status', '!=', 3);
    }

    public function scopeCollections($query) {
        return $query->where('type', '=', 1)->where('status', '!=', 3);
    }
    public function scopeZoneleader($query) {
        return $query->where('type', '=', 1)->where('laundry_forms.status', '!=', 3);
    }

    public function scopeDeliveries($query) {
        return $query->where('type', '=', 2)->where('status', '!=', 3);
    }

    public function scopeDeliverieszone($query) {
        return $query->where('type', '=', 2)->where('laundry_forms.status', '!=', 3);
    }

    public function scopeLast30Days($query) {
        return $query->where('created_at', '>=', \Carbon\Carbon::now()->subMonth());
    }

    public function scopeLast90Days($query) {
        return $query->where('created_at', '>=', \Carbon\Carbon::now()->subMonth(3));
    }

    public function scopeLastMonth($query) {
        return $query->where('created_at', '>=', \Carbon\Carbon::now()->startOfMonth());
    }

    public function scopeLast3Months($query) {
        return $query->where('created_at', '>=', \Carbon\Carbon::now()->subMonth(3)->startOfMonth());
    }

    public function scopeToday($query) {
        return $query->where('created_at', '>=', \Carbon\Carbon::today());
    }

    public function scopeApproveOnly($query) {
        return $query->where('status', '=', 2)->orWhere('status', '=', 5);
    }

    public function category() {
        return $this->belongsTo('App\Category');
    }

    public function hotel() {
        return $this->belongsTo('App\Hotel');
    }

    public function factory() {
        return $this->belongsTo('App\Factory');
    }

    public function driver() {
        return $this->belongsTo('App\Driver');
    }

    public function car() {
        return $this->belongsTo('App\Car');
    }

    public function creator() {
        return $this->belongsTo('App\User', 'created_by');
    }

    public function approved() {
        return $this->belongsTo('App\User', 'approved_by');
    }

    public function revised() {
        return $this->belongsTo('App\User', 'req_revision_by');
    }

    public function runner_revised() {
        return $this->belongsTo('App\User', 'req_revision_runner_by');
    }

    public function rev_cancelled() {
        return $this->belongsTo('App\User', 'rev_cancelled_by');
    }

    public function rev_rejected() {
        return $this->belongsTo('App\User', 'rev_rejected_by');
    }
    
    public function approved_revision() {
        return $this->belongsTo('App\User', 'approved_revision_by');
    }

    public function items() {
        return $this->hasMany('App\LaundryFormItem', 'form_id');
    }

    public function transactions() {
        return $this->hasMany('App\FormTransaction', 'form_id');
    }

    public function histories() {
        return $this->hasMany('App\LaundryFormHistory', 'form_id');
    }

    public function has_revised() {
        if (strpos($this->number, 'UPREV')) {
            return true;
        }
        return false;
    }

    public function revised_form() {
        $old_number = rtrim($this->number, '-UPREV');
        $old_form = LaundryForm::where('number', '=', $old_number)->first();

        return $old_form;
    }
}
