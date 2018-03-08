<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\FormNumber;

class ResetFormNumber extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laundry:reset_number';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset collection form year and number';

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
        $forms = FormNumber::all();
        foreach ($forms as $form) {
            $form->update([
                            'year' => \Carbon\Carbon::now()->year,
                            'HK' => 0,
                            'GL' => 0,
                            'SP' => 0,
                            'UN' => 0,
                            'FB' => 0,
			    'PT' => 0,
			    'RJ' => 0,
			    'HKM' => 0
                          ]);
        }
    }
}
