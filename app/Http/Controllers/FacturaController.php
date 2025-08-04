<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

use Exception;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

use App\Models\User;

//facturas
use App\Models\CfdiEmpresa;
use App\Models\CfdiProducto;
use App\Models\CfdiCliente;
use App\Models\CfdiComprobante;
use App\Models\CfdiReceptor;
use App\Models\CfdiConcepto;
use App\Models\CfdiArchivo;
use App\Models\CfdiTimbreFiscalDigital;
use App\Models\CfdiRecurrente;

use App\Models\Cfdi40CodigoPostal;
use App\Models\Cfdi40RegimenFiscal;
use App\Models\Cfdi40ProductoServicio;
use App\Models\Cfdi40ClaveUnidad;
use App\Models\Cfdi40FormaPago;
use App\Models\Cfdi40MetodoPago;
use App\Models\Cfdi40UsoCfdi;

use App\Models\Ingreso;
use App\Models\IngresoConcepto;
use App\Models\Producto;

//use Hash;
use DB;
//use Validator;

use Carbon\Carbon;

use Mail;
use Session;
use Redirect;
use Swift_SmtpTransport;
use Swift_Mailer;

//ejemplo factura cfdi 4.0
// Se desactivan los mensajes de debug
//error_reporting(~(E_WARNING|E_NOTICE));
error_reporting(E_ERROR);

// Se especifica la zona horaria
date_default_timezone_set('America/Mexico_City');

// Se incluye el SDK
//require_once 'sdk2/sdk2.php';
require_once public_path('sdk2/sdk2.php');

class FacturaController extends Controller
{
    public function validarToken(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            //return response()->json(['user' => $user], 200);
            return true;

        } catch (Exception $e) {

            //return true;

            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException){
                return ['error' => 'Token is Invalid'];
            } else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException){
                return ['error' => 'Token is Expired'];
            } else {
                return ['error' => 'Authorization Token not found'];
            }
        }

    }

    public function generarProductos()
    {

        $empresas = CfdiEmpresa::all();

        for ($i=0; $i < count($empresas); $i++) {

            //Crear el producto asociado a la empresa
            $nuevoProducto=CfdiProducto::create([
                'empresa_id'=>$empresas[$i]->id,
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

        }

        // Regresar una respuesta exitosa
        return response('OK', 200);
        
    }

    public function getClienteEmpresa(Request $request, $cliente_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        $obj = User::select('id','color_a','color_b','color_c','logo')
            //->with('cfdi_empresa')
            ->with(['cfdi_empresa.producto' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->find($cliente_id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        if($obj->cfdi_empresa){
            $obj->cfdi_empresa->pass = null;
            $obj->cfdi_empresa->cer = null;
            $obj->cfdi_empresa->key = null;    
        }

        return response()->json(['cliente'=>$obj], 200);
    }

    public function getCodigoPostal($cp)
    {
        $obj = Cfdi40CodigoPostal::find($cp);

        if(!$obj){
            return response()->json(['error'=>'Código Postal no disponible en el catálogo.'],404);
        }

        return response()->json(['cp'=>$obj], 200);
    }

    public function getCatalogoRegimen()
    {
        $objs = Cfdi40RegimenFiscal::all();

        return response()->json([
            'catalogoRegimenFiscal'=>$objs
        ], 200);
    }

    public function getCatalogoFormaPago()
    {
        $objs = Cfdi40FormaPago::all();

        return response()->json([
            'catalogoFormaPago'=>$objs
        ], 200);
    }

    public function getCatalogoMetodoPago()
    {
        $objs = Cfdi40MetodoPago::all();

        return response()->json([
            'catalogoMetodoPago'=>$objs
        ], 200);
    }

    public function update(Request $request, $empresa_id)
    {
        $token_result = $this->validarToken($request);
        if($token_result !== true){
            return response()->json($token_result, 401);
        }

        // Comprobamos si la empresa que nos están pasando existe o no.
        $empresa=CfdiEmpresa::find($empresa_id);

        if (!$empresa)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Empresa no encontrada.'], 404);
        }    
        
        // Listado de campos recibidos teóricamente.
        $flag_descuento=$request->input('flag_descuento');
        $flag_retencion=$request->input('flag_retencion');
        $flag_producto=$request->input('flag_producto');
        $Rfc=$request->input('Rfc');
        $RazonSocial=$request->input('RazonSocial');
        $RegimenFiscal=$request->input('RegimenFiscal');
        $CP=$request->input('CP');
        $cer=$request->input('cer');
        $key=$request->input('key');
        $pass=$request->input('pass');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.


        if (($flag_descuento != null && $flag_descuento !=  '') || $flag_descuento === 0)
        {
            $empresa->flag_descuento = $flag_descuento;
            $bandera=true;
        }

        if (($flag_retencion != null && $flag_retencion !=  '') || $flag_retencion === 0)
        {
            $empresa->flag_retencion = $flag_retencion;
            $bandera=true;
        }

        if (($flag_producto != null && $flag_producto !=    '') || $flag_producto === 0)
        {
            $empresa->flag_producto = $flag_producto;
            $bandera=true;
        }

        if ($Rfc != null && $Rfc != '')
        {
            // Eliminar espacios en blanco y guiones si los hay
            $Rfc = str_replace([' ', '-'], '', $Rfc);
            $Rfc = strtoupper($Rfc);

            $rfcValido = "/^[A-Z0-9]{12,13}$/";

            if (preg_match($rfcValido, $Rfc)) {
                $empresa->Rfc = $Rfc;
                $bandera=true;
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
                $CpBD = Cfdi40CodigoPostal::find($CP);;

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
            if ($empresa->save()) {

                $empresa->pass = null;

                return response()->json(['message'=>'Empresa actualizada.',
                 'empresa'=>$empresa], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar la empresa.'], 500);
            }           
        }
        else
        {
            // Se devuelve un array error con los error encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún a la empresa.'],500);
        }
    }

    public function storeArchivo(Request $request)
    {

        if (!$request->hasFile('archivo')) {
            return response()->json(['error'=>'Archivo no detectado.'], 422);
        }else if(!$request->input('ext')){
            return response()->json(['error'=>'Especifique una extención para el archivo.'], 422);
        }

        // Genera un nombre de archivo único
        if($request->input('ext') == '.cer'){
            $fileName = 'cer_' . uniqid() . '.cer';
        }else if($request->input('ext') == '.key'){
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

    public function indexEmitidasFilter(Request $request, $cliente_id)
    {
        // $token_result = $this->validarToken($request);
        // if($token_result !== true){
        //     return response()->json($token_result, 401);
        // }

        $obj = User::
            select('id','color_a','color_b','color_c','logo')
            ->find($cliente_id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        $emisor = CfdiEmpresa::
            where('user_id', $cliente_id)
            ->first();

        if (!$emisor)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Emisor no encontrado'], 404);
        }


        $anio = $request->input('anio');
        $mes = $request->input('mes');
        //$dia = $request->input('dia');

        if($mes >= 1 && $mes <= 9){
            $mes = '0'.$mes;
        }

        // if($dia >= 1 && $dia <= 9){
        //     $dia = '0'.$dia;
        // }

        //$fecha = $anio.'-'.$mes.'-'.$dia;
        $fecha = $anio.'-'.$mes.'-';

        //facturas en emitidas
        $facturas = CfdiComprobante::select('id','emisor_id','status','Serie','Folio','Fecha','Total','status_pay')
            ->where('emisor_id',$emisor->id)
            ->where('status', 1)
            ->where('Fecha', 'like', '%'.$fecha.'%')
            ->with(['receptor' => function ($query){
                $query->select('id','comprobante_id','Rfc','Nombre');
            }])
            ->with(['archivo' => function ($query){
                $query->select('id','comprobante_id','xml_archivo','pdf');
            }])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'cliente'=>$obj,
            'facturas'=>$facturas
        ], 200);
        
    }

    public function indexCanceladasFilter(Request $request, $cliente_id)
    {
        // $token_result = $this->validarToken($request);
        // if($token_result !== true){
        //     return response()->json($token_result, 401);
        // }

        $obj = User::
            select('id','color_a','color_b','color_c','logo')
            ->find($cliente_id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado'], 404);
        }

        $emisor = CfdiEmpresa::
            where('user_id', $cliente_id)
            ->first();

        if (!$emisor)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Emisor no encontrado'], 404);
        }

        $anio = $request->input('anio');
        $mes = $request->input('mes');
        //$dia = $request->input('dia');

        if($mes >= 1 && $mes <= 9){
            $mes = '0'.$mes;
        }

        // if($dia >= 1 && $dia <= 9){
        //     $dia = '0'.$dia;
        // }

        //$fecha = $anio.'-'.$mes.'-'.$dia;
        $fecha = $anio.'-'.$mes.'-';

        //facturas en canceladas
        $facturas = CfdiComprobante::select('id','emisor_id','status','Serie','Folio','Fecha','Total')
            ->where('emisor_id',$emisor->id)
            ->where('status', 2)
            ->where('Fecha', 'like', '%'.$fecha.'%')
            ->with(['receptor' => function ($query){
                $query->select('id','comprobante_id','Rfc','Nombre');
            }])
            ->with(['archivo' => function ($query){
                $query->select('id','comprobante_id','xml_archivo','pdf');
            }])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'cliente'=>$obj,
            'facturas'=>$facturas
        ], 200);
        
    }

    public function getFactura(Request $request, $factura_id)
    {

        $factura = CfdiComprobante::
            with(['receptor' => function ($query){
                $query->with('mi_regimen_fiscal')
                    ->with('mi_uso_cfdi');
            }])
            ->with(['conceptos' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->find($factura_id);

        if(!$factura){
            return response()->json(['error'=>'Factura no encontrada.'],404);
        }

        $TotalImpuestosTrasladados = 0;
        $TotalImpuestosRetenidos = 0;
        $TotalImpuestosRetenidosIva = 0;
        $TotalImpuestosRetenidosIsr = 0;

        for ($i=0; $i < count($factura->conceptos); $i++) { 

            $factura->conceptos[$i]->Impuestos = [];

            if($factura->conceptos[$i]->ObjetoImp == 1){

                $Impuestos = [];

                $factura->conceptos[$i]->ObjetoImp = 'Si obj de impuesto.';
                $Base = $factura->conceptos[$i]->Importe - $factura->conceptos[$i]->Descuento;

                $Importe = number_format(($Base * 0.16), 2, '.', '');
                $TotalImpuestosTrasladados = $TotalImpuestosTrasladados + $Importe;

                $resul = (object) [
                    'Impuesto' => "IVA",
                    'Tipo' => "Traslado",
                    'Base' => $Base,
                    'TipoFactor' => "Tasa",
                    'TasaOCuota' => "16.00%",
                    'Importe' => $Importe
                ];
                array_push($Impuestos,$resul);

                if($factura->conceptos[$i]->ObjetoImpRet == 1){

                    $retencionIva = $Base * ($factura->TasaIva/100);
                    $retencionIva = number_format(($retencionIva), 2, '.', '');
                    $resul = (object) [
                        'Impuesto' => "IVA",
                        'Tipo' => "Retención",
                        'Base' => $Base,
                        'TipoFactor' => "Tasa",
                        'TasaOCuota' => $factura->TasaIva."%",
                        'Importe' => $retencionIva
                    ];
                    array_push($Impuestos,$resul);

                    $retencionIsr = $Base * ($factura->TasaIsr/100);
                    $retencionIsr = number_format(($retencionIsr), 2, '.', '');
                    $resul = (object) [
                        'Impuesto' => "ISR",
                        'Tipo' => "Retención",
                        'Base' => $Base,
                        'TipoFactor' => "Tasa",
                        'TasaOCuota' => $factura->TasaIsr."%",
                        'Importe' => $retencionIsr
                    ];
                    array_push($Impuestos,$resul);

                    $TotalImpuestosRetenidosIva = $TotalImpuestosRetenidosIva + $retencionIva;
                    $TotalImpuestosRetenidosIsr = $TotalImpuestosRetenidosIsr + $retencionIsr;
                    $TotalImpuestosRetenidos = $TotalImpuestosRetenidos + $TotalImpuestosRetenidosIva + $TotalImpuestosRetenidosIsr;

                }

                $factura->conceptos[$i]->Impuestos = $Impuestos;


            }
        }
        $factura->TotalImpuestosTrasladados = number_format($TotalImpuestosTrasladados, 2, '.', '');
        $factura->TotalImpuestosRetenidos = number_format($TotalImpuestosRetenidos, 2, '.', '');
        $factura->TotalImpuestosRetenidosIva = number_format($TotalImpuestosRetenidosIva, 2, '.', '');
        $factura->TotalImpuestosRetenidosIsr = number_format($TotalImpuestosRetenidosIsr, 2, '.', '');

        $emisor = CfdiEmpresa::
            with('mi_regimen_fiscal')
            ->find($factura->emisor_id);

        $cliente = User::find($emisor->user_id);

        $emisor->cer = null;
        $emisor->key = null;
        $emisor->pass = null;

        return response()->json([
            'emisor' => $emisor,
            'factura'=>$factura,
        ], 200);

        
    }

    public function buscarPorSerie(Request $request)
    {

        $factura = CfdiComprobante::
            where('status', '<>', 0)
            ->where('emisor_id', $request->input('empresa_id'))
            ->where('Serie', $request->input('serie'))
            ->first();

        if(!$factura){
            return response()->json(['error'=>'Factura no encontrada.'],404);
        }

        
        return response()->json([
            'factura_id'=>$factura->id,
        ], 200);

        
    }

    public function cancelarFactura(Request $request, $factura_id)
    {

        // $token_result = $this->validarToken($request);
        // if($token_result !== true){
        //     return response()->json($token_result, 401);
        // }

        $factura = CfdiComprobante::
            with(['receptor' => function ($query){
                $query->with('mi_regimen_fiscal')
                    ->with('mi_uso_cfdi');
            }])
            ->with(['conceptos' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->find($factura_id);

        if(!$factura){
            return response()->json(['error'=>'Factura no encontrada.'],404);
        }

        if($factura->status == 2){
            return response()->json(['error'=>'Su factura ya está marcada como cancelada.'],409);
        }

        if(!$factura->timbre_fiscal_digital){
            return response()->json(['error'=>'Su factura no tiene un timbre para cancelar.'],409);
        }

        $emisor = CfdiEmpresa::
            with('mi_regimen_fiscal')
            ->find($factura->emisor_id);
 
        $cliente = User::find($emisor->user_id);

        
        // $datos['PAC']['usuario'] = "DEMO700101XXX";
        // $datos['PAC']['pass'] = "DEMO700101XXX";

        $datos['PAC']['usuario'] = 'AUMA9101171B4';
        $datos['PAC']['pass'] = 'AUMA9101171B41234';

        $datos['modulo']="cancelacion2022"; 
        $datos['accion']="cancelar"; 

        //$datos["produccion"]="NO"; 

        $datos['produccion'] = 'SI';

        //$datos["xml"]="../../timbrados/cfdi_ejemplo_factura.xml";
        $datos["uuid"]=$factura->timbre_fiscal_digital->UUID;
        $datos["rfc"] =$emisor->Rfc;

        // La cadena cifrada
        $cadenaEncriptada = $emisor->pass;
        $claveAdicional = config('app.lada_d');
        $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);

        if (preg_match('/[^\w\s]/', $cadenaDesencriptada)) {
            $datos["password"] = utf8_encode($cadenaDesencriptada);
        } else {
            $datos["password"] = $cadenaDesencriptada;
        }

        //$datos["motivo"]="02";
        $datos["motivo"]=$request->input('motivo');
        //$datos["folioSustitucion"]="";
        $datos["b64Cer"]=str_replace("https://apicontafacil.internow.com.mx/", "", $emisor->cer);
        $datos["b64Key"]=str_replace("https://apicontafacil.internow.com.mx/", "", $emisor->key);

        $res = mf_ejecuta_modulo($datos);

        file_put_contents('webhook_log_cfdi_cancelar.txt', print_r($res, true), FILE_APPEND);

        // echo "<pre>";
        // print_r($res);
        // echo "<pre>";

        if(
            isset($res['codigo_mf_texto']) &&
            isset($res['codigo_respuesta_sat_texto']) &&
            $res['codigo_mf_texto'] == "OK" &&
            $res['codigo_respuesta_sat_texto'] != "No Existe" 
        ){
            //Pasar a cancelada
            $factura->status = 2;
            $factura->save();

            //Reponer inventario
            for ($i=0; $i < count($factura->conceptos); $i++) { 
                if ($factura->conceptos[$i]->producto_id) {
                    
                    $producto = Producto::where('id', $factura->conceptos[$i]->producto_id)
                        ->whereNull('flag_eliminado')
                        ->first();

                    if ($producto)
                    {
                        $stock = $producto->stock + $factura->conceptos[$i]->Cantidad;
                        $producto->stock = $stock;
                        $producto->save();
                    }

                }
            }

            try {
                $this->emailFacturaCancelada($factura_id); 
            } catch (Exception $e) {
                
            }

            return response()->json([
                'message'=>'Factura cancelada con éxito.'
            ], 200);
        }
        else if(
            isset($res['codigo_mf_texto']) &&
            isset($res['codigo_respuesta_sat_texto']) &&
            $res['codigo_mf_texto'] == "OK" &&
            $res['codigo_respuesta_sat_texto'] == "No Existe" 
        )
        {
        
            return response()->json([
                'error'=>'Su factura no existe en el portal del SAT. Si emites una factura electrónica y quieres cancelarla, debes esperar al menos 72 horas antes de hacerlo.'
            ],409);

        }
        else {
            return response()->json([
                'error'=>'Error al conectar con la librería de timbrado.'
            ],500);
        }
        
    }

    public function getCatalogoProductos(Request $request)
    {
        $termino = $request->input('termino');

        $objs = Cfdi40ProductoServicio::
            where("id", "like", '%'.$termino.'%')
            ->orWhere("texto", "like", '%'.$termino.'%')
            ->orWhere("similares", "like", '%'.$termino.'%')
            ->get();

        return response()->json(['catalogoClaveProdServ'=>$objs], 200);
    }

    public function getCatalogoUnidades(Request $request)
    {
        $termino = $request->input('termino');

        $objs = Cfdi40ClaveUnidad::
            where("id", "like", '%'.$termino.'%')
            ->orWhere("texto", "like", '%'.$termino.'%')
            ->get();

        return response()->json(['catalogoClaveUnidad'=>$objs], 200);
    }

    

    public function getClientesPorRfc(Request $request)
    {
        $termino = $request->input('termino');
        $empresa_id = $request->input('empresa_id');

        $objs = CfdiCliente::
            where("empresa_id", $empresa_id)
            ->where("Rfc", "like", '%'.$termino.'%')
            ->with('mi_regimen_fiscal')
            ->with('mi_uso_cfdi')
            ->get();

        return response()->json(['clientes'=>$objs], 200);
    }

    public function getAllClientes(Request $request)
    {
        $empresa_id = $request->input('empresa_id');

        $objs = CfdiCliente::
            where("empresa_id", $empresa_id)
            ->with('mi_regimen_fiscal')
            ->with('mi_uso_cfdi')
            ->get();

        return response()->json(['clientes'=>$objs], 200);
    }

    public function getCatalogoUsoCfdi()
    {
        $objs = Cfdi40UsoCfdi::all();

        return response()->json([
            'catalogoUsoCfdi'=>$objs
        ], 200);
    }

    public function timbrarDesdePanel(Request $request, $empresa_id)
    {
        // $token_result = $this->validarToken($request);
        // if($token_result !== true){
        //     return response()->json($token_result, 401);
        // }

        // Comprobamos si la empresa que nos están pasando existe o no.
        $empresa=CfdiEmpresa::find($empresa_id);
        if (!$empresa)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Empresa no encontrada.'], 404);
        }

        $cliente=User::find($empresa->user_id);
        if (!$cliente)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado.'], 404);
        }

        if ($cliente->status != 1)
        {
            return response()->json(['error'=>'Emisor inhabilitado para generar timbre electrónico.'], 409);
        }

        //Validacion para user resico
        // if($empresa->RegimenFiscal == '626'){
        //     $total_facturado = $this->getTotalFacturado($empresa->user_id);

        //     if($total_facturado >= 290000){
        //         return response()->json(['error'=>'Ya alcanzaste el límite de $290,000 pesos mensuales facturables para usuarios con Régimen Simplificado de Confianza'], 409);
        //     }else if(($total_facturado+$request->input('Total')) >= 290000){
        //         return response()->json(['error'=>'El total de la factura excede el límite de $290,000 pesos mensuales facturables para usuarios con Régimen Simplificado de Confianza'], 409);
        //     }
        // }

        $limite_facturacion = $this->determinarLimiteFacturacion($empresa->Rfc,$empresa->RegimenFiscal);
        if($limite_facturacion != null && $limite_facturacion != 0){

            $total_facturado = $this->getTotalFacturado($empresa->user_id);

            if($total_facturado >= $limite_facturacion){
                return response()->json(['error'=>'Ya alcanzaste el límite de $'.$limite_facturacion.' pesos mensuales facturables.'], 409);
            }else if(($total_facturado+$request->input('Total')) >= $limite_facturacion){
                return response()->json(['error'=>'El total de la factura excede el límite de $'.$limite_facturacion.' pesos mensuales facturables.'], 409);
            }

        }

        if ($cliente->count_timbres < 1)
        {
            return response()->json(['error'=>'No cuentas con timbres disponibles. Te recomendamos adquirir un paquete para continuar disfrutando de nuestros servicios de timbrado.'], 409);
        }

        $conceptos = json_decode($request->input('conceptos'));
        if (count($conceptos) == 0) {
            // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Factura sin conceptos.'], 409);
        }

        //verificar stock
        for ($i=0; $i < count($conceptos); $i++) { 
            if ($conceptos[$i]->producto_id) {
                
                $producto = Producto::where('id', $conceptos[$i]->producto_id)
                    ->whereNull('flag_eliminado')
                    ->first();

                if (!$producto)
                {
                    return response()->json([
                        'error'=>'No existe el producto '.$conceptos[$i]->Descripcion
                    ], 409);
                }

                if ($producto->stock == 0) {
                    return response()->json([
                        'error'=>'No hay unidades disponibles del producto '.$conceptos[$i]->Descripcion
                    ], 409);
                }
                
                if ($producto->stock < $conceptos[$i]->Cantidad) {
                    return response()->json([
                        'error'=>'Solo hay '.$producto->stock.' unidades disponibles del producto '.$conceptos[$i]->Descripcion
                    ], 409);
                }

            }
        }

        $pedidoCurso = CfdiComprobante::
            where('emisor_id',$empresa->id)
            ->where('status', 0)
            ->with('receptor')
            //->with('conceptos')
            ->with(['conceptos' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->first();   

        //elimino cotizacion curso desde el panel en caso de que tenga
        if($pedidoCurso){
            for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                $pedidoCurso->conceptos[$i]->delete();
            }
            $pedidoCurso->receptor->delete();
            $pedidoCurso->delete();
        } 

        //Iniciar proceso de facturacion
        $Folio = (CfdiComprobante::count())+1;

        $Serie = (CfdiComprobante::
            where('emisor_id',$empresa->id)
            ->count())+1;

        //crear un pedido nuevo en curso
        $pedidoCurso=CfdiComprobante::create([
            'emisor_id'=>$empresa->id,
            'receptor_id'=>null,
            'status'=>0,
            'flag_cancelada'=>null,
            'Serie'=>"S-".$empresa->id."-".$Serie,
            'Folio'=>"F-".$empresa->id."-".$Folio,
            'Fecha'=>date('Y-m-d\TH:i:s', time() - (60*60)),
            'Sello'=>"",
            'FormaPago'=>$request->input('FormaPago'),
            'NoCertificado'=>"",
            'Certificado'=>"",
            'CondicionesDePago'=>"",
            'Subtotal'=>$request->input('Subtotal'),
            'Descuento'=>$request->input('Descuento'),
            'Moneda'=>"MXN",
            'TipoCambio'=>"",
            'Total'=>$request->input('Total'),
            'TipoDeComprobante'=>"I",
            'Exportacion'=>"01",
            'MetodoPago'=>$request->input('MetodoPago'),
            'LugarExpedicion'=>$empresa->CP,
            'Confirmacion'=>"",
            //'estado'=>null,
            //'function'=>null,
            'TasaIva'=>$request->input('TasaIva'),
            'TasaIsr'=>$request->input('TasaIsr'),
            'Tipo'=>$request->input('Tipo'),
        ]);

        //crear el receptor
        $newObjReceptor=CfdiReceptor::create([
            'comprobante_id'=>$pedidoCurso->id,
            'Rfc'=>$request->input('Rfc'),
            'Nombre'=>$request->input('Nombre'),
            'DomicilioFiscalReceptor'=>$request->input('DomicilioFiscalReceptor'),
            'ResidenciaFiscal'=>null,
            'NumRegIdTrib'=>null,
            'RegimenFiscalReceptor'=>$request->input('RegimenFiscalReceptor'),
            'UsoCFDI'=>$request->input('UsoCFDI'),
            'Email'=>$request->input('Email'),
        ]);

        // //crear cliente asociado al emisor
        // if($request->input('flag_cliente') == 2){


        //     $clienteExiste = CfdiCliente::
        //         where('empresa_id',$empresa_id)
        //         ->where('status', 1)
        //         ->where('Rfc', $request->input('Rfc'))
        //         ->with('mi_regimen_fiscal')
        //         ->with('mi_uso_cfdi')
        //         ->first();

        //     if(!$clienteExiste){
        //         $newCliente=CfdiCliente::create([
        //             'empresa_id'=>$empresa_id,
        //             'status'=>1,
        //             'Rfc'=>$request->input('Rfc'),
        //             'Nombre'=>$request->input('Nombre'),
        //             'DomicilioFiscalReceptor'=>$request->input('DomicilioFiscalReceptor'),
        //             'ResidenciaFiscal'=>null,
        //             'NumRegIdTrib'=>null,
        //             'RegimenFiscalReceptor'=>$request->input('RegimenFiscalReceptor'),
        //             'UsoCFDI'=>$request->input('UsoCFDI'),
        //             'Email'=>$request->input('Email'),
        //         ]);
        //     }

            
        // }

        // //actualizar cliente asociado del emisor
        // if($request->input('flag_cliente') == 1){

        //     if($request->input('cliente_id') != null && $request->input('cliente_id') != ''){

        //         $clienteReceptor = CfdiCliente::
        //             where('empresa_id',$empresa_id)
        //             ->where('status', 1)
        //             ->where('id', $request->input('cliente_id'))
        //             ->with('mi_regimen_fiscal')
        //             ->with('mi_uso_cfdi')
        //             ->first();

        //         if($clienteReceptor){

        //             $clienteReceptor->Nombre = $request->input('Nombre');
        //             $clienteReceptor->DomicilioFiscalReceptor = $request->input('DomicilioFiscalReceptor');
        //             $clienteReceptor->RegimenFiscalReceptor = $request->input('RegimenFiscalReceptor');
        //             $clienteReceptor->UsoCFDI = $request->input('UsoCFDI');
        //             $clienteReceptor->Email = $request->input('Email');
        //             $clienteReceptor->save();

        //             $clienteExiste = CfdiCliente::
        //                 where('empresa_id',$empresa_id)
        //                 ->where('status', 1)
        //                 ->where('id', '<>', $request->input('cliente_id'))
        //                 ->where('Rfc', $request->input('Rfc'))
        //                 ->with('mi_regimen_fiscal')
        //                 ->with('mi_uso_cfdi')
        //                 ->first();

        //             if(!$clienteExiste){
        //                 $clienteReceptor->Rfc = $request->input('Rfc');
        //                 $clienteReceptor->save();
        //             }

                    
        //         }

        //     }
        // }

        //Crear los conceptos
        for ($i=0; $i < count($conceptos); $i++) { 
            //agregar nuevo concepto
            $nuevoConcepto=CfdiConcepto::create([
                'comprobante_id' => $pedidoCurso->id,
                'ClaveProdServ' => $conceptos[$i]->ClaveProdServ,
                'NoIdentificacion' => "",
                'Cantidad' => $conceptos[$i]->Cantidad,
                'ClaveUnidad' => $conceptos[$i]->ClaveUnidad,
                'Unidad' => $conceptos[$i]->Unidad,
                'Descripcion' => $conceptos[$i]->Descripcion,
                'ValorUnitario' => $conceptos[$i]->ValorUnitario,
                'Importe' => $conceptos[$i]->Importe,
                'Descuento' => $conceptos[$i]->Descuento,
                'ObjetoImp' => $conceptos[$i]->ObjetoImp,
                'ObjetoImpRet' => $conceptos[$i]->ObjetoImpRet,
                'producto_id' => $conceptos[$i]->producto_id,
            ]);
        }

        $resTimbrado = $this->timbrarProduccion($pedidoCurso->id);

        if($resTimbrado != 1){

            $pedidoCurso = CfdiComprobante::
                where('emisor_id',$empresa->id)
                ->where('status', 0)
                ->with('receptor')
                //->with('conceptos')
                ->with(['conceptos' => function ($query){
                    $query->with('mi_clave_prod_serv')
                        ->with('mi_clave_unidad');
                }])
                ->with('impuesto')
                ->with('timbre_fiscal_digital')
                ->with('archivo')
                ->with('mi_forma_pago')
                ->with('mi_metodo_pago')
                ->first();   

            //elimino cotizacion curso desde el panel en caso de que tenga
            if($pedidoCurso){
                for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                    $pedidoCurso->conceptos[$i]->delete();
                }
                $pedidoCurso->receptor->delete();
                $pedidoCurso->delete();
            }

            $message = $resTimbrado;

            // Devolvemos un código 409 Conflict. 
            return response()->json([
                'error'=>$message
            ], 409);

        }else{

            //Timbrada exitosamente
            $pedidoCurso->status = 1;
            $pedidoCurso->save();

            $count_facturas = $cliente->count_facturas + 1;
            DB::table('users')
            ->where('id', $cliente->id)
            ->update([
                'count_facturas' => $count_facturas,
            ]);

            //descontar contador de timbres disponibles
            $count_timbres = $cliente->count_timbres - 1;
            DB::table('users')
            ->where('id', $cliente->id)
            ->update([
                'count_timbres' => $count_timbres,
            ]);

            $document = $this->facturaPdf($pedidoCurso->id);

            DB::table('cfdi_archivos')
                ->where('comprobante_id', $pedidoCurso->id)
                ->update([
                    'pdf' => $document,
                ]);


            //crear o actualizar cliente
            $clienteExiste = CfdiCliente::
                where('empresa_id',$empresa_id)
                ->where('status', 1)
                ->where('Rfc', $request->input('Rfc'))
                ->with('mi_regimen_fiscal')
                ->with('mi_uso_cfdi')
                ->first();

            if(!$clienteExiste){
                $newCliente=CfdiCliente::create([
                    'empresa_id'=>$empresa_id,
                    'status'=>1,
                    'Rfc'=>$request->input('Rfc'),
                    'Nombre'=>$request->input('Nombre'),
                    'DomicilioFiscalReceptor'=>$request->input('DomicilioFiscalReceptor'),
                    'ResidenciaFiscal'=>null,
                    'NumRegIdTrib'=>null,
                    'RegimenFiscalReceptor'=>$request->input('RegimenFiscalReceptor'),
                    'UsoCFDI'=>$request->input('UsoCFDI'),
                    'Email'=>$request->input('Email'),
                ]);
            }else if($clienteExiste){
                $clienteExiste->Nombre = $request->input('Nombre');
                $clienteExiste->DomicilioFiscalReceptor = $request->input('DomicilioFiscalReceptor');
                $clienteExiste->RegimenFiscalReceptor = $request->input('RegimenFiscalReceptor');
                $clienteExiste->UsoCFDI = $request->input('UsoCFDI');
                $clienteExiste->Email = $request->input('Email');
                $clienteExiste->save();
            }

            //Descontar inventario
            for ($i=0; $i < count($conceptos); $i++) { 
                if ($conceptos[$i]->producto_id) {
                    
                    $producto = Producto::where('id', $conceptos[$i]->producto_id)
                        ->whereNull('flag_eliminado')
                        ->first();

                    if ($producto)
                    {
                        $stock = $producto->stock - $conceptos[$i]->Cantidad;
                        $producto->stock = $stock;
                        $producto->save();
                    }

                }
            }

            try {
                $this->emailFactura($pedidoCurso->id); 
            } catch (Exception $e) {
                
            }

            return response()->json([
                'message'=>'Factura timbrada exitosamente.',
                'factura_id'=>$pedidoCurso->id,
            ], 200); 
        }
  
    }

    public function timbrarDesdePanelSandbox(Request $request, $empresa_id)
    {
        // $token_result = $this->validarToken($request);
        // if($token_result !== true){
        //     return response()->json($token_result, 401);
        // }

        // Comprobamos si la empresa que nos están pasando existe o no.
        $empresa=CfdiEmpresa::find($empresa_id);
        if (!$empresa)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Empresa no encontrada.'], 404);
        }

        $cliente=User::find($empresa->user_id);
        if (!$cliente)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado.'], 404);
        }

        if ($cliente->status != 1)
        {
            return response()->json(['error'=>'Emisor inhabilitado para generar timbre electrónico.'], 409);
        }

        //Validacion para user resico
        // if($empresa->RegimenFiscal == '626'){
        //     $total_facturado = $this->getTotalFacturado($empresa->user_id);

        //     if($total_facturado >= 290000){
        //         return response()->json(['error'=>'Ya alcanzaste el límite de $290,000 pesos mensuales facturables para usuarios con Régimen Simplificado de Confianza'], 409);
        //     }else if(($total_facturado+$request->input('Total')) >= 290000){
        //         return response()->json(['error'=>'El total de la factura excede el límite de $290,000 pesos mensuales facturables para usuarios con Régimen Simplificado de Confianza'], 409);
        //     }
        // }

        $limite_facturacion = $this->determinarLimiteFacturacion($empresa->Rfc,$empresa->RegimenFiscal);
        if($limite_facturacion != null && $limite_facturacion != 0){

            $total_facturado = $this->getTotalFacturado($empresa->user_id);

            if($total_facturado >= $limite_facturacion){
                return response()->json(['error'=>'Ya alcanzaste el límite de $'.$limite_facturacion.' pesos mensuales facturables.'], 409);
            }else if(($total_facturado+$request->input('Total')) >= $limite_facturacion){
                return response()->json(['error'=>'El total de la factura excede el límite de $'.$limite_facturacion.' pesos mensuales facturables.'], 409);
            }

        }

        if ($cliente->count_timbres < 1)
        {
            return response()->json(['error'=>'No cuentas con timbres disponibles. Te recomendamos adquirir un paquete para continuar disfrutando de nuestros servicios de timbrado.'], 409);
        }

        $conceptos = json_decode($request->input('conceptos'));
        if (count($conceptos) == 0) {
            // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Factura sin conceptos.'], 409);
        }

        //verificar stock
        for ($i=0; $i < count($conceptos); $i++) { 
            if ($conceptos[$i]->producto_id) {
                
                $producto = Producto::where('id', $conceptos[$i]->producto_id)
                    ->whereNull('flag_eliminado')
                    ->first();

                if (!$producto)
                {
                    return response()->json([
                        'error'=>'No existe el producto '.$conceptos[$i]->Descripcion
                    ], 409);
                }

                if ($producto->stock == 0) {
                    return response()->json([
                        'error'=>'No hay unidades disponibles del producto '.$conceptos[$i]->Descripcion
                    ], 409);
                }
                
                if ($producto->stock < $conceptos[$i]->Cantidad) {
                    return response()->json([
                        'error'=>'Solo hay '.$producto->stock.' unidades disponibles del producto '.$conceptos[$i]->Descripcion
                    ], 409);
                }

            }
        }

        $pedidoCurso = CfdiComprobante::
            where('emisor_id',$empresa->id)
            ->where('status', 0)
            ->with('receptor')
            //->with('conceptos')
            ->with(['conceptos' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->first();   

        //elimino cotizacion curso desde el panel en caso de que tenga
        if($pedidoCurso){
            for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                $pedidoCurso->conceptos[$i]->delete();
            }
            $pedidoCurso->receptor->delete();
            $pedidoCurso->delete();
        } 

        //Iniciar proceso de facturacion
        $Folio = (CfdiComprobante::count())+1;

        $Serie = (CfdiComprobante::
            where('emisor_id',$empresa->id)
            ->count())+1;

        //crear un pedido nuevo en curso
        $pedidoCurso=CfdiComprobante::create([
            'emisor_id'=>$empresa->id,
            'receptor_id'=>null,
            'status'=>0,
            'flag_cancelada'=>null,
            'Serie'=>"S-".$empresa->id."-".$Serie,
            'Folio'=>"F-".$empresa->id."-".$Folio,
            'Fecha'=>date('Y-m-d\TH:i:s', time() - (60*60)),
            'Sello'=>"",
            'FormaPago'=>$request->input('FormaPago'),
            'NoCertificado'=>"",
            'Certificado'=>"",
            'CondicionesDePago'=>"",
            'Subtotal'=>$request->input('Subtotal'),
            'Descuento'=>$request->input('Descuento'),
            'Moneda'=>"MXN",
            'TipoCambio'=>"",
            'Total'=>$request->input('Total'),
            'TipoDeComprobante'=>"I",
            'Exportacion'=>"01",
            'MetodoPago'=>$request->input('MetodoPago'),
            'LugarExpedicion'=>$empresa->CP,
            'Confirmacion'=>"",
            //'estado'=>null,
            //'function'=>null,
            'TasaIva'=>$request->input('TasaIva'),
            'TasaIsr'=>$request->input('TasaIsr'),
            'Tipo'=>$request->input('Tipo'),
        ]);

        //crear el receptor
        $newObjReceptor=CfdiReceptor::create([
            'comprobante_id'=>$pedidoCurso->id,
            'Rfc'=>$request->input('Rfc'),
            'Nombre'=>$request->input('Nombre'),
            'DomicilioFiscalReceptor'=>$request->input('DomicilioFiscalReceptor'),
            'ResidenciaFiscal'=>null,
            'NumRegIdTrib'=>null,
            'RegimenFiscalReceptor'=>$request->input('RegimenFiscalReceptor'),
            'UsoCFDI'=>$request->input('UsoCFDI'),
            'Email'=>$request->input('Email'),
        ]);

        //Crear los conceptos
        for ($i=0; $i < count($conceptos); $i++) { 
            //agregar nuevo concepto
            $nuevoConcepto=CfdiConcepto::create([
                'comprobante_id' => $pedidoCurso->id,
                'ClaveProdServ' => $conceptos[$i]->ClaveProdServ,
                'NoIdentificacion' => "",
                'Cantidad' => $conceptos[$i]->Cantidad,
                'ClaveUnidad' => $conceptos[$i]->ClaveUnidad,
                'Unidad' => $conceptos[$i]->Unidad,
                'Descripcion' => $conceptos[$i]->Descripcion,
                'ValorUnitario' => $conceptos[$i]->ValorUnitario,
                'Importe' => $conceptos[$i]->Importe,
                'Descuento' => $conceptos[$i]->Descuento,
                'ObjetoImp' => $conceptos[$i]->ObjetoImp,
                'ObjetoImpRet' => $conceptos[$i]->ObjetoImpRet,
                'producto_id' => $conceptos[$i]->producto_id,
            ]);
        }

        $resTimbrado = $this->timbrarSandbox($pedidoCurso->id);

        if($resTimbrado != 1){

            $pedidoCurso = CfdiComprobante::
                where('emisor_id',$empresa->id)
                ->where('status', 0)
                ->with('receptor')
                //->with('conceptos')
                ->with(['conceptos' => function ($query){
                    $query->with('mi_clave_prod_serv')
                        ->with('mi_clave_unidad');
                }])
                ->with('impuesto')
                ->with('timbre_fiscal_digital')
                ->with('archivo')
                ->with('mi_forma_pago')
                ->with('mi_metodo_pago')
                ->first();   

            //elimino cotizacion curso desde el panel en caso de que tenga
            if($pedidoCurso){
                for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                    $pedidoCurso->conceptos[$i]->delete();
                }
                $pedidoCurso->receptor->delete();
                $pedidoCurso->delete();
            }

            $message = $resTimbrado;

            // Devolvemos un código 409 Conflict. 
            return response()->json([
                'error'=>$message
            ], 409);

        }else{

            //Timbrada exitosamente
            $pedidoCurso->status = 1;
            $pedidoCurso->save();

            $count_facturas = $cliente->count_facturas + 1;
            DB::table('users')
            ->where('id', $cliente->id)
            ->update([
                'count_facturas' => $count_facturas,
            ]);

            //descontar contador de timbres disponibles
            $count_timbres = $cliente->count_timbres - 1;
            DB::table('users')
            ->where('id', $cliente->id)
            ->update([
                'count_timbres' => $count_timbres,
            ]);

            $document = $this->facturaPdf($pedidoCurso->id);

            DB::table('cfdi_archivos')
                ->where('comprobante_id', $pedidoCurso->id)
                ->update([
                    'pdf' => $document,
                ]);


            //crear o actualizar cliente
            $clienteExiste = CfdiCliente::
                where('empresa_id',$empresa_id)
                ->where('status', 1)
                ->where('Rfc', $request->input('Rfc'))
                ->with('mi_regimen_fiscal')
                ->with('mi_uso_cfdi')
                ->first();

            if(!$clienteExiste){
                $newCliente=CfdiCliente::create([
                    'empresa_id'=>$empresa_id,
                    'status'=>1,
                    'Rfc'=>$request->input('Rfc'),
                    'Nombre'=>$request->input('Nombre'),
                    'DomicilioFiscalReceptor'=>$request->input('DomicilioFiscalReceptor'),
                    'ResidenciaFiscal'=>null,
                    'NumRegIdTrib'=>null,
                    'RegimenFiscalReceptor'=>$request->input('RegimenFiscalReceptor'),
                    'UsoCFDI'=>$request->input('UsoCFDI'),
                    'Email'=>$request->input('Email'),
                ]);
            }else if($clienteExiste){
                $clienteExiste->Nombre = $request->input('Nombre');
                $clienteExiste->DomicilioFiscalReceptor = $request->input('DomicilioFiscalReceptor');
                $clienteExiste->RegimenFiscalReceptor = $request->input('RegimenFiscalReceptor');
                $clienteExiste->UsoCFDI = $request->input('UsoCFDI');
                $clienteExiste->Email = $request->input('Email');
                $clienteExiste->save();
            }

            //Descontar inventario
            for ($i=0; $i < count($conceptos); $i++) { 
                if ($conceptos[$i]->producto_id) {
                    
                    $producto = Producto::where('id', $conceptos[$i]->producto_id)
                        ->whereNull('flag_eliminado')
                        ->first();

                    if ($producto)
                    {
                        $stock = $producto->stock - $conceptos[$i]->Cantidad;
                        $producto->stock = $stock;
                        $producto->save();
                    }

                }
            }


            try {
                $this->emailFactura($pedidoCurso->id); 
            } catch (Exception $e) {
                
            }

            return response()->json([
                'message'=>'Factura timbrada exitosamente.',
                'factura_id'=>$pedidoCurso->id,
            ], 200); 
        }
  
    }

    public function timbrarProduccion($factura_id)
    {

        $factura = CfdiComprobante::
            with(['receptor' => function ($query){
                $query->with('mi_uso_cfdi');
            }])
            ->with(['conceptos' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->find($factura_id);

        if(!$factura){
            //return response()->json(['error'=>'Factura no encontrada.'],404);
            return 'Factura no encontrada.';
        }

        $emisor = CfdiEmpresa::
            with('mi_regimen_fiscal')
            ->where('id', $factura->emisor_id)
            ->first();

        // Se especifica la version de CFDi 4.0
        $datos['version_cfdi'] = '4.0';
        $datos['validacion_local']='NO';

        // Ruta del XML Timbrado
        $datos['cfdi']='sdk2/timbrados/cfdi_ejemplo_factura4.xml';

        // Ruta del XML de Debug
        $datos['xml_debug']='sdk2/timbrados/sin_timbrar_ejemplo_factura4.xml';

        // Credenciales de Timbrado
        // $datos['PAC']['usuario'] = 'DEMO700101XXX';
        // $datos['PAC']['pass'] = 'DEMO700101XXX';
        // $datos['PAC']['produccion'] = 'NO';

        $datos['PAC']['usuario'] = 'AUMA9101171B4';
        $datos['PAC']['pass'] = 'AUMA9101171B41234';
        $datos['PAC']['produccion'] = 'SI';

        // Rutas y clave de los CSD
        $datos['conf']['cer'] = str_replace("https://apicontafacil.internow.com.mx/", "", $emisor->cer);
        $datos['conf']['key'] = str_replace("https://apicontafacil.internow.com.mx/", "", $emisor->key);

        // La cadena cifrada
        $cadenaEncriptada = $emisor->pass;
        $claveAdicional = config('app.lada_d');
        $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);

        //$datos['conf']['pass'] = utf8_encode($cadenaDesencriptada);
        $datos['conf']['pass'] = $cadenaDesencriptada;

        // Datos de la Factura
        if($factura->Descuento > 0){
            $datos['factura']['descuento'] = $factura->Descuento;
        }
        
        //$datos['factura']['fecha_expedicion'] = $factura->Fecha;
        //$datos['factura']['fecha_expedicion'] = date('Y-m-d\TH:i:s', time() - (60*60));
        //$datos['factura']['fecha_expedicion'] = date('Y-m-d\TH:i:s', time() - 240);
        //$datos['factura']['fecha_expedicion'] = "2024-05-10T13:21:24";

        $fechaActual = date('Y-m-d\TH:i:s'); // Obtener la fecha y hora actual en formato ISO 8601
        // Restar dos horas a la fecha actual
        $dosHorasAtras = strtotime($fechaActual) - 7200; // Restar 7200 segundos (2 horas)
        // Formatear la fecha y hora dos horas atrás en formato ISO 8601
        $fechaDosHorasAtras = date('Y-m-d\TH:i:s', $dosHorasAtras);
        $datos['factura']['fecha_expedicion'] = $fechaDosHorasAtras;

        $datos['factura']['folio'] = $factura->Folio;

        $FormaPago = $factura->FormaPago;
        if($FormaPago >= 1 && $FormaPago <= 8){
            $FormaPago = '0'.$FormaPago;
        }

        $datos['factura']['forma_pago'] = $FormaPago;
        $datos['factura']['LugarExpedicion'] = $emisor->CP;
        $datos['factura']['metodo_pago'] = $factura->mi_metodo_pago->id;
        $datos['factura']['moneda'] = 'MXN';
        $datos['factura']['serie'] = $factura->Serie;
        $datos['factura']['subtotal'] = $factura->Subtotal;
        //$datos['factura']['tipocambio'] = 1;
        $datos['factura']['tipocomprobante'] = 'I';
        $datos['factura']['total'] = $factura->Total;
        ////$datos['factura']['RegimenFiscal'] = '601';
        $datos['factura']['Exportacion'] = '01';


        // Datos del Emisor
        $datos['emisor']['rfc'] = $emisor->Rfc;
        //$datos['emisor']['rfc'] = utf8_encode($emisor->Rfc);
        $datos['emisor']['nombre'] = $emisor->RazonSocial;
        //$datos['emisor']['nombre'] = utf8_encode($emisor->RazonSocial);
        //$datos['emisor']['nombre'] = iconv('UTF-8', 'ISO-8859-1',$emisor->RazonSocial);
         
        $datos['emisor']['RegimenFiscal'] = $emisor->RegimenFiscal;
        //$datos['emisor']['FacAtrAdquirente'] = 'ACCEM SERVICIOS EMPRESARIALES SC';

        // Datos del Receptor
        $datos['receptor']['rfc'] = $factura->receptor->Rfc;
        //$datos['receptor']['nombre'] = utf8_encode($factura->receptor->Nombre);
        $datos['receptor']['nombre'] = $factura->receptor->Nombre;

        $datos['receptor']['UsoCFDI'] = $factura->receptor->mi_uso_cfdi->id;
        //opcional
        if($factura->receptor->Rfc == "XAXX010101000"){
            $datos['receptor']['DomicilioFiscalReceptor'] = $emisor->CP;
            $factura->receptor->DomicilioFiscalReceptor = $emisor->CP;
            $factura->receptor->save();
        }else{
            $datos['receptor']['DomicilioFiscalReceptor'] = $factura->receptor->DomicilioFiscalReceptor;
        }
        
        ////$datos['receptor']['ResidenciaFiscal']= 'MEX';
        ////$datos['receptor']['NumRegIdTrib'] = 'B';
        $datos['receptor']['RegimenFiscalReceptor'] = $factura->receptor->RegimenFiscalReceptor;

        if($factura->receptor->Rfc == "XAXX010101000"){
            //Informacion Global
            $datos['InformacionGlobal']['Periodicidad'] = '02'; //Mensual
            $datos['InformacionGlobal']['Meses'] = date("m");
            $datos['InformacionGlobal']['Año'] = date("Y");
        }

        $TotalImpuestosTrasladados = 0;
        $TotalImpuestosRetenidos = 0;
        $TotalImpuestosRetenidosIva = 0;
        $TotalImpuestosRetenidosIsr = 0;

        $BaseTraslados = 0;
        $BaseRetenciones = 0;

        // Se agregan los conceptos
        for ($i=0; $i < count($factura->conceptos); $i++) { 
            $datos['conceptos'][$i]['cantidad'] = $factura->conceptos[$i]->Cantidad;
            $datos['conceptos'][$i]['unidad'] = $factura->conceptos[$i]->Unidad;
            //$datos['conceptos'][$i]['ID'] = "1726";
            
            //$datos['conceptos'][$i]['descripcion'] = utf8_encode($factura->conceptos[$i]->Descripcion);
            $datos['conceptos'][$i]['descripcion'] = $factura->conceptos[$i]->Descripcion;
            $datos['conceptos'][$i]['valorunitario'] = $factura->conceptos[$i]->ValorUnitario;
            $datos['conceptos'][$i]['importe'] = $factura->conceptos[$i]->Importe;

            if($factura->conceptos[$i]->Descuento > 0){
                $datos['conceptos'][0]['Descuento'] = $factura->conceptos[$i]->Descuento;
            }

            $datos['conceptos'][$i]['ClaveProdServ'] = $factura->conceptos[$i]->mi_clave_prod_serv->id;
            $datos['conceptos'][$i]['ClaveUnidad'] = $factura->conceptos[$i]->mi_clave_unidad->id;

            $datos['conceptos'][$i]['ObjetoImp'] = '01'; //no

            if($factura->conceptos[$i]->ObjetoImp == 1){
                $datos['conceptos'][$i]['ObjetoImp'] = '02'; //si

                $Base = $factura->conceptos[$i]->Importe - $factura->conceptos[$i]->Descuento;
                $BaseTraslados = $BaseTraslados + $Base;

                $Importe = number_format(($Base * 0.16), 2, '.', '');
                $TotalImpuestosTrasladados = $TotalImpuestosTrasladados + $Importe;

                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Base'] = $Base;
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Impuesto'] = '002';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Importe'] = $Importe;

                if($factura->conceptos[$i]->ObjetoImpRet == 1){

                    $BaseRetenciones = $BaseRetenciones + $Base;
                    $retencionIva = $Base * ($factura->TasaIva/100);
                    $retencionIva = number_format(($retencionIva), 2, '.', '');

                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Base'] = $Base;
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Impuesto'] = '002';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['TipoFactor'] = 'Tasa';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['TasaOCuota'] = $factura->TasaIva/100;
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Importe'] = $retencionIva;

                    $retencionIsr = $Base * ($factura->TasaIsr/100);
                    $retencionIsr = number_format(($retencionIsr), 2, '.', '');

                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Base'] = $Base;
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Impuesto'] = '001';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['TipoFactor'] = 'Tasa';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['TasaOCuota'] = $factura->TasaIsr/100;
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Importe'] = $retencionIsr;

                    $TotalImpuestosRetenidosIva = $TotalImpuestosRetenidosIva + $retencionIva;
                    $TotalImpuestosRetenidosIsr = $TotalImpuestosRetenidosIsr + $retencionIsr;
                    $TotalImpuestosRetenidos = $TotalImpuestosRetenidos + $TotalImpuestosRetenidosIva + $TotalImpuestosRetenidosIsr;

                }
            }
            
        }

        // Se agregan los Impuestos
        if($factura->conceptos[0]->ObjetoImp == 1){

            $datos['impuestos']['TotalImpuestosTrasladados'] = number_format($TotalImpuestosTrasladados, 2, '.', '');

            if($factura->conceptos[0]->ObjetoImpRet == 1){

                $datos['impuestos']['TotalImpuestosRetenidos'] = number_format($TotalImpuestosRetenidos, 2, '.', '');

            }

            $Importe = number_format(($BaseTraslados * 0.16), 2, '.', '');

            //Validacion adicional
            if($Importe != number_format($TotalImpuestosTrasladados, 2, '.', '')){
                $Importe = number_format($TotalImpuestosTrasladados, 2, '.', '');
            }

            $datos['impuestos']['translados'][0]['Base'] = $BaseTraslados;
            $datos['impuestos']['translados'][0]['impuesto'] = '002';
            $datos['impuestos']['translados'][0]['tasa'] = '0.160000';
            $datos['impuestos']['translados'][0]['importe'] = $Importe;
            $datos['impuestos']['translados'][0]['TipoFactor'] = 'Tasa';

            if($factura->conceptos[0]->ObjetoImpRet == 1){

                $datos['impuestos']['retenciones'][0]['impuesto'] = '002';
                $datos['impuestos']['retenciones'][0]['importe'] = number_format($TotalImpuestosRetenidosIva, 2, '.', '');

                $datos['impuestos']['retenciones'][1]['impuesto'] = '001';
                $datos['impuestos']['retenciones'][1]['importe'] = number_format($TotalImpuestosRetenidosIsr, 2, '.', '');

            }

            
        }

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

            $archivo_xml = $res['cfdi'];
            $archivo_png = $res['png'];

            $nuevoObjArchivo=CfdiArchivo::create([
                'comprobante_id'=>$factura->id,
                'xml'=>$archivo_xml,
                'png'=>$archivo_png,
            ]);

            // Genera un nombre de archivo único
            $nombreArchivo = 'xml_' . uniqid() . '.xml';

            // Guarda el XML en la carpeta "public" del directorio raíz
            Storage::disk('public_root')->put('xmls_facturas/'.$nombreArchivo, $archivo_xml);

            // Obtiene la URL del archivo guardado
            $url = asset('xmls_facturas/' . $nombreArchivo);

            DB::table('cfdi_archivos')
            ->where('comprobante_id', $factura->id)
            ->update([
                'xml_archivo' => $url,
            ]);

            $factura->Sello = $res['representacion_impresa_sello'][0];
            $factura->NoCertificado = $res['representacion_impresa_certificado_no'];
            $factura->save();

            $nuevoTimbreFiscalDigital=CfdiTimbreFiscalDigital::create([
                'comprobante_id'=>$factura->id,
                'Version'=>null,
                'UUID'=>$res['uuid'],
                'FechaTimbrado'=>$res['representacion_impresa_fecha_timbrado'][0],
                'RfcProvCertif'=>null,
                'SelloCFD'=>null,
                'NoCertificadoSAT'=>$res['representacion_impresa_certificadoSAT'][0],
                'SelloSAT'=>$res['representacion_impresa_selloSAT'][0],
                
            ]);

            //para debug
            $factura->timbre_fiscal_digital = $nuevoTimbreFiscalDigital;

            return 1;
        }
        else if(
            isset($res['codigo_mf_texto'])
        ){
            return $res['codigo_mf_texto'];
        }
        else {
            return 'Error al conectar con la librería de timbrado';
        }

    }

    public function timbrarSandbox($factura_id)
    {

        $factura = CfdiComprobante::
            with(['receptor' => function ($query){
                $query->with('mi_uso_cfdi');
            }])
            ->with(['conceptos' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->find($factura_id);

        if(!$factura){
            //return response()->json(['error'=>'Factura no encontrada.'],404);
            return 'Factura no encontrada.';
        }

        $emisor = CfdiEmpresa::
            with('mi_regimen_fiscal')
            ->where('id', $factura->emisor_id)
            ->first();

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
        // $datos['conf']['cer'] = str_replace("http://localhost/proy_conta_facil/conta_facilAPI/public/", "", $emisor->cer);
        // $datos['conf']['key'] = str_replace("http://localhost/proy_conta_facil/conta_facilAPI/public/", "", $emisor->key);

        $datos['conf']['cer'] = str_replace("https://apicontafacil.internow.com.mx/", "", $emisor->cer);
        $datos['conf']['key'] = str_replace("https://apicontafacil.internow.com.mx/", "", $emisor->key);

        // La cadena cifrada
        $cadenaEncriptada = $emisor->pass;
        $claveAdicional = config('app.lada_d');
        $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);

        //$datos['conf']['pass'] = utf8_encode($cadenaDesencriptada);
        $datos['conf']['pass'] = $cadenaDesencriptada;

        // Datos de la Factura
        if($factura->Descuento > 0){
            $datos['factura']['descuento'] = $factura->Descuento;
        }
        
        //$datos['factura']['fecha_expedicion'] = $factura->Fecha;
        //$datos['factura']['fecha_expedicion'] = date('Y-m-d\TH:i:s', time() - (60*60));
        //$datos['factura']['fecha_expedicion'] = date('Y-m-d\TH:i:s', time() - 240);
        //$datos['factura']['fecha_expedicion'] = "2024-05-10T13:21:24";

        $fechaActual = date('Y-m-d\TH:i:s'); // Obtener la fecha y hora actual en formato ISO 8601
        // Restar dos horas a la fecha actual
        $dosHorasAtras = strtotime($fechaActual) - 7200; // Restar 7200 segundos (2 horas)
        // Formatear la fecha y hora dos horas atrás en formato ISO 8601
        $fechaDosHorasAtras = date('Y-m-d\TH:i:s', $dosHorasAtras);
        $datos['factura']['fecha_expedicion'] = $fechaDosHorasAtras;

        $datos['factura']['folio'] = $factura->Folio;

        $FormaPago = $factura->FormaPago;
        if($FormaPago >= 1 && $FormaPago <= 8){
            $FormaPago = '0'.$FormaPago;
        }

        $datos['factura']['forma_pago'] = $FormaPago;
        $datos['factura']['LugarExpedicion'] = $emisor->CP;
        $datos['factura']['metodo_pago'] = $factura->mi_metodo_pago->id;
        $datos['factura']['moneda'] = 'MXN';
        $datos['factura']['serie'] = $factura->Serie;
        $datos['factura']['subtotal'] = $factura->Subtotal;
        //$datos['factura']['tipocambio'] = 1;
        $datos['factura']['tipocomprobante'] = 'I';
        $datos['factura']['total'] = $factura->Total;
        ////$datos['factura']['RegimenFiscal'] = '601';
        $datos['factura']['Exportacion'] = '01';


        // Datos del Emisor
        $datos['emisor']['rfc'] = $emisor->Rfc;
        //$datos['emisor']['rfc'] = utf8_encode($emisor->Rfc);
        $datos['emisor']['nombre'] = $emisor->RazonSocial;
        //$datos['emisor']['nombre'] = utf8_encode($emisor->RazonSocial);
        //$datos['emisor']['nombre'] = iconv('UTF-8', 'ISO-8859-1',$emisor->RazonSocial);
         
        $datos['emisor']['RegimenFiscal'] = $emisor->RegimenFiscal;
        //$datos['emisor']['FacAtrAdquirente'] = 'ACCEM SERVICIOS EMPRESARIALES SC';

        // Datos del Receptor
        $datos['receptor']['rfc'] = $factura->receptor->Rfc;
        //$datos['receptor']['nombre'] = utf8_encode($factura->receptor->Nombre);
        $datos['receptor']['nombre'] = $factura->receptor->Nombre;

        $datos['receptor']['UsoCFDI'] = $factura->receptor->mi_uso_cfdi->id;
        //opcional
        if($factura->receptor->Rfc == "XAXX010101000"){
            $datos['receptor']['DomicilioFiscalReceptor'] = $emisor->CP;
            $factura->receptor->DomicilioFiscalReceptor = $emisor->CP;
            $factura->receptor->save();
        }else{
            $datos['receptor']['DomicilioFiscalReceptor'] = $factura->receptor->DomicilioFiscalReceptor;
        }
        
        ////$datos['receptor']['ResidenciaFiscal']= 'MEX';
        ////$datos['receptor']['NumRegIdTrib'] = 'B';
        $datos['receptor']['RegimenFiscalReceptor'] = $factura->receptor->RegimenFiscalReceptor;

        if($factura->receptor->Rfc == "XAXX010101000"){
            //Informacion Global
            $datos['InformacionGlobal']['Periodicidad'] = '02'; //Mensual
            $datos['InformacionGlobal']['Meses'] = date("m");
            $datos['InformacionGlobal']['Año'] = date("Y");
        }

        $TotalImpuestosTrasladados = 0;
        $TotalImpuestosRetenidos = 0;
        $TotalImpuestosRetenidosIva = 0;
        $TotalImpuestosRetenidosIsr = 0;

        $BaseTraslados = 0;
        $BaseRetenciones = 0;

        // Se agregan los conceptos
        for ($i=0; $i < count($factura->conceptos); $i++) { 
            $datos['conceptos'][$i]['cantidad'] = $factura->conceptos[$i]->Cantidad;
            $datos['conceptos'][$i]['unidad'] = $factura->conceptos[$i]->Unidad;
            //$datos['conceptos'][$i]['ID'] = "1726";
            
            //$datos['conceptos'][$i]['descripcion'] = utf8_encode($factura->conceptos[$i]->Descripcion);
            $datos['conceptos'][$i]['descripcion'] = $factura->conceptos[$i]->Descripcion;
            $datos['conceptos'][$i]['valorunitario'] = $factura->conceptos[$i]->ValorUnitario;
            $datos['conceptos'][$i]['importe'] = $factura->conceptos[$i]->Importe;

            if($factura->conceptos[$i]->Descuento > 0){
                $datos['conceptos'][0]['Descuento'] = $factura->conceptos[$i]->Descuento;
            }

            $datos['conceptos'][$i]['ClaveProdServ'] = $factura->conceptos[$i]->mi_clave_prod_serv->id;
            $datos['conceptos'][$i]['ClaveUnidad'] = $factura->conceptos[$i]->mi_clave_unidad->id;

            $datos['conceptos'][$i]['ObjetoImp'] = '01'; //no

            if($factura->conceptos[$i]->ObjetoImp == 1){
                $datos['conceptos'][$i]['ObjetoImp'] = '02'; //si

                $Base = $factura->conceptos[$i]->Importe - $factura->conceptos[$i]->Descuento;
                $BaseTraslados = $BaseTraslados + $Base;

                $Importe = number_format(($Base * 0.16), 2, '.', '');
                $TotalImpuestosTrasladados = $TotalImpuestosTrasladados + $Importe;

                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Base'] = $Base;
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Impuesto'] = '002';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Importe'] = $Importe;

                if($factura->conceptos[$i]->ObjetoImpRet == 1){

                    $BaseRetenciones = $BaseRetenciones + $Base;
                    $retencionIva = $Base * ($factura->TasaIva/100);
                    $retencionIva = number_format(($retencionIva), 2, '.', '');

                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Base'] = $Base;
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Impuesto'] = '002';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['TipoFactor'] = 'Tasa';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['TasaOCuota'] = $factura->TasaIva/100;
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Importe'] = $retencionIva;

                    $retencionIsr = $Base * ($factura->TasaIsr/100);
                    $retencionIsr = number_format(($retencionIsr), 2, '.', '');

                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Base'] = $Base;
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Impuesto'] = '001';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['TipoFactor'] = 'Tasa';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['TasaOCuota'] = $factura->TasaIsr/100;
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Importe'] = $retencionIsr;

                    $TotalImpuestosRetenidosIva = $TotalImpuestosRetenidosIva + $retencionIva;
                    $TotalImpuestosRetenidosIsr = $TotalImpuestosRetenidosIsr + $retencionIsr;
                    $TotalImpuestosRetenidos = $TotalImpuestosRetenidos + $TotalImpuestosRetenidosIva + $TotalImpuestosRetenidosIsr;

                }
            }
            
        }

        // Se agregan los Impuestos
        if($factura->conceptos[0]->ObjetoImp == 1){

            $datos['impuestos']['TotalImpuestosTrasladados'] = number_format($TotalImpuestosTrasladados, 2, '.', '');

            if($factura->conceptos[0]->ObjetoImpRet == 1){

                $datos['impuestos']['TotalImpuestosRetenidos'] = number_format($TotalImpuestosRetenidos, 2, '.', '');

            }

            $Importe = number_format(($BaseTraslados * 0.16), 2, '.', '');

            //Validacion adicional
            if($Importe != number_format($TotalImpuestosTrasladados, 2, '.', '')){
                $Importe = number_format($TotalImpuestosTrasladados, 2, '.', '');
            }

            $datos['impuestos']['translados'][0]['Base'] = $BaseTraslados;
            $datos['impuestos']['translados'][0]['impuesto'] = '002';
            $datos['impuestos']['translados'][0]['tasa'] = '0.160000';
            $datos['impuestos']['translados'][0]['importe'] = $Importe;
            $datos['impuestos']['translados'][0]['TipoFactor'] = 'Tasa';

            if($factura->conceptos[0]->ObjetoImpRet == 1){

                $datos['impuestos']['retenciones'][0]['impuesto'] = '002';
                $datos['impuestos']['retenciones'][0]['importe'] = number_format($TotalImpuestosRetenidosIva, 2, '.', '');

                $datos['impuestos']['retenciones'][1]['impuesto'] = '001';
                $datos['impuestos']['retenciones'][1]['importe'] = number_format($TotalImpuestosRetenidosIsr, 2, '.', '');

            }

            
        }

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

            $archivo_xml = $res['cfdi'];
            $archivo_png = $res['png'];

            $nuevoObjArchivo=CfdiArchivo::create([
                'comprobante_id'=>$factura->id,
                'xml'=>$archivo_xml,
                'png'=>$archivo_png,
            ]);

            // Genera un nombre de archivo único
            $nombreArchivo = 'xml_' . uniqid() . '.xml';

            // Guarda el XML en la carpeta "public" del directorio raíz
            Storage::disk('public_root')->put('xmls_facturas/'.$nombreArchivo, $archivo_xml);

            // Obtiene la URL del archivo guardado
            $url = asset('xmls_facturas/' . $nombreArchivo);

            DB::table('cfdi_archivos')
            ->where('comprobante_id', $factura->id)
            ->update([
                'xml_archivo' => $url,
            ]);

            $factura->Sello = $res['representacion_impresa_sello'][0];
            $factura->NoCertificado = $res['representacion_impresa_certificado_no'];
            $factura->save();

            $nuevoTimbreFiscalDigital=CfdiTimbreFiscalDigital::create([
                'comprobante_id'=>$factura->id,
                'Version'=>null,
                'UUID'=>$res['uuid'],
                'FechaTimbrado'=>$res['representacion_impresa_fecha_timbrado'][0],
                'RfcProvCertif'=>null,
                'SelloCFD'=>null,
                'NoCertificadoSAT'=>$res['representacion_impresa_certificadoSAT'][0],
                'SelloSAT'=>$res['representacion_impresa_selloSAT'][0],
                
            ]);

            //para debug
            $factura->timbre_fiscal_digital = $nuevoTimbreFiscalDigital;

            return 1;
        }
        else if(
            isset($res['codigo_mf_texto'])
        ){
            return $res['codigo_mf_texto'];
        }
        else {
            return 'Error al conectar con la librería de timbrado';
        }

    }

    public function facturaPdf($factura_id)
    {

        set_time_limit(500);

        $factura = CfdiComprobante::
            with(['receptor' => function ($query){
                $query->with('mi_regimen_fiscal')
                    ->with('mi_uso_cfdi');
            }])
            ->with(['conceptos' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->find($factura_id);

        if(!$factura){
            return response()->json(['error'=>'Factura no encontrada.'],404);
        }

        $TotalImpuestosTrasladados = 0;
        $TotalImpuestosRetenidos = 0;
        $TotalImpuestosRetenidosIva = 0;
        $TotalImpuestosRetenidosIsr = 0;

        for ($i=0; $i < count($factura->conceptos); $i++) { 

            $factura->conceptos[$i]->Impuestos = [];

            if($factura->conceptos[$i]->ObjetoImp == 1){

                $Impuestos = [];

                $factura->conceptos[$i]->ObjetoImp = 'Si obj de impuesto.';
                $Base = $factura->conceptos[$i]->Importe - $factura->conceptos[$i]->Descuento;

                $Importe = number_format(($Base * 0.16), 2, '.', '');
                $TotalImpuestosTrasladados = $TotalImpuestosTrasladados + $Importe;

                $resul = (object) [
                    'Impuesto' => "IVA",
                    'Tipo' => "Traslado",
                    'Base' => $Base,
                    'TipoFactor' => "Tasa",
                    'TasaOCuota' => "16.00%",
                    'Importe' => $Importe
                ];
                array_push($Impuestos,$resul);

                if($factura->conceptos[$i]->ObjetoImpRet == 1){

                    $retencionIva = $Base * ($factura->TasaIva/100);
                    $retencionIva = number_format(($retencionIva), 2, '.', '');
                    $resul = (object) [
                        'Impuesto' => "IVA",
                        'Tipo' => "Retención",
                        'Base' => $Base,
                        'TipoFactor' => "Tasa",
                        'TasaOCuota' => $factura->TasaIva."%",
                        'Importe' => $retencionIva
                    ];
                    array_push($Impuestos,$resul);

                    $retencionIsr = $Base * ($factura->TasaIsr/100);
                    $retencionIsr = number_format(($retencionIsr), 2, '.', '');
                    $resul = (object) [
                        'Impuesto' => "ISR",
                        'Tipo' => "Retención",
                        'Base' => $Base,
                        'TipoFactor' => "Tasa",
                        'TasaOCuota' => $factura->TasaIsr."%",
                        'Importe' => $retencionIsr
                    ];
                    array_push($Impuestos,$resul);

                    $TotalImpuestosRetenidosIva = $TotalImpuestosRetenidosIva + $retencionIva;
                    $TotalImpuestosRetenidosIsr = $TotalImpuestosRetenidosIsr + $retencionIsr;
                    $TotalImpuestosRetenidos = $TotalImpuestosRetenidos + $TotalImpuestosRetenidosIva + $TotalImpuestosRetenidosIsr;

                }

                $factura->conceptos[$i]->Impuestos = $Impuestos;


            }
        }
        $factura->TotalImpuestosTrasladados = number_format($TotalImpuestosTrasladados, 2, '.', '');
        $factura->TotalImpuestosRetenidos = number_format($TotalImpuestosRetenidos, 2, '.', '');
        $factura->TotalImpuestosRetenidosIva = number_format($TotalImpuestosRetenidosIva, 2, '.', '');
        $factura->TotalImpuestosRetenidosIsr = number_format($TotalImpuestosRetenidosIsr, 2, '.', '');

        $emisor = CfdiEmpresa::/*with('producto')
            ->*/with('mi_regimen_fiscal')
            ->find($factura->emisor_id);

        $cliente = User::find($emisor->user_id);

        // return response()->json([
        //     'emisor' => $emisor,
        //     'factura'=>$factura,
        // ], 200);

        $data = [
            'header' => $cliente->header,
            'footer' => $cliente->footer,
            'emisor' => $emisor,
            'factura' => $factura
        ];

        //$pdf = Pdf::loadView('cotizaciones.cotizacion', $data);
        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('facturas.factura', $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs_facturas/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs_facturas/' . $nombreArchivo);

        return $url;
    }


    public function emailFactura($factura_id)
    {

        $factura = CfdiComprobante::select('id','emisor_id')
            ->with(['receptor' => function ($query){
                $query->select('id','comprobante_id','Rfc','Nombre','Email');
            }])
            ->with(['archivo' => function ($query){
                $query->select('id','comprobante_id','xml_archivo','pdf');
            }])
            ->find($factura_id);

        $empresa=CfdiEmpresa::select('id','user_id')
            ->with('user')
            ->find($factura->emisor_id);
        
        // $details = [

        //     'logo' => $empresa->user->logo,

        //     'color_a' => $empresa->user->color_a,

        //     'color_b' => $empresa->user->color_b,

        //     'color_c' => $empresa->user->color_c,

        //     'Nombre' => $factura->receptor->Nombre,

        //     'Rfc' => $factura->receptor->Rfc,


        // ];

        $details = [

            'logo' => 'https://apicontafacil.internow.com.mx/images_uploads/logos/logo_base.png',

            'color_a' => '#4285cb',

            'color_b' => '#ffffff',

            'color_c' => '#ffffff',

            'Nombre' => $factura->receptor->Nombre,

            'Rfc' => $factura->receptor->Rfc,


        ];

        $attachment1 = $factura->archivo->pdf;
        $attachment2 = $factura->archivo->xml_archivo;

        \Mail::to($factura->receptor->Email)->send(new \App\Mail\NuevaFacturaEmail($details,$attachment1,$attachment2));

        return 1;

    }

    public function emailFacturaCancelada($factura_id)
    {

        $factura = CfdiComprobante::select('id','emisor_id', 'Serie', 'Folio')
            ->with(['receptor' => function ($query){
                $query->select('id','comprobante_id','Rfc','Nombre','Email');
            }])
            ->with(['archivo' => function ($query){
                $query->select('id','comprobante_id','xml_archivo','pdf');
            }])
            ->find($factura_id);

        $empresa=CfdiEmpresa::select('id','user_id','Rfc','RazonSocial')
            ->with('user')
            ->find($factura->emisor_id);
        

        $details = [

            'logo' => 'https://apicontafacil.internow.com.mx/images_uploads/logos/logo_base.png',

            'color_a' => '#4285cb',

            'color_b' => '#ffffff',

            'color_c' => '#ffffff',

            'Nombre' => $empresa->RazonSocial,

            'Rfc' => $empresa->Rfc,

            'Serie' => $factura->Serie,

            'Folio' => $factura->Folio,

        ];

        $attachment1 = $factura->archivo->pdf;
        $attachment2 = $factura->archivo->xml_archivo;

        \Mail::to($empresa->user->email)->send(new \App\Mail\FacturaCanceladaEmail($details,$attachment1,$attachment2));

        return 1;

    }

    public function setFlagAlgoritmoFactura()
    {
        DB::table('users')
            ->update([
                'flag_algoritmo_factura' => null,
            ]);

        return response()->json([
            'message'=>'Usuarios inicializados',
        ], 200);

    }

    public function aplicarAlgoritmoSemanalFactura()
    {
        $usuario = User::whereNull('flag_eliminado')
            ->whereNull('flag_algoritmo_factura')
            ->where('tipo_algoritmo_factura',1)
            ->first();

        return $this->ingresosContables($usuario->id);

        // return response()->json([
        //     'usuario'=>$usuario,
        // ], 200);

    }

    public function aplicarAlgoritmoMansualFactura()
    {
        $usuario = User::whereNull('flag_eliminado')
            ->whereNull('flag_algoritmo_factura')
            ->where('tipo_algoritmo_factura',2)
            ->first();

        return $this->ingresosContables($usuario->id);

        // return response()->json([
        //     'usuario'=>$usuario,
        // ], 200);

    }

    public function ingresosContables($user_id)
    {
        //marcar aplicacion del algoritmo
        DB::table('users')
            ->where('id', $user_id)
            ->update([
                'flag_algoritmo_factura' => 1,
            ]);

        $usuario = User::whereNull('flag_eliminado')
            ->with('cfdi_empresa.mi_regimen_fiscal')
            ->find($user_id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado'], 404);
        }

        if (!$usuario->cfdi_empresa)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Emisor CFDI no encontrado'], 404);
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
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Emisor CFDI no configurado'], 404);
        }

        if($usuario->cfdi_empresa->pass != '' && $usuario->cfdi_empresa->pass != null){
            $usuario->cfdi_empresa->pass = 'pass';
        }else{
            $usuario->cfdi_empresa->pass = '';
        }

        $producto = CfdiProducto::
            where('empresa_id',$usuario->cfdi_empresa->id)
            // ->with('mi_clave_prod_serv')
            // ->with('mi_clave_unidad')
            ->first();

        if (!$producto)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Producto CFDI no encontrado'], 404);
        }

        if (
            $producto->ClaveProdServ == null || $producto->ClaveProdServ == '' ||
            $producto->ClaveUnidad == null || $producto->ClaveUnidad == '' ||
            $producto->Unidad == null || $producto->Unidad == '' ||
            $producto->Descripcion == null || $producto->Descripcion == '' ||
            $producto->FormaPago == null || $producto->FormaPago == ''
        )
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Producto CFDI no configurado'], 404);
        }

        //Ingresos cotables no facturados
        $total_ingresos_contables = Ingreso::whereNull('flag_eliminado')
            ->where('user_id',$usuario->id)
            ->where('tipo_id',1)
            ->whereNull('factura_id')
            ->sum('total');

        $total_ingresos_contables = ceil($total_ingresos_contables);

        if ($total_ingresos_contables == 0)
        {
            return response()->json([
                'message'=>'No tienes Ingresos contables pendientes por facturar.',
                'total_ingresos_contables'=>$total_ingresos_contables,
                'usuario'=>$usuario
            ], 200);
        }

        /*return response()->json([
            'total_ingresos_contables'=>$total_ingresos_contables,
            'usuario'=>$usuario
        ], 200);*/

        return $this->timbrarFacturaAutomatica($usuario->cfdi_empresa->id,$total_ingresos_contables);
        
    }
    
    public function timbrarFacturaAutomatica($empresa_id, $total_ingresos_contables)
    {

        // Comprobamos si la empresa que nos están pasando existe o no.
        $empresa=CfdiEmpresa::find($empresa_id);
        if (!$empresa)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Empresa no encontrada.'], 404);
        }

        $cliente=User::find($empresa->user_id);
        if (!$cliente)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado.'], 404);
        }

        if ($cliente->status != 1)
        {
            return response()->json(['error'=>'Emisor inhabilitado para generar timbre electrónico.'], 401);
        }

        $producto = CfdiProducto::
            where('empresa_id',$empresa->id)
            // ->with('mi_clave_prod_serv')
            // ->with('mi_clave_unidad')
            ->first();

        if (!$producto)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Producto CFDI no encontrado'], 404);
        }

        $pedidoCurso = CfdiComprobante::
            where('emisor_id',$empresa->id)
            ->where('status', 0)
            ->with('receptor')
            //->with('conceptos')
            ->with(['conceptos' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->first();   

        //elimino cotizacion curso desde el panel en caso de que tenga
        if($pedidoCurso){
            for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                $pedidoCurso->conceptos[$i]->delete();
            }
            $pedidoCurso->receptor->delete();
            $pedidoCurso->delete();
        } 

        //Iniciar proceso de facturacion
        $Folio = (CfdiComprobante::count())+1;

        $Serie = (CfdiComprobante::
            where('emisor_id',$empresa->id)
            ->count())+1;

        //quitar el iva al subtotal
        $Subtotal = $total_ingresos_contables/1.16;
        $Subtotal = number_format($Subtotal, 2, '.', '');

        //recalcular el total con el iva
        $Total = $Subtotal*1.16;
        $Total = number_format($Total, 2, '.', '');


        //crear un pedido nuevo en curso
        $pedidoCurso=CfdiComprobante::create([
            'emisor_id'=>$empresa->id,
            'receptor_id'=>null,
            'status'=>0,
            'flag_cancelada'=>null,
            'Serie'=>"S-".$empresa->id."-".$Serie,
            'Folio'=>"F-".$empresa->id."-".$Folio,
            'Fecha'=>date('Y-m-d\TH:i:s', time() - (60*60)),
            'Sello'=>"",
            'FormaPago'=>$producto->FormaPago, 
            'NoCertificado'=>"",
            'Certificado'=>"",
            'CondicionesDePago'=>"",
            'Subtotal'=>$Subtotal,
            'Descuento'=>0,
            'Moneda'=>"MXN",
            'TipoCambio'=>"",
            'Total'=>$Total,
            'TipoDeComprobante'=>"I",
            'Exportacion'=>"01",
            'MetodoPago'=>"2",
            'LugarExpedicion'=>$empresa->CP,
            'Confirmacion'=>"",
            //'estado'=>null,
            //'function'=>null,
            'TasaIva'=>0,
            'TasaIsr'=>0,
            'Tipo'=>2,
        ]);

        //crear el receptor
        $newObjReceptor=CfdiReceptor::create([
            'comprobante_id'=>$pedidoCurso->id,
            'Rfc'=>"XAXX010101000",
            'Nombre'=>"PUBLICO EN GENERAL",
            'DomicilioFiscalReceptor'=>$empresa->CP,
            'ResidenciaFiscal'=>null,
            'NumRegIdTrib'=>null,
            'RegimenFiscalReceptor'=>"616", //Sin obligaciones fiscales
            'UsoCFDI'=>"24", //Sin efectos fiscales.
            'Email'=>$cliente->email,
        ]);

        //agregar nuevo concepto
        $nuevoConcepto=CfdiConcepto::create([
            'comprobante_id' => $pedidoCurso->id,
            'ClaveProdServ' => $producto->ClaveProdServ, 
            'NoIdentificacion' => "",
            'Cantidad' => 1,
            'ClaveUnidad' => $producto->ClaveUnidad, 
            'Unidad' => $producto->Unidad, 
            'Descripcion' => $producto->Descripcion, 
            'ValorUnitario' => $Subtotal,
            'Importe' => $Subtotal,
            'Descuento' => 0,
            'ObjetoImp' => "1",
            'ObjetoImpRet' => "0",
        ]);
        

        $resTimbrado = $this->timbrarProduccion($pedidoCurso->id);

        if($resTimbrado != 1){

            $pedidoCurso = CfdiComprobante::
                where('emisor_id',$empresa->id)
                ->where('status', 0)
                ->with('receptor')
                //->with('conceptos')
                ->with(['conceptos' => function ($query){
                    $query->with('mi_clave_prod_serv')
                        ->with('mi_clave_unidad');
                }])
                ->with('impuesto')
                ->with('timbre_fiscal_digital')
                ->with('archivo')
                ->with('mi_forma_pago')
                ->with('mi_metodo_pago')
                ->first();   

            //elimino cotizacion curso desde el panel en caso de que tenga
            if($pedidoCurso){
                for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                    $pedidoCurso->conceptos[$i]->delete();
                }
                $pedidoCurso->receptor->delete();
                $pedidoCurso->delete();
            }

            $message = $resTimbrado;

            // Devolvemos un código 409 Conflict. 
            return response()->json([
                'error'=>$message
            ], 409);

        }else{

            //Timbrada exitosamente
            $pedidoCurso->status = 1;
            $pedidoCurso->save();

            $count_facturas = $cliente->count_facturas + 1;
            DB::table('users')
            ->where('id', $cliente->id)
            ->update([
                'count_facturas' => $count_facturas,
            ]);

            $document = $this->facturaPdf($pedidoCurso->id);

            DB::table('cfdi_archivos')
                ->where('comprobante_id', $pedidoCurso->id)
                ->update([
                    'pdf' => $document,
                ]);

            //marcar ingresos contables como facturados
            //se marcan con el id de la factura
            DB::table('ingresos')
                ->whereNull('flag_eliminado')
                ->where('user_id',$cliente->id)
                ->where('tipo_id',1)
                ->whereNull('factura_id')
                ->update([
                    'factura_id' => $pedidoCurso->id,
                ]);


            try {
                $this->emailFactura($pedidoCurso->id); 
            } catch (Exception $e) {
                
            }

            return response()->json([
                'message'=>'Factura timbrada exitosamente.',
                'factura_id'=>$pedidoCurso->id,
            ], 200); 
        }
  
    }

    public function getTotalFacturado($user_id) {
        $dia_actual = date("d"); //j  Día del mes sin ceros iniciales 1 a 31
                                //d Día del mes, 2 dígitos con ceros iniciales  01 a 31
        $mes_actual = date("m");
        $anio_actual = date("Y");

        $usuario = User::with('cfdi_empresa')->find($user_id);

        if (!$usuario)
        {
            return 0;
        }

        //total facturado
        $total = CfdiComprobante::
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
            ->where('emisor_id',$usuario->cfdi_empresa->id)
            ->where(function ($query) {
                $query
                    ->where('status',1)
                    /*->orWhere('status',2)*/;
            })
            ->sum('Total');

        //total pendiente por facturar
        $total_por_facturar = Ingreso::whereNull('flag_eliminado')
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            ->where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
            ->where('user_id',$usuario->id)
            ->where('tipo_id',1)
            ->whereNull('factura_id')
            ->sum('total');

        return $total + $total_por_facturar;
    }

    public function determinarLimiteFacturacion($rfc, $regimenFiscal) {
        // Verificar si el RFC es de una persona física o moral
        $esPersonaFisica = strlen($rfc) == 13;
        $esPersonaMoral = strlen($rfc) == 12;
    
        // Verificar si es una persona moral con terminación en 'SAT'
        $terminaEnSAT = $esPersonaMoral && substr($rfc, -3) === 'SAT';
    
        // Validar y determinar el límite de facturación
        if ($esPersonaMoral) {
            if ($regimenFiscal == 626) {
                // Persona Moral con RESICO
                return 33000000;
            } elseif ($terminaEnSAT) {
                // Persona Moral que termina en 'SAT'
                return 5000000;
            } else {
                // Persona Moral que no es RESICO
                return 0;
            }
        } elseif ($esPersonaFisica && $regimenFiscal == 626) {
            // Persona Física con RESICO
            return 290000;
        } else {
            // Caso no contemplado
            return null; // O cualquier otro valor que indique que no aplica
        }
    }

    public function updateStatusPay(Request $request, $factura_id)
    {
        // Comprobamos si lo que nos están pasando existe o no.
        $registro=CfdiComprobante::find($factura_id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Registro no encontrado.'], 404);
        }    
        
        // Listado de campos recibidos teóricamente.
        $status_pay=$request->input('status_pay');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.
        if (($status_pay != null && $status_pay!='') || $status_pay === 0)
        {
            $registro->status_pay = $status_pay;
            $bandera=true;
        }
       
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($registro->save()) {
                return response()->json(['message'=>'Registro actualizado.',
                 'registro'=>$registro], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el registro.'], 500);
            }           
        }
        else
        {
            // Se devuelve un array error con los error encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún al registro.'],500);
        }
    }

    public function correrFacturasRecurrentes()
    {

        set_time_limit(500);

        $hoy = Carbon::now();
        $dia_mes = $hoy->day;
        $dia_semana = $hoy->dayOfWeek;
        $hora = $hoy->hour;
        $minutos = $hoy->minute;
        $fecha_actual = $hoy->format('Y-m-d');
        
        $facturas_recurrentes = CfdiRecurrente::where('status', 1)
            ->where(function ($query) use ($fecha_actual) {
                    $query
                        ->where('date_last_run', '<>', $fecha_actual)
                        ->orWhereNull('date_last_run');
                })
            ->where(function ($query) use ($fecha_actual, $dia_semana, $dia_mes) {
                $query->where(function ($q) use ($fecha_actual) {
                    $q->where('frecuencia', 1)
                      ->where('fecha', $fecha_actual);
                })
                ->orWhere(function ($q) use ($dia_semana) {
                    $q->where('frecuencia', 2)
                      ->where('dia_semana', $dia_semana);
                })
                ->orWhere(function ($q) use ($dia_mes) {
                    $q->where('frecuencia', 3)
                      ->where('dia_mes', $dia_mes);
                });
            })
            ->get();

        $array_facturas = [];

        $hora1 = Carbon::createFromTimeString($hora.':'.$minutos);

        //Logica para la hora de timbrado
        foreach ($facturas_recurrentes as $factura) {

            $hora2 = Carbon::parse($factura->hora);
            //si la hora actual ($hora1) es mayor o igual a la hora de la factura ($hora2)
            if ($hora1->greaterThanOrEqualTo($hora2)) {
                array_push($array_facturas,$factura);
            }
        }  

        // Lógica para timbrar las facturas y actualizar date_last_run y log_run
        foreach ($array_facturas as $factura) {
            
            $factura->date_last_run = $fecha_actual;
            $factura->save();

            $this->timbrarFacturaRecurrente($factura->id);
        }

        return response()->json([
            'message'=>'Timbres generados',
            'dia_mes' => $dia_mes,
            'dia_semana' => $dia_semana,
            'hora' => $hora,
            'minutos' => $minutos,
            'fecha_actual' => $fecha_actual,
            // 'facturas_recurrentes' => $facturas_recurrentes,
            'array_facturas' => $array_facturas
        ], 200);
    }

    public function timbrarFacturaRecurrente($recurrente_id)
    {

        $recurrente=CfdiRecurrente::find($recurrente_id);

        if (!$recurrente)
        {
            $recurrente->log_run = 'Registro recurrente no encontrado.';
            $recurrente->save();

            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Registro no encontrado.'], 404);
        } 

        $factura = CfdiComprobante::
            with(['receptor' => function ($query){
                $query->with('mi_regimen_fiscal')
                    ->with('mi_uso_cfdi');
            }])
            ->with(['conceptos' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->find($recurrente->factura_id);

        if(!$factura){

            $recurrente->log_run = 'Factura base no encontrada.';
            $recurrente->save();

            return response()->json(['error'=>'Factura base no encontrada.'],404);
        }

        // Comprobamos si la empresa que nos están pasando existe o no.
        $empresa=CfdiEmpresa::find($factura->emisor_id);
        if (!$empresa)
        {
            $recurrente->log_run = 'Emisor no encontrado.';
            $recurrente->save();

            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Emisor no encontrado.'], 404);
        }

        $cliente=User::whereNull('flag_eliminado')
            ->find($empresa->user_id);
        if (!$cliente)
        {
            $recurrente->log_run = 'Cliente no encontrado.';
            $recurrente->save();

            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Cliente no encontrado.'], 404);
        }

        if ($cliente->status != 1)
        {
            $recurrente->log_run = 'Emisor inhabilitado para generar timbre electrónico.';
            $recurrente->save();

            return response()->json(['error'=>'Emisor inhabilitado para generar timbre electrónico.'], 409);
        }
        

        $limite_facturacion = $this->determinarLimiteFacturacion($empresa->Rfc,$empresa->RegimenFiscal);
        if($limite_facturacion != null && $limite_facturacion != 0){

            $total_facturado = $this->getTotalFacturado($empresa->user_id);

            if($total_facturado >= $limite_facturacion){

                $recurrente->log_run = 'Ya alcanzaste el límite de $'.$limite_facturacion.' pesos mensuales facturables.';
                $recurrente->save();

                return response()->json(['error'=>'Ya alcanzaste el límite de $'.$limite_facturacion.' pesos mensuales facturables.'], 409);
            }else if(($total_facturado+$request->input('Total')) >= $limite_facturacion){

                $recurrente->log_run = 'El total de la factura excede el límite de $'.$limite_facturacion.' pesos mensuales facturables.';
                $recurrente->save();

                return response()->json(['error'=>'El total de la factura excede el límite de $'.$limite_facturacion.' pesos mensuales facturables.'], 409);
            }

        }

        if ($cliente->count_timbres < 1)
        {
            $recurrente->log_run = 'No cuentas con timbres disponibles. Te recomendamos adquirir un paquete para continuar disfrutando de nuestros servicios de timbrado.';
            $recurrente->save();

            return response()->json(['error'=>'No cuentas con timbres disponibles. Te recomendamos adquirir un paquete para continuar disfrutando de nuestros servicios de timbrado.'], 409);
        }

        $conceptos = $factura->conceptos;
        if (count($conceptos) == 0) {

            $recurrente->log_run = 'Factura base sin conceptos.';
            $recurrente->save();

            // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Factura base sin conceptos.'], 409);
        }

        //verificar stock
        for ($i=0; $i < count($conceptos); $i++) { 
            if ($conceptos[$i]->producto_id) {
                
                $producto = Producto::where('id', $conceptos[$i]->producto_id)
                    ->whereNull('flag_eliminado')
                    ->first();

                if (!$producto)
                {
                    $recurrente->log_run = 'No existe el producto '.$conceptos[$i]->Descripcion;
                    $recurrente->save();

                    return response()->json([
                        'error'=>'No existe el producto '.$conceptos[$i]->Descripcion
                    ], 409);
                }

                if ($producto->stock == 0) {

                    $recurrente->log_run = 'No hay unidades disponibles del producto '.$conceptos[$i]->Descripcion;
                    $recurrente->save();

                    return response()->json([
                        'error'=>'No hay unidades disponibles del producto '.$conceptos[$i]->Descripcion
                    ], 409);
                }
                
                if ($producto->stock < $conceptos[$i]->Cantidad) {

                    $recurrente->log_run = 'Solo hay '.$producto->stock.' unidades disponibles del producto '.$conceptos[$i]->Descripcion;
                    $recurrente->save();

                    return response()->json([
                        'error'=>'Solo hay '.$producto->stock.' unidades disponibles del producto '.$conceptos[$i]->Descripcion
                    ], 409);
                }

            }
        }

        $pedidoCurso = CfdiComprobante::
            where('emisor_id',$empresa->id)
            ->where('status', 0)
            ->with('receptor')
            //->with('conceptos')
            ->with(['conceptos' => function ($query){
                $query->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad');
            }])
            ->with('impuesto')
            ->with('timbre_fiscal_digital')
            ->with('archivo')
            ->with('mi_forma_pago')
            ->with('mi_metodo_pago')
            ->first();   

        //elimino cotizacion curso desde el panel en caso de que tenga
        if($pedidoCurso){
            for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                $pedidoCurso->conceptos[$i]->delete();
            }
            $pedidoCurso->receptor->delete();
            $pedidoCurso->delete();
        } 

        //Iniciar proceso de facturacion
        $Folio = (CfdiComprobante::count())+1;

        $Serie = (CfdiComprobante::
            where('emisor_id',$empresa->id)
            ->count())+1;

        //crear un pedido nuevo en curso
        $pedidoCurso=CfdiComprobante::create([
            'emisor_id'=>$empresa->id,
            'receptor_id'=>null,
            'status'=>0,
            'flag_cancelada'=>null,
            'Serie'=>"S-".$empresa->id."-".$Serie,
            'Folio'=>"F-".$empresa->id."-".$Folio,
            'Fecha'=>date('Y-m-d\TH:i:s', time() - (60*60)),
            'Sello'=>"",
            'FormaPago'=>$factura->FormaPago,
            'NoCertificado'=>"",
            'Certificado'=>"",
            'CondicionesDePago'=>"",
            'Subtotal'=>$factura->Subtotal,
            'Descuento'=>$factura->Descuento,
            'Moneda'=>"MXN",
            'TipoCambio'=>"",
            'Total'=>$factura->Total,
            'TipoDeComprobante'=>"I",
            'Exportacion'=>"01",
            'MetodoPago'=>$factura->MetodoPago,
            'LugarExpedicion'=>$empresa->CP,
            'Confirmacion'=>"",
            //'estado'=>null,
            //'function'=>null,
            'TasaIva'=>$factura->TasaIva,
            'TasaIsr'=>$factura->TasaIsr,
            'Tipo'=>$factura->Tipo,
        ]);

        //crear el receptor
        $newObjReceptor=CfdiReceptor::create([
            'comprobante_id'=>$pedidoCurso->id,
            'Rfc'=>$factura->receptor->Rfc,
            'Nombre'=>$factura->receptor->Nombre,
            'DomicilioFiscalReceptor'=>$factura->receptor->DomicilioFiscalReceptor,
            'ResidenciaFiscal'=>null,
            'NumRegIdTrib'=>null,
            'RegimenFiscalReceptor'=>$factura->receptor->RegimenFiscalReceptor,
            'UsoCFDI'=>$factura->receptor->UsoCFDI,
            'Email'=>$factura->receptor->Email,
        ]);

        //Crear los conceptos
        for ($i=0; $i < count($conceptos); $i++) { 
            //agregar nuevo concepto
            $nuevoConcepto=CfdiConcepto::create([
                'comprobante_id' => $pedidoCurso->id,
                'ClaveProdServ' => $conceptos[$i]->ClaveProdServ,
                'NoIdentificacion' => "",
                'Cantidad' => $conceptos[$i]->Cantidad,
                'ClaveUnidad' => $conceptos[$i]->ClaveUnidad,
                'Unidad' => $conceptos[$i]->Unidad,
                'Descripcion' => $conceptos[$i]->Descripcion,
                'ValorUnitario' => $conceptos[$i]->ValorUnitario,
                'Importe' => $conceptos[$i]->Importe,
                'Descuento' => $conceptos[$i]->Descuento,
                'ObjetoImp' => $conceptos[$i]->ObjetoImp,
                'ObjetoImpRet' => $conceptos[$i]->ObjetoImpRet,
                'producto_id' => $conceptos[$i]->producto_id,
            ]);
        }

        $resTimbrado = $this->timbrarProduccion($pedidoCurso->id);

        if($resTimbrado != 1){

            $pedidoCurso = CfdiComprobante::
                where('emisor_id',$empresa->id)
                ->where('status', 0)
                ->with('receptor')
                //->with('conceptos')
                ->with(['conceptos' => function ($query){
                    $query->with('mi_clave_prod_serv')
                        ->with('mi_clave_unidad');
                }])
                ->with('impuesto')
                ->with('timbre_fiscal_digital')
                ->with('archivo')
                ->with('mi_forma_pago')
                ->with('mi_metodo_pago')
                ->first();   

            //elimino cotizacion curso desde el panel en caso de que tenga
            if($pedidoCurso){
                for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                    $pedidoCurso->conceptos[$i]->delete();
                }
                $pedidoCurso->receptor->delete();
                $pedidoCurso->delete();
            }

            $message = $resTimbrado;

            $recurrente->log_run = $resTimbrado;
            $recurrente->save();

            // Devolvemos un código 409 Conflict. 
            return response()->json([
                'error'=>$message
            ], 409);

        }else{

            //Timbrada exitosamente
            $pedidoCurso->status = 1;
            $pedidoCurso->save();

            $count_facturas = $cliente->count_facturas + 1;
            DB::table('users')
            ->where('id', $cliente->id)
            ->update([
                'count_facturas' => $count_facturas,
            ]);

            //descontar contador de timbres disponibles
            $count_timbres = $cliente->count_timbres - 1;
            DB::table('users')
            ->where('id', $cliente->id)
            ->update([
                'count_timbres' => $count_timbres,
            ]);

            $document = $this->facturaPdf($pedidoCurso->id);

            DB::table('cfdi_archivos')
                ->where('comprobante_id', $pedidoCurso->id)
                ->update([
                    'pdf' => $document,
                ]);

            //Descontar inventario
            for ($i=0; $i < count($conceptos); $i++) { 
                if ($conceptos[$i]->producto_id) {
                    
                    $producto = Producto::where('id', $conceptos[$i]->producto_id)
                        ->whereNull('flag_eliminado')
                        ->first();

                    if ($producto)
                    {
                        $stock = $producto->stock - $conceptos[$i]->Cantidad;
                        $producto->stock = $stock;
                        $producto->save();
                    }

                }
            }


            try {
                $this->emailFactura($pedidoCurso->id); 
            } catch (Exception $e) {
                
            }

            $array_registros = json_decode($recurrente->registros, true);
            array_push($array_registros,$pedidoCurso->id);

            $recurrente->registros = json_encode($array_registros);

            $recurrente->log_run = 'Factura timbrada exitosamente.';
            $recurrente->save();

            return response()->json([
                'message'=>'Factura timbrada exitosamente.',
                'factura_id'=>$pedidoCurso->id,
            ], 200); 
        }
  
    }

    
}
