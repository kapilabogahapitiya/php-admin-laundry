<?php

namespace App\Http\Controllers;

use App\Mail\ForgotPassword;
use App\User;
use App\UserSession;
use Auth;
use Hash;
use Illuminate\Http\Request;
use Mail;
use Session;
use Validator;

class SessionsController extends Controller {
    public function login() {
        return view('bsb.authentications.login');
    }

    public function doLogin(Request $request) {
        $valid = ['username' => 'required', 'password' => 'required'];
        $validate = Validator::make($request->all(), $valid);
        if ($validate->fails()) {
            return redirect('login')
                ->withInput()
                ->withErrors($validate);
        }
        if (Auth::attempt(
            ['username' => $request['username'],
                'password' => $request['password'],
                'status' => '1'],
            $request['remember']
        )) {
            // store session
            $found = UserSession::where('user_id', '=', Auth::user()->id)->first();
            if ($found) {
                $found->update(['session_id' => Session::getId()]);
            } else {
                UserSession::create(['user_id' => Auth::user()->id, 'session_id' => Session::getId()]);
            }
            if (Auth::user()->hasRole('super_admin')) {
                return redirect()->intended('dashboard');
            } elseif (Auth::user()->hasRole('pabrik_admin_timbangan')) {
                return redirect()->intended('scales');
            } elseif (Auth::user()->hasRole('hotel_supervisor')) {
                return redirect()->intended('general_report');
            } else {
                return redirect()->intended('ability');
            }
        } else {
            Session::flash('error', 'Login failed. Username and password do not match.');
            return redirect('login')
                ->withInput();
        }
    }

    public function logout() {
        Auth::logout();
        return redirect('login');
    }

    public function forgot() {
        return view('bsb.authentications.forgot');
    }

    public function doForgot(Request $request) {
        $user = User::where('email', '=', $request->get('email'))->first();
        if (!empty($user)) {
            $user->update(['forgot_token' => str_random(60)]);
            $reset_token = $user->forgot_token;
            Mail::to($user->email)->send(new ForgotPassword($user, $reset_token));
            Session::flash('success', 'please check your email for next step reset password');
            return back();
        }
        Session::flash('error', 'user not found');
        return back()->withInput();
    }

    public function passwordReset(Request $request, $token) {
        $user = User::where('forgot_token', $token)->first();
        if ($user == null) {
            Session::flash('error', 'Your token has expired');
            return redirect('login');
        }
        return view('bsb.authentications.reset')->with('token', $token);
    }

    public function doPasswordReset(Request $request, $token) {
        $user = User::where('forgot_token', $request->token)->first();
        if (!empty($user)) {
            $hash_password = Hash::make($request['password']);
            $validate = Validator::make($request->all(), User::valid_update_forgot());
            if ($validate->fails()) {
                return back()
                    ->withErrors($validate)
                    ->withInput();
            } else {
                if ($user->update(
                    [
                        'password' => $hash_password,
                        'password_confirmation' => $hash_password,
                        'forgot_token' => null]
                )) {
                    Session::flash('success', 'success update your password, lets login');
                    return redirect('login');
                }
            }
        }
        Session::flash('error', 'fails update user');
        return redirect('/');
    }
}
