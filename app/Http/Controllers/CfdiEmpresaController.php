<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Crypt;

use App\Models\User;
use App\Models\CfdiEmpresa;
use App\Models\CfdiCliente;
use App\Models\CfdiProducto;
use App\Models\Cfdi40RegimenFiscal;
use App\Models\Cfdi40CodigoPostal;
use App\Models\Cfdi40UsoCfdi;

use App\Models\Cfdi40ProductoServicio;
use App\Models\Cfdi40ClaveUnidad;
use App\Models\Cfdi40FormaPago;
use App\Models\Cfdi40MetodoPago;

//use Hash;
use DB;
//use Illuminate\Support\Facades\DB;
//use Validator;

use Carbon\Carbon;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

//ejemplo factura cfdi 4.0
// Se desactivan los mensajes de debug
//error_reporting(~(E_WARNING|E_NOTICE));
error_reporting(E_ERROR);

// Se especifica la zona horaria
date_default_timezone_set('America/Mexico_City');

// Se incluye el SDK
//require_once 'sdk2/sdk2.php';
require_once public_path('sdk2/sdk2.php');

class CfdiEmpresaController extends Controller
{
    public function updateUserEmisor(Request $request, $user_id)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[

            'logo'=>'required|string',
            'header'=>'required|string',
            'footer'=>'required|string',
            'color_a'=>'required|string',
            'color_b'=>'required|string',
            'color_c'=>'required|string',

            'Rfc'=>'required|string',
            'RazonSocial'=>'required|string',
            'RegimenFiscal'=>'required|string',
            'CP'=>'required|string',
            'cer'=>'required|string',
            'key'=>'required|string',
            //'pass'=>'required|string',

        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        // Comprobamos si lo que nos están pasando existe o no.
        $usuario = User::whereNull('flag_eliminado')->find($user_id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el usuario con id '.$user_id], 404);
        }

        $empresa = CfdiEmpresa::
            where('user_id',$user_id)
            ->first();

        if (!$empresa)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Empresa no encontrada.'], 404);
        }

        // Listado de campos recibidos teóricamente. 
        $logo=$request->input('logo');
        $header=$request->input('header');
        $footer=$request->input('footer');
        $color_a=$request->input('color_a');
        $color_b=$request->input('color_b');
        $color_c=$request->input('color_c');

        $Rfc=$request->input('Rfc');
        $RazonSocial=$request->input('RazonSocial');
        $RegimenFiscal=$request->input('RegimenFiscal');
        $CP=$request->input('CP');
        $cer='https://apicontafacil.internow.com.mx/sdk2/certificados/'.$request->input('cer');
        $key='https://apicontafacil.internow.com.mx/sdk2/certificados/'.$request->input('key');
        $pass=$request->input('pass');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos de usuario.

        if ($logo != null && $logo!='')
        {
            if(
                $usuario->logo != null && 
                $usuario->logo !='' && 
                $usuario->logo != $logo
            ){
                //Eliminar la imagen vieja
                $cadenas = explode('/',$usuario->logo);
                $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."logos".DIRECTORY_SEPARATOR;
                $fileName = $cadenas[count($cadenas)-1];
                if($fileName != 'logo_base.png'){
                    $archivo_ruta = $destinationPath.$fileName;
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar la imagen
                    }    
                }
                
            }

            $cadenas = explode('/',$logo);
            $fileName = $cadenas[count($cadenas)-1];
            $logo_allow_origin = 'https://apicontafacil.internow.com.mx/api/usuarios/logo/allow_origin/'.$fileName;

            $usuario->logo = $request->input('logo');
            $usuario->logo_allow_origin = $logo_allow_origin;
            $bandera=true;
        }

        if ($header != null && $header != '')
        {
            if(
                $usuario->header != null && 
                $usuario->header !='' && 
                $usuario->header != $header
            ){
                //Eliminar la imagen vieja
                $cadenas = explode('/',$usuario->header);
                $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."header_footer".DIRECTORY_SEPARATOR;
                $fileName = $cadenas[count($cadenas)-1];
                $archivo_ruta = $destinationPath.$fileName;
                if($fileName != 'header_base.png'){
                    $archivo_ruta = $destinationPath.$fileName;
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar la imagen
                    }    
                }
            }

            $usuario->header = $request->input('header');
            $bandera=true;
        }

        if ($footer != null && $footer != '')
        {
            if(
                $usuario->footer != null && 
                $usuario->footer !='' && 
                $usuario->footer != $footer
            ){
                //Eliminar la imagen vieja
                $cadenas = explode('/',$usuario->footer);
                $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."header_footer".DIRECTORY_SEPARATOR;
                $fileName = $cadenas[count($cadenas)-1];
                $archivo_ruta = $destinationPath.$fileName;
                if($fileName != 'footer_base.png'){
                    $archivo_ruta = $destinationPath.$fileName;
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar la imagen
                    }    
                }
            }

            $usuario->footer = $request->input('footer');
            $bandera=true;
        }

        if ($color_a != null && $color_a!='')
        {
            $usuario->color_a = $color_a;
            $bandera=true;
        }

        if ($color_b != null && $color_b!='')
        {
            $usuario->color_b = $color_b;
            $bandera=true;
        }

        if ($color_c != null && $color_c!='')
        {
            $usuario->color_c = $color_c;
            $bandera=true;
        }

        if ($Rfc != null && $Rfc != '')
        {
            // Eliminar espacios en blanco y guiones si los hay
            $Rfc = str_replace([' ', '-'], '', $Rfc);
            $Rfc = strtoupper($Rfc);

            $rfcValido = "/^[A-Z0-9]{12,13}$/";

            if (preg_match($rfcValido, $Rfc)) {

                $Rfc_aux = CfdiEmpresa::
                    where('id','<>',$empresa->id)
                    ->where('Rfc',$Rfc)
                    ->with('user')
                    ->first();

                if($Rfc_aux && $Rfc_aux->user->flag_eliminado == null){
                    $message = 'Ya existe otro usuario con ese RFC.';
                    return response()->json(['error'=>$message],409);
                }else{
                    $empresa->Rfc = $Rfc;
                    $bandera=true; 
                }

            } else {
                // El Rfc es inválido
                $message = 'Por favor, verifica el Rfc. En el caso de que sea una persona física, este campo debe contener una longitud de 13 posiciones, si se trata de personas morales debe contener una longitud de 12 posiciones.';
                return response()->json(['error'=>$message],409);
            }
            
        }

        if ($RazonSocial != null && $RazonSocial != '')
        {
            $empresa->RazonSocial = strtoupper($RazonSocial);
            $bandera=true;
        }

        if ($RegimenFiscal != null && $RegimenFiscal != '')
        {

            //checar si existe en el catalogo
            $RegimenFiscalBD = Cfdi40RegimenFiscal::find($RegimenFiscal);

            if($RegimenFiscalBD){
                $empresa->RegimenFiscal = $RegimenFiscal;
                $bandera=true; 
            }else{
                // El RegimenFiscal no existe en el catalogo
                $message = 'El Régimen fiscal que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un Régimen fiscal diferente.';

                return response()->json(['error'=>$message],409);
            }
            
        }

        if ($CP != null && $CP != '')
        {
            
            // Eliminar espacios en blanco y guiones si los hay
            $CP = str_replace([' ', '-'], '', $CP);

            $cpValido = "/^[0-9]{5}$/";

            if (preg_match($cpValido, $CP)) {

                //checar si existe en el catalogo
                $CpBD = Cfdi40CodigoPostal::find($CP);

                if($CpBD){
                    $empresa->CP = $CP;
                    $bandera=true;
                }else{
                    // El CP no existe en el catalogo
                    $message = 'El código postal que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un código postal diferente.';
                    return response()->json(['error'=>$message],409);
                }
            } else {
                // El CP es inválido
                $message = 'Por favor, verifica el Código Postal. Este campo es el código postal del domicilio fiscal del contribuyente y debe contener una longitud de 5 posiciones.';
                return response()->json(['error'=>$message],409);
            }
        }

        if ($cer != null && $cer != '')
        {
            $url_old = $empresa->cer;

            $empresa->cer = $cer;
            $bandera=true;

            if($url_old != $cer){
                if($url_old != null && $url_old != ''){
                    //Eliminar el archivo viejo
                    $cadenas = explode('/',$url_old);
                    $destinationPath = public_path().DIRECTORY_SEPARATOR."sdk2".DIRECTORY_SEPARATOR."certificados".DIRECTORY_SEPARATOR;
                    $fileName = $cadenas[count($cadenas)-1];
                    $archivo_ruta = $destinationPath.$fileName;
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar el archivo
                    }

                    $archivo_ruta = $destinationPath.$fileName.'.pem';
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar el archivo
                    }

                    $archivo_ruta = $destinationPath.$fileName.'.pem.txt';
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar el archivo
                    }
                }
            }
        }

        if ($key != null && $key != '')
        {
            $url_old = $empresa->key;

            $empresa->key = $key;
            $bandera=true;

            if($url_old != $key){
                if($url_old != null && $url_old != ''){
                    //Eliminar el archivo viejo
                    $cadenas = explode('/',$url_old);
                    $destinationPath = public_path().DIRECTORY_SEPARATOR."sdk2".DIRECTORY_SEPARATOR."certificados".DIRECTORY_SEPARATOR;
                    $fileName = $cadenas[count($cadenas)-1];
                    $archivo_ruta = $destinationPath.$fileName;
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar el archivo
                    }

                    $archivo_ruta = $destinationPath.$fileName.'.pem';
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar el archivo
                    }
                }
            }
        }

        if ($pass != null && $pass!='')
        {
            $claveAdicional = config('app.lada_d');
            $cadenaEncriptada = Crypt::encrypt($pass, $claveAdicional);

            $empresa->pass = $cadenaEncriptada;
            $bandera=true;
        }

        if ($bandera)
        {

            // Almacenamos en la base de datos el registro.
            if ($usuario->save()) {
                $empresa->save();
                return response()->json(['message'=>'Usuario configurado con éxito.',
                    'usuario'=>$usuario], 200);
            }else{
                return response()->json(['error'=>'Error al configurado el usuario.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al usuario.'],409);
        }
    }

    public function storeArchivo(Request $request, $ext)
    {

        if (!$request->hasFile('archivo')) {
            return response()->json(['error'=>'Archivo no detectado.'], 422);
        }

        // Genera un nombre de archivo único
        if($ext == 'cer'){
            $fileName = 'cer_' . uniqid() . '.cer';
        }else if($ext == 'key'){
            $fileName = 'key_' . uniqid() . '.key';
        }else{
            return response()->json(['error'=>'Extención inválida.'], 422);
        }
        
        $destinationPath = public_path().'/sdk2/certificados/';
        $request->file('archivo')->move($destinationPath,$fileName);

        // Obtiene la URL del archivo guardado
        $url = asset('sdk2/certificados/' . $fileName);

        return response()->json([
            'message'=>'Archivo cargado y configurado con éxito.',
            'url'=>$url,
            'fileName'=>$fileName,
         ], 200);
    }

    public function showUserEmisor($user_id)
    {
        // Comprobamos si lo que nos están pasando existe o no.
        $usuario = User::whereNull('flag_eliminado')
            ->with('cfdi_empresa.mi_regimen_fiscal')
            ->find($user_id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Emisor no encontrado'], 404);
        }

        if($usuario->cfdi_empresa->pass != '' && $usuario->cfdi_empresa->pass != null){
            $usuario->cfdi_empresa->pass = 'pass';
        }else{
            $usuario->cfdi_empresa->pass = '';
        }

        return response()->json([
            'usuario'=>$usuario,
        ], 200);

    }

    public function showProductoEmisor($user_id)
    {
        // Comprobamos si lo que nos están pasando existe o no.
        $usuario = User::find($user_id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Emisor no encontrado'], 404);
        }

        $producto = CfdiProducto::
            where('user_id',$user_id)
            ->with('mi_clave_prod_serv')
            ->with('mi_clave_unidad')
            ->first();

        if (!$producto)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Producto CFDI no encontrado'], 404);
        }

        return response()->json([
            'producto'=>$producto,
        ], 200);

    }

    public function updateProductoEmisor(Request $request, $producto_id)
    {
       
        // Comprobamos si la empresa que nos están pasando existe o no.
        $producto=CfdiProducto::find($producto_id);

        if (!$producto)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Producto no encontrado.'], 404);
        } 
        
        // Listado de campos recibidos teóricamente.
        $ClaveProdServ=$request->input('ClaveProdServ');
        $ClaveUnidad=$request->input('ClaveUnidad');
        $Descripcion=$request->input('Descripcion');
        $user_id=$request->input('user_id');
        $tipo_algoritmo_factura=$request->input('tipo_algoritmo_factura');
        $FormaPago=$request->input('FormaPago');

        if ($ClaveProdServ == null || $ClaveProdServ == '')
        {
            return response()->json(['error'=>'Clave de Producto o Servicio inválida'],409);
        }

        if ($ClaveUnidad == null || $ClaveUnidad == '')
        {
            return response()->json(['error'=>'Clave de Unidad inválida'],409);
        }

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.
        if (true)
        {

            //checar si existe en el catalogo
            $ProductoServicioBD = Cfdi40ProductoServicio::
                where('id_aux',$ClaveProdServ)
                ->first();

            if($ProductoServicioBD){
                $producto->ClaveProdServ = $ProductoServicioBD->id_aux;
                $bandera=true; 
            }else{
                // El Producto no existe en el catalogo
                $message = 'La Clave de Producto o Servicio que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar una Clave de Producto o Servicio diferente.';

                return response()->json(['error'=>$message],409);
            }
            
        }

        if (true)
        {

            //checar si existe en el catalogo
            $ClaveUnidadBD = Cfdi40ClaveUnidad::
                where('id_aux',$ClaveUnidad)
                ->first();

            if($ClaveUnidadBD){
                $producto->ClaveUnidad = $ClaveUnidadBD->id_aux;
                $producto->Unidad = $ClaveUnidadBD->id;
                $bandera=true; 
            }else{
                // El Producto no existe en el catalogo
                $message = 'La Clave de Unidad que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar una Clave de Unidad diferente.';

                return response()->json(['error'=>$message],409);
            }
            
        }

        if ($Descripcion != null && $Descripcion!='')
        {
            $producto->Descripcion = $Descripcion;
            $bandera=true;
        }

        if ($FormaPago != null && $FormaPago!='')
        {
            $producto->FormaPago = $FormaPago;
            $bandera=true;
        }

        if ($user_id != null && $user_id!='' && $tipo_algoritmo_factura != null && $tipo_algoritmo_factura!='')
        {
            DB::table('users')
            ->where('id', $user_id)
            ->update([
                'tipo_algoritmo_factura' => $tipo_algoritmo_factura,
            ]);
        }

       
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($producto->save()) {

                // $empresa->flag_producto = 1;
                // $empresa->save();

                return response()->json(['message'=>'Sistema actualizado.',
                 'producto'=>$producto], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el sistema.'], 500);
            }           
        }
        else
        {
            // Se devuelve un array error con los error encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al sistema.'],500);
        }
    }

    public function index(Request $request)
    {
        $emisores = CfdiEmpresa::
            where('user_id',$request->user_id)
            ->get();

        foreach ($emisores as $emisor) {
            $emisor->pass = '';
        }

        return response()->json([
            'success' => true,
            'data' => $emisores
        ], 200);

    }

    public function store(Request $request)
    {
        // 1. Validación de campos de texto
        $validator = Validator::make($request->all(), [
            'Rfc'          => 'required|string',
            'RazonSocial'  => 'required|string',
            'RegimenFiscal'=> 'required|string',
            'CP'           => 'required|string',
            'password'     => 'required|string',
            'user_id'      => 'required|integer',
            'archivo_cer'  => 'required|file',
            'archivo_key'  => 'required|file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data' => $validator->errors()
            ], 422);
        }

        $usuario = User::whereNull('flag_eliminado')->find($request->user_id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el usuario con id '.$request->user_id], 404);
        }

        // 2. Preparar datos

        // Eliminar espacios en blanco y guiones si los hay
        $Rfc = str_replace([' ', '-'], '', $request->Rfc);
        $Rfc = strtoupper($Rfc);

        $rfcValido = "/^[A-Z0-9]{12,13}$/";

        if (preg_match($rfcValido, $Rfc)) {

            $Rfc_aux = CfdiEmpresa::
                where('Rfc',$Rfc)
                ->with('user')
                ->first();

            if($Rfc_aux && $Rfc_aux->user->flag_eliminado == null){
                $message = 'Ya existe otro usuario con ese RFC.';
                return response()->json(['error'=>$message],409);
            }

        } else {
            // El Rfc es inválido
            $message = 'Por favor, verifica el Rfc. En el caso de que sea una persona física, este campo debe contener una longitud de 13 posiciones, si se trata de personas morales debe contener una longitud de 12 posiciones.';
            return response()->json(['error'=>$message],409);
        }

        // (Razón Social a Mayúsculas)
        // $RazonSocial = Str::upper($request->RazonSocial);
        $RazonSocial = strtoupper($request->RazonSocial);

        //checar si existe en el catalogo
        $RegimenFiscalBD = Cfdi40RegimenFiscal::find($request->RegimenFiscal);

        if(!$RegimenFiscalBD){
            // El RegimenFiscal no existe en el catalogo
            $message = 'El Régimen fiscal que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un Régimen fiscal diferente.';

            return response()->json(['error'=>$message],409);
        }

        // Eliminar espacios en blanco y guiones si los hay
        $CP = str_replace([' ', '-'], '', $request->CP);

        $cpValido = "/^[0-9]{5}$/";

        if (preg_match($cpValido, $CP)) {

            //checar si existe en el catalogo
            $CpBD = Cfdi40CodigoPostal::find($CP);

            if(!$CpBD){
                // El CP no existe en el catalogo
                $message = 'El código postal que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un código postal diferente.';
                return response()->json(['error'=>$message],409);
            }
        } else {
            // El CP es inválido
            $message = 'Por favor, verifica el Código Postal. Este campo es el código postal del domicilio fiscal del contribuyente y debe contener una longitud de 5 posiciones.';
            return response()->json(['error'=>$message],409);
        }

        // 3. Procesamiento de archivos (.cer y .key)
        try {
            $pathCer = $this->uploadFile($request, 'archivo_cer', 'cer');
            $pathKey = $this->uploadFile($request, 'archivo_key', 'key');
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $claveAdicional = config('app.lada_d');
        $cadenaEncriptada = Crypt::encrypt($request->password, $claveAdicional);
        $password = $cadenaEncriptada;
        
        // 4. --- Timbrado de prueba para validar datos ---
        $emisorTest = [
            'Rfc' => $Rfc,
            'RazonSocial' => $RazonSocial,
            'RegimenFiscal' => $request->RegimenFiscal,
            'CP' => $CP,
            'cer' => $pathCer['url'],
            'key' => $pathKey['url'],
            'pass' => $password
        ];

        $resTimbrado = $this->timbrarFacturaDePrueba($emisorTest);

        if($resTimbrado['status'] == 'error'){
            // BORRAR ARCHIVOS SI FALLA EL TIMBRADO
            File::delete([$pathCer['full_path'], $pathKey['full_path']]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validación ante el PAC fallida: '.$resTimbrado['message']
            ], 409);
        }

        $tieneCfdiEmpresas = CfdiEmpresa::
            where('user_id', $request->user_id)
            ->exists();

        $esPrimerEmisor = !$tieneCfdiEmpresas;

        // 5. Guardar en Base de Datos
        $emisor = CfdiEmpresa::create([
            'user_id'        => $request->user_id,
            'Rfc'            => $Rfc,
            'RazonSocial'   => $RazonSocial,
            'RegimenFiscal' => $request->RegimenFiscal,
            'CP'             => $CP,
            'pass'   => $password,
            'cer'       => $pathCer['url'],
            'key'       => $pathKey['url'],

            'flag_descuento'=>0,
            'flag_objetoImp'=>1,
            'flag_retencion'=>0,
            'flag_producto'=>0,

            // Activar funcionalidades adicionales solo para el primer emisor registrado por el usuario
            'emisor_bot'      => $esPrimerEmisor,
            'emisor_pos'      => $esPrimerEmisor,
            'emisor_ingresos' => $esPrimerEmisor,
        ]);

        // Crear contacto genérico
        $contacto = CfdiCliente::create([
            'empresa_id'=>$emisor->id,
            'status'=>true,
            'Rfc'=>"XAXX010101000",
            'Nombre'=>"PUBLICO EN GENERAL",
            'DomicilioFiscalReceptor'=>$emisor->CP,
            'ResidenciaFiscal'=>null,
            'NumRegIdTrib'=>null,
            'RegimenFiscalReceptor'=>"616", //Sin obligaciones fiscales
            'UsoCFDI'=>"24", //Sin efectos fiscales.
            'Email'=>$usuario->email,
            'user_id'=>$usuario->id,
            'tipo_entidad'=>'cliente',
            'tipo_cliente'=>'cliente',
            'origen'=>'pos',
        ]);

        $emisor->pass = '';

        return response()->json([
            'success' => true,
            'message' => 'Emisor creado y validado con éxito',
            'data'    => $emisor
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $emisor = CfdiEmpresa::find($id);
        if (!$emisor) {
            return response()->json(['error' => 'No existe el emisor con id ' . $id], 404);
        }

        $validator = Validator::make($request->all(), [
            'Rfc'           => 'required|string',
            'RazonSocial'   => 'required|string',
            'RegimenFiscal' => 'required|string',
            'CP'            => 'required|string',
            'password'      => 'nullable|string',
            // 'user_id'       => 'required|integer',
            'archivo_cer'   => 'nullable|file',
            'archivo_key'   => 'nullable|file',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'data' => $validator->errors()], 422);
        }

        // Preparar datos

        // Eliminar espacios en blanco y guiones si los hay
        $Rfc = str_replace([' ', '-'], '', $request->Rfc);
        $Rfc = strtoupper($Rfc);

        $rfcValido = "/^[A-Z0-9]{12,13}$/";

        if (preg_match($rfcValido, $Rfc)) {

            $Rfc_aux = CfdiEmpresa::
                where('Rfc',$Rfc)
                ->where('id', '!=', $id) // Excluir el registro actual
                ->with('user')
                ->first();

            if($Rfc_aux && $Rfc_aux->user->flag_eliminado == null){
                $message = 'Ya existe otro usuario con ese RFC.';
                return response()->json(['error'=>$message],409);
            }

        } else {
            // El Rfc es inválido
            $message = 'Por favor, verifica el Rfc. En el caso de que sea una persona física, este campo debe contener una longitud de 13 posiciones, si se trata de personas morales debe contener una longitud de 12 posiciones.';
            return response()->json(['error'=>$message],409);
        }

        // (Razón Social a Mayúsculas)
        // $RazonSocial = Str::upper($request->RazonSocial);
        $RazonSocial = strtoupper($request->RazonSocial);

        //checar si existe en el catalogo
        $RegimenFiscalBD = Cfdi40RegimenFiscal::find($request->RegimenFiscal);

        if(!$RegimenFiscalBD){
            // El RegimenFiscal no existe en el catalogo
            $message = 'El Régimen fiscal que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un Régimen fiscal diferente.';

            return response()->json(['error'=>$message],409);
        }

        // Eliminar espacios en blanco y guiones si los hay
        $CP = str_replace([' ', '-'], '', $request->CP);

        $cpValido = "/^[0-9]{5}$/";

        if (preg_match($cpValido, $CP)) {

            //checar si existe en el catalogo
            $CpBD = Cfdi40CodigoPostal::find($CP);

            if(!$CpBD){
                // El CP no existe en el catalogo
                $message = 'El código postal que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un código postal diferente.';
                return response()->json(['error'=>$message],409);
            }
        } else {
            // El CP es inválido
            $message = 'Por favor, verifica el Código Postal. Este campo es el código postal del domicilio fiscal del contribuyente y debe contener una longitud de 5 posiciones.';
            return response()->json(['error'=>$message],409);
        }

        // Guardamos las rutas viejas para eliminarlas después si todo sale bien
        $oldCerPath = $emisor->cer;
        $oldKeyPath = $emisor->key;

        $pathCer = ['url' => $emisor->cer, 'full_path' => null];
        $pathKey = ['url' => $emisor->key, 'full_path' => null];
        $password = $emisor->pass;
        $cambioCertificados = false;

        try {
            if ($request->hasFile('archivo_cer')) {
                $pathCer = $this->uploadFile($request, 'archivo_cer', 'cer');
                $cambioCertificados = true;
            }
            if ($request->hasFile('archivo_key')) {
                $pathKey = $this->uploadFile($request, 'archivo_key', 'key');
                $cambioCertificados = true;
            }
            if ($request->filled('password')) {
                $password = Crypt::encrypt($request->password, config('app.lada_d'));
                $cambioCertificados = true;
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // Validación ante el PAC
        if ($cambioCertificados) {
            $resTimbrado = $this->timbrarFacturaDePrueba([
                'Rfc' => strtoupper(str_replace([' ', '-'], '', $request->Rfc)),
                'RazonSocial' => strtoupper($request->RazonSocial),
                'RegimenFiscal' => $request->RegimenFiscal,
                'CP' => $request->CP,
                'cer' => $pathCer['url'],
                'key' => $pathKey['url'],
                'pass' => $password
            ]);

            if ($resTimbrado['status'] == 'error') {
                // Borrar archivos temporales nuevos si falló la validación
                if ($request->hasFile('archivo_cer')) File::delete($pathCer['full_path']);
                if ($request->hasFile('archivo_key')) File::delete($pathKey['full_path']);
                
                return response()->json(['error' => 'Validación ante el PAC fallida: ' . $resTimbrado['message']], 409);
            }
        }

        // 1. Actualizar Base de Datos
        $emisor->update([
            'Rfc'           => strtoupper(str_replace([' ', '-'], '', $request->Rfc)),
            'RazonSocial'   => strtoupper($request->RazonSocial),
            'RegimenFiscal' => $request->RegimenFiscal,
            'CP'            => $request->CP,
            'pass'          => $password,
            'cer'           => $pathCer['url'],
            'key'           => $pathKey['url'],
        ]);

        // 2. ELIMINACIÓN DE ARCHIVOS VIEJOS (Solo si se subieron nuevos)
        if ($cambioCertificados) {
            $destinationPath = public_path("sdk2" . DIRECTORY_SEPARATOR . "certificados" . DIRECTORY_SEPARATOR);
            
            $archivosAEliminar = [];
            
            if ($request->hasFile('archivo_cer')) {
                $cerOldName = basename($oldCerPath);
                $archivosAEliminar[] = $destinationPath . $cerOldName;
                $archivosAEliminar[] = $destinationPath . $cerOldName . '.pem';
                $archivosAEliminar[] = $destinationPath . $cerOldName . '.pem.txt';
            }

            if ($request->hasFile('archivo_key')) {
                $keyOldName = basename($oldKeyPath);
                $archivosAEliminar[] = $destinationPath . $keyOldName;
                $archivosAEliminar[] = $destinationPath . $keyOldName . '.pem';
            }

            foreach ($archivosAEliminar as $ruta) {
                if (file_exists($ruta)) {
                    unlink($ruta);
                }
            }
        }

        $emisor->pass = '';

        return response()->json([
            'success' => true, 
            'message' => 'Actualizado con éxito',
            'data'    => $emisor
        ], 200);
    }

    /**
     * Muestra emisor por ID
     */
    public function show($id)
    {
        $registro = CfdiEmpresa::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'=>$registro
        ], 200);
    }

    private function uploadFile($request, $inputName, $expectedExt)
    {
        if (!$request->hasFile($inputName)) {
            throw new \Exception("Archivo {$inputName} no detectado.");
        }

        $file = $request->file($inputName);
        $ext  = $file->getClientOriginalExtension();

        if ($ext !== $expectedExt) {
            throw new \Exception("Extensión {$ext} inválida para el campo {$inputName}.");
        }

        $fileName = $expectedExt . '_' . uniqid() . '.' . $expectedExt;
        $destinationPath = public_path() . '/sdk2/certificados/';
        
        $file->move($destinationPath, $fileName);

        return [
            'url'       => asset('sdk2/certificados/' . $fileName),
            'full_path' => $destinationPath . $fileName
        ];
    }

    public function timbrarFacturaDePrueba($emisor)
    {

        // Se especifica la version de CFDi 4.0
        $datos['version_cfdi'] = '4.0';
        $datos['validacion_local']='NO';    

        // Ruta del XML Timbrado
        $datos['cfdi']='sdk2/timbrados/cfdi_ejemplo_factura4.xml';

        // Ruta del XML de Debug
        $datos['xml_debug']='sdk2/timbrados/sin_timbrar_ejemplo_factura4.xml';

        // Credenciales de Timbrado
        $datos['PAC']['usuario'] = 'DEMO700101XXX';
        $datos['PAC']['pass'] = 'DEMO700101XXX';
        $datos['PAC']['produccion'] = 'NO';

        // Rutas y clave de los CSD
        $datos['conf']['cer'] = str_replace("https://apicontafacil.internow.com.mx/", "", $emisor['cer']);
        $datos['conf']['key'] = str_replace("https://apicontafacil.internow.com.mx/", "", $emisor['key']);

        // $datos['conf']['cer'] = str_replace("http://localhost/proy_conta_facil/conta_facilAPI/public/", "", $emisor['cer']);
        // $datos['conf']['key'] = str_replace("http://localhost/proy_conta_facil/conta_facilAPI/public/", "", $emisor['key']);

        // La cadena cifrada
        $cadenaEncriptada = $emisor['pass'];
        $claveAdicional = config('app.lada_d');
        $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);

        $datos['conf']['pass'] = $cadenaDesencriptada;

        // Datos de la Factura
        //$datos['factura']['descuento'] = '0.00';

        $fechaActual = date('Y-m-d\TH:i:s'); // Obtener la fecha y hora actual en formato ISO 8601
        // Restar dos horas a la fecha actual
        $dosHorasAtras = strtotime($fechaActual) - 7200; // Restar 7200 segundos (2 horas)
        // Formatear la fecha y hora dos horas atrás en formato ISO 8601
        $fechaDosHorasAtras = date('Y-m-d\TH:i:s', $dosHorasAtras);
        $datos['factura']['fecha_expedicion'] = $fechaDosHorasAtras;

        $datos['factura']['folio'] = uniqid();

        $datos['factura']['forma_pago'] = '01';
        $datos['factura']['LugarExpedicion'] = $emisor['CP'];
        $datos['factura']['metodo_pago'] = 'PUE';
        $datos['factura']['moneda'] = 'MXN';
        $datos['factura']['serie'] = uniqid();
        $datos['factura']['subtotal'] = 298.00;
        //$datos['factura']['tipocambio'] = 1;
        $datos['factura']['tipocomprobante'] = 'I';
        $datos['factura']['total'] = 345.68;
        ////$datos['factura']['RegimenFiscal'] = '601';
        $datos['factura']['Exportacion'] = '01';


        // Datos del Emisor
        $datos['emisor']['rfc'] = $emisor['Rfc'];
        $datos['emisor']['nombre'] = $emisor['RazonSocial'];
        $datos['emisor']['RegimenFiscal'] = $emisor['RegimenFiscal'];
        //$datos['emisor']['FacAtrAdquirente'] = 'ACCEM SERVICIOS EMPRESARIALES SC';

        // Datos del Receptor
        $datos['receptor']['rfc'] = 'VIDE9602275P5';
        $datos['receptor']['nombre'] = 'EDUARDO VICENTE DIONICIO';
        $datos['receptor']['UsoCFDI'] = 'G03';
        $datos['receptor']['DomicilioFiscalReceptor'] = '43612';
        
        ////$datos['receptor']['ResidenciaFiscal']= 'MEX';
        ////$datos['receptor']['NumRegIdTrib'] = 'B';
        $datos['receptor']['RegimenFiscalReceptor'] = '612';

        // Se agregan los conceptos
        $datos['conceptos'][0]['cantidad'] = 1.00;
        $datos['conceptos'][0]['unidad'] = 'Pieza';
        $datos['conceptos'][0]['ID'] = "1726";
        $datos['conceptos'][0]['descripcion'] = "Cigarros";
        $datos['conceptos'][0]['valorunitario'] = 99.00;
        $datos['conceptos'][0]['importe'] = 99.00;
        $datos['conceptos'][0]['ClaveProdServ'] = '50211503';
        $datos['conceptos'][0]['ClaveUnidad'] = 'H87';
        $datos['conceptos'][0]['ObjetoImp'] = '02';

        $datos['conceptos'][0]['Impuestos']['Traslados'][0]['Base'] = 99.00;
        $datos['conceptos'][0]['Impuestos']['Traslados'][0]['Impuesto'] = '002';
        $datos['conceptos'][0]['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa';
        $datos['conceptos'][0]['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000';
        $datos['conceptos'][0]['Impuestos']['Traslados'][0]['Importe'] = 15.84;

        $datos['conceptos'][1]['cantidad'] = 1.00;
        $datos['conceptos'][1]['unidad'] = 'NA';
        $datos['conceptos'][1]['ID'] = "1586";
        $datos['conceptos'][1]['descripcion'] = "PRODUCTO DE PRUEBA 2";
        $datos['conceptos'][1]['valorunitario'] = 199.00;
        $datos['conceptos'][1]['importe'] = 199.00;
        $datos['conceptos'][1]['ClaveProdServ'] = '01010101';
        $datos['conceptos'][1]['ClaveUnidad'] = 'ACT';
        $datos['conceptos'][1]['ObjetoImp'] = '02';

        $datos['conceptos'][1]['Impuestos']['Traslados'][0]['Base'] = 199.00;
        $datos['conceptos'][1]['Impuestos']['Traslados'][0]['Impuesto'] = '002';
        $datos['conceptos'][1]['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa';
        $datos['conceptos'][1]['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000';
        $datos['conceptos'][1]['Impuestos']['Traslados'][0]['Importe'] = 31.84;

        $datos['impuestos']['TotalImpuestosTrasladados'] = 47.68;

        // Se agregan los Impuestos
        $datos['impuestos']['translados'][0]['Base'] = 298.00;
        $datos['impuestos']['translados'][0]['impuesto'] = '002';
        $datos['impuestos']['translados'][0]['tasa'] = '0.160000';
        $datos['impuestos']['translados'][0]['importe'] = 47.68;
        $datos['impuestos']['translados'][0]['TipoFactor'] = 'Tasa';

        // echo "<pre>";
        // print_r($datos);
        // echo "</pre>";

        //echo "<pre>"; echo arr2cs($datos); echo "</pre>".die();
        // Se ejecuta el SDK
        $res = mf_genera_cfdi4($datos);

        file_put_contents('webhook_log_cfdi_timbrado.txt', print_r($res, true), FILE_APPEND);

        ///////////    MOSTRAR RESULTADOS DEL ARRAY $res   ///////////

        //dd($res);
        
        //en caso de que si timbre
        if(
            isset($res['cfdi']) &&
            isset($res['cancelada']) &&
            isset($res['abortar']) && 
            $res['cancelada'] == "NO" &&
            $res['abortar'] != 1
        )
        {
            return [
                'status'=>'ok',
                'message'=>'Factura timbrada exitosamente.'
            ];
        }
        else if(
            isset($res['codigo_mf_texto'])
        ){
            return [
                'status'=>'error',
                'message'=>$res['codigo_mf_texto']
            ];
        }
        else {
            return [
                'status'=>'error',
                'message'=>'Error al conectar con la librería de timbrado'
            ];
        }

    }

    public function destroy($id)
    {
        $obj = CfdiEmpresa::find($id);

        if (!$obj) {
            return response()->json([
                'success' => false,
                'message' => 'El emisor no existe.'
            ], 404);
        }

        try {
            DB::transaction(function () use ($obj) {
                
                // 1. Definimos la ruta física en el servidor
                // Esto apunta a: /tu_proyecto/public/sdk2/certificados/
                $destinationPath = public_path("sdk2" . DIRECTORY_SEPARATOR . "certificados" . DIRECTORY_SEPARATOR);
                
                // 2. Extraemos el nombre real del archivo de la URL
                $cerFileName = basename($obj->cer); // Resultado: cer_69025036d92c2.cer
                $keyFileName = basename($obj->key); // Resultado: key_6902502ce97b8.key

                // 3. Lista de archivos a eliminar (Base + derivados del SDK)
                $archivosAEliminar = [
                    $destinationPath . $cerFileName,
                    $destinationPath . $cerFileName . '.pem',
                    $destinationPath . $cerFileName . '.pem.txt',
                    $destinationPath . $keyFileName,
                    $destinationPath . $keyFileName . '.pem',
                ];

                // 4. Proceso de eliminación física
                foreach ($archivosAEliminar as $rutaCompleta) {
                    if (file_exists($rutaCompleta)) {
                        unlink($rutaCompleta);
                    }
                }

                // 5. Eliminamos el registro de la base de datos
                $obj->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Emisor y certificados eliminados correctamente.'
            ], 200);

        } catch (\Exception $e) {
            // Log para auditoría en caso de fallos de permisos o sistema
            Log::error("Error al eliminar emisor CFDI ID {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno al eliminar los archivos.'
            ], 500);
        }
    }  

    public function setFunctionalityEmisor(Request $request, $id) {
        $field = $request->function_name; // 'emisor_bot', 'emisor_pos' o 'emisor_ingresos'
        
        // 1. Desactivar el campo para todos los emisores de este contexto
        CfdiEmpresa::where('user_id', $request->user_id)->update([$field => false]);
        
        // 2. Activar solo el seleccionado
        $emisor = CfdiEmpresa::find($id);

        if (!$emisor) {
            return response()->json([
                'success' => false,
                'message' => 'El emisor no existe.'
            ], 404);
        }

        $emisor->$field = true;
        $emisor->save();

        $emisor->pass = '';

        return response()->json([
            'success' => true,
            'message' => 'Configuración actualizada',
            'data' => $emisor
        ], 200);
    }

}
