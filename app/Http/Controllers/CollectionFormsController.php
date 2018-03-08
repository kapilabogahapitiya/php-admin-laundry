<?php

namespace App\Http\Controllers;

use Alert;
use App\Car;
use App\Category;
use App\Driver;
use App\Factory;
use App\FactoryEmployee;
use App\FormNumber;
use App\FormTransaction;
use App\Hotel;
use App\HotelEmployee;
use App\LaundryForm;
use App\LaundryFormHistory;
use App\LaundryFormItem;
use App\TexcareEmployee;
use App\Mail\CFCreatedBalancing;
use App\Mail\CFApprovalRequest;
use App\Mail\CFRevisionApprovalRequest;
use App\User;
use App\ZoneRunner;
use App\Zone;
use Auth;
use DB;
use Hash;
use Illuminate\Http\Request;
use Mail;
use PDF;
use Session;

class CollectionFormsController extends Controller {
    public function index() {
        $user = Auth::user();
        if ($user->hasRole('hotel_supervisor')) {
            $hotel_id = HotelEmployee::findOrFail($user->table_id)->hotel_id;
            $collection_forms = LaundryForm::collections()
                                ->where('hotel_id', '=', $hotel_id)->get();
        } elseif ($user->hasRole('hotel_employee')) {
            $hotel_id = HotelEmployee::findOrFail($user->table_id)->hotel_id;
            $collection_forms = LaundryForm::collections()
                                ->where('is_balancing', 0)
                                ->where('hotel_id', '=', $hotel_id)->get();
        } elseif ($user->hasRole('pabrik_admin')) {
            $factory_id = FactoryEmployee::findOrFail($user->table_id)->factory_id;
            $collection_forms = LaundryForm::collections()
                                ->where('is_balancing', 0)
                                ->where('factory_id', '=', $factory_id)->get();
        } elseif ($user->hasRole('pabrik_admin_timbangan')) {
            $factory_id = FactoryEmployee::findOrFail($user->table_id)->factory_id;
            $collection_forms = LaundryForm::collections()
                            ->where('is_balancing', 0)
                            ->where('factory_id', '=', $factory_id)->get();
        } elseif ($user->hasRole('texcare_runner')) {
            $zone = ZoneRunner::where('runner_id', '=', $user->table_id)->firstOrFail()->zone;
            $hotels_id = $zone->hotels()->get()->pluck('id')->all();
            $collection_forms = LaundryForm::collections()
                                ->where('is_balancing', 0)
                                ->whereIn('hotel_id', $hotels_id)->get();
        } elseif ($user->hasRole('texcare_supervisor')) {
            $collection_forms = LaundryForm::collections()
                                ->where('is_balancing', 0)->get();
        } elseif ($user->hasRole('texcare_zona_leader')) {
            $getid = DB::table('users')
                    ->select('texcare_employees.id as id')
                    ->join('texcare_employees','texcare_employees.id','users.table_id')
                    ->where('users.id',Auth::user()->id)
                    ->first();
            $collection_forms = LaundryForm::zoneleader()
                ->select('laundry_forms.*')
                ->join('hotels','laundry_forms.hotel_id','hotels.id')
                ->join('zones','zones.id','hotels.zone_id')
                ->where('zones.fe_id',$getid->id)
                ->where('laundry_forms.status', '!=', 3)
                ->where('is_balancing', 0)
                ->get();
        } else {
            $collection_forms = LaundryForm::collections()->where('status', '!=', 3)->get();
        }

        $data = compact('collection_forms', 'c');
        return view('bsb.collection_forms.index')->with($data);
    }

    public function create(Request $request) {
        $categories = Category::where('code', '!=', 'GL')
            ->where('status','1')->get()->pluck('name', 'id')->all();
        if (Auth::user()->hasRole('texcare_runner')) {
            $zone = ZoneRunner::where('runner_id', '=', Auth::user()->table_id)->firstOrFail()->zone;
            $hotels = $zone->hotels()->where('status','1')->get()->pluck('name', 'id')->all();
        } else {
            $hotels = Hotel::where('status','1')->pluck('name', 'id')->all();
        }
        $factories = Factory::where('status','1')->pluck('name', 'id')->all();
        $drivers = Driver::where('status','1')->pluck('name', 'id')->all();
        $cars = Car::where('status','1')->pluck('name', 'id')->all();
        $type = $request->get('type');
        $category = Category::where('code', '=', $type)->first();

        $data = compact('categories', 'hotels', 'type', 'factories', 'drivers', 'cars', 'category');
        return view('bsb.collection_forms.create')->with($data);
    }

    public function store(Request $request) {
        $items = $request->get('items');
        $values = $request->get('values');
        if (!count($items)) {
            Session::flash('error', 'You must add item');
            return back()->withInput();
        }
        DB::beginTransaction();
        try {
            $number = FormNumber::findOrFail(1);
            $factory = Factory::findOrFail($request->get('factory_id'));
            $category = Category::findOrFail($request->get('category_id'));
            $hotel = Hotel::findOrFail($request->get('hotel_id'));
            $category_code = $category->code;
            $new_number = $number->$category_code + 1;
            $number->$category_code = $new_number;
            $number->save();
            $form_number = $factory->code . '/CF/' . $category_code . '/' . $number->year . '/' . $hotel->customer_code . '/' . str_pad($new_number, 5, "0", STR_PAD_LEFT);
            if ($request->get('is_balancing')) {
                $form_number = $form_number . '-BAL';
            }

            /**
             * Redefine if timezone is setted by user
             */
            if (! empty($request->input('created_at'))) {
                // dd($request->input('created_at'));
                $created_at = $request->input('created_at') . ' ' . \Carbon\Carbon::now('Asia/Hong_Kong')->toTimeString();
                $request->merge(['number' => $form_number, 
                                 'created_by' => Auth::user()->id,
                                 'created_at' => $created_at,
                                 'updated_at' => $created_at]);
            } else {
                $request->merge(['number' => $form_number, 'created_by' => Auth::user()->id]);
            }

            $request->merge(['number' => $form_number, 'created_by' => Auth::user()->id]);
            $form = LaundryForm::create($request->all());
            foreach ($items as $key => $item) {
                LaundryFormItem::create([
                    'form_id' => $form->id,
                    'item_id' => $item,
                    'amount' => $values[$key],
                ]);
            }
            // $number->update([$category_code => $new_number]);

            // If balancing form, send notify email to pic hotel's
            $employees = $form->hotel->employees;
            if ($form->is_balancing) {
                foreach ($employees as $employee) {
                    if($employee->user()->hasRole('hotel_supervisor')) {
                        \Log::info('new balancing form notification has been send to: ' . $employee->email);
                        Mail::to($employee->email)->send(new CFCreatedBalancing($employee, $form));
                    }
                }
            }
        } catch (Exception $e) {
            DB::rollback();
            dd($e);
            Alert::error('Fail creating collection form');
            return back()
                ->withInput();
        }
        DB::commit();
        Alert::success('Success create collection form');
        return redirect('transactions/collection_forms');
    }

    public function show($id) {
        $form = LaundryForm::findOrFail($id);

        // Only Superadmin and Supervisor Hotel only see balancing form
        if ($form->is_balancing) {
            if (! Auth::user()->hasRole('hotel_supervisor') && ! Auth::user()->hasRole('super_admin')) {
                Alert::error('Error!');
                Session::flash('error', 'You are not authorized to see that form!');

                return redirect('transactions/collection_forms');   
            }
        }

        if(Auth::user()->hasRole('hotel_supervisor') or Auth::user()->hasRole('hotel_employee')){
            // if (Auth::user()->hasRole('hotel_employee') && $form->is_balancing) {
            //     Alert::error('Error!');
            //     Session::flash('error', 'You are not authorized to see that form!');

            //     return redirect('transactions/collection_forms');
            // }

            $cekuser = DB::table('hotel_employees')
                ->select('hotel_employees.*','hotels.name as hotelname')
                ->join('hotels','hotel_employees.hotel_id','=','hotels.id')
                ->where('hotel_employees.id',Auth::user()->table_id)
                ->first();

            if($form->hotel->name != $cekuser->hotelname){
                Alert::error('Error!');
                Session::flash('error', 'You are not authorized to see that form! cause by different hotel.');
                    
                return redirect('transactions/collection_forms');
            } else {
                $data = compact('form');
                return view('bsb.collection_forms.show')->with($data);
            }
        } elseif (Auth::user()->hasRole('texcare_runner')){
            // if($form->created_by != Auth::user()->id) {
            //     return abort(404);
            // } else {
                $hotelZone = $form->hotel->zone_id;
                $userZone = ZoneRunner::where('runner_id', Auth::user()->table_id)->first()->zone_id;

                if ($hotelZone != $userZone) {
                    Alert::error('Error!');
                    Session::flash('error', 'You are not authorized to see that form! cause by different zone.');

                    return redirect('transactions/collection_forms');
                }

                // if ($form->is_balancing) {
                //     Session::flash('error', 'You are not authorized to see that form!');

                //     return redirect('transactions/collection_forms');
                // }

                $data = compact('form');
                return view('bsb.collection_forms.show')->with($data);
            // }
        } elseif(Auth::user()->hasRole('pabrik_admin_timbangan') or Auth::user()->hasRole('pabrik_admin')) {
            $cekuser = DB::table('users')
                ->select('factories.id')
                ->join('factory_employees','users.table_id','factory_employees.id')
                ->join('factories','factory_employees.factory_id','factories.id')
                ->where('users.id',Auth::user()->id)
                ->first();
            if($cekuser->id != $form->factory_id){
                Alert::error('Error!');
                Session::flash('error', 'You are not authorized to see that form! cause by different factories.');
                return redirect('transactions/collection_forms');
            } else {
                $data = compact('form');
                return view('bsb.collection_forms.show')->with($data);
            }
        } elseif(Auth::user()->hasRole('texcare_zona_leader')) {
            $cekuser = DB::table('users')
                ->select('laundry_forms.id')
                ->join('texcare_employees','users.table_id','texcare_employees.id')
                ->join('zones','texcare_employees.id','zones.fe_id')
                ->join('hotels','zones.id','hotels.zone_id')
                ->join('laundry_forms','hotels.id','laundry_forms.hotel_id')
                ->where('users.id',Auth::user()->id)
                ->where('laundry_forms.id',$id)
                ->first();
            if($cekuser == null){
                Alert::error('Error!');
                Session::flash('error', 'You are not authorized to see that form! cause by different zone.');
                
                return redirect('transactions/collection_forms');
            } else {
                $data = compact('form');
                return view('bsb.collection_forms.show')->with($data);
            }
        } else {
            $data = compact('form');
            return view('bsb.collection_forms.show')->with($data);
        }
    }

    public function edit(Request $request, $id) {
        // Determine if laundry form was not being editable
        if (LaundryForm::findOrFail($id)->status > 1) {
            Alert::error('Error! Collection Form is not editable!');
                    
            return redirect('transactions/collection_forms');
        }

        $categories = Category::where('code', '!=', 'GL')
            ->get()->where('status','1')->pluck('name', 'id')->all();
        if (Auth::user()->hasRole('texcare_runner')) {
            $zone = ZoneRunner::where('runner_id', '=', Auth::user()->table_id)->firstOrFail()->zone;
            $hotels = $zone->hotels()->get()->where('status','1')->pluck('name', 'id')->all();
        } else {
            $hotels = Hotel::pluck('name', 'id')->all();
        }
        $factories = Factory::where('status','1')->pluck('name', 'id')->all();
        $drivers = Driver::where('status','1')->pluck('name', 'id')->all();
        $cars = Car::where('status','1')->pluck('name', 'id')->all();
        $type = $request->get('type');
        $category = Category::where('code', '=', $type)->first();
        $form = LaundryForm::findOrFail($id);
        $form_items = $form->items()->get();
        $hotel_items = $form->hotel->items_array();

        // Only Superadmin and Supervisor Hotel only see balancing form
        if ($form->is_balancing) {
            if (! Auth::user()->hasRole('hotel_supervisor') && ! Auth::user()->hasRole('super_admin')) {
                Alert::error('Error!');
                Session::flash('error', 'You are not authorized to see that form!');

                return redirect('transactions/collection_forms');   
            }
        }

        $data = compact('categories', 'hotels', 'type', 'factories', 'drivers', 'cars', 'category', 'form', 'form_items', 'hotel_items');
        if(Auth::user()->hasRole('hotel_supervisor') or Auth::user()->hasRole('hotel_employee')){
            $cekuser = DB::table('hotel_employees')
                ->select('hotel_employees.*','hotels.name as hotelname')
                ->join('hotels','hotel_employees.hotel_id','=','hotels.id')
                ->where('hotel_employees.id',Auth::user()->table_id)
                ->first();

            if($form->hotel->name != $cekuser->hotelname){
                Alert::error('Error!');
                Session::flash('error', 'You are not authorized to see that form! cause by different hotel.');
                    
                return redirect('transactions/collection_forms');
            } else {
                return view('bsb.collection_forms.edit')->with($data);
            }
        } elseif (Auth::user()->hasRole('texcare_runner')){
            // if($form->created_by != Auth::user()->id) {
            //     return abort(404);
            // } else {
                $hotelZone = $form->hotel->zone_id;
                $userZone = ZoneRunner::where('runner_id', Auth::user()->table_id)->first()->zone_id;

                if ($hotelZone != $userZone) {
                    Alert::error('Error!');
                    Session::flash('error', 'You are not authorized to see that form! cause by different zone.');

                    return redirect('transactions/collection_forms');
                }

                return view('bsb.collection_forms.edit')->with($data);
            // }
        } elseif(Auth::user()->hasRole('pabrik_admin_timbangan') or Auth::user()->hasRole('pabrik_admin')) {
            $cekuser = DB::table('users')
                ->select('factories.id')
                ->join('factory_employees','users.table_id','factory_employees.id')
                ->join('factories','factory_employees.factory_id','factories.id')
                ->where('users.id',Auth::user()->id)
                ->first();
            if($cekuser->id != $form->factory_id){
                Alert::error('Error!');
                Session::flash('error', 'You are not authorized to see that form! cause by different factories.');
                return redirect('transactions/collection_forms');
            } else {
                return view('bsb.collection_forms.edit')->with($data);
            }
        } elseif(Auth::user()->hasRole('texcare_zona_leader')) {
            $cekuser = DB::table('users')
                ->select('laundry_forms.id')
                ->join('texcare_employees','users.table_id','texcare_employees.id')
                ->join('zones','texcare_employees.id','zones.fe_id')
                ->join('hotels','zones.id','hotels.zone_id')
                ->join('laundry_forms','hotels.id','laundry_forms.hotel_id')
                ->where('users.id',Auth::user()->id)
                ->where('laundry_forms.id',$id)
                ->first();
            if($cekuser == null){
                Alert::error('Error!');
                Session::flash('error', 'You are not authorized to see that form! cause by different zone.');
                
                return redirect('transactions/collection_forms');
            } else {
                return view('bsb.collection_forms.edit')->with($data);
            }
        } else {
            return view('bsb.collection_forms.edit')->with($data);
        }
    }

    public function update(Request $request, $id) {
        $form = LaundryForm::findOrFail($id);
        $items = $request->get('items');
        $values = $request->get('values');
        if (!count($items)) {
            Session::flash('error', 'You must add item');
            return back()->withInput();
        }
        DB::beginTransaction();
        try {
            $number = FormNumber::findOrFail(1);
            $factory = Factory::findOrFail($request->get('factory_id'));
            $category = Category::findOrFail($request->get('category_id'));
            $hotel = Hotel::findOrFail($request->get('hotel_id'));
            $category_code = $category->code;
            $no_array = explode('/', $form->number);
            $form_number = $factory->code . '/CF/' . $category_code . '/' . $number->year . '/' . $hotel->customer_code . '/' . $no_array[5];

            /**
             * Update
             * Append note when edit or revision is performed
             */
            if (! empty($request->input('add_note'))) {
                $note = $request->input('note') . "\n" . 'Edit note by ' . Auth::user()->name . ': ' . $request->input('add_note');
            } else {
                $note = $request->input('note');
            }


            /**
             * Redefine if timezone is setted by user
             */
            if (! empty($request->input('created_at'))) {
                // dd($request->input('created_at'));
                $dateCreated =  explode(' ', $request->input('created_at'))[0];
                $created_at = $dateCreated . ' ' . \Carbon\Carbon::now('Asia/Hong_Kong')->toTimeString();
                $request->merge(['number' => $form_number, 
                                 'note' => $note,
                                 'created_at' => $created_at,
                                 'updated_at' => $created_at]);
            } else {
                $request->merge(['number' => $form_number, 'note' => $note]);
            }

            $form->update($request->all());
            $form->items()->delete();
            foreach ($items as $key => $item) {
                LaundryFormItem::create(
                    [
                        'form_id' => $form->id,
                        'item_id' => $item,
                        'amount' => $values[$key]]
                );
            }

            LaundryFormHistory::create(['user_id' => Auth::user()->id, 'form_id' => $form->id]);
        } catch (Exception $e) {
            DB::rollback();
            dd($e);
            Alert::error('Fail updating collection form');
            return back()
                ->withInput();
        }
        DB::commit();
        Alert::success('Success update collection form');
        return redirect('transactions/collection_forms');
    }

    /**
     * 1 March 2017
     * Disable delete function in Collection Form
     *
    public function destroy($id) {
        $form = LaundryForm::find($id);
        $form->delete();
        Alert::success('Success Delete collection form');
        return redirect('transactions/collection_forms  ');
    }
    **/

    public function prints(Request $request, $id) {
        $form = LaundryForm::findOrFail($id);

        $data = compact('form');
        return view('bsb.collection_forms.print')->with($data);
    }

    public function save_pdf(Request $request, $id) {
        $form = LaundryForm::findOrFail($id);
        $data = compact('form');
        $pdf = PDF::loadView('bsb.collection_forms.print', $data);
        return $pdf->download($form->number . '.pdf');
    }

    public function reqApproval($id) {
        DB::beginTransaction();
        try {
            $form = LaundryForm::findOrFail($id);
            $form->update(['status' => 1]);

            // send email approval
            // If balancing form, send notify email to pic hotel's
            $employees = $form->hotel->employees;
            if ($form->is_balancing) {
                foreach ($employees as $employee) {
                    if($employee->user()->hasRole('hotel_supervisor')) {
                        \Log::info('collection form balancing form req approval sent to ' . $employee->email);
                        Mail::to($employee->email)->send(new CFApprovalRequest($employee, $form));
                    }
                }
            } else {
                foreach ($employees as $employee) {
                    \Log::info('cf req approval sent to ' . $employee->email);
                    Mail::to($employee->email)->send(new CFApprovalRequest($employee, $form));
                }
            }
        } catch (Exception $e) {
            DB::rollback();
            Session::flash('error', 'Approval request failed');
            return back();
        }
        DB::commit();
        Session::flash('success', 'Collection form approval requested');
        return redirect('transactions/collection_forms');
    }

    public function approve($id) {
        DB::beginTransaction();
        try {
            $form = LaundryForm::findOrFail($id);
            $form->update([
                'approved_by' => Auth::user()->id,
                'approved_at' => \Carbon\Carbon::now('Asia/Hong_Kong')->toDateTimeString(),
                'status' => 2,
            ]);

            // masukkan item ke transactions
            $form_items = $form->items()->get();
            foreach ($form_items as $key => $form_item) {
                $item = $form_item->item;
                FormTransaction::create([
                    'form_id' => $form->id,
                    'hotel_id' => $form->hotel_id,
                    'item_id' => $item->id,
                    'amount_cf' => $form_item->amount,
                    'price' => $item->price,
                ]);
            }

        } catch (Exception $e) {
            DB::rollback();
            Session::flash('error', 'Fail to approve collection form');
            return back();
        }
        DB::commit();
        Session::flash('success', 'Collection form approved');
        return redirect('transactions/collection_forms');
    }

    public function reqRevision(Request $request, $id) {
        $categories = Category::where('code', '!=', 'GL')
            ->get()->pluck('name', 'id')->all();
        $hotels = Hotel::pluck('name', 'id')->all();
        $factories = Factory::pluck('name', 'id')->all();
        $drivers = Driver::pluck('name', 'id')->all();
        $cars = Car::pluck('name', 'id')->all();
        $type = $request->get('type');
        $category = Category::where('code', '=', $type)->first();
        $form = LaundryForm::findOrFail($id);
        $form_items = $form->items()->get();
        $hotel_items = $form->hotel->items_array($form->category_id);

        $data = compact('categories', 'hotels', 'type', 'factories', 'drivers', 'cars', 'category', 'form', 'form_items', 'hotel_items');
        return view('bsb.collection_forms.revision')->with($data);
    }

    public function doReqRevision(Request $request, $id) {
        $old_form = LaundryForm::findOrFail($id);
        $old_form->update(['status' => 3]); // backup form

        $items = $request->get('items');
        $values = $request->get('values');
        if (!count($items)) {
            Session::flash('error', 'You must add item');
            return back()->withInput();
        }
        DB::beginTransaction();
        try {
            $factory = Factory::findOrFail($request->get('factory_id'));
            $category = Category::findOrFail($request->get('category_id'));
            $hotel = Hotel::findOrFail($request->get('hotel_id'));
            $category_code = $category->code;
            $form_number = $old_form->number . '-UPREV';

            /**
             * Update
             * Append note when edit or revision is performed
             */
            if (! empty($request->input('add_note'))) {
                $note = $request->input('note') . "\n" . 'Revision note by ' . Auth::user()->name . ': ' . $request->input('add_note');
            } else {
                $note = $request->input('note');
            }

            /**
             * Redefine if timezone is setted by user
             */
            if (! empty($request->input('created_at'))) {
                // dd($request->input('created_at'));
                $dateCreated =  explode(' ', $request->input('created_at'))[0];
                $created_at = $dateCreated . ' ' . \Carbon\Carbon::now('Asia/Hong_Kong')->toTimeString();
            } else {
                $created_at = \Carbon\Carbon::now('Asia/Hong_Kong')->toDateTimeString();
            }

            $request->merge([
                'note' => $note,
                'number' => $form_number,
                'created_by' => $old_form->created_by,
                'created_at' => $old_form->created_at,
                'approved_by' => $old_form->approved_by,
                'approved_at' => $old_form->approved_at,
                'req_revision_by' => Auth::user()->id,
                'req_revision_at' => $created_at,
                'status' => 4,
                'is_balancing' => $old_form->is_balancing
            ]);
            $form = LaundryForm::create($request->all());
            foreach ($items as $key => $item) {
                LaundryFormItem::create(
                    [
                        'form_id' => $form->id,
                        'item_id' => $item,
                        'amount' => $values[$key],
                    ]
                );
            }

            // send email approval
            $employees = $form->hotel->employees;
            if ($form->is_balancing) {
                foreach ($employees as $employee) {
                    if($employee->user()->hasRole('hotel_supervisor')) {
                        //\Log::info('collection form balancing form req approval sent to ' . $employee->email);
                        //Mail::to($employee->email)->send(new CFApprovalRequest($employee, $form));

                        \Log::info('cf req rev approval sent to ' . $employee->email);
                        Mail::to($employee->email)->send(new CFRevisionApprovalRequest($employee, $form));
                    }
                }
            } else {
                //foreach ($employees as $employee) {
                //    \Log::info('cf req approval sent to ' . $employee->email);
                //    Mail::to($employee->email)->send(new CFApprovalRequest($employee, $form));
                //}

                foreach ($employees as $employee) {
                    \Log::info('cf req rev approval sent to ' . $employee->email);
                    Mail::to($employee->email)->send(new CFRevisionApprovalRequest($employee, $form));
                }
            }
        } catch (Exception $e) {
            DB::rollback();
            dd($e);
            Session::flash('error', 'Fail updating collection form');
            return back()
                ->withInput();
        }
        DB::commit();
        Session::flash('success', 'Success update collection form');
        return redirect('transactions/collection_forms');
    }

    public function revApprove($id) {
        DB::beginTransaction();
        try {
            $form = LaundryForm::findOrFail($id);
            $form->update([
                'approved_revision_by' => Auth::user()->id,
                'approved_revision_at' => \Carbon\Carbon::now('Asia/Hong_Kong')->toDateTimeString(),
                'status' => 5,
            ]);
            $old_number = rtrim($form->number, '-UPREV');
            $parts = explode("/", $old_number);
            $old_form = LaundryForm::where('number', $old_number)->first();
            if ($old_form != null) {
                // delete transaction from old form
                $old_form->transactions()->delete();
                // masukkan item ke transactions
                $form_items = $form->items()->get();
                foreach ($form_items as $key => $form_item) {
                    $item = $form_item->item;
                    FormTransaction::create([
                        'form_id' => $form->id,
                        'hotel_id' => $form->hotel_id,
                        'item_id' => $item->id,
                        'amount_cf' => $form_item->amount,
                        'price' => $item->price,
                    ]);
                }
            } else {
                DB::rollback();
                Session::flash('error', 'Something wrong with your form');
                return back();
            }

        } catch (Exception $e) {
            DB::rollback();
            Session::flash('error', 'Fail approve collection form revision request');
            return back();
        }
        DB::commit();

        Session::flash('success', 'Collection form revision approved');
        return redirect('transactions/collection_forms');
    }

    public function reqApprovalAjax(Request $request, $id) {
        DB::beginTransaction();
        try {
            if (Hash::check($request->get('password'), Auth::user()->password)) {
                $form = LaundryForm::findOrFail($id);
                $form->update(['status' => 1]);

                // send email approval
                // If balancing form, send notify email to pic hotel's
                $employees = $form->hotel->employees;
                if ($form->is_balancing) {
                    foreach ($employees as $employee) {
                        if($employee->user()->hasRole('hotel_supervisor')) {
                            \Log::info('collection form balancing form req approval sent to ' . $employee->email);
                            Mail::to($employee->email)->send(new CFApprovalRequest($employee, $form));
                        }
                    }
                }
            } else {
                return response()->json(['error' => true, 'message' => 'Password anda salah']);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['error' => true, 'message' => 'Something wrong']);
        }
        DB::commit();
        return response()->json(['error' => false, 'message' => 'Collection form approval requested']);
    }

    public function approveAjax(Request $request, $id) {
        DB::beginTransaction();
        try {
            $username = $request->get('username');
            $password = $request->get('password');

            $user = User::where('username', '=', $username)->first();
            if ($user == null) {
                return response()->json(['error' => true, 'message' => 'Username or password not match']);
            }
            
            if ($user->status == "0") {
                return response()->json(['error' => true, 'message' => 'User is nonactive or deleted.']);
            }
            
            if ($user->table_name == 'hotel_employees') {
                $userHotelEmployee = HotelEmployee::find($user->table_id);
                $laundryForm = LaundryForm::find($id);

                if ($laundryForm->hotel_id != $userHotelEmployee->hotel_id) {
                    return response()->json(['error' => true, 'message' => 'You are not authorized to approve, because you are in different hotel.']);
                }
            } else {
                return response()->json(['error' => true, 'message' => 'You are not an Hotel Employee / PIC']);
            }

            if (!$user->can('approval_cf')) {
                return response()->json(['error' => true, 'message' => 'You are not authorized']);
            }

            if (Hash::check($request->get('password'), $user->password)) {
                $form = LaundryForm::findOrFail($id);
                $form->update([
                    'approved_by' => $user->id,
                    'approved_at' => \Carbon\Carbon::now('Asia/Hong_Kong')->toDateTimeString(),
                    'status' => 2,
                ]);

                // masukkan item ke transactions
                $form_items = $form->items()->get();
                foreach ($form_items as $key => $form_item) {
                    $item = $form_item->item;
                    FormTransaction::create([
                        'form_id' => $form->id,
                        'hotel_id' => $form->hotel_id,
                        'item_id' => $item->id,
                        'amount_cf' => $form_item->amount,
                        'price' => $item->price,
                    ]);
                }
            } else {
                return response()->json(['error' => true, 'message' => 'Password anda salah']);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['error' => true, 'message' => 'Fail to approve collection form']);
        }
        DB::commit();
        return response()->json(['error' => false, 'message' => 'Collection form approved']);
    }

    
    public function cancelAjax(Request $request, $id) {
        // DB::beginTransaction();
        try {
            $currentForm = LaundryForm::where('id', $id)->first();

            $number = $currentForm->number;

            $getOldForm = LaundryForm::where('number', rtrim($number, '-UPREV'))->first();

            /**
             * Update
             * Append note when edit or revision is performed
             */
            if (! empty($request->input('note'))) {
                $note = $currentForm->note . "\n" . 'Revision Cancel note by ' . Auth::user()->name . ': ' . $request->input('note');
            } else {
                $note = $request->input('note');
            }


            /**
             * Redefine if timezone is setted by user
             */
            if (! empty($request->input('created_at'))) {
                $dateCreated =  explode(' ', $request->input('created_at'))[0];
                $created_at = $dateCreated . ' ' . \Carbon\Carbon::now('Asia/Hong_Kong')->toTimeString();
                $getOldForm->update([
                    'rev_cancelled_by' => Auth::user()->id,
                    'rev_cancelled_at' => $created_at,
                    'note' => $note,
                    'number' => rtrim($number, '-UPREV'), 
                    'status' => 2,
                ]);
            } else {
                $getOldForm->update([
                    'rev_cancelled_by' => Auth::user()->id,
                    'rev_cancelled_at' => \Carbon\Carbon::now('Asia/Hong_Kong')->toDateTimeString(),
                    'note' => $note,
                    'number' => rtrim($number, '-UPREV'), 
                    'status' => 2,
                ]);
            }

        } catch (Exception $e) {
            // DB::rollback();
            return response()->json(['error' => false, 'message' => 'Failed to cancel revision form']);
        }
        // DB::commit();
	//$currentForm->delete();
        return response()->json(['error' => false, 'message' => 'Revision Form Cancelled', 'id' => $getOldForm->id, 'rev_id' => $currentForm->id]);
    }    

    public function rejectAjax(Request $request, $id) {
        // DB::beginTransaction();
        try {
            $currentForm = LaundryForm::where('id', $id)->first();

            $number = $currentForm->number;
            $getOldForm = LaundryForm::where('number', rtrim($number, '-UPREV'))->first();

            /**
             * Update
             * Append note when edit or revision is performed
             */
            if (! empty($request->input('note'))) {
                $note = $currentForm->note . "\n" . 'Revision Reject note by ' . Auth::user()->name . ': ' . $request->input('note');
            } else {
                $note = $request->input('note');
            }

            if (! empty($request->input('created_at'))) {
                $dateCreated =  explode(' ', $request->input('created_at'))[0];
                $created_at = $dateCreated . ' ' . \Carbon\Carbon::now('Asia/Hong_Kong')->toTimeString();
                $getOldForm->update([
                    'rev_rejected_by' => Auth::user()->id,
                    'rev_rejected_at' => $created_at,
                    'note' => $note,
                    'number' => rtrim($number, '-UPREV'), 
                    'status' => 7,
                ]);
            } else {
                $getOldForm->update([
                    'rev_rejected_by' => Auth::user()->id,
                    'rev_rejected_at' => \Carbon\Carbon::now('Asia/Hong_Kong')->toDateTimeString(),
                    'note' => $note,
                    'number' => rtrim($number, '-UPREV'), 
                    'status' => 7,
                ]);
            }

        } catch (Exception $e) {
            // DB::rollback();
            return response()->json(['error' => false, 'message' => 'Failed to cancel reject form']);
        }
        // DB::commit();
	//$currentForm->delete();
        return response()->json(['error' => false, 'message' => 'Revision Form Rejected', 'id' => $getOldForm->id, 'rev_id' => $currentForm->id]);
    }

    public function revDelete($rev_id) {
        try {
             $currentForm = LaundryForm::find($rev_id);
             $currentForm->delete();
        } catch (Exception $e) {
             return redirect('transactions/collection_forms');
        }

        return redirect('transactions/collection_forms');
    }
}
