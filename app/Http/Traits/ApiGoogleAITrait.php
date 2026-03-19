<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

use App\Http\Requests;

use App\Models\User;
use App\Models\CatGasto;

use App\Models\BotSistema;

use DB;

use Exception;

use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

trait ApiGoogleAITrait
{
    public static $base_url_googleAI = "https://generativelanguage.googleapis.com";
    public static $path_googleAI = "/v1beta";
    // public static $model_googleAI = "gemini-1.5-flash";
    // public static $model_googleAI = "gemini-2.0-flash";
    public static $model_googleAI = "gemini-2.5-flash";

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

                        'text' =>  "Eres una Inteligencia artificial especializada en contabilidad.\n" .
                            "Tu nombre es AudiBot AM, no debes hablar de otro tema más que lo que tienes en tus instrucciones.\n" .
                            "Es importante que respondas muy bien con estas instrucciones:\n\n" .

                            "- Si el usuario quiere:\n" .
                            "Configurar emisor de facturas, Configurar emisor, Emisor de CFDI, Configurar facturas\n" .
                            "Debes retornar el siguiente JSON:\n" .
                            "{\"modulo\":1,\"tab\":0}\n\n" .

                            "- Si el usuario quiere:\n" .
                            "Facturar, Crear factura, Nueva factura, Emitir factura\n" .
                            "Debes retornar el siguiente JSON:\n" .
                            "{\"modulo\":1,\"tab\":1}\n\n" .

                            "- Si el usuario quiere:\n" .
                            "Ver sus facturas, Ver facturas, Ver mis facturas, Listado de facturas\n" .
                            "Debes retornar el siguiente JSON:\n" .
                            "{\"modulo\":1,\"tab\":2}\n\n" .

                            "- Si el usuario quiere:\n" .
                            "Ver paquetes, Comprar un paquete, Listado de paquetes, Adquirir un paquete\n" .
                            "Debes retornar el siguiente JSON:\n" .
                            "{\"modulo\":2,\"tab\":0}\n\n" .

                            "- Si el usuario quiere:\n" .
                            "Crear ingreso, Nuevo ingreso, Emitir ingreso\n" .
                            "Debes retornar el siguiente JSON:\n" .
                            "{\"modulo\":3,\"tab\":0}\n\n" .

                            "- Si el usuario quiere:\n" .
                            "Ver sus ingresos, Ver ingresos, Ver mis ingresos, Listado de ingresos\n" .
                            "Debes retornar el siguiente JSON:\n" .
                            "{\"modulo\":3,\"tab\":1}\n\n" .

                            "- Si el usuario quiere:\n" .
                            "Crear gasto, Nuevo gasto, Emitir gasto\n" .
                            "Debes retornar el siguiente JSON:\n" .
                            "{\"modulo\":4,\"tab\":0}\n\n" .

                            "- Si el usuario quiere:\n" .
                            "Ver sus gastos, Ver gastos, Ver mis gastos, Listado de gastos\n" .
                            "Debes retornar el siguiente JSON:\n" .
                            "{\"modulo\":4,\"tab\":1}\n\n" .

                            "- Si el usuario quiere:\n" .
                            "Ver cursos, Listado de cursos\n" .
                            "Debes retornar el siguiente JSON:\n" .
                            "{\"modulo\":5,\"tab\":0}\n\n" .

                            "- Si el usuario quiere:\n" .
                            "Ver contadores, Buscar un contador, Ver la red fiscal, Encontrar un especialista, Asesoria contable y legal\n" .
                            "Debes retornar el siguiente JSON:\n" .
                            "{\"modulo\":6,\"tab\":0}\n\n" .

                            "- Si el usuario quiere:\n" .
                            "Cuales con tus habilidades, Que habilidades tienes, Que puedes hacer, Que sabes hacer, Que haces\n" .
                            "Debes retornar el siguiente JSON:\n" .
                            "{\"modulo\":7,\"tab\":0}\n\n" .

                            "- Si el usuario quiere otra cosa o habla de cualquier otro tema\n" .
                            "Debes retornar el siguiente JSON:\n" .
                            "{\"modulo\":0,\"tab\":0}"

 
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

                        'text' =>  "Eres una Inteligencia artificial especializada en contabilidad.\n" .
                        "Tu nombre es AudiBot AM, no debes hablar de otro tema más que lo que tienes en tus instrucciones.\n" .
                        "Es importante que respondas muy bien con estas instrucciones:\n\n" .

                        "- No debes responder que sacas la información de PDFS\n" .
                        "- No debes dar referencias o número de página donde está la información\n" .
                        "- Solo debes responder con información relacionada al documento\n" .
                        "- Si te saludan, puedes saludar amablemente\n" .
                        "- Si se despiden, te puedes despedir amablemente\n" .
                        "- Si te preguntan sobre información o temas que no están en el documento, di que no puedes responder a esa pregunta y que solo puedes responder preguntas relacionadas con contabilidad\n" .
                        "- Responde en español\n" .
                        "- Genera respuestas cortas, máximo de dos párrafos"
 
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

                        'text' =>  "Eres una Inteligencia artificial especializada en contabilidad.\n" .
                        "Tu nombre es AudiBot AM, no debes hablar de otro tema más que lo que tienes en tus instrucciones.\n" .
                        "Es importante que respondas muy bien con estas instrucciones:\n\n" .

                        "- No debes responder que extraes la información de PDFs\n" .
                        "- No debes dar referencias o número de página donde está la información\n" .
                        "- Solo debes responder con información relacionada al documento\n" .
                        "- **Solo y exclusivamente** usa la palabra 'Hola' como saludo **únicamente** si el usuario inicia su mensaje con la palabra 'Hola'. En **ningún otro caso** uses 'Hola'.\n" .
                        "- **No inicies tus respuestas con saludos a menos que el usuario te salude explícitamente con 'Hola'. Evita saludos automáticos como 'Buenos días', 'Buenas tardes' o cualquier otra forma de saludo a menos que sea una respuesta directa a un saludo del usuario.**\n" .
                        "- Si se despiden, te puedes despedir amablemente\n" .
                        "- Si te preguntan sobre información o temas que no están en el documento di que no puedes responder a esa pregunta y que solo puedes responder preguntas relacionadas con contabilidad\n" .
                        "- Responde en español utilizando un lenguaje llamativo y, cuando sea apropiado para el contexto, puedes utilizar emojis para enfatizar puntos importantes o añadir un toque de expresividad.\n" .
                        "- Genera respuestas cortas, máximo de dos párrafos\n" .
                        "- Ejemplos de interacciones:\n" .
                        "    * Usuario: '¿Cuál es el balance general?' - AudiBot AM: (Respuesta contable, sin 'Hola')\n" .
                        "    * Usuario: 'Hola' - AudiBot AM: '¡Hola! ¿En qué puedo ayudarte hoy?'\n" .
                        "    * Usuario: 'Hola, ¿cuál es el balance general?' - AudiBot AM: 'Hola, [Respuesta contable]'\n" .
                        "    * Usuario: 'Gracias.' - AudiBot AM: 'De nada.'\n" .
                        "    * Usuario: 'Adiós.' - AudiBot AM: 'Adiós.'"
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

    public static function _messageFunctionCallingGoogleAI($apiKey, $mensajes, $arrayFnProcesada = [])
    {
        set_time_limit(500);  

        $contents = [];

        // Contexto de la conversacion
        for ($i=0; $i < count($mensajes); $i++) { 

            $role = 'user';

            //Bot
            if($mensajes[$i]->autor == 0){
                $role = 'model';
            }
        
            $mensaje = [
                'parts' => [
                    [ 'text' => $mensajes[$i]->text ]
                ],
                'role' => $role
            ];

            if($mensajes[$i]->text != "En estos momentos presentamos alto flujo de peticiones, háblame en un tiempo."){
                array_push($contents,$mensaje);
            }

        }

        // Llamada y resultado de la funcion procesada
        for ($i=0; $i < count($arrayFnProcesada); $i++) { 

            array_push($contents,$arrayFnProcesada[$i]);

        }

        $enum_gastos = [];
        $gastos = CatGasto::whereNull('flag_eliminado')
            ->orderBy('id', 'asc')
            ->get();
        for ($i=0; $i < count($gastos); $i++) { 
            array_push($enum_gastos,$gastos[$i]->clave);
        }

        $function_crear_gasto = [
            "name" => "crear_gasto",
            "description" => "Registra un gasto con categoría, monto y descripcion",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "categoria" => [
                        "type"=>"string",
                        "enum"=> $enum_gastos
                    ],
                    "monto" => ["type"=>"number"],
                    "descripcion" => ["type"=>"string"],
                    // "date" => [
                    //     "type" => "string",
                    //     "description" => "Date (e.g., '2024-07-29')"
                    // ]
                ],
                "required" => ["categoria","monto","descripcion"]
            ]
        ];

        $function_crear_ingreso = [
            "name" => "crear_ingreso",
            "description" => "Registra un ingreso con categoría, monto y descripcion",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "categoria" => [
                        "type"=>"string",
                        "enum"=> ["Contable","No Contable"]
                    ],
                    "monto" => ["type"=>"number"],
                    "descripcion" => ["type"=>"string"]
                ],
                "required" => ["categoria","monto","descripcion"]
            ]
        ];

        $function_resumen_factura = [
            "name" => "resumen_factura",
            "description" => "Genera un resumen detallado de la factura antes de timbrar, mostrando receptor, forma de pago, retenciones y conceptos. Se utiliza para confirmar con el usuario antes de crear la factura.",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "forma_pago" => [
                        "type"=>"string",
                        "enum"=> [
                            "Efectivo",
                            "Cheque nominativo",
                            "Transferencia electrónica de fondos",
                            "Tarjeta de crédito",
                            "Tarjeta de débito",
                            "Por definir"
                        ]
                    ],
                    "receptor" => [
                        "type"=>"object",
                        "properties" => [
                            "rfc" => ["type"=>"string"],
                            "razon_social" => ["type"=>"string"],
                            "codigo_postal" => ["type"=>"string"],
                            "regimen_fiscal" => ["type"=>"string"],
                            "uso_cfdi" => ["type"=>"string"],
                            "email" => ["type"=>"string"]
                        ],
                        "required" => ["rfc","razon_social","codigo_postal","regimen_fiscal","uso_cfdi","email"]
                    ],
                    "retenciones" => [
                        "type"=>"string",
                        "enum"=> [
                            "Sin retenciones",
                            "Con retenciones"
                        ]
                    ],
                    "conceptos" => [
                        "type" => "array",
                        "items" => [
                            "type" => "object",
                            "properties" => [
                                "clave_prod_serv" => ["type"=>"string"],
                                "clave_unidad" => ["type"=>"string"],
                                "cantidad" => ["type"=>"number"],
                                "valor_unitario" => ["type"=>"number"],
                                "descripcion" => ["type"=>"string"]
                            ],
                            "required" => ["clave_prod_serv","clave_unidad","cantidad","valor_unitario","descripcion"]
                        ]
                    ]
                ],
                "required" => ["forma_pago","receptor","retenciones","conceptos"]
            ]
        ];

        $function_crear_factura = [
            "name" => "crear_factura",
            "description" => "Registra una factura (modalidad Más IVA) electrónica CFDI 4.0 incluyendo método de pago, retenciones, receptor y sus conceptos",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "forma_pago" => [
                        "type"=>"string",
                        "enum"=> [
                            "Efectivo",
                            "Cheque nominativo",
                            "Transferencia electrónica de fondos",
                            "Tarjeta de crédito",
                            "Tarjeta de débito",
                            "Por definir"
                        ]
                    ],
                    "receptor" => [
                        "type"=>"object",
                        "properties" => [
                            "rfc" => [
                                "type"=>"string",
                                // "pattern" => "^[A-Z0-9]{12,13}$",
                                // "minLength" => "12",
                                // "maxLength" => "13",
                                // "description" => "RFC con 13 caracteres si es persona física y 12 si es persona moral"
                                "description" => "RFC (se convertirá automáticamente a mayúsculas)"
                            ],
                            "razon_social" => [
                                "type"=>"string",
                                "description" => "Razón social (se convertirá TODO automáticamente a mayúsculas)"
                            ],
                            "codigo_postal" => [
                                "type"=>"string",
                                "pattern" => "^[0-9]{5}$",
                                "minLength" => "5",
                                "maxLength" => "5",
                                "description" => "Código postal de 5 digitos"
                            ],
                            "regimen_fiscal" => [
                                "type"=>"string",
                                "enum"=> [
                                    "General de Ley Personas Morales",
                                    "Personas Morales con Fines no Lucrativos",
                                    "Sueldos y Salarios e Ingresos Asimilados a Salarios",
                                    "Arrendamiento",
                                    "Régimen de Enajenación o Adquisición de Bienes",
                                    "Demás ingresos",
                                    "Residentes en el Extranjero sin Establecimiento Permanente en México",
                                    "Ingresos por Dividendos (socios y accionistas)",
                                    "Personas Físicas con Actividades Empresariales y Profesionales",
                                    "Ingresos por intereses",
                                    "Régimen de los ingresos por obtención de premios",
                                    "Sin obligaciones fiscales",
                                    "Sociedades Cooperativas de Producción que optan por diferir sus ingresos",
                                    "Incorporación Fiscal",
                                    "Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras",
                                    "Opcional para Grupos de Sociedades",
                                    "Coordinados",
                                    "Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas",
                                    "Régimen Simplificado de Confianza"
                                ]
                            ],
                            "uso_cfdi" => [
                                "type"=>"string",
                                "enum"=> [
                                    "Nómina",
                                    "Pagos",
                                    "Honorarios médicos, dentales y gastos hospitalarios.",
                                    "Gastos médicos por incapacidad o discapacidad.",
                                    "Gastos funerales.",
                                    "Donativos.",
                                    "Intereses reales efectivamente pagados por créditos hipotecarios (casa habitación).",
                                    "Aportaciones voluntarias al SAR.",
                                    "Primas por seguros de gastos médicos.",
                                    "Gastos de transportación escolar obligatoria.",
                                    "Depósitos en cuentas para el ahorro, primas que tengan como base planes de pensiones.",
                                    "Pagos por servicios educativos (colegiaturas).",
                                    "Adquisición de mercancías.",
                                    "Devoluciones, descuentos o bonificaciones.",
                                    "Gastos en general.",
                                    "Construcciones.",
                                    "Mobiliario y equipo de oficina por inversiones.",
                                    "Equipo de transporte.",
                                    "Equipo de computo y accesorios.",
                                    "Dados, troqueles, moldes, matrices y herramental.",
                                    "Comunicaciones telefónicas.",
                                    "Comunicaciones satelitales.",
                                    "Otra maquinaria y equipo.",
                                    "Sin efectos fiscales."
                                ],
                                "description" => "Uso del CFDI"
                            ],
                            "email" => [
                                "type"=>"string"
                            ]
                        ],
                        "required" => ["rfc", "razon_social", "codigo_postal", "regimen_fiscal", "uso_cfdi", "email"]
                    ],
                    "retenciones" => [
                        "type"=>"string",
                        "enum"=> [
                            "Sin retenciones",
                            "Con retenciones"
                        ],
                        // "default"=> "Sin retenciones"
                    ],
                    "conceptos" => [
                        "type" => "array",
                        "items" => [
                            "type" => "object",
                            "properties" => [
                                "clave_prod_serv" => [
                                    "type" => "string",
                                    "description" => "Código de 8 dígitos del catálogo c_ClaveProdServ del SAT, ej. 01010101"
                                ],
                                "clave_unidad" => [
                                    "type" => "string",
                                    "description" => "Código alfanumérico del catálogo c_ClaveUnidad del SAT, ej. ACT (Actividad)"
                                ],
                                "cantidad" => ["type" => "number"],
                                "valor_unitario" => ["type" => "number"],
                                "descripcion" => ["type" => "string"] // Descripción a nivel de cada concepto
                                // "importe" => ["type" => "number"] // Monto de este concepto (cantidad * valor_unitario)
                                // ... y cualquier otro detalle del concepto como impuestos por partida
                            ],
                            "required" => ["clave_prod_serv","clave_unidad","cantidad","valor_unitario","descripcion"]
                        ]
                    ]
                ],
                "required" => ["forma_pago","receptor","retenciones","conceptos"]
            ]
        ];

        $function_cancelar_factura = [
            "name" => "cancelar_factura",
            "description" => "Cancela una factura mediante su número de serie",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "serie" => [
                        "type"=>"string",
                        "description" => "Número de serie de la factura a cancelar."
                    ]
                ],
                "required" => ["serie"]
            ]
        ];

        $function_historial = [
            "name" => "historial",
            "description" => "Retornar listado de gastos, ingresos o facturas del mes en curso",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "lista" => [
                        "type"=>"string",
                        "enum"=> ["Gastos","Ingresos","Facturas"],
                        "description"=> 'Lista que se desea visualizar'
                    ]
                ],
                "required" => ["lista"]
            ]
        ];

        $function_obtener_eventos_fiscales = [
            'name' => 'obtener_eventos_fiscales',
            'description' => 'Devuelve los eventos del calendario fiscal para una fecha determinada.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'fecha' => [
                        'type' => 'string',
                        'description' => 'La fecha solicitada en formato ISO 8601 (YYYY-MM-DD). Puede representar hoy, ayer o un día como lunes, martes, etc.'
                    ]
                ],
                'required' => ['fecha']
            ]
        ];

        $function_listar_receptores = [
            "name" => "listar_receptores",
            "description" => "Retorna el listado de receptores de facturas del usuario.",
            "parameters" => [
                "type" => "object"
            ]
        ];

        $function_listar_calculadoras_fiscales = [
            "name" => "listar_calculadoras_fiscales",
            "description" => "Devuelve las carpetas de calculadoras fiscales disponibles para que el usuario elija una.",
            "parameters" => [
                "type" => "object"
            ]
        ];

        $function_listar_documentos_de_carpeta = [
            "name" => "listar_documentos_de_carpeta",
            "description" => "Devuelve los documentos dentro de una carpeta de calculadoras fiscales, según el nombre proporcionado por el usuario.",
            "parameters" => [
                "type" => "object",
                "properties" => [
                "nombre_carpeta" => [
                    "type" => "string",
                    "description" => "Nombre (texto) de la carpeta seleccionada"
                ]
                ],
                "required" => ["nombre_carpeta"]
            ]
        ];

        $function_seleccionar_calculadora = [
            "name" => "seleccionar_calculadora",
            "description" => "Devuelve una calculadora fiscal, según el nombre de la carpeta y del documento.",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "nombre_carpeta" => [
                        "type" => "string",
                        "description" => "Nombre exacto (campo 'texto') de la carpeta seleccionada"
                    ],
                    "nombre_documento" => [
                        "type" => "string",
                        "description" => "Nombre exacto (campo 'texto') del documento que el usuario desea recibir"
                    ]
                ],
                "required" => ["nombre_carpeta","nombre_documento"]
            ]
        ];

        $function_listar_paquetes_por_categoria = [
            "name" => "listar_paquetes_por_categoria",
            "description" => "Devuelve los paquetes disponibles en una categoría específica.",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "categoria" => [
                        "type" => "string",
                        "enum" => ["Timbres", "Asesorias", "Servicios"],
                        "description" => "La categoría de paquetes a consultar."
                    ]
                ],
                "required" => ["categoria"]
            ]
        ];

        $function_obtener_detalle_paquete = [
            "name" => "obtener_detalle_paquete",
            "description" => "Devuelve los detalles completos de un paquete específico, identificado por su nombre.",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "nombre_paquete" => [
                        "type" => "string",
                        "description" => "Nombre exacto (campo 'texto') del paquete del que se quieren consultar los detalles."
                    ]
                ],
                "required" => ["nombre_paquete"]
            ]
        ];

        $function_generar_link_pago_paquete = [
            "name" => "generar_link_pago_paquete",
            "description" => "Genera un link de pago para que el usuario pueda comprar un paquete.",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "nombre_paquete" => [
                        "type" => "string",
                        "description" => "Nombre exacto (campo 'texto') del paquete que el usuario quiere comprar."
                    ]
                ],
                "required" => ["nombre_paquete"]
            ]
        ];

        $function_obtener_resumen_financiero = [
            "name" => "obtener_resumen_financiero",
            "description" => "Devuelve un resumen financiero del usuario, incluyendo ingresos, gastos, facturación y análisis comparativo del mes actual vs anterior.",
            "parameters" => [
                "type" => "object"
            ]
        ];

        // Definir funciones
        $tools = [[
            "functionDeclarations" => [
                $function_crear_gasto,
                $function_crear_ingreso,
                $function_listar_receptores,
                $function_resumen_factura,
                $function_crear_factura,
                $function_cancelar_factura,
                $function_historial,
                $function_obtener_eventos_fiscales,
                
                $function_listar_calculadoras_fiscales,
                $function_listar_documentos_de_carpeta,
                $function_seleccionar_calculadora,
                $function_listar_paquetes_por_categoria,
                $function_obtener_detalle_paquete,
                $function_generar_link_pago_paquete,
                $function_obtener_resumen_financiero
            ]
        ]];

        $fechaActual = now()->toDateString();

        $CONTADOR_TIMBRES_DISPONIBLES = 0;

        $cliente = User::whereNull('flag_eliminado')
            ->where('id', $mensajes[0]->user_id)
            // ->with('cfdi_empresa')
            ->first();

        if ($cliente)
        {
            $CONTADOR_TIMBRES_DISPONIBLES = $cliente->count_timbres;
        }

        //Armando la peticion cURL        
        $fields = [
            "contents" => $contents,
            "systemInstruction" => [

                "parts" => [
                    [ 

                        "text" =>  

                            <<<PROMPT
                                <identidad_central>
                                    Tu nombre es AudiBot AM. Eres una IA especializada en contabilidad.
                                </identidad_central>

                                <directrices_generales>
                                    - NO debes hablar de temas que estén fuera de tus funciones definidas.
                                    - Si una llamada a función tiene consecuencias significativas (p. ej., crear un gasto, crear un ingreso, crear una factura, comprar un paquete), **debes validar la acción con el usuario antes de ejecutarla**.
                                    - Realiza preguntas aclaratorias cuando sea necesario.
                                    - Si el usuario solicita una función que no está implementada, indícale que no puedes realizarla.
                                    - Genera respuestas breves, con un máximo de dos párrafos.
                                    - Puedes incluir emojis para clarificar o enfatizar ideas, cuando sea apropiado.
                                    - Si el mensaje contiene un link, **NO lo incluyas en tu respuesta**. El sistema lo agregará automáticamente al final.
                                    - NO uses el formato `**texto**`; en su lugar, usa *texto* para resaltar palabras o frases importantes.
                                    - Sé SIEMPRE específico, detallado y preciso.
                                    - Solo puedes ejecutar una función por turno.
                                    - En cada turno, debes decidir si respondes con un mensaje de texto o llamas a una función, pero nunca ambas cosas al mismo tiempo.
                                    - Si decides responder con texto, no incluyas llamadas a funciones en la misma respuesta.
                                    - Si decides llamar a una función, no incluyas texto adicional fuera del JSON de llamada.
                                    - Si el usuario pregunta por tus habilidades, responde hablando de tus funciones en lenguaje natural, pero **NUNCA menciones la función `listar_receptores`, ya que es una función auxiliar del sistema.**
                                    - CONTADOR DE TIMBRES DISPONIBLES $CONTADOR_TIMBRES_DISPONIBLES
                                    - Si el usuario quiere crear una nueva factura y no cuenta con timbres disponibles, respóndele:
                                        "No cuentas con timbres disponibles. Te recomendamos adquirir un paquete de timbres para continuar disfrutando de nuestros servicios de timbrado.  
                                        ¿Quieres ver los paquetes que tenemos disponibles?"
                                </directrices_generales>

                                <crear_factura>
                                    Si el usuario solicita crear una factura, solicita los datos para la factura siguiendo este flujo:

                                        0. Primero valida el *CONTADOR DE TIMBRES DISPONIBLES* $CONTADOR_TIMBRES_DISPONIBLES.
                                            - Si $CONTADOR_TIMBRES_DISPONIBLES es igual a 0, NO continúes con el flujo.
                                            - Debes responder exactamente con el siguiente mensaje:
                                                "No cuentas con timbres disponibles. Te recomendamos adquirir un paquete de timbres para continuar disfrutando de nuestros servicios de timbrado.  
                                                ¿Quieres ver los paquetes que tenemos disponibles?"
                                            - Si $CONTADOR_TIMBRES_DISPONIBLES es mayor a 0, continúa con el flujo normal.
                                    
                                        1. Debes comenzar EXACTAMENTE con la pregunta: *¿La factura será emitida para un receptor nuevo o para uno existente de tu listado?*
                                            - Si la factura es para un receptor existente, **debes llamar SIEMPRE a la función `listar_receptores`**. 
                                                Después de listar, el usuario debe elegir uno de los receptores disponibles. 
                                                Cuando seleccione, continúa el flujo desde el paso 2.
                                            - Si la factura es para un receptor nuevo, solicita todos los datos del receptor:
                                                RFC
                                                Razón Social
                                                Código Postal
                                                Régimen Fiscal
                                                Uso CFDI
                                                Email

                                                IMPORTANTE:
                                                - Cuando recibas el Rfc y la Razón Social, NO LOS REPITAS en la respuesta de texto y no razones sobre ello.
                                                - Confirma verbalmente que los entendiste, pero no los recites de forma literal.
                                                - Si el email no parece válido, solicita confirmación o corrección.

                                        2. Luego solicita la forma de pago. 
                                        (Ejemplo: Efectivo, Transferencia, Tarjeta, etc. según el catálogo permitido).

                                        3. Luego SIEMPRE pregunta si la factura será Con retenciones o Sin retenciones.
                                            IMPORTANTE:
                                            - Si la factura es para la razón social *PUBLICO EN GENERAL* o el RFC *XAXX010101000*, 
                                            **debes configurarla SIEMPRE como `Sin Retenciones`**.

                                        4. Luego solicita los datos de los conceptos de la factura:
                                            - Primero pregunta: *¿El concepto será un Producto o un Servicio?*
                                            
                                            - Si el concepto es un **Producto**, solicita:
                                                Clave del producto  
                                                Clave de unidad  
                                                Cantidad  
                                                Valor unitario  
                                                Descripción  

                                            - Si el concepto es un **Servicio**, solicita:
                                                Clave del producto  
                                                Cantidad  
                                                Valor unitario  
                                                Descripción  
                                                IMPORTANTE: En este caso, la Clave de unidad debe configurarse SIEMPRE con el valor `E48`.

                                            IMPORTANTE:
                                            - Cuando recibas todos los datos de un concepto, muéstralos en un pequeño resumen para confirmar.
                                            - Luego pregunta si desea agregar otro concepto.
                                            - Si el usuario responde que sí, repite este paso.

                                        5. Cuando el usuario haya proporcionado todos los datos (receptor, forma de pago, retenciones y conceptos), 
                                        **debes llamar SIEMPRE a la función `resumen_factura` pasando todos los datos recopilados**.  
                                        Esta función devolverá un resumen completo de lo que se va a facturar.  
                                        Muestra el resumen y pide SIEMPRE una confirmación explícita de que son correctos 
                                        (ejemplo: "¿Confirmas que todos los datos son correctos? Responde 'Sí, confirmo' para crear la factura").

                                        6. Cuando el usuario confirme que los datos son correctos, llama a la función `crear_factura` con todos los campos recopilados.
                                </crear_factura>

                                <cancelar_factura>
                                    Si el usuario solicita cancelar una factura, sigue este flujo:
                                    
                                        1. Solicita el Número de Serie de la factura a cancelar.
                                            - Si el usuario no proporciona un número válido, vuelve a pedírselo.

                                        2. Cuando el usuario proporcione el Número de Serie, muéstralo y pregúntale SIEMPRE si realmente está seguro que desea cancelar la factura.
                                            - Indica que debe responder de forma explícita con: "Sí, cancelar" o "No, conservar".

                                        3. Si el usuario responde "Sí, cancelar", llama a la función `cancelar_factura` con el campo `serie`.

                                        4. Si el usuario responde "No, conservar", indícale que la factura no será cancelada.
                                </cancelar_factura>

                                <calendario_fiscal>
                                    El usuario puede pedir ver eventos fiscales asociados a una fecha específica, usando expresiones como:
                                    - 'hoy'
                                    - 'ayer'
                                    - 'mañana'
                                    - 'el miércoles'
                                    - 'el 15 de julio'
                                    - 'quiero ver mi calendario fiscal'
                                    - etc.
                                    Tu tarea es:
                                    1. Interpretar la fecha solicitada por el usuario.
                                    2. Si el usuario no especifica una fecha clara (por ejemplo: 'quiero ver mi calendario fiscal'), asume que se refiere a *hoy*.
                                    3. Convierte esa fecha a formato ISO (YYYY-MM-DD).
                                    4. Llama a la función `obtener_eventos_fiscales` pasando esa fecha como parámetro.
                                    La fecha actual es $fechaActual.
                                    No generes la respuesta directamente. Solo determina la fecha correcta y llama a la función.
                                </calendario_fiscal>

                                <listado_de_receptores>
                                    - Cada item de la lista a retornar es un receptor con sus datos: Razón Social, RFC, Email, Código Postal, Régimen Fiscal, y Uso CFDI.
                                <listado_de_receptores>

                                <calculadoras_fiscales>
                                    El usuario puede solicitar calculadoras fiscales, que están organizadas por carpetas. Cada carpeta contiene uno o más documentos (archivos Excel). Tu flujo es:
                                    1. Si el usuario dice algo como *'quiero una calculadora fiscal'* o *'muéstrame las calculadoras'*, llama a `listar_calculadoras_fiscales`. NUNCA cambies el listado original de carpetas.
                                    2. Cuando el usuario mencione el nombre de una carpeta (por ejemplo: *paquete fiscal 2024*), llama a `listar_documentos_de_carpeta` con el campo `nombre_carpeta`. NUNCA cambies el listado original de documentos.
                                    3. Cuando el usuario mencione el nombre del documento que desea (por ejemplo: *aguinaldo 2024*), llama a `seleccionar_calculadora` con los campos `nombre_carpeta` y `nombre_documento`.
                                    4. Si el nombre proporcionado no coincide con una carpeta o documento real, indícaselo amablemente y pídele que revise el nombre.
                                    - NUNCA inventes nombres de carpetas ni de documentos.
                                    - NUNCA cambies el listado original de carpetas ni de documentos.
                                </calculadoras_fiscales>

                                <paquetes>
                                    - Si el usuario pregunta por *paquetes*, *compras de timbres*, *asesorías* o *servicios*, sigue este flujo:
                                        1. Si el usuario menciona que quiere ver o comprar *paquetes*, *timbres*, *asesorías* o *servicios*, identifica si ya especificó una categoría:
                                            - Si ya indicó una categoría (por ejemplo: 'quiero comprar un paquete de timbres'), llama directamente a `listar_paquetes_por_categoria`.
                                            - Si no indicó ninguna categoría, muestra las tres disponibles: *Timbres*, *Asesorías* y *Servicios*, y pregunta cuál desea consultar.
                                        2. Una vez que indique una categoría, llama a la función `listar_paquetes_por_categoria` con el campo `categoria`.
                                        3. Responde mostrando los paquetes de esa categoría. Por cada uno incluye:
                                            - `título`
                                            - `precio`
                                            - Si la categoría es **Timbres**, incluye también la `cantidad de timbres`.
                                        4. SOLO SI el usuario solicita más información sobre un paquete específico:
                                            - Llama a `obtener_detalle_paquete` con `nombre_paquete`.
                                            - Muestra los detalles del paquete incluyendo:
                                                - `título`
                                                - `descripción`
                                                - `precio`
                                                - `cantidad de timbres` (solo si aplica)
                                        5. Si el usuario desea comprar un paquete (ya sea después de ver los detalles o directamente desde la lista), llama a `generar_link_pago` con el campo `nombre_paquete`.
                                            - No incluyas el link directamente en tu respuesta. El sistema lo agregará automáticamente al mensaje.
                                    Siempre usa un tono amable, claro y orientado a ayudar al usuario a completar su compra.
                                </paquetes>

                                <resumen_financiero>
                                    El usuario puede solicitar su *resumen financiero* o *análisis financiero* usando frases como:
                                    - '¿Cuál es mi resumen financiero?'
                                    - 'Dame un análisis de este mes'
                                    - 'Resumen de ingresos, gastos y facturación'
                                    - 'Muéstrame mis ingresos y gastos del mes' (solo si NO pide fechas específicas ni listados de transacciones)
                                    - 'Quiero ver mi facturación y comparativa' (solo si NO pide facturas individuales ni historial detallado)
                                    Tu tarea es:
                                    1. Detectar si el usuario está solicitando información financiera resumida y global, no un historial.
                                    2. Si es así, llamar SIEMPRE a la función `obtener_resumen_financiero` sin parámetros.
                                    3. No generes la respuesta directamente; espera a que la función devuelva los datos y luego preséntalos al usuario en un texto breve y claro, usa emojis para ayudar a la comprensión.
                                </resumen_financiero>
                            PROMPT
                            
                    ]
                ]
            ],
            "tools" => $tools,
            "generationConfig" => [
                // "stopSequences" => [
                //     "Title"
                // ],
                "temperature" => 0.3,
                // "topP" => 0.8,
                // "topK" => 10
            ]

        ];
   
        $fields_json = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        // =============================
        // LISTA DE API KEYS A PROBAR
        // =============================
        $keys = BotSistema::where('key', '<>', $apiKey)
            // ->where('active', false)
            ->where('file_state', "ACTIVE")    
            ->get()
            ->pluck('key')
            ->toArray();

        // Siempre probar primero con la clave que vino como parámetro
        array_unshift($keys, $apiKey);

        // =============================
        // LISTA DE MODELOS A PROBAR
        // =============================
        $models = ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash', 'gemini-2.5-flash-lite'];

        // =============================
        // INTENTAR CON CADA API KEY
        // =============================
        foreach ($keys as $key) {

            foreach ($models as $model) {

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, static::$base_url_googleAI.static::$path_googleAI."/models/".$model.":generateContent?key=".$key);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    "Content-Type: application/json"
                ));

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_HEADER, FALSE);
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_json);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

                $response = curl_exec($ch);

                curl_close($ch);

                $config = [
                    'key' => $key,
                    'model' => $model
                ];
                file_put_contents('log_functions.txt', print_r($config, true), FILE_APPEND);

                $google_ai_obj = json_decode($response);

                file_put_contents('log_functions.txt', print_r($google_ai_obj, true), FILE_APPEND);

                if (property_exists($google_ai_obj, 'candidates')) {

                    if($google_ai_obj->candidates[0]->finishReason == 'STOP'){
                    
                        // ✅ Respuesta válida → devolver
                        $parts = $google_ai_obj->candidates[0]->content->parts;

                        return [
                            'status'=>200,
                            'parts'=>$parts,
                            'google_ai'=>$google_ai_obj,
                            'used_key'=>$key,
                            'used_model' => $model
                        ];

                    }
                } 

            }

        }

        // ❌ Si ninguna clave funcionó
        return [
            'status'=>409,
            'error'=>"En estos momentos presentamos alto flujo de peticiones, háblame en un tiempo.",
            'google_ai'=>null
        ];

    }

    public static function _messageFunctionCallingConfigGoogleAI($apiKey, $mensajes, $datos_recolectados = [], $arrayFnProcesada = [])
    {
        set_time_limit(500);  

        $contents = [];

        // Contexto de la conversacion
        for ($i=0; $i < count($mensajes); $i++) { 

            $role = 'user';

            //Bot
            if($mensajes[$i]->autor == 0){
                $role = 'model';
            }
        
            $mensaje = [
                'parts' => [
                    [ 'text' => $mensajes[$i]->text ]
                ],
                'role' => $role
            ];

            if($mensajes[$i]->text != "En estos momentos presentamos alto flujo de peticiones, háblame en un tiempo."){
                array_push($contents,$mensaje);
            }

        }

        // Llamada y resultado de la funcion procesada
        for ($i=0; $i < count($arrayFnProcesada); $i++) { 

            array_push($contents,$arrayFnProcesada[$i]);

        }

        $function_solicitar_aviso_privacidad = [
            "name" => "solicitar_aviso_privacidad",
            "description" => "Pregunta al usuario si acepta el aviso de privacidad y dispara el envío del documento correspondiente.",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "mensaje" => [
                        "type" => "string",
                        "description" => "Texto con la pregunta que se le debe mostrar al usuario."
                    ]
                ],
                "required" => ["mensaje"]
            ]
        ];

        $function_configurar_aviso_privacidad = [
            "name" => "configurar_aviso_privacidad",
            "description" => "Guarda la aceptación del aviso de privacidad",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "acepta_aviso_privacidad" => [
                        "type" => "boolean",
                        "description" => "Indica si el usuario acepta el aviso de privacidad"
                    ]
                ],
                "required" => ["acepta_aviso_privacidad"]
            ]
        ];

        $function_configurar_usuario = [
            "name" => "configurar_usuario",
            "description" => "Guarda el correo electrónico del usuario",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "email" => ["type" => "string", "description" => "Correo electrónico del usuario"]
                ],
                "required" => ["email"]
            ]
        ];

        $function_configurar_emisor_cfdi = [
            "name" => "configurar_emisor_cfdi",
            "description" => "Guarda la configuración fiscal del emisor de CFDI",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "rfc" => ["type" => "string", "description" => "RFC del emisor"],
                    "razon_social" => ["type" => "string", "description" => "Razón social del emisor"],
                    "regimen_fiscal" => [
                        "type" => "string",
                        "enum"=> [
                            "General de Ley Personas Morales",
                            "Personas Morales con Fines no Lucrativos",
                            "Sueldos y Salarios e Ingresos Asimilados a Salarios",
                            "Arrendamiento",
                            "Régimen de Enajenación o Adquisición de Bienes",
                            "Demás ingresos",
                            "Residentes en el Extranjero sin Establecimiento Permanente en México",
                            "Ingresos por Dividendos (socios y accionistas)",
                            "Personas Físicas con Actividades Empresariales y Profesionales",
                            "Ingresos por intereses",
                            "Régimen de los ingresos por obtención de premios",
                            "Sin obligaciones fiscales",
                            "Sociedades Cooperativas de Producción que optan por diferir sus ingresos",
                            "Incorporación Fiscal",
                            "Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras",
                            "Opcional para Grupos de Sociedades",
                            "Coordinados",
                            "Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas",
                            "Régimen Simplificado de Confianza"
                        ],
                        "description" => "Régimen fiscal del emisor"
                    ],
                    "codigo_postal" => [
                        "type" => "string",
                        "pattern" => "^[0-9]{5}$",
                        "minLength" => "5",
                        "maxLength" => "5",
                        "description" => "Código postal de 5 dígitos"
                    ],
                    // "clave_privada_csd" => ["type" => "string", "description" => "Archivo .key en base64"],
                    // "certificado_csd" => ["type" => "string", "description" => "Archivo .cer en base64"],
                    "ruta_certificado_csd" => ["type" => "string", "description" => "Ruta del archivo .cer en el servidor"],
                    "ruta_clave_privada_csd" => ["type" => "string", "description" => "Ruta del archivo .key en el servidor"],
                    "password_clave_privada" => ["type" => "string", "description" => "Contraseña de la clave privada CSD"]
                ],
                "required" => ["rfc", "razon_social", "regimen_fiscal", "codigo_postal", "ruta_certificado_csd", "ruta_clave_privada_csd", "password_clave_privada"]
            ]
        ];

        $function_configurar_facturacion = [
            "name" => "configurar_facturacion_ingresos",
            "description" => "Guarda la configuración para la facturación de ingresos contables",
            "parameters" => [
                "type" => "object",
                "properties" => [
                    "frecuencia" => [
                        "type" => "string",
                        "enum" => ["Semanal", "Mensual"],
                        "description" => "Frecuencia de facturación a público en general"
                    ],
                    "forma_pago" => [
                        "type" => "string",
                        "enum" => [
                            "Efectivo",
                            "Cheque nominativo",
                            "Transferencia electrónica de fondos",
                            "Tarjeta de crédito",
                            "Tarjeta de débito",
                            "Por definir"
                        ],
                        "description" => "Forma de pago para la facturación"
                    ],
                    "clave_producto_servicio" => [
                        "type" => "string",
                        "description" => "Código de 8 dígitos del catálogo c_ClaveProdServ del SAT, ej. 01010101"
                    ],
                    "clave_unidad" => [
                        "type" => "string",
                        "description" => "Código alfanumérico del catálogo c_ClaveUnidad del SAT, ej. ACT (Actividad)"
                    ]
                ],
                "required" => ["frecuencia", "forma_pago", "clave_producto_servicio", "clave_unidad"]
            ]
        ];


        // Definir funciones
        $tools = [[
            "functionDeclarations" => [
                $function_solicitar_aviso_privacidad,
                $function_configurar_aviso_privacidad,
                $function_configurar_usuario,
                $function_configurar_emisor_cfdi,
                $function_configurar_facturacion
            ]
        ]];

        $JSON_DE_DATOS_RECOLECTADOS = json_encode($datos_recolectados);

        //Armando la peticion cURL        
        $fields = [
            "contents" => $contents,
            "systemInstruction" => [

                "parts" => [
                    [ 

                        "text" =>  

                            <<<PROMPT
                                <identidad_central>
                                    Tu nombre es AudiBot AM. Eres una IA especializada en contabilidad.
                                    Tu tarea es solicitar al usuario los siguientes datos de configuración que aún no se han recopilado en el JSON_DE_DATOS_RECOLECTADOS,
                                    en el orden que consideres más lógico para una conversación natural.
                                </identidad_central>

                                <datos_requeridos>
                                    Aviso de Privacidad:
                                    - Aceptación del Aviso de Privacidad (boolean: Sí/No)

                                    Usuario:
                                    - Email

                                    Emisor de CFDI:
                                    - Rfc
                                    - Razón Social (Nombre)
                                    - Régimen Fiscal
                                    - Código Postal
                                    - Certificado CSD (.cer)
                                    - Clave privada CSD (.key)
                                    - Password de clave privada

                                    Facturación de Ingresos Contables (Para los Ingresos Contables el sistema emitirá una factura automáticamente a público en general, con la frecuencia seleccionada.):
                                    - Frecuencia ['Semanal','Mensual']
                                    - Forma de Pago
                                    - Clave de Producto o Servicio
                                    - Clave de Unidad
                                </datos_requeridos>

                                <directrices_generales>
                                    - NO debes hablar de temas que estén fuera de tus funciones definidas.
                                    - Si una llamada a función tiene consecuencias significativas (p. ej., configurar_usuario, configurar_emisor_cfdi o configurar_facturacion_ingresos), debes validar la acción con el usuario antes de ejecutarla.
                                    - Realiza preguntas aclaratorias cuando sea necesario.
                                    - Si el usuario solicita una función que no está implementada, indícale que no puedes realizarla.
                                    - Genera respuestas breves, con un máximo de dos párrafos.
                                    - Puedes incluir emojis para clarificar o enfatizar ideas, cuando sea apropiado.
                                    - NO uses el formato `**texto**`; en su lugar, usa *texto* para resaltar palabras o frases importantes.
                                    - Sé SIEMPRE específico, detallado y preciso.
                                    - Dispones de las funciones: configurar_usuario, configurar_emisor_cfdi y configurar_facturacion_ingresos.
                                    - Solo puedes ejecutar una función por turno.
                                    - En cada turno, debes decidir si respondes con un mensaje de texto o llamas a una función, pero nunca ambas cosas al mismo tiempo.
                                    - Si decides responder con texto, no incluyas llamadas a funciones en la misma respuesta.
                                    - Si decides llamar a una función, no incluyas texto adicional fuera del JSON de llamada.
                                    - Antes de ejecutar una función, asegúrate de que tienes todos los datos requeridos para esa función.
                                    - No mezcles datos de diferentes funciones en la misma llamada.
                                    - USA SIEMPRE el JSON_DE_DATOS_RECOLECTADOS y el historial para saber qué datos ya se han recopilado.
                                    - Cuando todos los datos de las secciones de Usuario, Emisor de CFDI y Facturación de Ingresos Contables hayan sido configurados, informa al usuario que la configuración ha terminado y que ya puede comenzar a usar todas las habilidades de AudiBot AM y dale un listado de todas las habilidades que estan en la sección de habilidades.
                                </directrices_generales>

                                <habilidades>
                                    - Registrar gastos o ingresos.
                                    - Crear facturas (modalidad Más IVA)/Cancelar facturas.
                                    - Mostrar historial de transacciones.
                                    - Consultar calendario fiscal.
                                    - Ofrecer calculadoras fiscales.
                                    - Informar sobre paquetes de timbres, asesorías o servicios.
                                    - Resumen financiero.
                                </habilidades>

                                <configurar_aviso_privacidad>
                                    Tu tarea es:
                                    1. SIEMPRE comienza llamando a la función `solicitar_aviso_privacidad` con el campo `mensaje` para preguntar al usuario si acepta el aviso de privacidad, antes de solicitar cualquier otro dato.:
                                        - `mensaje` = "Antes de continuar, necesito preguntarte: ¿aceptas el aviso de privacidad para poder usar el sistema? ✅"
                                    2. Cuando el usuario responda:
                                        - Si responde negativamente, indícale que no puede continuar con la configuración y que para continuar debe aceptar el aviso de privacidad.
                                        - Si el usuario confirma la aceptación del aviso de privacidad, llama a la función `configurar_aviso_privacidad` con el campo `acepta_aviso_privacidad`.
                                    3. Cuando la función `configurar_aviso_privacidad` se ejecute correctamente:
                                        - Revisa el JSON_DE_DATOS_RECOLECTADOS y si faltan datos, continúa solicitando los demás datos faltantes de las otras funciones.
                                        - Si todos los datos de las secciones de Aviso de Privacidad, Usuario, Emisor de CFDI y Facturación de Ingresos Contables han sido configurados, informa al usuario que la configuración ha terminado y que ya puede comenzar a usar todas las habilidades de AudiBot AM y dale un listado de todas las habilidades que estan en la sección de habilidades.
                                </configurar_aviso_privacidad>

                                <configurar_usuario>
                                    Tu tarea es:
                                    1. Solicitar el Email al usuario.
                                    2. Cuando el usuario proporcione el Email, pídele que confirme que es correcto.
                                    3. Cuando el usuario confirme que el Email es correcto llama a la función `configurar_usuario` con el campo `email`.
                                    4. Cuando la función se ejecute correctamente:
                                        - Revisa el JSON_DE_DATOS_RECOLECTADOS y si faltan datos, continúa solicitando los demás datos faltantes de las otras funciones.
                                        - Si todos los datos de las secciones de Aviso de Privacidad, Usuario, Emisor de CFDI y Facturación de Ingresos Contables han sido configurados, informa al usuario que la configuración ha terminado y que ya puede comenzar a usar todas las habilidades de AudiBot AM y dale un listado de todas las habilidades que estan en la sección de habilidades.
                                </configurar_usuario>

                                <configurar_emisor_cfdi>
                                    Tu tarea es:
                                    1. Solicitar los datos de Emisor de CFDI en el siguiente orden, pidiendo *uno por uno* y avanzando solo cuando el usuario haya respondido al anterior:

                                        1. Rfc
                                        2. Razón Social (Nombre de Empresa)
                                        3. Régimen Fiscal
                                        4. Código Postal
                                        5. Certificado CSD (.cer)
                                        6. Clave privada CSD (.key)
                                        7. Password de clave privada

                                        IMPORTANTE:
                                        - Cuando recibas el Rfc y la Razón Social, NO LOS REPITAS en la respuesta de texto y no razones sobre ello.
                                        - Confirma verbalmente que los entendiste, pero no los recites de forma literal.
                                        - Para el Certificado CSD (.cer) y la Clave privada CSD (.key), solicita al usuario que te envíe los archivos de manera individual, confirma al recibir cada uno y después solicita el siguiente dato.

                                    2. Cuando el usuario proporcione todos los datos, pídele que confirme que son correctos.
                                    3. Cuando el usuario confirme que los datos son correctos llama a la función `configurar_emisor_cfdi` con los campos `rfc`, `razon_social`, `regimen_fiscal`, `codigo_postal`, `ruta_certificado_csd`, `ruta_clave_privada_csd` y `password_clave_privada`.
                                    4. Cuando la función se ejecute correctamente:
                                        - Revisa el JSON_DE_DATOS_RECOLECTADOS y si faltan datos, continúa solicitando los demás datos faltantes de las otras funciones.
                                        - Si todos los datos de las secciones de Aviso de Privacidad, Usuario, Emisor de CFDI y Facturación de Ingresos Contables han sido configurados, informa al usuario que la configuración ha terminado y que ya puede comenzar a usar todas las habilidades de AudiBot AM y dale un listado de todas las habilidades que estan en la sección de habilidades.
                                </configurar_emisor_cfdi>

                                <configurar_facturacion_ingresos>
                                    Tu tarea es:
                                    1. Solicitar los datos de Facturación de Ingresos Contables.
                                    2. Cuando el usuario proporcione los datos, pídele que confirme que son correctos.
                                    3. Cuando el usuario confirme que los datos son correctos llama a la función `configurar_facturacion_ingresos` con los campos `frecuencia`, `forma_pago`, `clave_producto_servicio` y `clave_unidad`.
                                    4. Cuando la función se ejecute correctamente:
                                        - Revisa el JSON_DE_DATOS_RECOLECTADOS y si faltan datos, continúa solicitando los demás datos faltantes de las otras funciones.
                                        - Si todos los datos de las secciones de Aviso de Privacidad, Usuario, Emisor de CFDI y Facturación de Ingresos Contables han sido configurados, informa al usuario que la configuración ha terminado y que ya puede comenzar a usar todas las habilidades de AudiBot AM y dale un listado de todas las habilidades que estan en la sección de habilidades.
                                </configurar_facturacion_ingresos>

                                <JSON_DE_DATOS_RECOLECTADOS>
                                    Datos recopilados hasta el momento:
                                    $JSON_DE_DATOS_RECOLECTADOS
                                </JSON_DE_DATOS_RECOLECTADOS>
                            PROMPT
                            
                    ]
                ]
            ],
            "tools" => $tools,
            "generationConfig" => [
                // "stopSequences" => [
                //     "Title"
                // ],
                "temperature" => 0.3,
                // "topP" => 0.8,
                // "topK" => 10
            ]

        ];
   
        $fields_json = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        // =============================
        // LISTA DE API KEYS A PROBAR
        // =============================
        $keys = BotSistema::where('key', '<>', $apiKey)
            // ->where('active', false)
            ->where('file_state', "ACTIVE")    
            ->get()
            ->pluck('key')
            ->toArray();

        // Siempre probar primero con la clave que vino como parámetro
        array_unshift($keys, $apiKey);

        // =============================
        // LISTA DE MODELOS A PROBAR
        // =============================
        $models = ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash', 'gemini-2.5-flash-lite'];

        // =============================
        // INTENTAR CON CADA API KEY
        // =============================
        foreach ($keys as $key) {

            foreach ($models as $model) {

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, static::$base_url_googleAI.static::$path_googleAI."/models/".$model.":generateContent?key=".$key);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    "Content-Type: application/json"
                ));

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_HEADER, FALSE);
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_json);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

                $response = curl_exec($ch);

                curl_close($ch);

                $config = [
                    'key' => $key,
                    'model' => $model
                ];
                file_put_contents('log_functions.txt', print_r($config, true), FILE_APPEND);

                $google_ai_obj = json_decode($response);

                file_put_contents('log_functions.txt', print_r($google_ai_obj, true), FILE_APPEND);

                if (property_exists($google_ai_obj, 'candidates')) {

                    if($google_ai_obj->candidates[0]->finishReason == 'STOP'){
                    
                        // ✅ Respuesta válida → devolver
                        $parts = $google_ai_obj->candidates[0]->content->parts;

                        return [
                            'status'=>200,
                            'parts'=>$parts,
                            'google_ai'=>$google_ai_obj,
                            'used_key'=>$key
                        ];

                    }
                } 

            }

        }

        // ❌ Si ninguna clave funcionó
        return [
            'status'=>409,
            'error'=>"En estos momentos presentamos alto flujo de peticiones, háblame en un tiempo.",
            'google_ai'=>null
        ];

    }

 

}
