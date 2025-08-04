<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AlertaFiscalMail extends Mailable
{
    use Queueable, SerializesModels;

    public $usuario;
    public $eventos;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($usuario, $eventos)
    {
        $this->usuario = $usuario;
        $this->eventos = $eventos;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Recordatorio Fiscal de Hoy')
            ->view('emails.alerta_fiscal');
    }
}
