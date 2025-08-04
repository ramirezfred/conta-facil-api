<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Exception;
use Carbon\Carbon;

use Illuminate\Support\Facades\Mail;
use App\Mail\AlertaFiscalMail;

use App\Models\User;
use App\Models\CfdiEmpresa;
use App\Models\CalendarioFiscal;
use App\Models\UserSetting;


class EnviarAlertasCalendarioFiscal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // protected $signature = 'command:name';
    protected $signature = 'alertas:calendario-fiscal';

    /**
     * The console command description.
     *
     * @var string
     */
    // protected $description = 'Command description';
    protected $description = 'Envía correos de alerta a los usuarios por eventos fiscales del día';

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
     * @return int
     */
    public function handle()
    {
        $hoy = Carbon::today(); //fecha de hoy sin hora (Y-m-d)

        $eventosGenerales = CalendarioFiscal::whereDate('fecha', $hoy)
            ->whereNull('RegimenFiscal')
            ->get();

        $eventosEspecificos = CalendarioFiscal::whereDate('fecha', $hoy)
            ->whereNotNull('RegimenFiscal')
            ->get()
            ->groupBy('RegimenFiscal');

        User::whereNull('flag_eliminado')
            ->where('rol', 2) // rol 2 = cliente
            ->where('status', 1)
            ->chunk(50, function ($usuarios) use ($eventosGenerales, $eventosEspecificos) {
                foreach ($usuarios as $usuario) {

                    $setting = UserSetting::
                        where('user_id',$usuario->id)
                        ->where('notificaciones_flag', 1)
                        ->first();

                    if($setting && $setting->notificaciones_email) {

                        $empresa = CfdiEmpresa::
                            where('user_id',$usuario->id)
                            ->first();

                        if ($empresa && $empresa->RegimenFiscal)
                        {
                            $regimen = $empresa->RegimenFiscal;
                        
                            // Comenzamos con los eventos generales (RegimenFiscal = null) que aplican para todos los usuarios
                            $eventosDelUsuario = collect($eventosGenerales);
                            // $eventosDelUsuario = $eventosGenerales;

                            // Si el usuario tiene un régimen fiscal específico con eventos para hoy
                            if ($eventosEspecificos->has($regimen)) {

                                // Se combinan (merge) los eventos generales con los eventos específicos de ese régimen
                                // Así el usuario verá en su correo todos los eventos que le corresponden
                                $eventosDelUsuario = $eventosDelUsuario->merge($eventosEspecificos[$regimen]);

                            }

                            if ($eventosDelUsuario->isNotEmpty()) {

                                try {

                                    // $eventos = [];
                                    // foreach ($eventosDelUsuario as $evento) {
                                    //     array_push($eventos, [
                                    //         'id' => $evento->id,
                                    //         'titulo' => $evento->titulo,
                                    //         'descripcion' => $evento->descripcion,
                                    //         'tipo' => $evento->tipo,
                                    //         'fecha' => $evento->fecha,
                                    //         'RegimenFiscal' => $evento->RegimenFiscal,
                                    //     ]);
                                    // }

                                    // $res = [
                                    //     'usuario' => $usuario->email,
                                    //     'usuario_regimen' => $regimen,
                                    //     'eventos' => $eventos,
                                    // ];
                                    
                                    // file_put_contents('test_log_alertas.txt', print_r($res, true), FILE_APPEND);

                                    Mail::to($setting->notificaciones_email)->send(new AlertaFiscalMail($usuario, $eventosDelUsuario));
                                } catch (Exception $e) {
                                    //throw $th;
                                }
                                


                                
                            }

                        }

                    }
                }
            });

        $this->info('Correos de alerta fiscal enviados correctamente.');

        return Command::SUCCESS;
    }
}
