<?php

namespace App\Http\Controllers\Admin;

use Artisan;
use Auth;
use App\DatabaseHistory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Session;

class DatabaseBackupController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $backups = DatabaseHistory::backup()->get();

        $data = compact('backups');
        return view('bsb.security.database.backup.index')->with($data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        try {
            // fire backup
            // TODO
            // Stabilkan ini dibawah.
            Artisan::call('backup:mysql-dump');

            // get output
            $date = \Carbon\Carbon::now('Asia/Hong_Kong')->format('YmdHis');
            $output = Artisan::output();
            $prefix = rtrim(ltrim(explode(' ', $output)[1], '\''), '\'') . '_';
            $filename = $prefix . $date . '.sql';

            // save to database
            $backup = DatabaseHistory::create([
                'type' => 'backup',
                'user_id' => Auth::user()->id,
                'created_at' => \Carbon\Carbon::now('Asia/Hong_Kong')->format('Y-m-d H:i:s'),
                'updated_at' => \Carbon\Carbon::now('Asia/Hong_Kong')->format('Y-m-d H:i:s'),
            ]);
            
            $link = storage_path('app/backups/') . $filename;
            return response()->download($link);
            
            // return response()->json(['error' => false, 'message' => 'Your download has been started.', 'link' => $link]);
        } catch (Exception $e) {
            abort(404);
        }
    }

    public function downloadAjax(Request $request) {
        $link = $request->get('link');

        if (! empty($link)) {
            return response()->download($link);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
