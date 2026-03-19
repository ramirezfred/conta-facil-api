<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NuevaFacturaEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $details;

    public $attachment1;

    public $attachment2;

    public $subject;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($details,$attachment1,$attachment2 = null,$subject = 'Nuevo CFDI 4.0')
    {
        $this->details = $details;
        $this->attachment1 = $attachment1;
        $this->attachment2 = $attachment2;
        $this->subject = $subject;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        //return $this->view('view.name');

        // return $this->subject($this->subject)
        //             ->view('emails.factura-nueva', $this->details)
        //             ->attach($this->attachment1)
        //             ->attach($this->attachment2);

        $email = $this->subject($this->subject)
                  ->view('emails.factura-nueva', $this->details)
                  ->attach($this->attachment1);
    
        // Adjuntar solo si existe y no es null
        if ($this->attachment2) {
            $email->attach($this->attachment2);
        }
        
        return $email;
    }
}
