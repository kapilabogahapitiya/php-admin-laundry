<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class CFCreatedBalancing extends Mailable
{
    use Queueable, SerializesModels;

    public $employee;
    public $form;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($employee, $form) {
        $this->employee = $employee;
        $this->form = $form;
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('noreply@tex.iot.co.id')
                    ->subject('Collection Balancing Form Created')
                    ->view('emails.cf_balancing_created');
    }
}
