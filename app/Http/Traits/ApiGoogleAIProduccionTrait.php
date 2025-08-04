<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

use App\Http\Requests;

use App\Models\User;

use DB;

use Exception;

use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

trait ApiGoogleAITrait
{
    public static $base_url_googleAI = "https://generativelanguage.googleapis.com";
    public static $path_googleAI = "/v1beta";
    // public static $model_googleAI = "gemini-1.5-flash";
    public static $model_googleAI = "gemini-2.0-flash";

    public static $cache_ttl_googleAI = "3600s"; //1 hora
    // public static $cache_model_googleAI = "gemini-1.5-flash-001";
    // public static $cache_model_googleAI = "gemini-2.0-flash";
    public static $cache_model_googleAI = "gemini-1.5-flash-002";

    
    public static function _messageGoogleAI($apiKey, $mensajes)
    {
        set_time_limit(500);  

        $text_mensajes = '';
        for ($i=0; $i < count($mensajes); $i++) { 
            if($i == 0){
                $text_mensajes = $mensajes[$i];
            }else{
                $text_mensajes = $text_mensajes.' '.$mensajes[$i];
            }
        }

        //Armando la peticion cURL        
        $fields = [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $text_mensajes ]/*,
                        [ 'file_data' => [
                            'mime_type' => 'application/pdf',
                            'file_uri' => $fileUri
                        ]]*/
                    ],
                    'role' => 'user'
                ]
            ],
            'systemInstruction' => [

                'parts' => [
                    [ 

'text' =>  'Eres una Inteligencia artificial especializada en contabilidad.
Tu nombre es AudiBot AM, no debes hablar de otro tema más que lo que tienes en tus instrucciones.
Es importante que respondas muy bien con estas instrucciones:

- Si el usuario quiere:
Configurar emisor de facturas, Configurar emisor, Emisor de CFDI, Configurar facturas

Debes retornar el siguiente JSON:
{"modulo":1,"tab":0}

- Si el usuario quiere:
Facturar, Crear factura, Nueva factura, Emitir factura

Debes retornar el siguiente JSON:
{"modulo":1,"tab":1}

- Si el usuario quiere:
Ver sus facturas, Ver facturas, Ver mis facturas, Listado de facturas

Debes retornar el siguiente JSON:
{"modulo":1,"tab":2}

- Si el usuario quiere:
Ver paquetes, Comprar un paquete, Listado de paquetes, Adquirir un paquete

Debes retornar el siguiente JSON:
{"modulo":2,"tab":0}

- Si el usuario quiere:
Crear ingreso, Nuevo ingreso, Emitir ingreso

Debes retornar el siguiente JSON:
{"modulo":3,"tab":0}

- Si el usuario quiere:
Ver sus ingresos, Ver ingresos, Ver mis ingresos, Listado de ingresos

Debes retornar el siguiente JSON:
{"modulo":3,"tab":1}

- Si el usuario quiere:
Crear gasto, Nuevo gasto, Emitir gasto

Debes retornar el siguiente JSON:
{"modulo":4,"tab":0}

- Si el usuario quiere:
Ver sus gastos, Ver gastos, Ver mis gastos, Listado de gastos

Debes retornar el siguiente JSON:
{"modulo":4,"tab":1}

- Si el usuario quiere:
Ver cursos, Listado de cursos

Debes retornar el siguiente JSON:
{"modulo":5,"tab":0}

- Si el usuario quiere:
Ver contadores, Buscar un contador, Ver la red fiscal, Encontrar un especialista, Asesoria contable y legal

Debes retornar el siguiente JSON:
{"modulo":6,"tab":0}

- Si el usuario quiere:
Cuales con tus habilidades, Que habilidades tienes, Que puedes hacer, Que sabes hacer, Que haces

Debes retornar el siguiente JSON:
{"modulo":7,"tab":0}

- Si el usuario quiere otra cosa o habla de cualquier otro tema

Debes retornar el siguiente JSON:
{"modulo":0,"tab":0}

'
 
                    ]
                ]
            ]

        ];
   
        $fields_json = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_googleAI.static::$path_googleAI."/models/".static::$model_googleAI.":generateContent?key=".$apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json"
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_json);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con GoogleIA',
                'google_ai'=>$err
            ];
        } else {

            $google_ai_obj = json_decode($response);

            if (property_exists($google_ai_obj, 'candidates')) {

                $response_ai =  $google_ai_obj->candidates[0]->content->parts[0]->text;

                return [
                    'status'=>200,
                    'response_ai'=>$response_ai,
                    'google_ai'=>$google_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$google_ai_obj->error->message,
                    'google_ai'=>$google_ai_obj
                ];
            }
          
        }  

    }

    public static function _messagePDFGoogleAI($apiKey, $mensajes, $file_uri)
    {
        set_time_limit(500);

        $text_mensajes = '';
        for ($i=0; $i < count($mensajes); $i++) { 
            if($i == 0){
                $text_mensajes = $mensajes[$i];
            }else{
                $text_mensajes = $text_mensajes.' '.$mensajes[$i];
            }
        }

        //Armando la peticion cURL        
        $fields = [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $text_mensajes ],
                        [ 'file_data' => [
                            'mime_type' => 'application/pdf',
                            'file_uri' => $file_uri
                        ]]
                    ],
                    'role' => 'user'
                ]
            ],
            'systemInstruction' => [

                'parts' => [
                    [ 

'text' =>  'Eres una Inteligencia artificial especializada en contabilidad.
Tu nombre es AudiBot AM, no debes hablar de otro tema más que lo que tienes en tus instrucciones.
Es importante que respondas muy bien con estas instrucciones:

- No debes responder que sacas la infirmación de PDFS
- No debes dar referencias o número de página donde esta la información
- Solo debes responder con información relacionada al documento
- Si te saludan, puedes saludar amablemente
- Si se despiden, te puedes despedir amablemente
- Si te preguntan sobre información o temas que no estan en el documento di que no puedes responder a esa pregunta y que solo puedes responder preguntas relacionadas con contabilidad
- Responde en español
- Genera respuestas cortas, máximo de dos parrafos'
 
                    ]
                ]
            ]

        ];
   
        $fields_json = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_googleAI.static::$path_googleAI."/models/".static::$model_googleAI.":generateContent?key=".$apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json"
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_json);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con GoogleIA',
                'google_ai'=>$err
            ];
        } else {

            $google_ai_obj = json_decode($response);

            if (property_exists($google_ai_obj, 'candidates')) {

                $response_ai =  $google_ai_obj->candidates[0]->content->parts[0]->text;

                return [
                    'status'=>200,
                    'response_ai'=>$response_ai,
                    'google_ai'=>$google_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$google_ai_obj->error->message,
                    'google_ai'=>$google_ai_obj
                ];
            }
          
        }  

    }

    public static function _storeCacheGoogleAI($apiKey, $file_uri)
    {
        set_time_limit(500);

        //Armando la peticion cURL        
        $fields = [
            'model' => 'models/'.static::$cache_model_googleAI,
            'contents' => [
                [
                    'parts' => [
                        [ 'file_data' => [
                            'mime_type' => 'application/pdf',
                            'file_uri' => $file_uri
                        ]]
                    ],
                    'role' => 'user'
                ]
            ],
            'systemInstruction' => [

                'parts' => [
                    [ 

'text' =>  "Eres una Inteligencia artificial especializada en contabilidad.
Tu nombre es AudiBot AM, no debes hablar de otro tema más que lo que tienes en tus instrucciones.
Es importante que respondas muy bien con estas instrucciones:

- No debes responder que extraes la información de PDFs
- No debes dar referencias o número de página donde está la información
- Solo debes responder con información relacionada al documento
- **Solo y exclusivamente** usa la palabra 'Hola' como saludo **únicamente** si el usuario inicia su mensaje con la palabra 'Hola'. En **ningún otro caso** uses 'Hola'.
- **No inicies tus respuestas con saludos a menos que el usuario te salude explícitamente con 'Hola'. Evita saludos automáticos como 'Buenos días', 'Buenas tardes' o cualquier otra forma de saludo a menos que sea una respuesta directa a un saludo del usuario.**
- Si se despiden, te puedes despedir amablemente
- Si te preguntan sobre información o temas que no están en el documento di que no puedes responder a esa pregunta y que solo puedes responder preguntas relacionadas con contabilidad
- Responde en español utilizando un lenguaje llamativo y, cuando sea apropiado para el contexto, puedes utilizar emojis para enfatizar puntos importantes o añadir un toque de expresividad.
- Genera respuestas cortas, máximo de dos párrafos
- Ejemplos de interacciones:
    *   Usuario: '¿Cuál es el balance general?' - AudiBot AM: (Respuesta contable, sin 'Hola')
    *   Usuario: 'Hola' - AudiBot AM: '¡Hola! ¿En qué puedo ayudarte hoy?'
    *   Usuario: 'Hola, ¿cuál es el balance general?' - AudiBot AM: 'Hola, [Respuesta contable]'
    *   Usuario: 'Gracias.' - AudiBot AM: 'De nada.'
    *   Usuario: 'Adiós.' - AudiBot AM: 'Adiós.'"
                    ]
                ]
            ],
            'ttl' => static::$cache_ttl_googleAI

        ];
   
        $fields_json = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_googleAI.static::$path_googleAI."/cachedContents?key=".$apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json"
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_json);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con GoogleIA',
                'google_ai'=>$err
            ];
        } else {

            $google_ai_obj = json_decode($response);


            if (property_exists($google_ai_obj, 'name')) {

                $cache_name =  $google_ai_obj->name;

                return [
                    'status'=>200,
                    'cache_name'=>$cache_name,
                    'google_ai'=>$google_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$google_ai_obj->error->message,
                    'google_ai'=>$google_ai_obj
                ];
            }
          
        }  

    }

    public static function _messageWhitCacheGoogleAI($apiKey, $CACHE_NAME, $mensajes)
    {
        set_time_limit(500);

        $text_mensajes = '';
        for ($i=0; $i < count($mensajes); $i++) { 
            if($i == 0){
                $text_mensajes = $mensajes[$i];
            }else{
                $text_mensajes = $text_mensajes.' '.$mensajes[$i];
            }
        }

        //Armando la peticion cURL        
        $fields = [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $text_mensajes ]
                    ],
                    'role' => 'user'
                ]
            ],
            'cachedContent' => $CACHE_NAME

        ];
   
        $fields_json = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_googleAI.static::$path_googleAI."/models/".static::$cache_model_googleAI.":generateContent?key=".$apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json"
        ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_json);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con GoogleIA',
                'google_ai'=>$err
            ];
        } else {

            $google_ai_obj = json_decode($response);

            if (property_exists($google_ai_obj, 'candidates')) {

                $response_ai =  $google_ai_obj->candidates[0]->content->parts[0]->text;

                return [
                    'status'=>200,
                    'response_ai'=>$response_ai,
                    'google_ai'=>$google_ai_obj
                ];    

            }else{
                return [
                    'status'=>409,
                    'error'=>$google_ai_obj->error->message,
                    'google_ai'=>$google_ai_obj
                ];
            }
          
        }  

    }

    public static function _getFilesGoogleAI($apiKey)
    {
        set_time_limit(500);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_googleAI.static::$path_googleAI."/files?key=" . $apiKey);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        //     "Content-Type: application/json"
        // ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con GoogleIA',
                'google_ai'=>$err
            ];
        } else {

            $google_ai_obj = json_decode($response);

            if (property_exists($google_ai_obj, 'files')) {

                $files =  $google_ai_obj->files;

                return [
                    'status'=>200,
                    'files'=>$files,
                    'google_ai'=>$google_ai_obj
                ];    

            }else if (property_exists($google_ai_obj, 'error')) {

                return [
                    'status'=>409,
                    'error'=>$google_ai_obj->error->message,
                    'google_ai'=>$google_ai_obj
                ];    

            }{
                return [
                    'status'=>200,
                    'files'=>[],
                    'google_ai'=>$google_ai_obj
                ];  
            }
          
        }  

    }

    public static function _deleteFileGoogleAI($apiKey, $file_name)
    {
        set_time_limit(500);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url_googleAI.static::$path_googleAI."/".$file_name."?key=" . $apiKey);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        //     "Content-Type: application/json"
        // ));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return [
                'status'=>409,
                'error'=>'Error al conectar con GoogleIA',
                'google_ai'=>$err
            ];
        } else {

            $google_ai_obj = json_decode($response);

            if (property_exists($google_ai_obj, 'error')) {

                return [
                    'status'=>409,
                    'error'=>$google_ai_obj->error->message,
                    'google_ai'=>$google_ai_obj
                ];    

            }{
                return [
                    'status'=>200,
                    'message'=>'File eliminado exitosamente.',
                    'google_ai'=>$google_ai_obj
                ];  
            }
          
        }  

    }

 

}
