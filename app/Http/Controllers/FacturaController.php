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
use App\Models\CfdiEmisor;

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

use App\Models\PosOrder;
use App\Models\PosOrderDetail;

//use Hash;
use DB;
//use Validator;

use Carbon\Carbon;

use Mail;
use Session;
use Redirect;
use Swift_SmtpTransport;
use Swift_Mailer;

use App\Services\InventoryService;

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
        $facturas = CfdiComprobante::select('id','user_id','emisor_id','status','Serie','Folio','Fecha','Total','status_pay')
            ->where('user_id',$cliente_id)
            ->where('status', 1)
            ->where('Fecha', 'like', '%'.$fecha.'%')
            ->with(['receptor' => function ($query){
                $query->select('id','comprobante_id','Rfc','Nombre');
            }])
            ->with(['archivo' => function ($query){
                $query->select('id','comprobante_id','xml_archivo','pdf');
            }])
            ->with(['emisor' => function ($query){
                $query->select('id','comprobante_id','Rfc','RazonSocial');
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
        $facturas = CfdiComprobante::select('id','user_id','emisor_id','status','Serie','Folio','Fecha','Total')
            ->where('user_id',$cliente_id)
            ->where('status', 2)
            ->where('Fecha', 'like', '%'.$fecha.'%')
            ->with(['receptor' => function ($query){
                $query->select('id','comprobante_id','Rfc','Nombre');
            }])
            ->with(['archivo' => function ($query){
                $query->select('id','comprobante_id','xml_archivo','pdf');
            }])
            ->with(['emisor' => function ($query){
                $query->select('id','comprobante_id','Rfc','RazonSocial');
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
            ->with(['emisor' => function ($query){
                $query->with('mi_regimen_fiscal');
            }])
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

                $Importe = round($Base * 0.16, 4);
                $TotalImpuestosTrasladados += $Importe;

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

                    $retencionIva = round($Base * ($factura->TasaIva / 100), 4);
                    $resul = (object) [
                        'Impuesto' => "IVA",
                        'Tipo' => "Retención",
                        'Base' => $Base,
                        'TipoFactor' => "Tasa",
                        'TasaOCuota' => $factura->TasaIva."%",
                        'Importe' => $retencionIva
                    ];
                    array_push($Impuestos,$resul);

                    $retencionIsr = round($Base * ($factura->TasaIsr / 100), 4);
                    $resul = (object) [
                        'Impuesto' => "ISR",
                        'Tipo' => "Retención",
                        'Base' => $Base,
                        'TipoFactor' => "Tasa",
                        'TasaOCuota' => $factura->TasaIsr."%",
                        'Importe' => $retencionIsr
                    ];
                    array_push($Impuestos,$resul);

                    $TotalImpuestosRetenidosIva += $retencionIva;
                    $TotalImpuestosRetenidosIsr += $retencionIsr;

                }

                $factura->conceptos[$i]->Impuestos = $Impuestos;

            }
        }

        $TotalImpuestosRetenidos = round($TotalImpuestosRetenidosIva, 2) + round($TotalImpuestosRetenidosIsr, 2);
        $factura->TotalImpuestosTrasladados = round($TotalImpuestosTrasladados, 2);
        $factura->TotalImpuestosRetenidos = round($TotalImpuestosRetenidos, 2);
        $factura->TotalImpuestosRetenidosIva = round($TotalImpuestosRetenidosIva, 2);
        $factura->TotalImpuestosRetenidosIsr = round($TotalImpuestosRetenidosIsr, 2);

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

        if(!$emisor){
            return response()->json(['error'=>'Emisor no encontrado.'],404);
        }

        // Credenciales de Timbrado
        if($emisor->user_id == 6){
            $datos['PAC']['usuario'] = "DEMO700101XXX";
            $datos['PAC']['pass'] = "DEMO700101XXX";
            $datos["produccion"]="NO";
        }else{
            $datos['PAC']['usuario'] = 'AUMA9101171B4';
            $datos['PAC']['pass'] = 'AUMA9101171B41234';
            $datos['produccion'] = 'SI';
        }

        $datos['modulo']="cancelacion2022"; 
        $datos['accion']="cancelar"; 

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

        $objs = CfdiCliente::activos()
            ->where("empresa_id", $empresa_id)
            ->where("Rfc", "like", '%'.$termino.'%')
            ->whereIn("tipo_entidad", ["cliente", "ambos"])
            ->with('mi_regimen_fiscal')
            ->with('mi_uso_cfdi')
            ->get();

        return response()->json(['clientes'=>$objs], 200);
    }

    public function getAllClientes(Request $request)
    {
        $empresa_id = $request->input('empresa_id');

        $objs = CfdiCliente::activos()
            ->where("empresa_id", $empresa_id)
            ->whereIn("tipo_entidad", ["cliente", "ambos"])
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
            ->with('emisor')
            ->first();   

        //elimino cotizacion curso desde el panel en caso de que tenga
        if($pedidoCurso){
            for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                $pedidoCurso->conceptos[$i]->delete();
            }
            if ($pedidoCurso->emisor) {
                $pedidoCurso->emisor->delete();
            }
            if ($pedidoCurso->receptor) {
                $pedidoCurso->receptor->delete();
            }
            $pedidoCurso->delete();
        } 

        //Iniciar proceso de facturacion

        $contador = (CfdiComprobante::
            where('emisor_id',$empresa->id)
            ->count())+1;

        $Folio = strtoupper("F0".$cliente->id."0".$empresa->id."0".$contador);
        $Serie = strtoupper("A0".$cliente->id."0".$empresa->id."0".$contador);

        //crear un pedido nuevo en curso
        $pedidoCurso=CfdiComprobante::create([
            'emisor_id'=>$empresa->id,
            'receptor_id'=>null,
            'status'=>0,
            'flag_cancelada'=>null,
            'Serie'=>$Serie,
            'Folio'=>$Folio,
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
            'TasaIva'=>$request->input('TasaIva'),
            'TasaIsr'=>$request->input('TasaIsr'),
            'Tipo'=>$request->input('Tipo'),
            'user_id'=>$empresa->user_id,
        ]);

        //crear el emisor
        $newObjEmisor=CfdiEmisor::create([
            'comprobante_id'=>$pedidoCurso->id,
            'Rfc'=>$empresa->Rfc,
            'RazonSocial'=>$empresa->RazonSocial,
            'RegimenFiscal'=>$empresa->RegimenFiscal,
            'CP'=>$empresa->CP,
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
                ->with('emisor')
                ->first();   

            //elimino cotizacion curso desde el panel en caso de que tenga
            if($pedidoCurso){
                for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                    $pedidoCurso->conceptos[$i]->delete();
                }
                if ($pedidoCurso->emisor) {
                    $pedidoCurso->emisor->delete();
                }
                if ($pedidoCurso->receptor) {
                    $pedidoCurso->receptor->delete();
                }
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
            $clienteExiste = CfdiCliente::noEliminados()
                ->where('empresa_id',$empresa_id)
                ->where('Rfc', $request->input('Rfc'))
                ->with('mi_regimen_fiscal')
                ->with('mi_uso_cfdi')
                ->first();

            if(!$clienteExiste){
                $newCliente=CfdiCliente::create([
                    'empresa_id'=>$empresa_id,
                    'status'=>true,
                    'Rfc'=>$request->input('Rfc'),
                    'Nombre'=>$request->input('Nombre'),
                    'DomicilioFiscalReceptor'=>$request->input('DomicilioFiscalReceptor'),
                    'ResidenciaFiscal'=>null,
                    'NumRegIdTrib'=>null,
                    'RegimenFiscalReceptor'=>$request->input('RegimenFiscalReceptor'),
                    'UsoCFDI'=>$request->input('UsoCFDI'),
                    'Email'=>$request->input('Email'),
                    'user_id'=>$empresa->user_id,
                    'origen'=>'cfdi',
                ]);
            }else if($clienteExiste){
                $clienteExiste->Nombre = $request->input('Nombre');
                $clienteExiste->DomicilioFiscalReceptor = $request->input('DomicilioFiscalReceptor');
                $clienteExiste->RegimenFiscalReceptor = $request->input('RegimenFiscalReceptor');
                $clienteExiste->UsoCFDI = $request->input('UsoCFDI');
                $clienteExiste->Email = $request->input('Email');
                $clienteExiste->save();
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
            ->with('emisor')
            ->first();   

        //elimino cotizacion curso desde el panel en caso de que tenga
        if($pedidoCurso){
            for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                $pedidoCurso->conceptos[$i]->delete();
            }
            if ($pedidoCurso->emisor) {
                $pedidoCurso->emisor->delete();
            }
            if ($pedidoCurso->receptor) {
                $pedidoCurso->receptor->delete();
            }
            $pedidoCurso->delete();
        } 

        //Iniciar proceso de facturacion

        $contador = (CfdiComprobante::
            where('emisor_id',$empresa->id)
            ->count())+1;

        $Folio = strtoupper("F0".$cliente->id."0".$empresa->id."0".$contador);
        $Serie = strtoupper("A0".$cliente->id."0".$empresa->id."0".$contador);

        //crear un pedido nuevo en curso
        $pedidoCurso=CfdiComprobante::create([
            'emisor_id'=>$empresa->id,
            'receptor_id'=>null,
            'status'=>0,
            'flag_cancelada'=>null,
            'Serie'=>$Serie,
            'Folio'=>$Folio,
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
            'TasaIva'=>$request->input('TasaIva'),
            'TasaIsr'=>$request->input('TasaIsr'),
            'Tipo'=>$request->input('Tipo'),
            'user_id'=>$empresa->user_id,
        ]);

        //crear el emisor
        $newObjEmisor=CfdiEmisor::create([
            'comprobante_id'=>$pedidoCurso->id,
            'Rfc'=>$empresa->Rfc,
            'RazonSocial'=>$empresa->RazonSocial,
            'RegimenFiscal'=>$empresa->RegimenFiscal,
            'CP'=>$empresa->CP,
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
                ->with('emisor')
                ->first();   

            //elimino cotizacion curso desde el panel en caso de que tenga
            if($pedidoCurso){
                for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                    $pedidoCurso->conceptos[$i]->delete();
                }
                if ($pedidoCurso->emisor) {
                    $pedidoCurso->emisor->delete();
                }
                if ($pedidoCurso->receptor) {
                    $pedidoCurso->receptor->delete();
                }
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
            $clienteExiste = CfdiCliente::noEliminados()
                ->where('empresa_id',$empresa_id)
                ->where('Rfc', $request->input('Rfc'))
                ->with('mi_regimen_fiscal')
                ->with('mi_uso_cfdi')
                ->first();

            if(!$clienteExiste){
                $newCliente=CfdiCliente::create([
                    'empresa_id'=>$empresa_id,
                    'status'=>true,
                    'Rfc'=>$request->input('Rfc'),
                    'Nombre'=>$request->input('Nombre'),
                    'DomicilioFiscalReceptor'=>$request->input('DomicilioFiscalReceptor'),
                    'ResidenciaFiscal'=>null,
                    'NumRegIdTrib'=>null,
                    'RegimenFiscalReceptor'=>$request->input('RegimenFiscalReceptor'),
                    'UsoCFDI'=>$request->input('UsoCFDI'),
                    'Email'=>$request->input('Email'),
                    'user_id'=>$empresa->user_id,
                    'origen'=>'cfdi',
                ]);
            }else if($clienteExiste){
                $clienteExiste->Nombre = $request->input('Nombre');
                $clienteExiste->DomicilioFiscalReceptor = $request->input('DomicilioFiscalReceptor');
                $clienteExiste->RegimenFiscalReceptor = $request->input('RegimenFiscalReceptor');
                $clienteExiste->UsoCFDI = $request->input('UsoCFDI');
                $clienteExiste->Email = $request->input('Email');
                $clienteExiste->save();
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
        if($emisor->user_id == 6){
            $datos['PAC']['usuario'] = 'DEMO700101XXX';
            $datos['PAC']['pass'] = 'DEMO700101XXX';
            $datos['PAC']['produccion'] = 'NO';
        }else{
            $datos['PAC']['usuario'] = 'AUMA9101171B4';
            $datos['PAC']['pass'] = 'AUMA9101171B41234';
            $datos['PAC']['produccion'] = 'SI';
        }
        
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
        //$datos['factura']['tipocambio'] = 1;
        $datos['factura']['tipocomprobante'] = 'I';
        ////$datos['factura']['RegimenFiscal'] = '601';
        $datos['factura']['Exportacion'] = '01';


        // Datos del Emisor
        $datos['emisor']['rfc'] = $emisor->Rfc;
        $datos['emisor']['nombre'] = $emisor->RazonSocial;
        $datos['emisor']['RegimenFiscal'] = $emisor->RegimenFiscal;

        // Datos del Receptor
        $datos['receptor']['rfc'] = $factura->receptor->Rfc;
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

        // Inicializar totales
        $TotalImpuestosTrasladados = 0;
        $TotalImpuestosRetenidos = 0;
        $TotalImpuestosRetenidosIva = 0;
        $TotalImpuestosRetenidosIsr = 0;
        $totalDescuentos = 0;
        $subtotal = 0;

        // Se agregan los conceptos
        for ($i=0; $i < count($factura->conceptos); $i++) { 
            $concepto = $factura->conceptos[$i];

            // Usamos 4 decimales como lo indicaste
            $cantidad = round($concepto->Cantidad, 4);
            $valorUnitario = round($concepto->ValorUnitario, 4);
            $importeConcepto = round($cantidad * $valorUnitario, 4);

            //Neta
            if($factura->tipo == 1){
                $importeConcepto = round($importeConcepto / 1.16, 4);
            }

            $descuentoConcepto = round($concepto->Descuento, 4);

            $datos['conceptos'][$i]['cantidad'] = $cantidad;
            $datos['conceptos'][$i]['unidad'] = $concepto->Unidad;
            $datos['conceptos'][$i]['descripcion'] = $concepto->Descripcion;
            $datos['conceptos'][$i]['valorunitario'] = $valorUnitario;
            $datos['conceptos'][$i]['importe'] = $importeConcepto;
            
            if ($descuentoConcepto > 0) {
                $datos['conceptos'][$i]['Descuento'] = $descuentoConcepto;
                $totalDescuentos += $descuentoConcepto;
            }

            $datos['conceptos'][$i]['ClaveProdServ'] = $concepto->mi_clave_prod_serv->id;
            $datos['conceptos'][$i]['ClaveUnidad'] = $concepto->mi_clave_unidad->id;

            // La Base del cálculo del impuesto es el importe del concepto menos su descuento.
            $Base = $importeConcepto - $descuentoConcepto;
            $subtotal += $importeConcepto;

            if ($concepto->ObjetoImp == 1) {
                $datos['conceptos'][$i]['ObjetoImp'] = '02'; // Sí objeto de impuesto

                // Cálculo y redondeo del IVA
                $ImporteIVA = round($Base * 0.16, 4);
                $TotalImpuestosTrasladados += $ImporteIVA;

                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Base'] = round($Base, 4);
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Impuesto'] = '002'; // IVA
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Importe'] = $ImporteIVA;
                
                // Lógica para las retenciones
                if ($concepto->ObjetoImpRet == 1) {
                    $retencionIva = round($Base * ($factura->TasaIva / 100), 4);
                    $retencionIsr = round($Base * ($factura->TasaIsr / 100), 4);

                    $TotalImpuestosRetenidosIva += $retencionIva;
                    $TotalImpuestosRetenidosIsr += $retencionIsr;

                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Base'] = round($Base, 4);
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Impuesto'] = '002';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['TipoFactor'] = 'Tasa';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['TasaOCuota'] = number_format($factura->TasaIva / 100, 6, '.', '');
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Importe'] = $retencionIva;

                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Base'] = round($Base, 4);
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Impuesto'] = '001';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['TipoFactor'] = 'Tasa';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['TasaOCuota'] = number_format($factura->TasaIsr / 100, 6, '.', '');
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Importe'] = $retencionIsr;
                }
            } else {
                $datos['conceptos'][$i]['ObjetoImp'] = '01'; // No objeto de impuesto
            }
            
        }

        // Aquí se asignan los totales de la factura con 2 decimales
        $datos['factura']['subtotal'] = round($subtotal, 2);
        $datos['factura']['descuento'] = round($totalDescuentos, 2);
        $datos['factura']['total'] = round($datos['factura']['subtotal'] - $datos['factura']['descuento'] + $TotalImpuestosTrasladados - ($TotalImpuestosRetenidosIva + $TotalImpuestosRetenidosIsr), 2);
        
        // Impuestos globales para el XML, redondeados a 2 decimales
        if ($TotalImpuestosTrasladados > 0 || $TotalImpuestosRetenidosIva > 0 || $TotalImpuestosRetenidosIsr > 0) {
            $datos['impuestos']['TotalImpuestosTrasladados'] = round($TotalImpuestosTrasladados, 2);
            
            if ($TotalImpuestosRetenidosIva > 0 || $TotalImpuestosRetenidosIsr > 0) {
                // $datos['impuestos']['TotalImpuestosRetenidos'] = round($TotalImpuestosRetenidosIva + $TotalImpuestosRetenidosIsr, 2);
                $datos['impuestos']['TotalImpuestosRetenidos'] = round(round($TotalImpuestosRetenidosIva, 2) + round($TotalImpuestosRetenidosIsr, 2), 2);
            }
        
            // A nivel de Comprobante, el SAT exige que la Base de los traslados
            // sea la suma de las bases de los conceptos con el mismo impuesto y tipo de factor.
            $datos['impuestos']['translados'][0]['Base'] = round($subtotal - $totalDescuentos, 2); // Base total
            $datos['impuestos']['translados'][0]['impuesto'] = '002';
            $datos['impuestos']['translados'][0]['tasa'] = '0.160000';
            $datos['impuestos']['translados'][0]['importe'] = round($TotalImpuestosTrasladados, 2);
            $datos['impuestos']['translados'][0]['TipoFactor'] = 'Tasa';

            if ($TotalImpuestosRetenidosIva > 0) {
                $datos['impuestos']['retenciones'][0]['impuesto'] = '002';
                $datos['impuestos']['retenciones'][0]['importe'] = round($TotalImpuestosRetenidosIva, 2);
            }

            if ($TotalImpuestosRetenidosIsr > 0) {
                $datos['impuestos']['retenciones'][1]['impuesto'] = '001';
                $datos['impuestos']['retenciones'][1]['importe'] = round($TotalImpuestosRetenidosIsr, 2);
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
        if($emisor->user_id == 6){
            $datos['PAC']['usuario'] = 'DEMO700101XXX';
            $datos['PAC']['pass'] = 'DEMO700101XXX';
            $datos['PAC']['produccion'] = 'NO';
        }else{
            $datos['PAC']['usuario'] = 'AUMA9101171B4';
            $datos['PAC']['pass'] = 'AUMA9101171B41234';
            $datos['PAC']['produccion'] = 'SI';
        }
        
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
        //$datos['factura']['tipocambio'] = 1;
        $datos['factura']['tipocomprobante'] = 'I';
        ////$datos['factura']['RegimenFiscal'] = '601';
        $datos['factura']['Exportacion'] = '01';


        // Datos del Emisor
        $datos['emisor']['rfc'] = $emisor->Rfc;
        $datos['emisor']['nombre'] = $emisor->RazonSocial;
        $datos['emisor']['RegimenFiscal'] = $emisor->RegimenFiscal;

        // Datos del Receptor
        $datos['receptor']['rfc'] = $factura->receptor->Rfc;
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

        // Inicializar totales
        $TotalImpuestosTrasladados = 0;
        $TotalImpuestosRetenidos = 0;
        $TotalImpuestosRetenidosIva = 0;
        $TotalImpuestosRetenidosIsr = 0;
        $totalDescuentos = 0;
        $subtotal = 0;

        // Se agregan los conceptos
        for ($i=0; $i < count($factura->conceptos); $i++) { 
            $concepto = $factura->conceptos[$i];

            // Usamos 4 decimales como lo indicaste
            $cantidad = round($concepto->Cantidad, 4);
            $valorUnitario = round($concepto->ValorUnitario, 4);
            $importeConcepto = round($cantidad * $valorUnitario, 4);

            //Neta
            if($factura->tipo == 1){
                $importeConcepto = round($importeConcepto / 1.16, 4);
            }

            $descuentoConcepto = round($concepto->Descuento, 4);

            $datos['conceptos'][$i]['cantidad'] = $cantidad;
            $datos['conceptos'][$i]['unidad'] = $concepto->Unidad;
            $datos['conceptos'][$i]['descripcion'] = $concepto->Descripcion;
            $datos['conceptos'][$i]['valorunitario'] = $valorUnitario;
            $datos['conceptos'][$i]['importe'] = $importeConcepto;
            
            if ($descuentoConcepto > 0) {
                $datos['conceptos'][$i]['Descuento'] = $descuentoConcepto;
                $totalDescuentos += $descuentoConcepto;
            }

            $datos['conceptos'][$i]['ClaveProdServ'] = $concepto->mi_clave_prod_serv->id;
            $datos['conceptos'][$i]['ClaveUnidad'] = $concepto->mi_clave_unidad->id;

            // La Base del cálculo del impuesto es el importe del concepto menos su descuento.
            $Base = $importeConcepto - $descuentoConcepto;
            $subtotal += $importeConcepto;

            if ($concepto->ObjetoImp == 1) {
                $datos['conceptos'][$i]['ObjetoImp'] = '02'; // Sí objeto de impuesto

                // Cálculo y redondeo del IVA
                $ImporteIVA = round($Base * 0.16, 4);
                $TotalImpuestosTrasladados += $ImporteIVA;

                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Base'] = round($Base, 4);
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Impuesto'] = '002'; // IVA
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Importe'] = $ImporteIVA;
                
                // Lógica para las retenciones
                if ($concepto->ObjetoImpRet == 1) {
                    $retencionIva = round($Base * ($factura->TasaIva / 100), 4);
                    $retencionIsr = round($Base * ($factura->TasaIsr / 100), 4);

                    $TotalImpuestosRetenidosIva += $retencionIva;
                    $TotalImpuestosRetenidosIsr += $retencionIsr;

                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Base'] = round($Base, 4);
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Impuesto'] = '002';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['TipoFactor'] = 'Tasa';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['TasaOCuota'] = number_format($factura->TasaIva / 100, 6, '.', '');
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Importe'] = $retencionIva;

                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Base'] = round($Base, 4);
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Impuesto'] = '001';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['TipoFactor'] = 'Tasa';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['TasaOCuota'] = number_format($factura->TasaIsr / 100, 6, '.', '');
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Importe'] = $retencionIsr;
                }
            } else {
                $datos['conceptos'][$i]['ObjetoImp'] = '01'; // No objeto de impuesto
            }
            
        }

        // Aquí se asignan los totales de la factura con 2 decimales
        $datos['factura']['subtotal'] = round($subtotal, 2);
        $datos['factura']['descuento'] = round($totalDescuentos, 2);
        $datos['factura']['total'] = round($datos['factura']['subtotal'] - $datos['factura']['descuento'] + $TotalImpuestosTrasladados - ($TotalImpuestosRetenidosIva + $TotalImpuestosRetenidosIsr), 2);
        
        // Impuestos globales para el XML, redondeados a 2 decimales
        if ($TotalImpuestosTrasladados > 0 || $TotalImpuestosRetenidosIva > 0 || $TotalImpuestosRetenidosIsr > 0) {
            $datos['impuestos']['TotalImpuestosTrasladados'] = round($TotalImpuestosTrasladados, 2);
            
            if ($TotalImpuestosRetenidosIva > 0 || $TotalImpuestosRetenidosIsr > 0) {
                // $datos['impuestos']['TotalImpuestosRetenidos'] = round($TotalImpuestosRetenidosIva + $TotalImpuestosRetenidosIsr, 2);
                $datos['impuestos']['TotalImpuestosRetenidos'] = round(round($TotalImpuestosRetenidosIva, 2) + round($TotalImpuestosRetenidosIsr, 2), 2);
            }
        
            // A nivel de Comprobante, el SAT exige que la Base de los traslados
            // sea la suma de las bases de los conceptos con el mismo impuesto y tipo de factor.
            $datos['impuestos']['translados'][0]['Base'] = round($subtotal - $totalDescuentos, 2); // Base total
            $datos['impuestos']['translados'][0]['impuesto'] = '002';
            $datos['impuestos']['translados'][0]['tasa'] = '0.160000';
            $datos['impuestos']['translados'][0]['importe'] = round($TotalImpuestosTrasladados, 2);
            $datos['impuestos']['translados'][0]['TipoFactor'] = 'Tasa';

            if ($TotalImpuestosRetenidosIva > 0) {
                $datos['impuestos']['retenciones'][0]['impuesto'] = '002';
                $datos['impuestos']['retenciones'][0]['importe'] = round($TotalImpuestosRetenidosIva, 2);
            }

            if ($TotalImpuestosRetenidosIsr > 0) {
                $datos['impuestos']['retenciones'][1]['impuesto'] = '001';
                $datos['impuestos']['retenciones'][1]['importe'] = round($TotalImpuestosRetenidosIsr, 2);
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

                $Importe = round($Base * 0.16, 4);
                $TotalImpuestosTrasladados += $Importe;

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

                    $retencionIva = round($Base * ($factura->TasaIva / 100), 4);
                    $resul = (object) [
                        'Impuesto' => "IVA",
                        'Tipo' => "Retención",
                        'Base' => $Base,
                        'TipoFactor' => "Tasa",
                        'TasaOCuota' => $factura->TasaIva."%",
                        'Importe' => $retencionIva
                    ];
                    array_push($Impuestos,$resul);

                    $retencionIsr = round($Base * ($factura->TasaIsr / 100), 4);
                    $resul = (object) [
                        'Impuesto' => "ISR",
                        'Tipo' => "Retención",
                        'Base' => $Base,
                        'TipoFactor' => "Tasa",
                        'TasaOCuota' => $factura->TasaIsr."%",
                        'Importe' => $retencionIsr
                    ];
                    array_push($Impuestos,$resul);

                    $TotalImpuestosRetenidosIva += $retencionIva;
                    $TotalImpuestosRetenidosIsr += $retencionIsr;

                }

                $factura->conceptos[$i]->Impuestos = $Impuestos;

            }
        }

        $TotalImpuestosRetenidos = round($TotalImpuestosRetenidosIva, 2) + round($TotalImpuestosRetenidosIsr, 2);
        $factura->TotalImpuestosTrasladados = round($TotalImpuestosTrasladados, 2);
        $factura->TotalImpuestosRetenidos = round($TotalImpuestosRetenidos, 2);
        $factura->TotalImpuestosRetenidosIva = round($TotalImpuestosRetenidosIva, 2);
        $factura->TotalImpuestosRetenidosIsr = round($TotalImpuestosRetenidosIsr, 2);

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
            ->where('id', $user_id)
            ->first();

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado'], 404);
        }

        $cfdi_empresa=CfdiEmpresa::
            where('user_id', $user_id)
            ->where('emisor_ingresos', true)
            ->first();

        if (!$cfdi_empresa)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Emisor CFDI no encontrado'], 404);
        }

        $producto = CfdiProducto::
            where('user_id', $user_id)
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

        return $this->timbrarFacturaAutomatica($cfdi_empresa->id,$total_ingresos_contables);
        
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
            where('user_id',$cliente->id)
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
            ->with('emisor')
            ->first();   

        //elimino cotizacion curso desde el panel en caso de que tenga
        if($pedidoCurso){
            for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                $pedidoCurso->conceptos[$i]->delete();
            }
            if ($pedidoCurso->emisor) {
                $pedidoCurso->emisor->delete();
            }
            if ($pedidoCurso->receptor) {
                $pedidoCurso->receptor->delete();
            }
            $pedidoCurso->delete();
        } 

        //Iniciar proceso de facturacion

        $contador = (CfdiComprobante::
            where('emisor_id',$empresa->id)
            ->count())+1;

        $Folio = strtoupper("F0".$cliente->id."0".$empresa->id."0".$contador);
        $Serie = strtoupper("A0".$cliente->id."0".$empresa->id."0".$contador);

        //quitar el iva al subtotal
        $Subtotal = $total_ingresos_contables/1.16;
        $Subtotal = round($Subtotal, 2);

        //recalcular el total con el iva
        $Total = $Subtotal*1.16;
        $Total = round($Total, 2);


        //crear un pedido nuevo en curso
        $pedidoCurso=CfdiComprobante::create([
            'emisor_id'=>$empresa->id,
            'receptor_id'=>null,
            'status'=>0,
            'flag_cancelada'=>null,
            'Serie'=>$Serie,
            'Folio'=>$Folio,
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
            'TasaIva'=>0,
            'TasaIsr'=>0,
            'Tipo'=>2,
            'user_id'=>$empresa->user_id,
        ]);

        //crear el emisor
        $newObjEmisor=CfdiEmisor::create([
            'comprobante_id'=>$pedidoCurso->id,
            'Rfc'=>$empresa->Rfc,
            'RazonSocial'=>$empresa->RazonSocial,
            'RegimenFiscal'=>$empresa->RegimenFiscal,
            'CP'=>$empresa->CP,
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
                ->with('emisor')
                ->first();   

            //elimino cotizacion curso desde el panel en caso de que tenga
            if($pedidoCurso){
                for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                    $pedidoCurso->conceptos[$i]->delete();
                }
                if ($pedidoCurso->emisor) {
                    $pedidoCurso->emisor->delete();
                }
                if ($pedidoCurso->receptor) {
                    $pedidoCurso->receptor->delete();
                }
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
            //->where('emisor_id',$usuario->cfdi_empresa->id)
            ->where('user_id',$user_id)
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
            ->with('emisor')
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
            ->with('emisor')
            ->first();   

        //elimino cotizacion curso desde el panel en caso de que tenga
        if($pedidoCurso){
            for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                $pedidoCurso->conceptos[$i]->delete();
            }
            if ($pedidoCurso->emisor) {
                $pedidoCurso->emisor->delete();
            }
            if ($pedidoCurso->receptor) {
                $pedidoCurso->receptor->delete();
            }
            $pedidoCurso->delete();
        } 

        //Iniciar proceso de facturacion
        // $Folio = (CfdiComprobante::count())+1;

        // $Serie = (CfdiComprobante::
        //     where('emisor_id',$empresa->id)
        //     ->count())+1;

        $contador = (CfdiComprobante::
            where('emisor_id',$empresa->id)
            ->count())+1;

        $Folio = strtoupper("F0".$cliente->id."0".$empresa->id."0".$contador);
        $Serie = strtoupper("A0".$cliente->id."0".$empresa->id."0".$contador);

        //crear un pedido nuevo en curso
        $pedidoCurso=CfdiComprobante::create([
            'emisor_id'=>$empresa->id,
            'receptor_id'=>null,
            'status'=>0,
            'flag_cancelada'=>null,
            'Serie'=>$Serie,
            'Folio'=>$Folio,
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
            'TasaIva'=>$factura->TasaIva,
            'TasaIsr'=>$factura->TasaIsr,
            'Tipo'=>$factura->Tipo,
            'user_id'=>$empresa->user_id,
        ]);

        //crear el emisor
        $newObjEmisor=CfdiEmisor::create([
            'comprobante_id'=>$pedidoCurso->id,
            'Rfc'=>$empresa->Rfc,
            'RazonSocial'=>$empresa->RazonSocial,
            'RegimenFiscal'=>$empresa->RegimenFiscal,
            'CP'=>$empresa->CP,
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
                ->with('emisor')
                ->first();   

            //elimino cotizacion curso desde el panel en caso de que tenga
            if($pedidoCurso){
                for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                    $pedidoCurso->conceptos[$i]->delete();
                }
                if ($pedidoCurso->emisor) {
                    $pedidoCurso->emisor->delete();
                }
                if ($pedidoCurso->receptor) {
                    $pedidoCurso->receptor->delete();
                }
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

    public function timbrarFacturaDePrueba1($user_id)
    {

        $emisor = CfdiEmpresa::
            with('mi_regimen_fiscal')
            ->where('user_id', $user_id)
            ->first();

        if(!$emisor){
            return response()->json(['error'=>'Emisor no encontrado.'],404);
        }

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
        $datos['factura']['subtotal'] = 12931.03;
        //$datos['factura']['tipocambio'] = 1;
        $datos['factura']['tipocomprobante'] = 'I';
        $datos['factura']['total'] = 15000.00;
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
        $datos['conceptos'][0]['cantidad'] = 1.0000;
        $datos['conceptos'][0]['unidad'] = 'Pieza';
        $datos['conceptos'][0]['ID'] = "1726";
        $datos['conceptos'][0]['descripcion'] = "Cigarros";
        $datos['conceptos'][0]['valorunitario'] = 12931.0345;
        $datos['conceptos'][0]['importe'] = 12931.0345;
        $datos['conceptos'][0]['ClaveProdServ'] = '50211503';
        $datos['conceptos'][0]['ClaveUnidad'] = 'H87';
        $datos['conceptos'][0]['ObjetoImp'] = '02';

        $datos['conceptos'][0]['Impuestos']['Traslados'][0]['Base'] = 12931.0345;
        $datos['conceptos'][0]['Impuestos']['Traslados'][0]['Impuesto'] = '002';
        $datos['conceptos'][0]['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa';
        $datos['conceptos'][0]['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000';
        $datos['conceptos'][0]['Impuestos']['Traslados'][0]['Importe'] = 2068.9655;

        // $datos['conceptos'][1]['cantidad'] = 1.00;
        // $datos['conceptos'][1]['unidad'] = 'NA';
        // $datos['conceptos'][1]['ID'] = "1586";
        // $datos['conceptos'][1]['descripcion'] = "PRODUCTO DE PRUEBA 2";
        // $datos['conceptos'][1]['valorunitario'] = 199.00;
        // $datos['conceptos'][1]['importe'] = 199.00;
        // $datos['conceptos'][1]['ClaveProdServ'] = '01010101';
        // $datos['conceptos'][1]['ClaveUnidad'] = 'ACT';
        // $datos['conceptos'][1]['ObjetoImp'] = '02';

        // $datos['conceptos'][1]['Impuestos']['Traslados'][0]['Base'] = 199.00;
        // $datos['conceptos'][1]['Impuestos']['Traslados'][0]['Impuesto'] = '002';
        // $datos['conceptos'][1]['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa';
        // $datos['conceptos'][1]['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000';
        // $datos['conceptos'][1]['Impuestos']['Traslados'][0]['Importe'] = 31.84;

        $datos['impuestos']['TotalImpuestosTrasladados'] = 2068.97;

        // Se agregan los Impuestos
        $datos['impuestos']['translados'][0]['Base'] = 12931.03;
        $datos['impuestos']['translados'][0]['impuesto'] = '002';
        $datos['impuestos']['translados'][0]['tasa'] = '0.160000';
        $datos['impuestos']['translados'][0]['importe'] = 2068.97;
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
        
        return $res;
        

    }

    public function timbrarFacturaDePrueba($user_id)
    {
        $json_string = '{
            "TasaIva": 16,
            "TasaIsr": 1.25,
            "conceptos": [
                {
                    "Unidad": "Pieza",
                    "ClaveProdServ": "50211503",
                    "ClaveUnidad": "H87",
                    "Descripcion": "Producto de prueba 1",
                    "Cantidad": 1,
                    "ValorUnitario": 285.00,
                    "Descuento": 0,
                    "ObjetoImp": 1,
                    "ObjetoImpRet":0
                }
            ]
        }';

        // Decodificar el JSON en un objeto PHP
        $factura = json_decode($json_string);

        $emisor = CfdiEmpresa::
            with('mi_regimen_fiscal')
            ->where('user_id', $user_id)
            ->first();

        if(!$emisor){
            return response()->json(['error'=>'Emisor no encontrado.'],404);
        }

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

        // La cadena cifrada
        $cadenaEncriptada = $emisor['pass'];
        $claveAdicional = config('app.lada_d');
        $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);

        $datos['conf']['pass'] = $cadenaDesencriptada;

        // Datos de la Factura
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
        //$datos['factura']['tipocambio'] = 1;
        $datos['factura']['tipocomprobante'] = 'I';
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

        // Inicializar totales
        $TotalImpuestosTrasladados = 0;
        $TotalImpuestosRetenidos = 0;
        $TotalImpuestosRetenidosIva = 0;
        $TotalImpuestosRetenidosIsr = 0;
        $totalDescuentos = 0;
        $subtotal = 0;

        // Se agregan los conceptos
        for ($i=0; $i < count($factura->conceptos); $i++) { 
            $concepto = $factura->conceptos[$i];

            // Usamos 4 decimales como lo indicaste
            $valorUnitario = round($concepto->ValorUnitario, 4);
            $importeConcepto = round($concepto->Cantidad * $valorUnitario, 4);
            $importeConcepto = round($importeConcepto / 1.16, 4);
            $descuentoConcepto = round($concepto->Descuento, 4);

            $datos['conceptos'][$i]['cantidad'] = $concepto->Cantidad;
            $datos['conceptos'][$i]['unidad'] = $concepto->Unidad;
            $datos['conceptos'][$i]['descripcion'] = $concepto->Descripcion;
            $datos['conceptos'][$i]['valorunitario'] = $valorUnitario;
            $datos['conceptos'][$i]['importe'] = $importeConcepto;
            
            if ($descuentoConcepto > 0) {
                $datos['conceptos'][$i]['Descuento'] = $descuentoConcepto;
                $totalDescuentos += $descuentoConcepto;
            }

            $datos['conceptos'][$i]['ClaveProdServ'] = $concepto->ClaveProdServ;
            $datos['conceptos'][$i]['ClaveUnidad'] = $concepto->ClaveUnidad;

            // La Base del cálculo del impuesto es el importe del concepto menos su descuento.
            $Base = $importeConcepto - $descuentoConcepto;
            $subtotal += $importeConcepto;

            if ($concepto->ObjetoImp == 1) {
                $datos['conceptos'][$i]['ObjetoImp'] = '02'; // Sí objeto de impuesto

                // Cálculo y redondeo del IVA
                $ImporteIVA = round($Base * 0.16, 4);
                $TotalImpuestosTrasladados += $ImporteIVA;

                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Base'] = round($Base, 4);
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Impuesto'] = '002'; // IVA
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Importe'] = $ImporteIVA;
                
                // Lógica para las retenciones
                if ($concepto->ObjetoImpRet == 1) {
                    $retencionIva = round($Base * ($factura->TasaIva / 100), 4);
                    $retencionIsr = round($Base * ($factura->TasaIsr / 100), 4);

                    $TotalImpuestosRetenidosIva += $retencionIva;
                    $TotalImpuestosRetenidosIsr += $retencionIsr;

                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Base'] = round($Base, 4);
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Impuesto'] = '002';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['TipoFactor'] = 'Tasa';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['TasaOCuota'] = number_format($factura->TasaIva / 100, 6, '.', '');
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Importe'] = $retencionIva;

                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Base'] = round($Base, 4);
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Impuesto'] = '001';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['TipoFactor'] = 'Tasa';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['TasaOCuota'] = number_format($factura->TasaIsr / 100, 6, '.', '');
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Importe'] = $retencionIsr;
                }
            } else {
                $datos['conceptos'][$i]['ObjetoImp'] = '01'; // No objeto de impuesto
            }
            
        }

        // Aquí se asignan los totales de la factura con 2 decimales
        $datos['factura']['subtotal'] = round($subtotal, 2);
        $datos['factura']['descuento'] = round($totalDescuentos, 2);
        $datos['factura']['total'] = round($datos['factura']['subtotal'] - $datos['factura']['descuento'] + $TotalImpuestosTrasladados - ($TotalImpuestosRetenidosIva + $TotalImpuestosRetenidosIsr), 2);
        
        // Impuestos globales para el XML, redondeados a 2 decimales
        if ($TotalImpuestosTrasladados > 0 || $TotalImpuestosRetenidosIva > 0 || $TotalImpuestosRetenidosIsr > 0) {
            $datos['impuestos']['TotalImpuestosTrasladados'] = round($TotalImpuestosTrasladados, 2);
            
            if ($TotalImpuestosRetenidosIva > 0 || $TotalImpuestosRetenidosIsr > 0) {
                // $datos['impuestos']['TotalImpuestosRetenidos'] = round($TotalImpuestosRetenidosIva + $TotalImpuestosRetenidosIsr, 2);
                $datos['impuestos']['TotalImpuestosRetenidos'] = round(round($TotalImpuestosRetenidosIva, 2) + round($TotalImpuestosRetenidosIsr, 2), 2);
            }
        
            // A nivel de Comprobante, el SAT exige que la Base de los traslados
            // sea la suma de las bases de los conceptos con el mismo impuesto y tipo de factor.
            $datos['impuestos']['translados'][0]['Base'] = round($subtotal - $totalDescuentos, 2); // Base total
            $datos['impuestos']['translados'][0]['impuesto'] = '002';
            $datos['impuestos']['translados'][0]['tasa'] = '0.160000';
            $datos['impuestos']['translados'][0]['importe'] = round($TotalImpuestosTrasladados, 2);
            $datos['impuestos']['translados'][0]['TipoFactor'] = 'Tasa';

            if ($TotalImpuestosRetenidosIva > 0) {
                $datos['impuestos']['retenciones'][0]['impuesto'] = '002';
                $datos['impuestos']['retenciones'][0]['importe'] = round($TotalImpuestosRetenidosIva, 2);
            }

            if ($TotalImpuestosRetenidosIsr > 0) {
                $datos['impuestos']['retenciones'][1]['impuesto'] = '001';
                $datos['impuestos']['retenciones'][1]['importe'] = round($TotalImpuestosRetenidosIsr, 2);
            }
        }

        echo "<pre>";
        print_r($datos);
        echo "</pre>";

        //echo "<pre>"; echo arr2cs($datos); echo "</pre>".die();
        // Se ejecuta el SDK
        $res = mf_genera_cfdi4($datos);

        ///////////    MOSTRAR RESULTADOS DEL ARRAY $res   ///////////

        //dd($res);
        
        echo "<h1>Respuesta Generar XML y Timbrado</h1>";
        foreach ($res AS $variable => $valor) {
            $valor = htmlentities($valor);
            $valor = str_replace('&lt;br/&gt;', '<br/>', $valor);
            echo "<b>[$variable]=</b>$valor<hr>";
        }
        

    }

    /*Timbra una venta generada desde el POS*/
    public function timbrarVenta(Request $request, $venta_id)
    {
        $orden = PosOrder::with([
            'detalles.producto',
            'pagos.user',
            'user',
            'caja',
            'contacto',
            'comprobante'
        ])->find($venta_id);

        if (!$orden) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada.'
            ], 404);
        }

        if($orden->status !== 'pagada'){
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden timbrar órdenes pagadas.'
            ], 400);
        }

        if($orden->facturada || $orden->comprobante_id){
            return response()->json([
                'success' => false,
                'message' => 'La orden ya está timbrada.'
            ], 400);
        }

        $cliente = User::whereNull('flag_eliminado')
            ->where('id', $orden->user_id)
            ->first();

        if (!$cliente)
        {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.'
            ], 404);
        }

        if ($cliente->status != 1)
        {
            return response()->json([
                'success' => false,
                'message' => 'Emisor inhabilitado para generar timbre electrónico.'
            ], 400);
        }

        $cfdi_empresa = CfdiEmpresa::
            where('user_id', $cliente->id)
            ->where('emisor_pos', true)
            ->first();

        if (!$cfdi_empresa)
        {
            return response()->json([
                'success' => false,
                'message' => 'Para crear una factura, primero debes configurar tus datos de emisor y activar la función POS al emisor.'
            ], 404);
        }

        $empresa = $cfdi_empresa;

        // --- Validar datos de Emisor ---
        $camposRequeridosEmisor = [
            'Rfc', 'RazonSocial', 'RegimenFiscal',
            'CP', 'cer', 'key', 'pass'
        ];

        foreach ($camposRequeridosEmisor as $campo) {
            if (empty($empresa->$campo)) {
                $message = 'Para crear una factura, primero debes configurar tus datos de emisor.';

                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 409);
            }
        }

        $limite_facturacion = $this->determinarLimiteFacturacion($empresa->Rfc,$empresa->RegimenFiscal);
        if($limite_facturacion != null && $limite_facturacion != 0){

            $total_facturado = $this->getTotalFacturado($empresa->user_id);

            if($total_facturado >= $limite_facturacion){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya alcanzaste el límite de $'.$limite_facturacion.' pesos mensuales facturables.'
                ], 409);
            }else if(($total_facturado+$orden->total) >= $limite_facturacion){
                return response()->json([
                    'success' => false,
                    'message'=>'El total de la factura excede el límite de $'.$limite_facturacion.' pesos mensuales facturables.'
                ], 409);
            }

        }

        if ($cliente->count_timbres < 1) {
            return response()->json([
                'success' => false,
                'message'=>'No cuentas con timbres disponibles. Te recomendamos adquirir un paquete de timbres para continuar disfrutando de nuestros servicios de timbrado.'
            ], 409);
        }

        // =============================
        // FACTURA A PUBLICO EN GENERAL
        // =============================

        if(
            strtoupper(str_replace([' ', '-'], '', $orden->contacto->Rfc)) == 'XAXX010101000' ||
            strtoupper($orden->contacto->Nombre) == 'PUBLICO EN GENERAL' ||
            strtoupper($orden->contacto->Nombre) == 'PÚBLICO EN GENERAL' 
        ){
            $orden->contacto->Rfc = 'XAXX010101000';
            $orden->contacto->Nombre = 'PUBLICO EN GENERAL';
            $orden->contacto->UsoCFDI = 24; //'Sin efectos fiscales.'
            $orden->contacto->RegimenFiscalReceptor = 616; //Sin obligaciones fiscales
            $orden->retenciones == 'Sin retenciones';
            $MetodoPago = 2; //Pago en una sola exhibición
        }

        // =============================
        // DATOS DEL COMPROBANTE
        // =============================

        // --- Forma de pago ---

        $FormaPago = $request->input('FormaPago');
        if($FormaPago >= 1 && $FormaPago <= 8){
            $FormaPago = '0'.$FormaPago;
        }

        $forma_pago = Cfdi40FormaPago::where('id', $FormaPago)
            ->first();

        if (!$forma_pago) {
            return response()->json([
                'success' => false,
                'message' => 'Forma de Pago no disponible en el catálogo. Por favor, intenta ingresar una Forma de Pago diferente.'
            ], 404);
        }

        $comprobante_forma_pago = $forma_pago->id;

        // --- Metodo de pago ---
        $MetodoPago = 2; //Pago en una sola exhibición

        // --- Tipo de factura ---
        $Tipo = 2; //1 = factura neta 2 = factura mas iba

        // --- Retenciones ---
        $TasaIva = 0;
        $TasaIsr = 0;

        if ($orden->retenciones == 'Con retenciones') {
            $TasaIva = 16;
            $TasaIsr = 1.25;
        }

        // =============================
        // DATOS DEL RECEPTOR
        // =============================

        // --- RFC ---
        $receptor_rfc = $orden->contacto->Rfc;

        // Normalizar: eliminar espacios o guiones y convertir a mayúsculas
        $receptor_rfc = strtoupper(str_replace([' ', '-'], '', $receptor_rfc));

        // Validar formato RFC
        $rfcValido = "/^[A-Z0-9]{12,13}$/";

        if (!preg_match($rfcValido, $receptor_rfc)) {
            $message = 'Por favor, verifica el Rfc. En el caso de que sea una persona física, este campo debe contener una longitud de 13 posiciones, si se trata de personas morales debe contener una longitud de 12 posiciones.';

            return response()->json([
                'success' => false,
                'message' => $message
            ], 404);
        }

        // --- Razón social ---
        $receptor_razon_social = strtoupper($orden->contacto->Nombre);

        // --- Régimen fiscal ---
        $regimen_fiscal = Cfdi40RegimenFiscal::
            where('id', $orden->contacto->RegimenFiscalReceptor)
            ->first();

        if (!$regimen_fiscal) {
            return response()->json([
                'success' => false,
                'message' => 'El Régimen fiscal que ingresaste *'.$orden->contacto->RegimenFiscalReceptor.'* no está disponible en nuestro catálogo. Por favor, intenta ingresar un Régimen fiscal diferente.'
            ], 404);
        }

        $receptor_regimen_fiscal = $regimen_fiscal->id;

        // --- Uso de CFDI ---

        $uso_cfdi = Cfdi40UsoCfdi::
            where('id_aux', $orden->contacto->UsoCFDI)
            ->first();

        if (!$uso_cfdi) {
            return response()->json([
                'success' => false,
                'message'=>'El Uso de CFDI que ingresaste *'.$orden->contacto->UsoCFDI.'* no está disponible en nuestro catálogo. Por favor, intenta ingresar un Uso de CFDI diferente.'
            ], 404);
        }

        $receptor_uso_cfdi = $uso_cfdi->id_aux;

        // --- Código Postal ---
        $receptor_codigo_postal = str_replace([' ', '-'], '', $orden->contacto->DomicilioFiscalReceptor);

        // Validar código postal
        $cpValido = "/^[0-9]{5}$/";

        if (!preg_match($cpValido, $receptor_codigo_postal)) {
            $message = 'Por favor, verifica el Código Postal *'.$orden->contacto->DomicilioFiscalReceptor.'*. Este campo es el código postal del domicilio fiscal del receptor y debe contener una longitud de 5 posiciones.';
            return response()->json([
                'success' => false,
                'message'=>$message
            ], 404);
        }

        // --- Email ---
        $receptor_email = $orden->contacto->Email;

        // Validar sintaxis del email
        if ($receptor_email && !filter_var($receptor_email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'El email del receptor no es válido. Verifica que tenga el formato correcto (usuario@dominio.com).'
            ], 409);
        }

        // =============================
        // DATOS DE LOS CONCEPTOS
        // =============================

        // --- Conceptos ---
        $conceptos = $orden->detalles;
        if (count($conceptos) == 0) {
            return response()->json([
                'success' => false,
                'message'=>'Factura sin conceptos.'
            ], 409);
        }

        // Verificar valores
        for ($i=0; $i < count($conceptos); $i++) { 

            // --- Clave de Producto/Servicio ---
            $concepto_clave_prod_serv = Cfdi40ProductoServicio::
                where('id_aux', $conceptos[$i]->producto->ClaveProdServ)->first();

            if (!$concepto_clave_prod_serv) {
                return response()->json([
                    'success' => false,
                    'message'=>'La Clave de Producto/Servicio que ingresaste *'.$conceptos[$i]->producto->ClaveProdServ.'* no está disponible en nuestro catálogo. Por favor, intenta ingresar una Clave de Producto/Servicio diferente.'
                ], 409);
            }

            // --- Clave de Unidad ---
            $concepto_clave_unidad = Cfdi40ClaveUnidad::
                where('id_aux', $conceptos[$i]->producto->ClaveUnidad)
                ->first();

            if (!$concepto_clave_unidad) {
                return response()->json([
                    'success' => false,
                    'message'=>'La Clave de Unidad que ingresaste *'.$conceptos[$i]->producto->ClaveUnidad.'* no está disponible en nuestro catálogo. Por favor, intenta ingresar una Clave de Unidad diferente.'
                ], 409);
            }

            // --- Limpieza de descripción ---
            $descripcionSinAcentos = iconv('UTF-8', 'ASCII//TRANSLIT', $conceptos[$i]->producto_nombre);
            $descripcionSinAcentos = preg_replace('/[^A-Za-z0-9 ]/', '', $descripcionSinAcentos);
            $conceptos[$i]->descripcion = $descripcionSinAcentos;

            // --- Ajuste de valores ---
            // Usamos 4 decimales como lo indicaste
            $conceptos[$i]->cantidad = round($conceptos[$i]->cantidad, 4);
            $conceptos[$i]->valor_unitario = round($conceptos[$i]->precio_unitario, 4);
            $conceptos[$i]->importeConcepto = round($conceptos[$i]->cantidad * $conceptos[$i]->valor_unitario, 4);

            $conceptos[$i]->clave_prod_serv = $concepto_clave_prod_serv->id_aux;
            $conceptos[$i]->clave_unidad = $concepto_clave_unidad->id_aux; 
            $conceptos[$i]->unidad = $concepto_clave_unidad->texto;

            $conceptos[$i]->Descuento = $conceptos[$i]->descuento ? round($conceptos[$i]->descuento, 4) : 0;
            $conceptos[$i]->ObjetoImp = ($conceptos[$i]->porcentaje_impuesto > 0) ? 1 : 0;
            $conceptos[$i]->ObjetoImpRet = ($orden->retenciones == 'Con retenciones') ? 1 : 0;
            // $conceptos[$i]->producto_id = null;
 
        }

        // Inicializar totales
        $TotalImpuestosTrasladados = 0;
        $TotalImpuestosRetenidos = 0;
        $TotalImpuestosRetenidosIva = 0;
        $TotalImpuestosRetenidosIsr = 0;
        $totalDescuentos = 0;
        $subtotal = 0;

        // Se agregan los conceptos
        for ($i=0; $i < count($orden->detalles); $i++) { 
            $concepto = $orden->detalles[$i];

            // Usamos 4 decimales como lo indicaste
            $cantidad = $concepto->cantidad;
            $valorUnitario = $concepto->valor_unitario;
            $importeConcepto = round($cantidad * $valorUnitario, 4);
            $descuentoConcepto = $concepto->Descuento;
            // $descuentoConcepto = 0;

            $datos['conceptos'][$i]['cantidad'] = $cantidad;
            // $datos['conceptos'][$i]['unidad'] = $concepto->Unidad;
            $datos['conceptos'][$i]['descripcion'] = $concepto->descripcion;
            $datos['conceptos'][$i]['valorunitario'] = $valorUnitario;
            $datos['conceptos'][$i]['importe'] = $importeConcepto;
            
            if ($descuentoConcepto > 0) {
                $datos['conceptos'][$i]['Descuento'] = $descuentoConcepto;
                $totalDescuentos += $descuentoConcepto;
            }

            $datos['conceptos'][$i]['ClaveProdServ'] = $concepto->clave_prod_serv;
            $datos['conceptos'][$i]['ClaveUnidad'] = $concepto->clave_unidad;

            // La Base del cálculo del impuesto es el importe del concepto menos su descuento.
            $Base = $importeConcepto - $descuentoConcepto;
            $subtotal += $importeConcepto;

            if ($concepto->ObjetoImp == 1) {
                $datos['conceptos'][$i]['ObjetoImp'] = '02'; // Sí objeto de impuesto

                // Cálculo y redondeo del IVA
                $ImporteIVA = round($Base * 0.16, 4);
                $TotalImpuestosTrasladados += $ImporteIVA;

                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Base'] = round($Base, 4);
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Impuesto'] = '002'; // IVA
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['TipoFactor'] = 'Tasa';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['TasaOCuota'] = '0.160000';
                $datos['conceptos'][$i]['Impuestos']['Traslados'][0]['Importe'] = $ImporteIVA;
                
                // Lógica para las retenciones
                if ($concepto->ObjetoImpRet == 1) {
                    $retencionIva = round($Base * ($TasaIva / 100), 4);
                    $retencionIsr = round($Base * ($TasaIsr / 100), 4);

                    $TotalImpuestosRetenidosIva += $retencionIva;
                    $TotalImpuestosRetenidosIsr += $retencionIsr;

                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Base'] = round($Base, 4);
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Impuesto'] = '002';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['TipoFactor'] = 'Tasa';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['TasaOCuota'] = number_format($TasaIva / 100, 6, '.', '');
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][0]['Importe'] = $retencionIva;

                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Base'] = round($Base, 4);
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Impuesto'] = '001';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['TipoFactor'] = 'Tasa';
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['TasaOCuota'] = number_format($TasaIsr / 100, 6, '.', '');
                    $datos['conceptos'][$i]['Impuestos']['Retenciones'][1]['Importe'] = $retencionIsr;
                }
            } else {
                $datos['conceptos'][$i]['ObjetoImp'] = '01'; // No objeto de impuesto
            }
        }

        // Aquí se asignan los totales de la factura con 2 decimales
        $datos['factura']['subtotal'] = round($subtotal, 2);
        $datos['factura']['descuento'] = round($totalDescuentos, 2);
        $datos['factura']['total'] = round($datos['factura']['subtotal'] - $datos['factura']['descuento'] + $TotalImpuestosTrasladados - ($TotalImpuestosRetenidosIva + $TotalImpuestosRetenidosIsr), 2);
        
        // Impuestos globales para el XML, redondeados a 2 decimales
        if ($TotalImpuestosTrasladados > 0 || $TotalImpuestosRetenidosIva > 0 || $TotalImpuestosRetenidosIsr > 0) {
            $datos['impuestos']['TotalImpuestosTrasladados'] = round($TotalImpuestosTrasladados, 2);
            
            if ($TotalImpuestosRetenidosIva > 0 || $TotalImpuestosRetenidosIsr > 0) {
                // $datos['impuestos']['TotalImpuestosRetenidos'] = round($TotalImpuestosRetenidosIva + $TotalImpuestosRetenidosIsr, 2);
                $datos['impuestos']['TotalImpuestosRetenidos'] = round(round($TotalImpuestosRetenidosIva, 2) + round($TotalImpuestosRetenidosIsr, 2), 2);
            }
        
            // A nivel de Comprobante, el SAT exige que la Base de los traslados
            // sea la suma de las bases de los conceptos con el mismo impuesto y tipo de factor.
            $datos['impuestos']['translados'][0]['Base'] = round($subtotal - $totalDescuentos, 2); // Base total
            $datos['impuestos']['translados'][0]['impuesto'] = '002';
            $datos['impuestos']['translados'][0]['tasa'] = '0.160000';
            $datos['impuestos']['translados'][0]['importe'] = round($TotalImpuestosTrasladados, 2);
            $datos['impuestos']['translados'][0]['TipoFactor'] = 'Tasa';

            if ($TotalImpuestosRetenidosIva > 0) {
                $datos['impuestos']['retenciones'][0]['impuesto'] = '002';
                $datos['impuestos']['retenciones'][0]['importe'] = round($TotalImpuestosRetenidosIva, 2);
            }

            if ($TotalImpuestosRetenidosIsr > 0) {
                $datos['impuestos']['retenciones'][1]['impuesto'] = '001';
                $datos['impuestos']['retenciones'][1]['importe'] = round($TotalImpuestosRetenidosIsr, 2);
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
            ->with('emisor')
            ->first();   

        //elimino cotizacion curso desde el panel en caso de que tenga
        if($pedidoCurso){
            for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                $pedidoCurso->conceptos[$i]->delete();
            }
            if ($pedidoCurso->emisor) {
                $pedidoCurso->emisor->delete();
            }
            if ($pedidoCurso->receptor) {
                $pedidoCurso->receptor->delete();
            }
            $pedidoCurso->delete();
        } 

        //Iniciar proceso de facturacion

        $contador = (CfdiComprobante::
            where('emisor_id',$empresa->id)
            ->count())+1;

        $Folio = strtoupper("F0".$cliente->id."0".$empresa->id."0".$contador);
        $Serie = strtoupper("A0".$cliente->id."0".$empresa->id."0".$contador);

        //crear un pedido nuevo en curso
        $pedidoCurso=CfdiComprobante::create([
            'emisor_id'=>$empresa->id,
            'receptor_id'=>null,
            'status'=>0,
            'flag_cancelada'=>null,
            'Serie'=>$Serie,
            'Folio'=>$Folio,
            'Fecha'=>date('Y-m-d\TH:i:s', time() - (60*60)),
            'Sello'=>"",
            'FormaPago'=>$comprobante_forma_pago,
            'NoCertificado'=>"",
            'Certificado'=>"",
            'CondicionesDePago'=>"",
            'Subtotal'=>$datos['factura']['subtotal'],
            'Descuento'=>$datos['factura']['descuento'],
            'Moneda'=>"MXN",
            'TipoCambio'=>"",
            'Total'=>$datos['factura']['total'],
            'TipoDeComprobante'=>"I",
            'Exportacion'=>"01",
            'MetodoPago'=>$MetodoPago,
            'LugarExpedicion'=>$empresa->CP,
            'Confirmacion'=>"",
            'TasaIva'=>$TasaIva,
            'TasaIsr'=>$TasaIsr,
            'Tipo'=>$Tipo,
            'user_id'=>$empresa->user_id,
        ]);

        //crear el emisor
        $newObjEmisor=CfdiEmisor::create([
            'comprobante_id'=>$pedidoCurso->id,
            'Rfc'=>$empresa->Rfc,
            'RazonSocial'=>$empresa->RazonSocial,
            'RegimenFiscal'=>$empresa->RegimenFiscal,
            'CP'=>$empresa->CP,
        ]);

        //crear el receptor
        $newObjReceptor=CfdiReceptor::create([
            'comprobante_id'=>$pedidoCurso->id,
            'Rfc'=>$receptor_rfc,
            'Nombre'=>$receptor_razon_social,
            'DomicilioFiscalReceptor'=>$receptor_codigo_postal,
            'ResidenciaFiscal'=>null,
            'NumRegIdTrib'=>null,
            'RegimenFiscalReceptor'=>$receptor_regimen_fiscal,
            'UsoCFDI'=>$receptor_uso_cfdi,
            'Email'=>$receptor_email,
        ]);

        //Crear los conceptos
        for ($i=0; $i < count($conceptos); $i++) { 
            //agregar nuevo concepto
            $nuevoConcepto=CfdiConcepto::create([
                'comprobante_id' => $pedidoCurso->id,
                'ClaveProdServ' => $conceptos[$i]->clave_prod_serv,
                'NoIdentificacion' => "",
                'Cantidad' => $conceptos[$i]->cantidad,
                'ClaveUnidad' => $conceptos[$i]->clave_unidad,
                'Unidad' => $conceptos[$i]->unidad,
                'Descripcion' => $conceptos[$i]->descripcion,
                'ValorUnitario' => $conceptos[$i]->valor_unitario,
                'Importe' => $conceptos[$i]->importeConcepto,
                'Descuento' => $conceptos[$i]->Descuento,
                'ObjetoImp' => $conceptos[$i]->ObjetoImp,
                'ObjetoImpRet' => $conceptos[$i]->ObjetoImpRet,
                // 'producto_id' => $conceptos[$i]->producto_id,
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
                ->with('emisor')
                ->first();   

            //elimino cotizacion curso desde el panel en caso de que tenga
            if($pedidoCurso){
                for ($i=0; $i < count($pedidoCurso->conceptos); $i++) { 
                    $pedidoCurso->conceptos[$i]->delete();
                }
                if ($pedidoCurso->emisor) {
                    $pedidoCurso->emisor->delete();
                }
                if ($pedidoCurso->receptor) {
                    $pedidoCurso->receptor->delete();
                }
                $pedidoCurso->delete();
            }

            $message = $resTimbrado;

            return response()->json([
                'success' => false,
                'message'=>$message
            ], 500);

        }else{

            //Timbrada exitosamente
            $pedidoCurso->status = 1;
            $pedidoCurso->save();

            $orden->facturada = 1;
            $orden->comprobante_id = $pedidoCurso->id;
            $orden->save();

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

            $orden->pdf = $document;
            $orden->save();

            //crear o actualizar cliente
            $clienteExiste = CfdiCliente::noEliminados()
                ->where('empresa_id',$empresa->id)
                ->where('Rfc', $receptor_rfc)
                ->with('mi_regimen_fiscal')
                ->with('mi_uso_cfdi')
                ->first();

            if(!$clienteExiste){
                $newCliente=CfdiCliente::create([
                    'empresa_id'=>$empresa->id,
                    'status'=>true,
                    'Rfc'=>$receptor_rfc,
                    'Nombre'=>$receptor_razon_social,
                    'DomicilioFiscalReceptor'=>$receptor_codigo_postal,
                    'ResidenciaFiscal'=>null,
                    'NumRegIdTrib'=>null,
                    'RegimenFiscalReceptor'=>$receptor_regimen_fiscal,
                    'UsoCFDI'=>$receptor_uso_cfdi,
                    'Email'=>$receptor_email,
                    'user_id'=>$empresa->user_id,
                    'origen'=>'pos',
                ]);
            }else if($clienteExiste){
                $clienteExiste->Nombre = $receptor_razon_social;
                $clienteExiste->DomicilioFiscalReceptor = $receptor_codigo_postal;
                $clienteExiste->RegimenFiscalReceptor = $receptor_regimen_fiscal;
                $clienteExiste->UsoCFDI = $receptor_uso_cfdi;
                $clienteExiste->Email = $receptor_email;
                $clienteExiste->save();
            }

            try {
                $this->emailFactura($pedidoCurso->id); 
            } catch (Exception $e) {
                
            }

            return response()->json([
                'success' => true,
                'message'=>'Factura timbrada exitosamente.',
                'factura_id'=>$pedidoCurso->id,
                'factura_pdf'=>$document
            ], 200);
        }


        // return response()->json([
        //     'success' => true,
        //     'forma_pago'=> $forma_pago,
        //     'comprobante_forma_pago'=>$comprobante_forma_pago,
        //     'metodo_pago'=>$MetodoPago,
        //     'tipo_factura'=>$Tipo,
        //     'tasa_iva'=>$TasaIva,
        //     'tasa_isr'=>$TasaIsr,
        //     'data' => $orden,
        //     'factura_data' => $datos
        // ]);
    }

    /*Cancela una venta generada desde el POS*/
    public function cancelarVenta(Request $request, $venta_id)
    {

        $orden = PosOrder::with('detalles.producto')->find($venta_id);

        if (!$orden) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada.'
            ], 404);
        }

        if ($orden->status === 'cancelada') {
            return response()->json([
                'success' => false,
                'message' => 'La orden ya está cancelada'
            ], 400);
        }

        // 1. Si está facturada, intentar primero con el SAT
        if ($orden->facturada) {
            
            $cliente = User::whereNull('flag_eliminado')
                ->where('id', $orden->user_id)
                ->first();

            if (!$cliente)
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado.'
                ], 404);
            }

            $comprobante = CfdiComprobante::find($orden->comprobante_id);

            if(!$comprobante){
                return response()->json([
                    'success' => false,
                    'message' => 'Comprobante asociado a la orden no encontrado.'
                ], 404);
            }

            $cfdi_empresa = CfdiEmpresa::
                where('id', $comprobante->emisor_id)
                ->first();

            if (!$cfdi_empresa)
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Emisor CFDI no encontrado.'
                ], 404);
            }

            $empresa = $cfdi_empresa;

            // --- Validar datos de Emisor ---
            $camposRequeridosEmisor = [
                'Rfc', 'RazonSocial', 'RegimenFiscal',
                'CP', 'cer', 'key', 'pass'
            ];

            foreach ($camposRequeridosEmisor as $campo) {
                if (empty($empresa->$campo)) {
                    $message = 'Para cancelar una factura, primero debes configurar tus datos de emisor.';

                    return response()->json([
                        'success' => false,
                        'message' => $message
                    ], 409);
                }
            }

            // Preparamos un Request "falso" para reutilizar función existente
            $requestCancelacion = new Request();
            $requestCancelacion->replace(['motivo' => $request->motivo_cfdi]);

            // Llamamos a la función y capturamos la respuesta
            $respuestaSat = $this->cancelarFactura($requestCancelacion, $orden->comprobante_id);
            
            // El método original devuelve un objeto JsonResponse
            // Obtenemos el contenido y el código de estado
            $datosSat = $respuestaSat->getData();
            
            if ($respuestaSat->getStatusCode() !== 200) {
                // Si el SAT falló, retornamos el error tal cual vino del SAT
                return response()->json([
                    'success' => false,
                    'message' => $datosSat->error ?? 'Error al cancelar ante el SAT'
                ], $respuestaSat->getStatusCode());
            }

        }

        // 2. Si llegamos aquí, o no estaba facturada o la cancelación SAT fue exitosa
        try {
            DB::transaction(function () use ($orden, $request) {
                // Marcar la orden como cancelada (usando tu método del modelo)
                $orden->cancelar($request->motivo_cancelacion);

                foreach ($orden->detalles as $detalle) {
                    // Solo productos físicos
                    if(!$detalle->producto->is_service){
                        InventoryService::adjustStock(
                            $detalle->producto->id,
                            $detalle->cantidad,
                            'ajuste_positivo',
                            PosOrder::class,
                            $orden->id,
                            $orden->user_id,
                            'Venta ' . $orden->folio . ' cancelada'
                        );
                    }
                }
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la cancelación en el sistema: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Venta cancelada correctamente.'
        ]);
        
    }

    public function migrarEmisoresLegacy() {
        // 1. Cargamos 'emisor' de antemano (Eager Loading) para evitar el N+1 del exists()
        $cfdis = CfdiComprobante::with('emisor')
            ->select('id', 'user_id', 'emisor_id')
            ->get();

        $emisorIds = $cfdis->pluck('emisor_id')->unique();

        $empresas = CfdiEmpresa::select('id', 'user_id', 'Rfc', 'RazonSocial', 'RegimenFiscal', 'CP')
            ->whereIn('id', $emisorIds)
            ->get()
            ->keyBy('id');

        $migradas = 0;
        $omitidas = 0;

        foreach ($cfdis as $cfdi) {
            $empresa = $empresas->get($cfdi->emisor_id);

            if ($empresa) {
                // Solo guardamos si el user_id es diferente para ahorrar recursos
                if ($cfdi->user_id !== $empresa->user_id) {
                    $cfdi->user_id = $empresa->user_id;
                    $cfdi->save();
                }
            }

            // Al usar with('emisor'), esto ya no hace una consulta extra, lo mira en memoria
            if ($cfdi->emisor) { 
                $omitidas++;
                continue;
            }

            if ($empresa) {
                CfdiEmisor::create([
                    'comprobante_id' => $cfdi->id,
                    'Rfc'            => $empresa->Rfc,
                    'RazonSocial'    => $empresa->RazonSocial,
                    'RegimenFiscal'  => $empresa->RegimenFiscal,
                    'CP'             => $empresa->CP,
                ]);

                $migradas++;
            }
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Migración de emisores legacy completada.',
            'migradas' => $migradas,
            'omitidas' => $omitidas,
        ]);
    }

    public function migrarProductosLegacy() {
        $productos = CfdiProducto::all();

        $migradas = 0;
        $omitidas = 0;

        for ($i=0; $i < count($productos); $i++) { 
            $producto = $productos[$i];

            $empresa = CfdiEmpresa::find($producto->empresa_id);

            if(!$empresa){
                $omitidas++;
                continue; // Si no encontramos la empresa, omitimos este producto
            }

            $producto->user_id = $empresa->user_id;
            $producto->save();

            $migradas++;
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Migración de productos legacy completada.',
            'migradas' => $migradas,
            'omitidas' => $omitidas,
        ]);
    }

    public function eliminarEmpresasLegacy() {
        $empresas = CfdiEmpresa::all();

        $migradas = 0;
        $omitidas = 0;

        for ($i=0; $i < count($empresas); $i++) { 
            $empresa = $empresas[$i];

            if($empresa->Rfc){

                $empresa->emisor_bot = true;
                $empresa->emisor_pos = true;
                $empresa->emisor_ingresos = true;
                $empresa->save();

                $omitidas++;
                continue; // Si la empresa tiene datos, omitimos esta empresa
            }

            $empresa->delete();

            $migradas++;
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Eliminación de empresas legacy completada.',
            'migradas' => $migradas,
            'omitidas' => $omitidas,
        ]);
    }

    
}
