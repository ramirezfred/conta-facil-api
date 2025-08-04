<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use Mail;
use Session;
use Redirect;
use Swift_SmtpTransport;
use Swift_Mailer;

use DB;
use Exception;
use Carbon\Carbon;

use App\Models\Bot;

// Se especifica la zona horaria
date_default_timezone_set('America/Mexico_City');

class BotController extends Controller
{
    public function getBot()
    {
        // Comprobamos si lo que nos están pasando existe o no.
        $bot = Bot::find(1);

        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Bot no encontrado'], 404);
        }

        $configAt = Carbon::parse($bot->updated_at);
        $ultima_config = $configAt->format('Y-m-d H:i:s');
        $bot->ultima_config = $ultima_config;

        return response()->json([
            'bot'=>$bot
        ], 200);
    }

    public function updateTokenBot(Request $request)
    {
        // Comprobamos si lo que nos están pasando existe o no.
        $bot = Bot::find(1);

        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Bot no encontrado'], 404);
        }

        // Listado de campos recibidos teóricamente.
        $access_token=$request->input('access_token');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.

        if ($access_token != null && $access_token!='')
        {
            $bot->access_token = $access_token;

            $date = Carbon::now();
            $newDate = $date->addDays(1);

            $bot->fecha_token = $newDate;

            $bandera=true;
        }
        
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($bot->save()) {

                $configAt = Carbon::parse($bot->updated_at);
                $ultima_config = $configAt->format('Y-m-d H:i:s');
                $bot->ultima_config = $ultima_config;
                
                return response()->json([
                    'message'=>'Bot configurado con éxito.',
                    'bot'=>$bot
                ], 200);

            }else{
                return response()->json(['error'=>'Error al configurar el bot.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al bot.'],409);
        }
    }

    public function alertToken()
    {
        $bot = Bot::find(1);

        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Bot no encontrado'], 404);
        }

        //fecha actual
        $date = Carbon::now();
        // $date = Carbon::create(2025, 01, 24, 12, 00);
        $hora = $date->hour;
        $minutos = $date->minute;
        $dia = $date->day;
        $mes = $date->month;
        $anio = $date->year;

        // Crea dos objetos Carbon que representan las horas que deseas comparar
        $hora1 = Carbon::createFromTimeString($hora.':'.$minutos);

        // Dividir la cadena en partes utilizando el espacio como separador
        $dateParts = explode(" ", $bot->fecha_token);

        if($bot->fecha_token != '' && $bot->fecha_token != null){

            // Dividir la cadena en partes utilizando el espacio como separador
            $dateParts = explode(" ", $bot->fecha_token);

            $fechaParts = explode("-", $dateParts[0]);
            if(

                $anio == $fechaParts[0] &&
                $mes == $fechaParts[1] &&
                $dia == $fechaParts[2]

            ){

                // Obtener la parte de la hora y dividirla en horas, minutos y segundos
                $timeParts = explode(":", $dateParts[1]);

                // Acceder a las partes específicas
                $hour = $timeParts[0];
                $minute = $timeParts[1];

                $hora2 = Carbon::createFromTimeString($hour.':'.$minute);
                

                if ($hora2->greaterThanOrEqualTo($hora1)) {
                    
                    $diferencia_en_minutos = $hora2->diffInMinutes($hora1);

                    if($diferencia_en_minutos <= 30){

                        //Enviar Email
                        $details = [
                            'title' => 'Actualizar Token de AudiBot AM',
                            'body' => 'Hola, por favor actualiza el token de AudiBot AM Powered by IA.'
                        ];

                        $correos = [
                            'Tonii.jaam@gmail.com',
                            'ramirez.fred016@gmail.com',

                        ];

                        for ($i=0; $i < count($correos); $i++) { 
                            try {
                                \Mail::to($correos[$i])->send(new \App\Mail\NotificacionEmail($details));
                            } catch (Exception $e) {
                                
                            }
                        } 

                    }

                }

            }


        }  

        return 1;    
        
    }
}
