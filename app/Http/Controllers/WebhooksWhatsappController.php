<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\File;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Crypt;

use Illuminate\Support\Facades\Auth;

use Exception;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

//use Hash;
use DB;
//use Validator;

use Carbon\Carbon;

use Mail;
use Session;
use Redirect;
use Swift_SmtpTransport;
use Swift_Mailer;

use App\Http\Traits\ApiWhatsAppTrait;
use App\Http\Traits\ApiChatPdfTrait;
use App\Http\Traits\ApiGoogleAITrait;

use App\Models\User;
use App\Models\BotMessage;
use App\Models\BotSistema;
use App\Models\CfdiEmpresa;
use App\Models\CfdiProducto;

date_default_timezone_set('America/Mexico_City');

use App\Services\BotService;

class WebhooksWhatsappController extends Controller
{
    use ApiWhatsAppTrait;
    use ApiChatPdfTrait;
    use ApiGoogleAITrait;

    protected $botService;

    public function __construct(BotService $botService)
    {
        $this->botService = $botService;
    }

    public function handleSuscribe(Request $request)
    {
        $input = $request->all();
        // Aquí puedes realizar cualquier acción necesaria con los datos recibidos del Webhook

        // Por ejemplo, guardar los datos recibidos en un archivo de registro
        file_put_contents('webhook_log.txt', print_r($input, true), FILE_APPEND);

        //https://apicontafacil.internow.com.mx/api/webhooks/handle

        $challenge = $request->input('hub_challenge');
        $verify_token = $request->input('hub_verify_token');

        if ($verify_token === 'webhookIAContaFacil') {
            return $challenge;
            //return response($challenge, 200);
        }

    }

    public function handleMessage(Request $request)
    {

        set_time_limit(500);
        
        $input = $request->all();
        // Aquí puedes realizar cualquier acción necesaria con los datos recibidos del Webhook

        // Verificar el tipo de evento
        if ($input['object'] === 'whatsapp_business_account' && isset($input['entry'][0]['changes'])) {

            $whatsapp_id = $input['entry'][0]['id'];

            foreach ($input['entry'][0]['changes'] as $change) {
                if ($change['field'] === 'messages') {


                        if(
                            isset($change['value']['contacts']) &&
                            isset($change['value']['messages']) 
                        ){

                            // Procesar el evento de messages

                            $name = $change['value']['contacts'][0]['profile']['name'];
                            $wa_id = $change['value']['contacts'][0]['wa_id'];

                            $from = $change['value']['messages'][0]['from'];
                            $id = $change['value']['messages'][0]['id'];
                            $type = $change['value']['messages'][0]['type'];

                            if ($type != 'text'){
                                // file_put_contents('webhook_log.txt', print_r($input, true), FILE_APPEND);

                                return response('OK', 200);
                            }

                            $body = $change['value']['messages'][0]['text']['body'];
                            
                            // Por ejemplo, guardar los datos recibidos en un archivo de registro

                            file_put_contents('webhook_log.txt', print_r($input, true), FILE_APPEND);

                            // // $respuestaAI = $this->messageAIChatPDF([$body]);
                            // $respuestaAI = $this->messageGoogleAI([$body]);
                            // // $resp = $this->_messageTextWS($from,'Hola '.$name);
                            // $resp = $this->_messageTextWS($from,$respuestaAI);
                            // return response('OK', 200);

                            //registro el mensaje en la BD
                            $user_id = $this->tratarMensaje($whatsapp_id,$name,$from,$id,$body,$type);

                            if($user_id){

                                // Verifica si hay una ejecución anterior en curso para este cliente
                                if (Cache::has('ejecucion:'.$user_id)) {
                                    // Si hay una ejecución anterior en curso, devuelve una respuesta sin esperar
                                    return response('OK', 200);
                                }

                                // Establece la variable de bloqueo para este cliente
                                Cache::put('ejecucion:'.$user_id, true, 2); // 4 segundos de duración de la ejecución

                                // Pausa la ejecución durante 15 segundos
                                sleep(2);

                                // Aquí puedes procesar el mensaje recibido del cliente
                                $respuesta = $this->respMsjsSinProcesar($user_id);

                                // Elimina la variable de bloqueo después de los 15 segundos
                                Cache::forget('ejecucion:'.$user_id);

                                // Regresar una respuesta exitosa a Facebook
                                return response('OK', 200);

                            }else{
                                /*Regresar una respuesta exitosa a Facebook
                                para que no me vuelva en enviar la notificacion*/
                                return response('OK', 200);
                            }
  

                        }               
 
                }
            }

            // Regresar una respuesta exitosa a Facebook
            //return response('OK', 200);
        }


        // Si no se procesa un evento de feed, puedes realizar otras acciones o retornar una respuesta diferente si es necesario

        // Por ejemplo, guardar los datos recibidos en un archivo de registro
        // file_put_contents('webhook_log.txt', print_r($input, true), FILE_APPEND);

        // Regresar una respuesta exitosa a Facebook
        return response('OK', 200);
    }

    private function tratarMensaje($whatsapp_id,$name,$from,$id,$body,$type)
    {
        $user_id = $this->getUserId($name,$from);
        $this->storeMessage($user_id,$id,$body,1); //cliente

        return $user_id;
    }

    private function getUserId($nombre, $telefono)
    {
        //Para numeros mexicanos
        if (substr($telefono, 0, 3) === '521') {
            $telefono = '52' . substr($telefono, 3);
        }

        $obj=User::
            where('telefono',$telefono)
            ->first();

        if (!$obj)
        {

            $nuevoObj=User::create([
                'rol' => 2, //Cliente 
                'status' => 1,
                'telefono' => $telefono,
                'email' => $telefono.'@contafacil.com',
                'password' => Hash::make($telefono),
                'nombre' => $nombre,
                'color_a' => '#4285cb',
                'color_b' => '#4285cb',
                'color_c' => '#ffffff',
                'header' => 'https://apicontafacil.internow.com.mx/images_uploads/header_footer/header_base.png',
                'footer' => 'https://apicontafacil.internow.com.mx/images_uploads/header_footer/footer_base.png',
                'logo' => 'https://apicontafacil.internow.com.mx/images_uploads/logos/logo_base.png',
                'logo_allow_origin' => 'https://apicontafacil.internow.com.mx/api/usuarios/logo/allow_origin/logo_base.png',
                'count_timbres' => 10,
                
            ]);

            //Crear la empresa emisora para las facturas
            $count_empresas = CfdiEmpresa::count();

            // Aseguramos que el número tenga un máximo de 8 dígitos
            $folio_venta = str_pad($count_empresas+1, 8, '0', STR_PAD_LEFT);

            //Crear la empresa emisora para las facturas
            $nuevaEmpresa=CfdiEmpresa::create([
                'user_id'=>$nuevoObj->id,
                'tipo_persona'=>null,
                'Rfc'=>null,
                'RazonSocial'=>null,
                'RegimenFiscal'=>null,
                'FacAtrAdquirente'=>null,
                'CP'=>null,
                'cer'=>null,
                'key'=>null,
                'pass'=>null,
                'flag_descuento'=>0,
                'flag_objetoImp'=>1,
                'flag_retencion'=>0,
                'flag_producto'=>0,
                'folio_venta'=>$folio_venta,

            ]);

            //Crear el producto asociado a la empresa
            $nuevoProducto=CfdiProducto::create([
                'empresa_id'=>$nuevaEmpresa->id,
                'ClaveProdServ'=>null,
                'NoIdentificacion'=>null,
                'Cantidad'=>null,
                'ClaveUnidad'=>null,
                'Unidad'=>null,
                'Descripcion'=>null,
                'ValorUnitario'=>null,
                'Importe'=>null,
                'Descuento'=>null,
                'ObjetoImp'=>null,
                'ObjetoImpRet'=>null,
                
            ]);


            return $nuevoObj->id;
        }

        return $obj->id;
        
    }

    private function storeMessage($user_id, $wamid, $text, $autor)
    {

        //validacion para no duplicar el mensaje del cliente
        if($autor == 1){
            $contador=BotMessage::
                where('user_id',$user_id)
                ->where('wamid',$wamid)
                ->count();

            if($contador>0){
                return 0;
            }
        }

        $nuevoObj=BotMessage::create([
            'user_id'=>$user_id,
            'wamid'=>$wamid,
            'text'=>$text,
            'autor'=>$autor,
            'status'=>0, //sin procesar
            
        ]);

        return $nuevoObj->id;
        
    }

    public function messageText(Request $request, $telefono ){

        $resp = $this->_messageTextWS($telefono,$request->input('message'));

        if ($resp['status'] == 200) {

            // $this->storeMsgChat($cliente->bot_id,$cliente->id,null,$message,0); //bot
            return 1;

        }else{

            return 0;

        }
    }

    public function messageAIChatPDF($mensajes)
    {

        $admin = User::select('id','rol','pdf_id','pdf_url','flag_eliminado')
            ->whereNull('flag_eliminado')
            ->where('rol', 1)
            ->first();

        if(!$admin){
            return 'Administrador no encontrado.';    
        }

        if($admin->pdf_id == null || $admin->pdf_id == ''){ 
            return 'Contexto no configurado.';  
        }

        $resp = $this->_messageChatPdf($mensajes, $admin->pdf_id);
        if ($resp['status'] == 200) {
            return $resp['response_ai'];
        }else{
           return $resp['error'];
        }
    }

    public function messageWhitCacheGoogleAI($apiKey, $mensajes)
    {

        $activeKey = BotSistema::where('key', $apiKey)->first();

        if(!$activeKey){
            return 'Key para caché no encontrada';
        }

        /*
        Si no tiene cache o
        esta cerca de vencer el cache de una hora
        actualizo el cache
        */

        $flag_actualizar = false;

        if(
            $activeKey->cache_name == "" || $activeKey->cache_name == null ||
            $activeKey->cache_create_at == "" || $activeKey->cache_create_at == null
        ){
            $flag_actualizar = true;
        }

        // Fecha inicial de cache
        $fechaInicio = Carbon::parse($activeKey->cache_create_at);

        // Fecha final (fecha y hora actual)
        $fechaFinal = Carbon::now();

        // Calcular minutos transcurridos
        $minutosTranscurridos = $fechaInicio->diffInMinutes($fechaFinal);

        if($minutosTranscurridos >= 58){
            $flag_actualizar = true;
        }

        if($flag_actualizar){

            $respCache = $this->_storeCacheGoogleAI($apiKey, $activeKey->file_uri);
            if ($respCache['status'] == 200) {

                $activeKey->cache_name = $respCache['cache_name'];
                $activeKey->cache_create_at = Carbon::now()->format('Y-m-d H:i:s');
                $activeKey->save();

            }else{
                return $respCache['error'];
            }

        }

        $resp = $this->_messageWhitCacheGoogleAI($apiKey, $activeKey->cache_name, $mensajes);
        if ($resp['status'] == 200) {
            return $resp['response_ai'];
        }else{
           return $resp['error'];
        }
    }

    public function messageGoogleAI($mensajes)
    {

        $apiKey = "";

        try {
            // Obtener la clave activa o rotada
            $apiKey = BotSistema::getActiveKey();

        } catch (\Exception $e) {
            // return response()->json(['error' => $e->getMessage()], 400);
            return $e->getMessage();
        }

        $resp = $this->_messageGoogleAI($apiKey, $mensajes);
        if ($resp['status'] == 200) {
            return $resp['response_ai'];
        }else{
           return $resp['error'];
        }
    }

    public function respMsjsSinProcesar($user_id)
    {
        $mensajes = BotMessage::select('id', 'user_id', 'text', 'autor', 'status')
            // ->where('status', 0)
            // ->where('autor', 1)
            ->where('user_id', $user_id)
            ->orderBy('id', 'desc')
            ->take(30)
            ->get();

        $data = [];
        for ($i=0; $i < count($mensajes); $i++) { 

            //autor 0=bot 1=user
            if($mensajes[$i]->autor == 0){
                $texto_sin_links = preg_replace('/https:\/\/[^\s]+/', '', $mensajes[$i]->text);
                $mensajes[$i]->text = $texto_sin_links;
            }
            array_unshift($data, $mensajes[$i]);
        }

        DB::table('bot_messages')
            ->where('user_id', $user_id)
            ->update(['status' => 1]);

        if(count($mensajes)==0){
            return 1;
        }

        $user = User::find($user_id);

        $user->mensajes = $data;

        $resp = $this->procesarMessagesAI($user);

        if(isset($resp['function']) && isset($resp['link'])){

            // if($resp['function'] == 'historial'){

            //     if($resp['args']->lista == "Ingresos" || $resp['args']->lista == "Gastos"){
            //         $this->messageDocumentToUser($user,$resp['message'],$resp['link'],'document');
            //     }else{

            //         // Expresión regular para eliminar ambos dominios y todo lo que 
            //         // venga después hasta un espacio o fin de línea
            //         $pattern = '/https:\/\/(api)?contafacil\.internow\.com\.mx\/[^\s]*/';

            //         // Eliminar los links
            //         $texto_sin_links = preg_replace($pattern, '', $resp['message']);

            //         $link_acortado = $this->botService->getTinyUrl($resp['link']);

            //         $resp_message = $texto_sin_links . "\n\n" . $link_acortado;

            //         $this->messageTextToUser($user,$resp_message);

            //     }

            // }else{
            //     $this->messageDocumentToUser($user,$resp['message'],$resp['link'],'document');
            // }

            if($resp['function'] == 'seleccionar_calculadora'){

                $extension = strtolower(pathinfo(parse_url($resp['link'], PHP_URL_PATH), PATHINFO_EXTENSION));

                $this->messageDocumentToUser($user,$resp['message'],$resp['link'],'document',$extension);

            }else{
                $this->messageDocumentToUser($user,$resp['message'],$resp['link'],'document');
            }

            

        }else{
            $this->messageTextToUser($user,$resp['message']);
        }
        
        return 1;
    }

    public function procesarMessagesAI($user)
    {

        // if(count($user->mensajes) == 1 && $user->mensajes[0]->autor == 1){

        //     // Detectar si esta saludando
        //     // if (preg_match('/\b(?:hola|buenos días|buenas tardes|buenas noches)\b/i', $text_mensajes)) {
        //     //     return 'Hola, ¿en qué puedo ayudarte hoy?';
        //     // }
        //     if (preg_match('/(?:\b|[¡!¿\s])(hola|buen[oa]s\s+(d[ií]as|tardes|noches))(?=\b|[!¡.,\s])/iu', $user->mensajes[0]->text)) {
        //         return 'Hola, ¿en qué puedo ayudarte hoy?';
        //     }

        //     // Detectar si pregunta por habilidades
        //     if (preg_match('/habilidad(?:es)?|qu[ée]\s+(puedes|sabes)\s+hacer|qu[ée]\s+haces/iu', $user->mensajes[0]->text)) {
        //         return "Estas son algunas de las cosas en las que puedo ayudarte:\n\n" .
        //             "💸 *GASTOS*\n" .
        //             "    - Registrar gasto\n" .
        //             "    - Ver gastos (Aquí gestionas tus gastos)\n\n" .
        //             "Escríbeme una instrucción como: *'registrar un gasto de 200 en comida'* y me encargaré.";
        //     }

        // }

        $apiKey = "";

        try {
            // Obtener la clave activa o rotada
            $apiKey = BotSistema::getActiveKey();

        } catch (\Exception $e) {
            // return response()->json(['error' => $e->getMessage()], 400);
            return [
                'message' => $e->getMessage()
            ];
        }

        $resp = $this->_messageFunctionCallingGoogleAI($apiKey, $user->mensajes);
        if ($resp['status'] == 200) {

            // Si no hay acción
            foreach ($resp['parts'] as $p) {
                if (isset($p->text)) {
                    return [
                        'message' => $p->text
                    ];
                }
            }

            //Si hay accion
            foreach ($resp['parts'] as $p) {
                if (isset($p->functionCall)) {

                    $permitidas = [
                        'crear_gasto', 'crear_ingreso', 'crear_factura', 'listar_receptores',
                        'historial', 'obtener_eventos_fiscales', 'listar_calculadoras_fiscales',
                        'listar_documentos_de_carpeta', 'seleccionar_calculadora'
                    ];
                    
                    $call = $p->functionCall;
                    $fn = $call->name;

                    if (!in_array($fn, $permitidas)) {
                        return [
                            'message' => 'Función no permitida: ' . $fn
                        ];
                    }

                    $link = null;

                    // $result = call_user_func($fn, $call->args);
                    // $result = $this->$fn($call->args);  // llama $this->crear_gasto($args)
                    $result = $this->botService->$fn($call->args, $user->id);

                    if(isset($result['link'])){
                        $link = $result['link'];
                        unset($result['link']);
                    }

                    $contents2 =
                        [
                            ['role' => 'model', 'parts' => [['functionCall' => $call]]],
                            ['role' => 'function', 'parts' => [[
                                'functionResponse' => [
                                    'name' => $call->name,
                                    'response' => $result
                                ]
                            ]]]
                        ];

                    $resp2 = $this->_messageFunctionCallingGoogleAI($apiKey, $user->mensajes, $contents2);
                    if ($resp2['status'] == 200) {

                        // Si no hay acción
                        foreach ($resp2['parts'] as $p) {
                            if (isset($p->text)) {

                                $response = [
                                    'message' => $p->text,
                                    'function' => $fn,
                                    'args' => $call->args
                                ];

                                if ($link) {
                                    $response['link'] = $link;
                                }

                                return $response;
                                
                            }
                        }

                        return [
                            'message' => 'Formato de respuesta2 desconocido.'
                        ];

                    }else{
                        return [
                            'message' => $resp2['error']
                        ];
                    }
                }
            }

        }else{
            return [
                'message' => $resp['error']
            ];
        }

        return [
            'message' => 'Formato de respuesta2 desconocido.'
        ];

    }

    public function messageTextToUser($user,$message){
        $resp = $this->_messageTextWS($user->telefono,$message);

        if ($resp['status'] == 200) {

            $this->storeMessage($user->id,null,$message,0); //bot

            return 1;

        }else{

            return 0;

        }
    }

    public function messageDocumentToUser($user,$message,$link,$reference,$ext='pdf'){
        $resp = $this->_messageDocumentWS($user->telefono,$message,$link,$reference,$ext);

        if ($resp['status'] == 200) {

            $this->storeMessage($user->id,null,$message,0); //bot

            return 1;

        }else{

            return 0;

        }
    }

    public function getMensajes($user_id)
    {
        $mensajes = BotMessage::select('id', 'user_id', 'text', 'autor', 'status')
            // ->where('status', 0)
            // ->where('autor', 1)
            ->where('user_id', $user_id)
            ->orderBy('id', 'desc')
            ->take(30)
            ->get();

        $data = [];
        for ($i=0; $i < count($mensajes); $i++) { 
            array_unshift($data, $mensajes[$i]);
        }

        return response()->json([
            'data'=>$data
        ], 200);
    }

    
}
