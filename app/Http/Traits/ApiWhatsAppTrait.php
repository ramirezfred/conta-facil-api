<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use App\Http\Requests;

use App\Models\Bot;

use DB;

use Exception;

date_default_timezone_set('America/Mexico_City');

trait ApiWhatsAppTrait
{
    public static $base_url_whatsapp = "https://graph.facebook.com";
    public static $path_whatsapp = "/v21.0";

    // Pruebas
    // public static $access_token_whatsapp = "EAAXH0WF9Wn8BO4pizymuF5FViaTDbIVc5WtakWEw83GAaMi7D7yXIwZCr0CmWXdEreecEgl5mpcl8fjZAPLUACPomCJBnd6OXsHeZAqdqivhwkUxPyRxJGO5DcYZAIb5COdMZAjDCslJXhku5CrMaBEEo99O9M1YpcNf6FMALZBjueNc6It7uJNkwWZCs3bQZBRG";
    // public static $number_id_whatsapp = "512806535253681";
    // public static $app_id_whatsapp = "485495804649231";

    // Produccion
    public static $access_token_whatsapp = "EAAG1OtoJusoBO8MHD7TuK8Osgk8FK21ErOEE9K2RSSrxaJONLsie9Vh2xKB2BekOwJ9PrzljGRO1ws2vCItzhfVbN61bE8PEadb0Rcnh1qQJZBr7wPFGJ0LXWAml26WY2PKUMbWw2FSnXSK7GANKZAy4ZBHxy2oZB5A0nlVz2Cxp7Ll9SZBeKD0HeDqeOKsMmunRRvTHt9mZAEZCNBFFpZByxoIHTza0iQS9tqIGMs2YODQZD";
    public static $number_id_whatsapp = "539858185875821";
    public static $app_id_whatsapp = "522975457567880";

    public static function _messageTextWS($to, $body)
    {
        set_time_limit(500);

        $bot = Bot::find(1);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Bot no encontrado.',
                'whatsapp'=>null
            ];
        }

        $rest = substr($to, 0, 3);
        if($rest == 521){
            $to = str_replace("521", "52", $to);
        }

        //Armando la peticion cURL        
        $fields = array(
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'type'=> 'text',
            'to' => '+'.$to,
            //'to' => $to,
            'text' => array(
                'preview_url' => false,
                'body' => $body
            )
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_whatsapp.static::$path_whatsapp."/".$bot->number_id."/messages");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$bot->access_token,
            "Content-Type: application/json"
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {

            file_put_contents('webhook_log.txt', print_r($err, true), FILE_APPEND);

            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con WhatsApp',
                'whatsapp'=>$err
            ];

        } else {

            $whatsapp_obj = json_decode($response);

            file_put_contents('webhook_log.txt', print_r($whatsapp_obj, true), FILE_APPEND);

            return [
                'status'=>200,
                'whatsapp'=>$whatsapp_obj
            ];

            if (property_exists($whatsapp_obj, 'messages')) {

                return [
                    'status'=>200,
                    'whatsapp'=>$whatsapp_obj
                ]; 

            }else{
                return [
                    'status'=>409,
                    'error'=>'Error al enviar mensaje',
                    'whatsapp'=>$whatsapp_obj
                ];
            }

        }  

    }

    // public static function _messageInteractive($bot_id, $to, $body)
    // {
    //     set_time_limit(500);

    //     $bot = Bot::find($bot_id);
    //     if (!$bot)
    //     {
    //         // Devolvemos error codigo http 404
    //         return [
    //             'status'=>404,
    //             'error'=>'No existe el bot con id '.$bot_id,
    //             'whatsapp'=>null
    //         ];
    //     }

    //     if ($bot->status != 1)
    //     {
    //         // Devolvemos error codigo http 409
    //         return [
    //             'status'=>409,
    //             'error'=>'Bot inactivo',
    //             'whatsapp'=>null
    //         ];
    //     }

    //     $rest = substr($to, 0, 3);
    //     if($rest == 521){
    //         $to = str_replace("521", "52", $to);
    //     }

    //     //Armando la peticion cURL        
    //     $fields = array(
    //         'messaging_product' => 'whatsapp',
    //         'recipient_type' => 'individual',
    //         'to' => '+'.$to,
    //         'type'=> 'interactive',
    //         //'to' => $to,
    //         'interactive' => $body
    //     ); 

            
    //     $fields = json_encode($fields);
    //     /* print("\nJSON sent:\n");
    //     print($fields); */

    //     $claveAdicional = config('app.lada_b');
    //     $cadenaDesencriptada = Crypt::decrypt($bot->access_token, $claveAdicional);
    //     $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

    //     $ch = curl_init();
    //     curl_setopt($ch, CURLOPT_URL, static::$base_url_whatsapp.static::$path_whatsapp."/".$bot->number_id."/messages");
    //     curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    //         "Authorization: Bearer ".$cadenaDesencriptada,
    //         "Content-Type: application/json"
    //     ));

    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    //     curl_setopt($ch, CURLOPT_HEADER, FALSE);
    //     curl_setopt($ch, CURLOPT_POST, TRUE);
    //     curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    //     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    //     $response = curl_exec($ch);
    //     $err = curl_error($ch);

    //     curl_close($ch);

    //     if ($err) {
    //         //echo "cURL Error #:" . $err;
    //         return [
    //             'status'=>409,
    //             'error'=>'Error al conectar con WhatsApp',
    //             'whatsapp'=>$err
    //         ];

    //     } else {

    //         $whatsapp_obj = json_decode($response);

    //         return [
    //             'status'=>200,
    //             'whatsapp'=>$whatsapp_obj
    //         ];

    //         if (property_exists($whatsapp_obj, 'messages')) {

    //             return [
    //                 'status'=>200,
    //                 'whatsapp'=>$whatsapp_obj
    //             ]; 

    //         }else{
    //             return [
    //                 'status'=>409,
    //                 'error'=>'Error al enviar mensaje',
    //                 'whatsapp'=>$whatsapp_obj
    //             ];
    //         }

    //     }  

    // }

    public static function _messageImageWS($to, $body, $link)
    {
        set_time_limit(500);

        $bot = Bot::find(1);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Bot no encontrado.',
                'whatsapp'=>null
            ];
        }

        $rest = substr($to, 0, 3);
        if($rest == 521){
            $to = str_replace("521", "52", $to);
        }

        //Armando la peticion cURL        
        $fields = array(
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'type'=> 'image',
            'to' => '+'.$to,
            //'to' => $to,
            'image' => array(
                'link' => $link,
                'caption' => $body
            )
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_whatsapp.static::$path_whatsapp."/".$bot->number_id."/messages");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$bot->access_token,
            "Content-Type: application/json"
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {

            file_put_contents('webhook_log.txt', print_r($err, true), FILE_APPEND);

            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con WhatsApp',
                'whatsapp'=>$err
            ];

        } else {

            $whatsapp_obj = json_decode($response);

            file_put_contents('webhook_log.txt', print_r($whatsapp_obj, true), FILE_APPEND);

            return [
                'status'=>200,
                'whatsapp'=>$whatsapp_obj
            ];

            if (property_exists($whatsapp_obj, 'messages')) {

                return [
                    'status'=>200,
                    'whatsapp'=>$whatsapp_obj
                ]; 

            }else{
                return [
                    'status'=>409,
                    'error'=>'Error al enviar mensaje',
                    'whatsapp'=>$whatsapp_obj
                ];
            }

        }  

    }

    public static function _messageDocumentWS($to, $body, $link, $reference, $ext='pdf')
    {
        set_time_limit(500);

        $bot = Bot::find(1);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Bot no encontrado.',
                'whatsapp'=>null
            ];
        }

        $rest = substr($to, 0, 3);
        if($rest == 521){
            $to = str_replace("521", "52", $to);
        }

        $fechaHora = date('Ymd_His');
        $nombreArchivo = "{$reference}_{$fechaHora}.{$ext}";

        //Armando la peticion cURL        
        $fields = array(
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'type'=> 'document',
            'to' => '+'.$to,
            //'to' => $to,
            'document' => array(
                'link' => $link,
                'filename' => $nombreArchivo,
                'caption' => $body
            )
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_whatsapp.static::$path_whatsapp."/".$bot->number_id."/messages");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$bot->access_token,
            "Content-Type: application/json"
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {

            file_put_contents('webhook_log.txt', print_r($err, true), FILE_APPEND);

            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con WhatsApp',
                'whatsapp'=>$err
            ];

        } else {

            $whatsapp_obj = json_decode($response);

            file_put_contents('webhook_log.txt', print_r($whatsapp_obj, true), FILE_APPEND);

            return [
                'status'=>200,
                'whatsapp'=>$whatsapp_obj
            ];

            if (property_exists($whatsapp_obj, 'messages')) {

                return [
                    'status'=>200,
                    'whatsapp'=>$whatsapp_obj
                ]; 

            }else{
                return [
                    'status'=>409,
                    'error'=>'Error al enviar mensaje',
                    'whatsapp'=>$whatsapp_obj
                ];
            }

        } 

    }

    // public static function _messageTemplate($bot_id, $to, $name, $code = 'es_MX' )
    // {
    //     set_time_limit(500);

    //     $bot = Bot::find($bot_id);
    //     if (!$bot)
    //     {
    //         // Devolvemos error codigo http 404
    //         return [
    //             'status'=>404,
    //             'error'=>'No existe el bot con id '.$bot_id,
    //             'whatsapp'=>null
    //         ];
    //     }

    //     if ($bot->status != 1)
    //     {
    //         // Devolvemos error codigo http 409
    //         return [
    //             'status'=>409,
    //             'error'=>'Bot inactivo',
    //             'whatsapp'=>null
    //         ];
    //     }

    //     $rest = substr($to, 0, 3);
    //     if($rest == 521){
    //         $to = str_replace("521", "52", $to);
    //     }

    //     //Armando la peticion cURL        
    //     $fields = array(
    //         'messaging_product' => 'whatsapp',
    //         'recipient_type' => 'individual',
    //         'type'=> 'template',
    //         'to' => '+'.$to,
    //         //'to' => $to,
    //         'template' => array(
    //             'name' => $name,
    //             'language' => array(
    //                 'code' => $code
    //             )/*,
    //             'components' => array(
    //                 array(
    //                     'type' => 'body',
    //                     'parameters' => array(
    //                         array(
    //                             'type' => 'text',
    //                             'type' => 'TEXT_STRING',
    //                             )
    //                     )
    //                 )
    //             )*/
    //         )
    //     ); 

            
    //     $fields = json_encode($fields);
    //     /* print("\nJSON sent:\n");
    //     print($fields); */

    //     $claveAdicional = config('app.lada_b');
    //     $cadenaDesencriptada = Crypt::decrypt($bot->access_token, $claveAdicional);
    //     $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

    //     $ch = curl_init();
    //     curl_setopt($ch, CURLOPT_URL, static::$base_url_whatsapp.static::$path_whatsapp."/".$bot->number_id."/messages");
    //     curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    //         "Authorization: Bearer ".$cadenaDesencriptada,
    //         "Content-Type: application/json"
    //     ));

    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    //     curl_setopt($ch, CURLOPT_HEADER, FALSE);
    //     curl_setopt($ch, CURLOPT_POST, TRUE);
    //     curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    //     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    //     $response = curl_exec($ch);
    //     $err = curl_error($ch);

    //     curl_close($ch);

    //     if ($err) {
    //         //echo "cURL Error #:" . $err;
    //         return [
    //             'status'=>409,
    //             'error'=>'Error al conectar con WhatsApp',
    //             'whatsapp'=>$err
    //         ];

    //     } else {

    //         $whatsapp_obj = json_decode($response);

    //         return [
    //             'status'=>200,
    //             'whatsapp'=>$whatsapp_obj
    //         ];

    //         if (property_exists($whatsapp_obj, 'messages')) {

    //             return [
    //                 'status'=>200,
    //                 'whatsapp'=>$whatsapp_obj
    //             ]; 

    //         }else{
    //             return [
    //                 'status'=>409,
    //                 'error'=>'Error al enviar mensaje',
    //                 'whatsapp'=>$whatsapp_obj
    //             ];
    //         }

    //     }  

    // }

    // public static function _messageTemplateParameters($bot_id, $to, $name, $code = 'es_MX', $type, $parameters)
    // {
    //     set_time_limit(500);

    //     $bot = Bot::find($bot_id);
    //     if (!$bot)
    //     {
    //         // Devolvemos error codigo http 404
    //         return [
    //             'status'=>404,
    //             'error'=>'No existe el bot con id '.$bot_id,
    //             'whatsapp'=>null
    //         ];
    //     }

    //     if ($bot->status != 1)
    //     {
    //         // Devolvemos error codigo http 409
    //         return [
    //             'status'=>409,
    //             'error'=>'Bot inactivo',
    //             'whatsapp'=>null
    //         ];
    //     }

    //     $rest = substr($to, 0, 3);
    //     if($rest == 521){
    //         $to = str_replace("521", "52", $to);
    //     }

    //     //Armando la peticion cURL        
    //     $fields = array(
    //         'messaging_product' => 'whatsapp',
    //         'recipient_type' => 'individual',
    //         'type'=> 'template',
    //         'to' => '+'.$to,
    //         //'to' => $to,
    //         'template' => array(
    //             'name' => $name,
    //             'language' => array(
    //                 'code' => $code
    //             ),
    //             'components' => array(
    //                 array(
    //                     'type' => $type,
    //                     'parameters' => $parameters
    //                 )
    //             )
    //         )
    //     ); 

            
    //     $fields = json_encode($fields);
    //     /* print("\nJSON sent:\n");
    //     print($fields); */

    //     $claveAdicional = config('app.lada_b');
    //     $cadenaDesencriptada = Crypt::decrypt($bot->access_token, $claveAdicional);
    //     $cadenaDesencriptada = substr($cadenaDesencriptada, 0, -5);

    //     $ch = curl_init();
    //     curl_setopt($ch, CURLOPT_URL, static::$base_url_whatsapp.static::$path_whatsapp."/".$bot->number_id."/messages");
    //     curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    //         "Authorization: Bearer ".$cadenaDesencriptada,
    //         "Content-Type: application/json"
    //     ));

    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    //     curl_setopt($ch, CURLOPT_HEADER, FALSE);
    //     curl_setopt($ch, CURLOPT_POST, TRUE);
    //     curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    //     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    //     $response = curl_exec($ch);
    //     $err = curl_error($ch);

    //     curl_close($ch);

    //     if ($err) {
    //         //echo "cURL Error #:" . $err;
    //         return [
    //             'status'=>409,
    //             'error'=>'Error al conectar con WhatsApp',
    //             'whatsapp'=>$err
    //         ];

    //     } else {

    //         $whatsapp_obj = json_decode($response);

    //         return [
    //             'status'=>200,
    //             'whatsapp'=>$whatsapp_obj
    //         ];

    //         if (property_exists($whatsapp_obj, 'messages')) {

    //             return [
    //                 'status'=>200,
    //                 'whatsapp'=>$whatsapp_obj
    //             ]; 

    //         }else{
    //             return [
    //                 'status'=>409,
    //                 'error'=>'Error al enviar mensaje',
    //                 'whatsapp'=>$whatsapp_obj
    //             ];
    //         }

    //     }  

    // }

    public static function _readConfirmationWS($WHATSAPP_MESSAGE_ID)
    {
        set_time_limit(500);

        $bot = Bot::find(1);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Bot no encontrado.',
                'whatsapp'=>null
            ];
        }

        //Armando la peticion cURL        
        $fields = array(
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id'=> $WHATSAPP_MESSAGE_ID,
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_whatsapp.static::$path_whatsapp."/".$bot->number_id."/messages");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$bot->access_token,
            "Content-Type: application/json"
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {

            // file_put_contents('webhook_log.txt', print_r($err, true), FILE_APPEND);

            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con WhatsApp',
                'whatsapp'=>$err
            ];

        } else {

            $whatsapp_obj = json_decode($response);

            // file_put_contents('webhook_log.txt', print_r($whatsapp_obj, true), FILE_APPEND);

            return [
                'status'=>200,
                'whatsapp'=>$whatsapp_obj
            ];

        }  

    }

    public static function _typingIndicatorWS($WHATSAPP_MESSAGE_ID)
    {
        set_time_limit(500);

        $bot = Bot::find(1);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Bot no encontrado.',
                'whatsapp'=>null
            ];
        }

        //Armando la peticion cURL        
        $fields = array(
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id'=> $WHATSAPP_MESSAGE_ID,
            'typing_indicator' => array(
                'type' => 'text'
            )
        ); 

            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_whatsapp.static::$path_whatsapp."/".$bot->number_id."/messages");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer ".$bot->access_token,
            "Content-Type: application/json"
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {

            // file_put_contents('webhook_log.txt', print_r($err, true), FILE_APPEND);

            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con WhatsApp',
                'whatsapp'=>$err
            ];

        } else {

            $whatsapp_obj = json_decode($response);

            // file_put_contents('webhook_log.txt', print_r($whatsapp_obj, true), FILE_APPEND);

            return [
                'status'=>200,
                'whatsapp'=>$whatsapp_obj
            ];

        }  

    }

    public static function _downloadWhatsAppMediaWS($mediaId, $fileName) {

        set_time_limit(500);

        $bot = Bot::find(1);
        if (!$bot)
        {
            // Devolvemos error codigo http 404
            return [
                'status'=>404,
                'error'=>'Bot no encontrado.',
                'whatsapp'=>null
            ];
        }

        $token = $bot->access_token; // Tu token del Cloud API
        $phoneId = $bot->number_id; // Tu ID del número

        // Paso 1: obtener la URL del archivo
        $response = Http::withToken($token)->get("https://graph.facebook.com/v21.0/{$mediaId}");
        
        if ($response->failed()) {
            // \Log::error("Error obteniendo metadata del archivo: ".$response->body());
            $err = "Error obteniendo metadata del archivo: ".$response->body();
            file_put_contents('webhook_log.txt', print_r($err, true), FILE_APPEND);
            // return false;
            return "Error obteniendo metadata del archivo";
        }

        $mediaUrl = $response->json()['url'];

        // Paso 2: descargar el archivo real
        $fileResponse = Http::withToken($token)->get($mediaUrl);

        if ($fileResponse->successful()) {
            Storage::put("whatsapp/{$fileName}", $fileResponse->body());
            // \Log::info("Archivo guardado en: storage/app/whatsapp/{$fileName}");
            $err = "Archivo guardado en: storage/app/whatsapp/{$fileName}";
            file_put_contents('webhook_log.txt', print_r($err, true), FILE_APPEND);
            // return true;
            return "storage/app/whatsapp/{$fileName}";
        } else {
            // \Log::error("Error descargando archivo: ".$fileResponse->body());
            $err = "Error descargando archivo: ".$fileResponse->body();
            file_put_contents('webhook_log.txt', print_r($err, true), FILE_APPEND);
            // return false;
            return "Error descargando archivo";
        }
    }

}
