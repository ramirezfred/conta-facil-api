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

class WebhooksWhatsappController extends Controller
{
    use ApiWhatsAppTrait;
    use ApiChatPdfTrait;
    use ApiGoogleAITrait;

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
        $mensajes = BotMessage::
            select('id','user_id','text','autor','status')
            ->where('status', 0)
            ->where('autor', 1)
            ->where('user_id', $user_id)
            ->get();

        DB::table('bot_messages')
            ->where('user_id', $user_id)
            ->update(['status' => 1]);

        if(count($mensajes)==0){
            return 1;
        }

        $user = User::find($user_id);

        $msjs = []; 
        for ($j=0; $j < count($mensajes); $j++) { 
            array_push($msjs,$mensajes[$j]->text);
        }
        $user->mensajes = $msjs;

        $message = $this->procesarMessagesAI($user);
        // $message = 'Respuesta predefinida...';
        $this->messageTextToUser($user,$message);
        
        return 1;
    }

    public function procesarMessagesAI($user)
    {

        $text_mensajes = '';
        for ($i=0; $i < count($user->mensajes); $i++) { 
            if($i == 0){
                $text_mensajes = $user->mensajes[$i];
            }else{
                $text_mensajes = $text_mensajes.' '.$user->mensajes[$i];
            }
        }

        $minusculas = strtolower($text_mensajes);

        if ($minusculas == 'hola' || $minusculas == '¡hola!') {
            return 'Hola, ¿en qué puedo ayudarte hoy?';
        }

        $apiKey = "";

        try {
            // Obtener la clave activa o rotada
            $apiKey = BotSistema::getActiveKey();

        } catch (\Exception $e) {
            // return response()->json(['error' => $e->getMessage()], 400);
            return $e->getMessage();
        }

        $resp = $this->_messageGoogleAI($apiKey, $user->mensajes);
        if ($resp['status'] == 200) {
            // return $resp['response_ai'];

            $cadena = $resp['response_ai'];

            $posicionA = strpos($cadena, '{');
            $posicionB = strrpos($cadena, '}');
            $cadena = substr($cadena,$posicionA,$posicionB+1-($posicionA));

            if ($posicionA === false || $posicionB === false) {
                return $resp['response_ai']; // Retornar cadena vacía si no se encuentran los caracteres
            }

            $user_token=User::find($user->id);
            $token = JWTAuth::fromUser($user_token);

            $claveAdicional = config('app.lada_a');
            $cadenaEncriptada = Crypt::encrypt($user->id, $claveAdicional);

            $obj = json_decode($cadena);

            $link = "";
            $short_link = "";
            $message = "";

            //Facturas
            if($obj->modulo == 1){

                //Configurar emisor
                if($obj->tab == 0){
                    $link = 'https://contafacil.internow.com.mx/#/bot-facturacion/0/'.$cadenaEncriptada.'/'.$token;

                    $message = "Ingresa en el siguiente enlace para configurar tus datos de emisor de CFDI:\n\n{{short_link}}";
                }
                //Crear factura
                else if($obj->tab == 1){

                    $usuario = User::whereNull('flag_eliminado')
                        ->with('cfdi_empresa.mi_regimen_fiscal')
                        ->find($user->id);

                    if (!$usuario)
                    {
                        return 'Usuario no encontrado';
                    }

                    if (!$usuario->cfdi_empresa)
                    {
                        return 'Emisor CFDI no encontrado';
                    }

                    if (
                        $usuario->cfdi_empresa->Rfc == null || $usuario->cfdi_empresa->Rfc == '' ||
                        $usuario->cfdi_empresa->RazonSocial == null || $usuario->cfdi_empresa->RazonSocial == '' ||
                        $usuario->cfdi_empresa->RegimenFiscal == null || $usuario->cfdi_empresa->RegimenFiscal == '' ||
                        $usuario->cfdi_empresa->CP == null || $usuario->cfdi_empresa->CP == '' ||
                        $usuario->cfdi_empresa->cer == null || $usuario->cfdi_empresa->cer == '' ||
                        $usuario->cfdi_empresa->key == null || $usuario->cfdi_empresa->key == '' ||
                        $usuario->cfdi_empresa->pass == null || $usuario->cfdi_empresa->pass == ''
                    )
                    {
                        $link = 'https://contafacil.internow.com.mx/#/bot-facturacion/0/'.$cadenaEncriptada.'/'.$token;

                        $message = "Para facturar, primero debes configurar tus datos de emisor de CFDI.\n\nIngresa en el siguiente enlace para configurar tus datos de emisor de CFDI:\n\n{{short_link}}";

                    }else{

                        $link = 'https://contafacil.internow.com.mx/#/bot-facturacion/1/'.$cadenaEncriptada.'/'.$token;

                        $message = "Ingresa en el siguiente enlace para emitir una nueva factura:\n\n{{short_link}}";

                    }  
                    
                }
                //Listado de facturas
                else if($obj->tab == 2){
                    $link = 'https://contafacil.internow.com.mx/#/bot-facturacion/2/'.$cadenaEncriptada.'/'.$token;

                    $message = "Ingresa en el siguiente enlace para ver el listado de tus facturas:\n\n{{short_link}}";

                }else{
                    $message = 'Módulo de facturas';
                }
            }
            //Paquetes
            else if($obj->modulo == 2){

                $link = 'https://contafacil.internow.com.mx/#/bot-paquetes/0/'.$cadenaEncriptada.'/'.$token;

                $message = "Ingresa en el siguiente enlace para ver el listado de paquetes disponibles:\n\n{{short_link}}";
                
            }
            //Ingresos
            else if($obj->modulo == 3){

                //Crear ingreso
                if($obj->tab == 0){
                    $link = 'https://contafacil.internow.com.mx/#/bot-ingresos/0/'.$cadenaEncriptada.'/'.$token;

                    $message = "Ingresa en el siguiente enlace para emitir un nuevo ingreso:\n\n{{short_link}}";
                }
                //Listado de ingresos
                else if($obj->tab == 1){
                    $link = 'https://contafacil.internow.com.mx/#/bot-ingresos/1/'.$cadenaEncriptada.'/'.$token;

                    $message = "Ingresa en el siguiente enlace para ver el listado de tus ingresos:\n\n{{short_link}}";

                }else{
                    $message = 'Módulo de ingresos';
                }
                
            }
            //Gastos
            else if($obj->modulo == 4){

                //Crear gasto
                if($obj->tab == 0){
                    $link = 'https://contafacil.internow.com.mx/#/bot-gastos/0/'.$cadenaEncriptada.'/'.$token;

                    $message = "Ingresa en el siguiente enlace para emitir un nuevo gasto:\n\n{{short_link}}";
                }
                //Listado de gastos
                else if($obj->tab == 1){
                    $link = 'https://contafacil.internow.com.mx/#/bot-gastos/1/'.$cadenaEncriptada.'/'.$token;

                    $message = "Ingresa en el siguiente enlace para ver el listado de tus gastos:\n\n{{short_link}}";

                }else{
                    $message = 'Módulo de gastos';
                }
                
            }
            //Cursos
            else if($obj->modulo == 5){

                $link = 'https://contafacil.internow.com.mx/#/bot-cursos/0/'.$cadenaEncriptada.'/'.$token;

                $message = "Ingresa en el siguiente enlace para ver los cursos disponibles:\n\n{{short_link}}";
                
            }
            //Red fiscal
            else if($obj->modulo == 6){

                $link = "https://contafacil.internow.com.mx/#/doctoralia-contadores";

                $message = "Ingresa en el siguiente enlace para buscar un contador:\n\n{{short_link}}";
                
            }
            //Habilidades
            else if($obj->modulo == 7){

                $message = "Actualmente, estas son mis habilidades en las que puedo ayudarte:

📄 FACTURAS
    - Emisor de CFDI (Aquí configuras tus datos)
    - Emitir factura
    - Ver facturas (Aquí gestionas tus facturas)

📦 PAQUETES
    - Ver paquetes (Aquí puedes adquirir alguno de los paquetes disponibles)

💰 INGRESOS
    - Emitir ingreso
    - Ver ingresos (Aquí gestionas tus ingresos)

💸 GASTOS
    - Emitir gasto
    - Ver gastos (Aquí gestionas tus gastos)

📚 CURSOS
    - Ver cursos (Aquí puedes ver los cursos disponibles)

🤝 RED FISCAL
    - Red fiscal (Aquí puedes contactar a especialistas y recibir asesoría contable)

❓ CONSULTAS CONTABLES
    - También puedes hacerme preguntas relacionadas con la contabilidad";

            }
            else{

                // El mensaje lo procesa ChatPDF
                // $message = $this->messageAIChatPDF($user->mensajes);

                // El mensaje lo procesa google con cache
                $message = $this->messageWhitCacheGoogleAI($apiKey, $user->mensajes);

            }

            if($link != ""){
                $short_link = $this->shortenURL($link);
            }

            $message = str_replace("{{short_link}}", $short_link, $message);

            return $message;

        }else{
           return $resp['error'];
        }
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

    public function shortenURL($url)
    {
        // return $url;

        $apiUrl = 'https://is.gd/api.php';
        $response = file_get_contents($apiUrl . '?longurl=' . urlencode($url));

        // Verificar si se obtuvo una respuesta válida
        if (filter_var($response, FILTER_VALIDATE_URL)) {
            return $response; // Devuelve el enlace acortado
        } else {
            // Manejar el error en caso de no obtener un enlace acortado válido
            return $url; // Devuelve la URL original sin acortar
        }
    }
}
