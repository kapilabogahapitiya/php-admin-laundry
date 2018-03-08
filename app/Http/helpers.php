<?php
use App\Dummy;

function get_factory_employee_role($employee) {

}

function category_speed() {
    return [1 => 'Regular', 2 => 'Express'];
}

function get_category_speed($id) {
    return category_speed()[$id];
}

function category_service() {
    return [1 => 'Laundry', 2 => 'Dry Clean', 3 => 'Pressing', 4 => 'Drying Only'];
}

function get_category_service($id) {
    return category_service()[$id];
}

function category_allocation() {
    return [1 => 'Gentle', 2 => 'Ladies', 3 => 'Kids'];
}

function get_category_allocation($id) {
    return category_allocation()[$id];
}

function form_status() {
    // Due to revision from 13 April 2017
    // return [
    //     0 => 'Created',
    //     1 => 'Waiting Hotel Approval',
    //     2 => 'Approved by Hotel',
    //     3 => 'Not Used',
    //     4 => 'Waiting revision approval',
    //     5 => 'Revision approved'];

    return [
        0 => 'Created',
        1 => 'Submit for Hotel Approval',
        2 => 'Approved by Hotel',
        3 => 'Not Used',
        4 => 'Waiting for Rev Approval',
        5 => 'Revision approved',
        6 => 'Submit for Hotel Rev Approval',
        7 => 'Revision Rejected by Hotel'];
}

function get_form_status($id, $type, $args = null) {
    // Due to revision in 13 April 2017
    // return form_status()[$id];
    $status = form_status()[$id];

    if ($id == 4) {
        if ($type == 1) {
            return $status . ' from Hotel';
        } elseif ($type == 2) {
            return $status . ' from Admin';
        } else {
            // tolerance
            return $status;
        }
    } else {
        return $status;
    }
}

function form_approvee($form) {
    $approvee = new Dummy();
    $approvee->name = '-';
    $approvee->hp = '-';

    if ($form->type == 1) {
        if ($form->approved_revision_by != null) {
            $approvee = $form->approved_revision->assigned();
        } elseif ($form->approved_by != null) {
            $approvee = $form->approved->assigned();
        }
    } else {
        if ($form->req_revision_by != null) {
            $approvee = $form->revised->assigned();
        } elseif ($form->approved_by != null) {
            $approvee = $form->approved->assigned();
        }
    }

    return $approvee;
}

function getMonth($mo) {
    $months = [
        '01' => 'January',
        '02' => 'February',
        '03' => 'Maret',
        '04' => 'April',
        '05' => 'Mei',
        '06' => 'Juni',
        '07' => 'Juli',
        '08' => 'Agustus',
        '09' => 'September',
        '10' => 'Oktober',
        '11' => 'November',
        '12' => 'Desember',
    ];

    return $months[$mo];
}

function rand_color() {
    return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
}
