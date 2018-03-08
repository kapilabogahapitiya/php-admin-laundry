<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDF, File;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Pool;
use App\Hotel;
use Mail, Log;
use App\Mail\DailyReport;
use App\Role;

class SendDailyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laundry:report {hotel_id? : Hotel id for create specific hotel report} {--daily} {--for= : For all or just for hotel} {--send}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send report to email';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $client = new GuzzleClient();
        $is_daily = $this->option('daily');
        $hotel_id = $this->argument('hotel_id');
        $is_send = $this->option('send');
        $hotels = Hotel::all();

        if ($is_daily) {
            if ($hotel_id) {
                dd('Not implemented for specific hotel id');
            } else {
                // for superadmin
                $res = $client->request('GET', env('APP_URL', 'http://tex.iot.co.id').'/reports/daily?type=plain', []);
                $admin_path = public_path('pdf/daily/superadmin');
                if (!File::exists( $admin_path)) {
                    File::makeDirectory($admin_path, 0755, true);
                }
                $admin_file_path = $admin_path .'/daily_report.pdf';
                PDF::loadHTML($res->getBody())->setPaper('a4', 'landscape')->save($admin_file_path);
                Log::info('PDF admin created');
                if ($is_send) {
                    $role = Role::where('name', '=', 'super_admin')->first();
                    foreach ($role->users as $user) {
                        Mail::to($user->email)->queue(new DailyReport($admin_file_path));
                        $this->info('Report sent to '. $user->email);
                        Log::info('Sent to '. $user->email);
                    }

                }

                // for each hotels
                foreach ($hotels as $hotel) {
                    $hotel_path = public_path('pdf/daily/hotels/'.$hotel->id);
                    if (!File::exists( $hotel_path)) {
                        File::makeDirectory($hotel_path, 0755, true);
                    }
		            $hotel_file_path = $hotel_path . '/daily_report.pdf';
                    $res = $client->request('GET', env('APP_URL', 'http://tex.iot.co.id').'/reports/daily?type=plain&hotel_id='.$hotel->id, []);
                    PDF::loadHTML($res->getBody())->setPaper('a4', 'landscape')->save($hotel_file_path);
                    if ($is_send) {
                        foreach ($hotel->HotelPics() as $pic) {
                            Mail::to($pic->email)->queue(new DailyReport($hotel_file_path));
                            $this->info('Report sent to '. $pic->hotel_name . ' ('. $pic->email.')');
                        }

                    }
                }
            }
        } else {
            dd('Not implemented');
        }


    }
}
