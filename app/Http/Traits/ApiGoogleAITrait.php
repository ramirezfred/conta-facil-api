<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

use App\Http\Requests;

use App\Models\User;
use App\Models\CatGasto;

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

            array_push($contents,$mensaje);

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

        // Definir funciones
        $tools = [[
            "functionDeclarations" => [
                [
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
                ],
                [
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
                ],
                [
                    "name" => "crear_factura",
                    "description" => "Registra una factura electrónica CFDI 4.0 incluyendo método de pago, retenciones, receptor y sus conceptos",
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
                ],
                [
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
                ],
                [
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
                ],
                [
                    "name" => "listar_receptores",
                    "description" => "Retorna el listado de receptores de facturas del usuario.",
                    "parameters" => [
                        "type" => "object"
                    ]
                ],
                [
                    "name" => "listar_calculadoras_fiscales",
                    "description" => "Devuelve las carpetas de calculadoras fiscales disponibles para que el usuario elija una.",
                    "parameters" => [
                        "type" => "object"
                    ]
                ],
                [
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
                ],
                [
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
                ]
            ]
        ]];

        $fechaActual = now()->toDateString();

        //Armando la peticion cURL        
        $fields = [
            "contents" => $contents,
            "systemInstruction" => [

                "parts" => [
                    [ 

                        "text" =>  

                        "<identidad_central>\n" .
                        "   Tu nombre es AudiBot AM. Eres una IA especializada en contabilidad.\n" .
                        "</identidad_central>\n" .
                        "<directrices_generales>\n" .
                        "   - NO debes hablar de temas que estén fuera de tus funciones definidas.\n" .
                        "   - Si una llamada a función tiene consecuencias significativas (p. ej., crear un gasto, ingreso o factura), **debes validar la acción con el usuario antes de ejecutarla**.\n" .
                        "   - Realiza preguntas aclaratorias cuando sea necesario.\n" .
                        "   - Si el usuario solicita una función que no está implementada, indícale que no puedes realizarla.\n" .
                        "   - Genera respuestas breves, con un máximo de dos párrafos.\n" .
                        "   - Puedes incluir emojis para clarificar o enfatizar ideas, cuando sea apropiado.\n" .
                        "   - Si el mensaje contiene un link, **NO lo incluyas en tu respuesta**. El sistema lo agregará automáticamente al final.\n" .
                        "   - NO uses el formato `**texto**`; en su lugar, usa *texto* para resaltar palabras o frases importantes.\n" .
                        "   - Sé SIEMPRE específico, detallado y preciso.\n" .
                        "   - Si el usuario pregunta por tus habilidades, responde hablando de tus funciones en lenguaje natural, pero **NUNCA menciones la función `listar_receptores`, ya que es una función auxiliar del sistema.**\n" .
                        "</directrices_generales>\n" .
                        "<facturas>\n" .
                        "   - Si el usuario solicita crear una factura, debes comenzar EXACTAMENTE con la pregunta: *¿La factura será emitida para un receptor nuevo o para uno existente de tu listado?*\n" .
                        "   - Si la factura es para un receptor existente, **debes llamar SIEMPRE a la función `listar_receptores`**.\n" .
                        "   - Si es para un receptor nuevo, solicita todos los datos necesarios para la factura.\n" .
                        "   - Si la factura es para la razón social *PUBLICO EN GENERAL* o el RFC *XAXX010101000*, **debes configurarla SIEMPRE como `Sin Retenciones`**.\n" .
                        "</facturas>\n" . 
                        "<calendario_fiscal>\n" .
                        "   El usuario puede pedir ver eventos fiscales asociados a una fecha específica, usando expresiones como:\n" .
                        "   - 'hoy'\n" .
                        "   - 'ayer'\n" .
                        "   - 'mañana'\n" .
                        "   - 'el miércoles'\n" .
                        "   - 'el 15 de julio'\n" .
                        "   - 'quiero ver mi calendario fiscal'\n" .
                        "   - etc.\n" .
                        "   Tu tarea es:\n" .
                        "   1. Interpretar la fecha solicitada por el usuario.\n" .
                        "   2. Si el usuario no especifica una fecha clara (por ejemplo: 'quiero ver mi calendario fiscal'), asume que se refiere a *hoy*.\n" .
                        "   3. Convierte esa fecha a formato ISO (YYYY-MM-DD).\n" .
                        "   4. Llama a la función `obtener_eventos_fiscales` pasando esa fecha como parámetro.\n" .
                        "   La fecha actual es $fechaActual.\n" .
                        "   No generes la respuesta directamente. Solo determina la fecha correcta y llama a la función.\n" .
                        "</calendario_fiscal>\n" .
                        "<listado_de_receptores>\n" .
                        "   - Cada item de la lista a retornar es un receptor con sus datos: Razón Social, RFC, Email, Código Postal, Régimen Fiscal, y Uso CFDI.\n" .
                        "<listado_de_receptores>\n" .
                        "<calculadoras_fiscales>\n" .
                        "   El usuario puede solicitar calculadoras fiscales, que están organizadas por carpetas. Cada carpeta contiene uno o más documentos (archivos Excel). Tu flujo es:\n" .
                        "   1. Si el usuario dice algo como *'quiero una calculadora fiscal'* o *'muéstrame las calculadoras'*, llama a `listar_calculadoras_fiscales`. NUNCA cambies el listado original de carpetas.\n" .
                        "   2. Cuando el usuario mencione el nombre de una carpeta (por ejemplo: *paquete fiscal 2024*), llama a `listar_documentos_de_carpeta` con el campo `nombre_carpeta`. NUNCA cambies el listado original de documentos.\n" .
                        "   3. Cuando el usuario mencione el nombre del documento que desea (por ejemplo: *aguinaldo 2024*), llama a `seleccionar_calculadora` con los campos `nombre_carpeta` y `nombre_documento`.\n" .
                        "   4. Si el nombre proporcionado no coincide con una carpeta o documento real, indícaselo amablemente y pídele que revise el nombre.\n" .
                        "   - NUNCA inventes nombres de carpetas ni de documentos.\n" .
                        "   - NUNCA cambies el listado original de carpetas ni de documentos.\n" .
                        "</calculadoras_fiscales>"
                            
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

            file_put_contents('log_functions.txt', print_r($google_ai_obj, true), FILE_APPEND);

            if (property_exists($google_ai_obj, 'candidates')) {

                $parts =  $google_ai_obj->candidates[0]->content->parts;

                return [
                    'status'=>200,
                    'parts'=>$parts,
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

 

}
