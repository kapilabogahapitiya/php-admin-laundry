<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Carbon\Carbon;

class DailyReport extends Mailable
{
    use Queueable, SerializesModels;

    protected $file_path;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($file_path)
    {
        $this->file_path = $file_path;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $date = Carbon::now()->format('j F Y');
        return $this->from('support@tex.iot.co.id')
                ->subject('[TexCare] Daily Report, '.$date)
                ->view('emails.daily_report')
                ->attach($this->file_path);
    }
}
