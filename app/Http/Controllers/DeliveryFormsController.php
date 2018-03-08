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
use App\Mail\DFCreatedBalancing;
use App\Mail\DFApprovalRequest;
use App\Mail\DFRevisionApprovalRequest;
use App\User;
use App\ZoneRunner;
use Auth;
use DB;
use Hash;
use Illuminate\Http\Request;
use Mail;
use PDF;
use Session;

class DeliveryFormsController extends Controller {
    public function index() {
        $user = Auth::user();
        if ($user->hasRole('hotel_supervisor')) {
            $hotel_id = HotelEmployee::findOrFail($user->table_id)->hotel_id;
            $delivery_forms = LaundryForm::deliveries()
                           ->where('hotel_id', '=', $hotel_id)->get();
        } elseif ($user->hasRole('hotel_employee')) {
            $hotel_id = HotelEmployee::findOrFail($user->table_id)->hotel_id;
            $delivery_forms = LaundryForm::deliveries()
                            ->where('is_balancing', 0)
                            ->where('hotel_id', '=', $hotel_id)->get();
        } elseif ($user->hasRole('pabrik_admin')) {
            $factory_id = FactoryEmployee::findOrFail($user->table_id)->factory_id;
            $delivery_forms = LaundryForm::deliveries()
                            ->where('is_balancing', 0)
                            ->where('factory_id', '=', $factory_id)->get();
        } elseif ($user->hasRole('pabrik_admin_timbangan')) {
            $factory_id = FactoryEmployee::findOrFail($user->table_id)->factory_id;
            $delivery_forms = LaundryForm::deliveries()
                            ->where('is_balancing', 0)
                            ->where('factory_id', '=', $factory_id)->get();
        } elseif ($user->hasRole('texcare_runner')) {
            $zone = ZoneRunner::where('runner_id', '=', $user->table_id)->firstOrFail()->zone;
            $hotels_id = $zone->hotels()->get()->pluck('id')->all();
            $delivery_forms = LaundryForm::deliveries()
                            ->where('is_balancing', 0)
                            ->whereIn('hotel_id', $hotels_id)->get();
        } elseif ($user->hasRole('texcare_supervisor')) {
            $delivery_forms = LaundryForm::deliveries()
                            ->where('is_balancing', 0)->get();
        } elseif ($user->hasRole('texcare_zona_leader')) {
            $getid = DB::table('users')
                    ->select('texcare_employees.id as id')
                    ->join('texcare_employees','texcare_employees.id','users.table_id')
                    ->where('users.id',Auth::user()->id)
                    ->first();
            $delivery_forms = LaundryForm::deliverieszone()
                ->select('laundry_forms.*')
                ->join('hotels','laundry_forms.hotel_id','hotels.id')
                ->join('zones','zones.id','hotels.zone_id')
                ->where('zones.fe_id',$getid->id)
                ->where('is_balancing', 0)
                ->get(); 
        } else {
            $delivery_forms = LaundryForm::deliveries()->get();
        }

        $data = compact('delivery_forms');
        return view('bsb.delivery_forms.index')->with($data);
    }

    public function create(Request $request) {
        $categories = Category::where('code', '!=', 'GL')
            ->where('status','1')->get()->pluck('name', 'id')->all();
        $hotels = Hotel::where('status','1')->pluck('name', 'id')->all();
        $user = Auth::user();
        if ($user->hasRole('pabrik_admin')) {
            $factory_id = FactoryEmployee::findOrFail($user->table_id)->factory_id;
            $factories = Factory::where('id', '=', $factory_id)->where('status','1')->get()->pluck('name', 'id')->all();
        } else {
            $factories = Factory::where('status','1')->pluck('name', 'id')->all();
        }

        $drivers = Driver::where('status','1')->pluck('name', 'id')->all();
        $cars = Car::where('status','1')->pluck('name', 'id')->all();
        $type = $request->get('type');
        $category = Category::where('code', '=', $type)->first();

        $data = compact('categories', 'hotels', 'type', 'factories', 'drivers', 'cars', 'category');
        return view('bsb.delivery_forms.create')->with($data);
    }

    public function store(Request $request) {
        $items = $request->get('items');
        $values = $request->get('values');
        if (!count($items)) {
            Alert::error('You must add item');
            return back()->withInput();
        }
        DB::beginTransaction();
        try {
            $number = FormNumber::findOrFail(2);
            $factory = Factory::findOrFail($request->get('factory_id'));
            $category = Category::findOrFail($request->get('category_id'));
            $hotel = Hotel::findOrFail($request->get('hotel_id'));
            $category_code = $category->code;
            $new_number = $number->$category_code + 1;
            $number->$category_code = $new_number;
            $number->save();
            $form_number = $factory->code . '/DF/' . $category_code . '/' . $number->year . '/' . $hotel->customer_code . '/' . str_pad($new_number, 5, "0", STR_PAD_LEFT);
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
                                 'updated_at' => $created_at,
                                 'type' => 2]);
                // $request->merge([
                //     'number' => $form_number,
                //     'created_by' => Auth::user()->id,
                //     'type' => 2,
                // ]);
            } else {
                $request->merge([
                    'number' => $form_number,
                    'created_by' => Auth::user()->id,
                    'type' => 2,
                ]);
            }

            $form = LaundryForm::create($request->all());
            foreach ($items as $key => $item) {
                LaundryFormItem::create(
                    [
                        'form_id' => $form->id,
                        'item_id' => $item,
                        'amount' => $values[$key]]
                );
            }
            $number->update([$category_code => $new_number]);

            // If balancing form, send notify email to pic hotel's
            $employees = $form->hotel->employees;
            if ($form->is_balancing) {
                foreach ($employees as $employee) {
                    if($employee->user()->hasRole('hotel_supervisor')) {
                        \Log::info('new balancing form notification has been send to: ' . $employee->email);
                        Mail::to($employee->email)->send(new DFCreatedBalancing($employee, $form));
                    }
                }
            }
        } catch (Exception $e) {
            DB::rollback();
            dd($e);
            Alert::error('Fail creating delivery form');
            return back()
                ->withInput();
        }
        DB::commit();
        Alert::success('Success create delivery form');
        return redirect('transactions/delivery_forms');
    }

    public function show($id) {
        $form = LaundryForm::findOrFail($id);

        // Only Superadmin and Supervisor Hotel only see balancing form
        if ($form->is_balancing) {
            if (! Auth::user()->hasRole('hotel_supervisor') && ! Auth::user()->hasRole('super_admin')) {
                Alert::error('Error!');
                Session::flash('error', 'You are not authorized to see that form!');

                return redirect('transactions/delivery_forms');   
            }
        }

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
                $data = compact('form');
                return view('bsb.delivery_forms.show')->with($data);
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

                $data = compact('form');
                return view('bsb.delivery_forms.show')->with($data);
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
                
                return redirect('transactions/delivery_forms');
            } else {
                $data = compact('form');
                return view('bsb.delivery_forms.show')->with($data);
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
                
                return redirect('transactions/delivery_forms');
            } else {
                $data = compact('form');
                return view('bsb.delivery_forms.show')->with($data);
            }
        } else {
            $data = compact('form');
            return view('bsb.delivery_forms.show')->with($data);
        }
        
    }

    public function edit(Request $request, $id) {
        // Determine if laundry form was not being editable
        if (LaundryForm::findOrFail($id)->status > 1) {
            Alert::error('Error! Delivery Form is not editable!');
            
            return redirect('transactions/delivery_forms');
        }

        $categories = Category::where('code', '!=', 'GL')
            ->where('status','1')->get()->pluck('name', 'id')->all();
        $hotels = Hotel::where('status','1')->pluck('name', 'id')->all();
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

                return redirect('transactions/delivery_forms');   
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
                return view('bsb.delivery_forms.edit')->with($data);
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

                return view('bsb.delivery_forms.edit')->with($data);
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
                
                return redirect('transactions/delivery_forms');
            } else {
                return view('bsb.delivery_forms.edit')->with($data);
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
                
                return redirect('transactions/delivery_forms');
            } else {
                return view('bsb.delivery_forms.edit')->with($data);
            }
        } else {
            return view('bsb.delivery_forms.edit')->with($data);
        }
    }

    public function update(Request $request, $id) {
        $form = LaundryForm::findOrFail($id);
        $items = $request->get('items');
        $values = $request->get('values');
        if (!count($items)) {
            Alert::error('You must add item');
            return redirect()->back();
        }
        DB::beginTransaction();
        try {
            // $username = $request->get('username');
            // $password = $request->get('password');


            // $user = User::where('username', '=', $username)->first();
            // if ($user == null) {
            //     Alert::error('Username or password not match');
            //     return redirect()->back();
            // }

            // if ($user->status == "0") {
            //     Alert::error('User is nonactive or deleted.');
            //     return redirect()->back();
            // }

            // if ($user->table_name == 'texcare_employees') {
            //     $userRunner = TexcareEmployee::find($user->table_id);
            //     $userRunnerZone = ZoneRunner::where('runner_id', $userRunner->id)->first();

            //     $laundryForm = LaundryForm::find($id);
            //     $laundryFormHotel = Hotel::where('id', $laundryForm->hotel_id)->first();

            //     if ($laundryFormHotel->zone_id != $userRunnerZone->zone_id) {
            //         Alert::error('You are not authorized to confirm, because you are in different zone.');
            //         return redirect()->back();
            //     }
            // } else {
            //     Alert::error('You are not an Runner!');
            //     return redirect()->back();
            // }

            // if (!$user->hasRole('texcare_runner')) {
            //     Alert::error('You are not authorized');
            //     return redirect()->back();
            // }

            $number = FormNumber::findOrFail(1);
            $factory = Factory::findOrFail($request->get('factory_id'));
            $category = Category::findOrFail($request->get('category_id'));
            $hotel = Hotel::findOrFail($request->get('hotel_id'));
            $category_code = $category->code;
            $no_array = explode('/', $form->number);
            $form_number = $factory->code . '/DF/' . $category_code . '/' . $number->year . '/' . $hotel->customer_code . '/' . $no_array[5];

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
            dd('here');
            DB::rollback();
            Alert::error('Fail updating delivery form');
            return back()
                ->withInput();
        }
        DB::commit();
        Session::flash('success', 'Success update delivery form');
        return redirect('transactions/delivery_forms');
    }

    /**
     * 1 March 2017
     * Disable delete function in Delivery Form.
     *
    public function destroy($id) {
        $form = LaundryForm::find($id);
        $form->delete();
        Alert::success('Success Delete delivery form');
        return redirect('transactions/delivery_forms');
    }
    **/

    public function prints(Request $request, $id) {
        $form = LaundryForm::findOrFail($id);

        $data = compact('form');
        return view('bsb.delivery_forms.print')->with($data);
    }

    public function save_pdf(Request $request, $id) {
        $form = LaundryForm::findOrFail($id);
        $data = compact('form');
        $pdf = PDF::loadView('bsb.delivery_forms.print', $data);
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
                        \Log::info('delivery balancing form req approval sent to ' . $employee->email);
                        Mail::to($employee->email)->send(new DFApprovalRequest($employee, $form));
                    }
                }
            } else {
                foreach ($employees as $employee) {
                    \Log::info('df req approval sent to ' . $employee->email);
                    Mail::to($employee->email)->send(new DFApprovalRequest($employee, $form));
                }
            }
        } catch (Exception $e) {
            DB::rollback();
            Alert::error('Approval request failed');
            return back();
        }
        DB::commit();

        Alert::success('Delivery form approval requested');
        return redirect('transactions/delivery_forms');
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
                    'amount_df' => $form_item->amount,
                ]);
            }

        } catch (Exception $e) {
            DB::rollback();
            Alert::error('Fail to approve delivery form');
            return back();
        }
        DB::commit();

        Alert::success('Delivery form approved');
        return redirect('transactions/delivery_forms');
    }

    public function reqRevision(Request $request, $id) {
        if ($request->ajax()) {
            if (Hash::check($request->get('password'), Auth::user()->password)) {
                return response()->json(['error' => false, 'message' => 'Delivery form revision requested']);
            } else {
                return response()->json(['error' => true, 'message' => 'Password anda salah']);
            }
        }

        $categories = Category::where('code', '!=', 'GL')
            ->get()->pluck('name', 'id')->all();
        $user = Auth::user();
        $hotel_id = HotelEmployee::findOrFail($user->table_id)->hotel_id;
        $hotels = Hotel::where('id', '=', $hotel_id)->get()->pluck('name', 'id')->all();
        $factories = Factory::pluck('name', 'id')->all();
        $drivers = Driver::pluck('name', 'id')->all();
        $cars = Car::pluck('name', 'id')->all();
        $type = $request->get('type');
        $category = Category::where('code', '=', $type)->first();
        $form = LaundryForm::findOrFail($id);
        $form_items = $form->items()->get();
        $hotel_items = $form->hotel->items_array($form->category_id);

        $data = compact('categories', 'hotels', 'type', 'factories', 'drivers', 'cars', 'category', 'form', 'form_items', 'hotel_items');
        return view('bsb.delivery_forms.revision')->with($data);
    }

    public function doReqRevision(Request $request, $id) {
        $old_form = LaundryForm::findOrFail($id);
        $old_form->update(['status' => 3]); // backup form

        $items = $request->get('items');
        $values = $request->get('values');
        if (!count($items)) {
            Alert::error('You must add item');
            return back();
        }

        DB::beginTransaction();
        try {
            $username = $request->get('username');
            $password = $request->get('password');

            $user = User::where('username', '=', $username)->first();

            if ($user == null) {
                Alert::error('Username or password runner not match');
                return back();
            }

            if (!$user->hasRole('texcare_runner')) {
                Alert::error('Only runner can confirm delivery form revision');
                return back();
            }

            if ($user->status == "0") {
                Alert::error('User is nonactive or deleted.');
                return back();
            }

            if ($user->table_name == 'texcare_employees') {
                $userRunner = TexcareEmployee::find($user->table_id);
                $userRunnerZone = ZoneRunner::where('runner_id', $userRunner->id)->first();

                $laundryForm = LaundryForm::find($id);
                $laundryFormHotel = Hotel::where('id', $laundryForm->hotel_id)->first();

                if ($laundryFormHotel->zone_id != $userRunnerZone->zone_id) {
                    Alert::error('Runner not authorized to confirm, because in different zone.');
                    return back();
                }
            } else {
                Alert::error('You are not an Runner!');
                return back();
            }

            // dd($user->id);
            if (Hash::check($password, $user->password)) {
                $factory = Factory::findOrFail($request->get('factory_id'));
                $category = Category::findOrFail($request->get('category_id'));
                $hotel = Hotel::findOrFail($request->get('hotel_id'));
                $category_code = $category->code;
                $form_number = rtrim($old_form->number, '-UPREV') . '-UPREV';
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
                    'created_at' => $old_form->created_at . "",
                    'approved_by' => $old_form->approved_by,
                    'approved_at' => $old_form->approved_at,
                    'req_revision_by' => Auth::user()->id,
                    'req_revision_runner_by' => $user->id,
                    'req_revision_at' =>  $created_at,
                    'status' => 4,
                    'type' => 2,
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

                // send email notification
                // If balancing form, send notify email to pic hotel's
                $employees = $form->hotel->employees;
                if ($form->is_balancing == true) {
                    foreach ($employees as $employee) {
                        if($employee->user()->hasRole('hotel_supervisor')) {
                            \Log::info('df req rev notification sent to ' . $employee->email);
                            Mail::to($employee->email)->send(new DFRevisionApprovalRequest($employee, $form));
                        }
                    }
                } else {
                    // send email approval
                    $admins = $form->factory->factory_admins();
                    foreach ($admins as $admin) {
                        \Log::info('df req rev approval sent to ' . $admin->email);
                        Mail::to($admin->email)->send(new DFRevisionApprovalRequest($admin, $form));
                    }
                }

            } else {
                Alert::error('Password anda salah');
                return back();
            }

        } catch (Exception $e) {
            DB::rollback();
            dd($e);
            Alert::error('Fail updating delivery form');
            return back();
        }
        DB::commit();
        Alert::success('Success update delivery form');
        return redirect('transactions/delivery_forms/' . $form->id);
    }

    public function revApprove($id) {
        DB::beginTransaction();
        try {
            $form = LaundryForm::findOrFail($id);
            $form->update([
                'approved_revision_by' => Auth::user()->id,
                'approved_revision_at' => \Carbon\Carbon::now('Asia/Hong_Kong')->toDateTimeString(),
                'status' => 6,
            ]);
            $old_number = rtrim($form->number, '-UPREV');
            $parts = explode("/", $old_number);
            $old_form = LaundryForm::where('number', $old_number)->first();
            // 13 April 2017
            // Delivery Form Flow Change
            //
            // if ($old_form != null) {
            //     // delete transaction from old form
            //     $old_form->transactions()->delete();
            //     // masukkan item ke transactions
            //     $form_items = $form->items()->get();
            //     foreach ($form_items as $key => $form_item) {
            //         $item = $form_item->item;
            //         FormTransaction::create([
            //             'form_id' => $form->id,
            //             'hotel_id' => $form->hotel_id,
            //             'item_id' => $item->id,
            //             'amount_df' => $form_item->amount,
            //         ]);
            //     }
            // } else {
            //     DB::rollback();
            //     Alert::success('Something wrong with your form');
            //     return back();
            // }
        } catch (Exception $e) {
            DB::rollback();
            Alert::error('Fail approve delivery form revision request');
            return back();
        }
        DB::commit();
        Alert::success('Delivery form revision approved');
        return redirect('transactions/delivery_forms/' . $form->id);
    }

    public function approveAjax(Request $request, $id) {
        DB::beginTransaction();
        try {
            if (Hash::check($request->get('password'), Auth::user()->password)) {
                $form = LaundryForm::findOrFail($id);
                $form->update(['status' => 1]);
            } else {
                return response()->json(['error' => true, 'message' => 'Password anda salah']);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['error' => true, 'message' => 'Something wrong']);
        }
        DB::commit();
        return response()->json(['error' => false, 'message' => 'Delivery form telah diapprove!']);
    }

    public function runnerConfirmationAjax(Request $request, $id) {
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

            if ($user->table_name == 'texcare_employees') {
                $userRunner = TexcareEmployee::find($user->table_id);
                $userRunnerZone = ZoneRunner::where('runner_id', $userRunner->id)->first();

                $laundryForm = LaundryForm::find($id);
                $laundryFormHotel = Hotel::where('id', $laundryForm->hotel_id)->first();

                if ($laundryFormHotel->zone_id != $userRunnerZone->zone_id) {
                    return response()->json(['error' => true, 'message' => 'You are not authorized to confirm, because you are in different zone.']);
                }
            } else {
                return response()->json(['error' => true, 'message' => 'You are not an Runner!']);
            }

            if (!$user->hasRole('texcare_runner')) {
                return response()->json(['error' => true, 'message' => 'You are not authorized']);
            }

            if (Hash::check($request->get('password'), $user->password)) {
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
                        'amount_df' => $form_item->amount,
                        'price' => $item->price,
                    ]);
                }
            } else {
                return response()->json(['error' => true, 'message' => 'Password anda salah']);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['error' => true, 'message' => 'Fail to approve delivery form']);
        }
        DB::commit();
        return response()->json(['error' => false, 'message' => 'Delivery form approved']);
    }

    public function revRunnerConfirmationAjax(Request $request, $id) {
        DB::beginTransaction();
        try {
            $username = $request->get('username');
            $password = $request->get('password');

            $user = User::where('username', '=', $username)->first();
            if ($user == null) {
                return response()->json(['error' => true, 'message' => 'Username or password not match']);
            }

            if (!$user->hasRole('texcare_runner')) {
                return response()->json(['error' => true, 'message' => 'You are not authorized']);
            }

            if (Hash::check($request->get('password'), $user->password)) {
                /*$form = LaundryForm::findOrFail($id);
            $form->update([
            'approved_revision_by' => $user->id,
            'approved_revision_at' => \Carbon\Carbon::now('Asia/Hong_Kong')->toDateTimeString(),
            'status' => 5
            ]);
            $old_number = rtrim($form->number, '-UPREV');
            $parts = explode("/", $old_number);
            $old_form = LaundryForm::where('number', 'like', '%DF%/'.$parts[5])->first();
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
            'amount_df' => $form_item->amount
            ]);
            }
            } else {
            DB::rollback();
            return response()->json(['error' => true, 'message' => 'Something wrong with your form']);
            }*/
            } else {
                return response()->json(['error' => true, 'message' => 'Password anda salah']);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['error' => true, 'message' => 'Fail to approve delivery form']);
        }
        DB::commit();
        return response()->json(['error' => false, 'message' => 'Delivery form approved']);
    }

    public function cancelAjax(Request $request, $id) {
        // DB::beginTransaction();
        try {
            $currentForm = LaundryForm::find($id);

            $number = $currentForm->number;

            // Revisioning
            // $getOldForm = LaundryForm::where('number', rtrim($number, '-UPREV'))->first();
            $getOldForm = LaundryForm::where('number', $number)->first();

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
                    'status' => 1,
                ]);
            } else {
                $getOldForm->update([
                    'rev_cancelled_by' => Auth::user()->id,
                    'rev_cancelled_at' => \Carbon\Carbon::now('Asia/Hong_Kong')->toDateTimeString(),
                    'note' => $note,
                    'number' => rtrim($number, '-UPREV'), 
                    'status' => 1,
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
            $currentForm = LaundryForm::find($id);

            $number = $currentForm->number;
            // $getOldForm = LaundryForm::where('number', rtrim($number, '-UPREV'))->first();
            $getOldForm = LaundryForm::where('number', $number, '-UPREV')->first();

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
                    'status' => 1,
                ]);
            } else {
                $getOldForm->update([
                    'rev_rejected_by' => Auth::user()->id,
                    'rev_rejected_at' => \Carbon\Carbon::now('Asia/Hong_Kong')->toDateTimeString(),
                    'note' => $note,
                    'number' => rtrim($number, '-UPREV'), 
                    'status' => 1,
                ]);
            }

        } catch (Exception $e) {
            // DB::rollback();
            return response()->json(['error' => false, 'message' => 'Failed to cancel reject form']);
        }
        // DB::commit();

        return response()->json(['error' => false, 'message' => 'Revision Form Rejected', 'id' => $getOldForm->id, 'rev_id' => $currentForm->id]);
    }
    
    public function revDelete($rev_id) {
        try {
	     $currentForm = LaundryForm::find($rev_id);
             $currentForm->delete();
        } catch (Exception $e) {
             return redirect('transactions/delivery_forms');
        }

        return redirect('transactions/delivery_forms');
    }
    /*public function fix_delivery() {
$transactions = FormTransaction::select('laundry_forms.type as form_type', 'laundry_forms.number as form_number', 'hotels.name as hotel_name', 'hotels.code as hotel_code', 'hotels.customer_code as hotel_customer_code', 'sub_categories.code as subcategory_code', 'laundry_forms.factory_id', 'category_items.code as item_code', DB::raw('CONCAT(hotels.code, sub_categories.code, category_items.code) as SKU'), 'category_items.name as item_name', 'form_transactions.*', DB::raw('DATE(form_transactions.created_at) as created_at'))
->leftJoin('laundry_forms', 'laundry_forms.id', '=', 'form_transactions.form_id')
->leftJoin('hotels', 'hotels.id', '=', 'form_transactions.hotel_id')
->leftJoin('hotel_items', 'hotel_items.id', '=', 'form_transactions.item_id')
->leftJoin('category_items', 'category_items.id', '=', 'hotel_items.item_id')
->leftJoin('sub_categories', 'sub_categories.id', '=', 'category_items.subcategory_id')
->where('laundry_forms.type', '=', 2)
->where('form_transactions.amount_df', '=', 0)
->orderBy('created_at', 'ASC')
->get();

foreach ($transactions as $transaction) {
$formTransaction = FormTransaction::find($transaction->id);
$amount = $formTransaction->amount_cf;
$formTransaction->update(['amount_cf' => 0, 'amount_df' => $amount]);
}

return $transactions;
}*/
}
