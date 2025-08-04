<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Crypt;

use App\Models\User;
use App\Models\CfdiEmpresa;
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

date_default_timezone_set('America/Mexico_City');

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
        $usuario = User::with('cfdi_empresa')
            ->find($user_id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Emisor no encontrado'], 404);
        }

        if (!$usuario->cfdi_empresa)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Emisor CFDI no encontrado'], 404);
        }

        $producto = CfdiProducto::
            where('empresa_id',$usuario->cfdi_empresa->id)
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

}
