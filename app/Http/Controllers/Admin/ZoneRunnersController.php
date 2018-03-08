<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\ZoneRunner, App\Zone, App\TexcareEmployee, App\Role;
use Auth, DB, Session, Validator, Alert;

class ZoneRunnersController extends Controller
{
    public function index()
    {
        $runners = ZoneRunner::select('zone_runner.*')
                            ->join('texcare_employees','texcare_employees.id','zone_runner.runner_id')
                            ->where('texcare_employees.status','1')
                            ->where('zone_runner.status','1')
                            ->get();
        $runners_deleted = ZoneRunner::where('status','0')->get();

        $data = compact('runners', 'runners_deleted');
        return view('bsb.zone_runners.index')->with($data);
    }

    public function create()
    {
        $zones = Zone::where('status','1')->pluck('name', 'id')->all();
        $assigned_runners = ZoneRunner::where('status','1')->pluck('runner_id')->all();
        $assigned_runners_deleted = ZoneRunner::where('status','0')->pluck('runner_id')->all();

        $role = Role::findOrFail(4);
        $available_runners = $role->users()
            ->select('users.id as user_id', 'texcare_employees.*')
            ->leftJoin('texcare_employees', 'users.table_id', '=', 'texcare_employees.id')
            ->where('texcare_employees.status','1')
            ->whereNotIn('texcare_employees.id', $assigned_runners)
            ->whereNotIn('texcare_employees.id', $assigned_runners_deleted)
            ->get()->pluck('name', 'id')->all();

        $data = compact('zones', 'available_runners');
        return view('bsb.zone_runners.create')->with($data);
    }

    public function edit(Request $request, $runner_id) {
        $zones = Zone::where('status','1')->pluck('name', 'id')->all();
        $zone_runner = ZoneRunner::where('runner_id', $runner_id)->first();

        if (! $zone_runner) {
            return abort(404);
        }

        $data = compact('zones', 'zone_runner');
        return view('bsb.zone_runners.edit')->with($data);
    }

    public function update(Request $request, $runner_id){
        $validate = Validator::make($request->all(), ZoneRunner::updateValid());
        if ($validate->fails()) {
            return redirect('admin/zone_runners/edit')
                            ->withErrors($validate)
                            ->withInput();
        } else {
            $zone_runner = ZoneRunner::where('runner_id', $runner_id)->first();

            if (! $zone_runner) {
                return abort(404);
            }

            DB::beginTransaction();
            try {
                // update
                $zone_runner->update(['zone_id' => $request->input('zone_id')]);
            } catch (Exception $e) {
                DB::rollback();
                Alert::error('Failed to edit Zone Runner');
                return back();
            }
            DB::commit();

            Alert::success('Success Edit Zone Runner');
            return redirect('admin/zone_runners');
        }
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), ZoneRunner::valid());
        if ($validate->fails()) {
            return redirect('admin/zone_runners/create')
            ->withErrors($validate)
            ->withInput();
        } else {
            $request->merge(['status', '1']);
            ZoneRunner::create($request->all());
            Alert::success('Success Create Zone Runner');
            return redirect('admin/zone_runners');
        }
    }

    public function destroy($id)
    {
        $runner = DB::table('zone_runner')->where('runner_id', '=', $id)->delete();
        Alert::success('Success Delete Zone Runner');
        return redirect('admin/zone_runners');
    }

    public function update_status($id){
        $runner = ZoneRunner::where('runner_id',$id);
        $runner->update(['status' => '0']);

        Alert::success('Success Delete Zone Runner');
        return redirect('admin/zone_runners');
    }

    public function activate($id){
        $runners = ZoneRunner::where('runner_id', $id)->update(['status' => '1']);
        Alert::success('Success Activate Runner');
        return redirect('admin/zone_runners');
    }
}
