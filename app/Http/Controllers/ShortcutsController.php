<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use Auth;

class ShortcutsController extends Controller
{
    public function index() 
    {
        return view('bsb.shortcuts.index');
    }
}
