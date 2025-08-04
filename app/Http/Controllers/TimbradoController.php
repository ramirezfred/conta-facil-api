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

use App\Models\Cfdi40CodigoPostal;
use App\Models\Cfdi40RegimenFiscal;
use App\Models\Cfdi40ProductoServicio;
use App\Models\Cfdi40ClaveUnidad;
use App\Models\Cfdi40FormaPago;
use App\Models\Cfdi40MetodoPago;
use App\Models\Cfdi40UsoCfdi;

use App\Models\Ingreso;
use App\Models\IngresoConcepto;

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
error_reporting(~(E_WARNING|E_NOTICE));
//error_reporting(E_ALL);

// Se especifica la zona horaria
date_default_timezone_set('America/Mexico_City');

// Se incluye el SDK
//require_once 'sdk2/sdk2.php';
require_once public_path('sdk2/sdk2.php');

class TimbradoController extends Controller
{

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

        //return $this->ingresosContables($usuario->id);

        return response()->json([
            'usuario'=>$usuario,
        ], 200);

    }

    public function aplicarAlgoritmoMansualFactura()
    {
        $usuario = User::whereNull('flag_eliminado')
            ->whereNull('flag_algoritmo_factura')
            ->where('tipo_algoritmo_factura',2)
            ->first();

        //return $this->ingresosContables($usuario->id);

        return response()->json([
            'usuario'=>$usuario,
        ], 200);

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
        

        $resTimbrado = $this->timbrarSanbox($pedidoCurso->id);
        //$resTimbrado = $this->timbrar($pedidoCurso->id);

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

    public function timbrarSanbox($factura_id)
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

        // $datos['PAC']['usuario'] = 'AUMA9101171B4';
        // $datos['PAC']['pass'] = 'AUMA9101171B41234';
        // $datos['PAC']['produccion'] = 'SI';

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

   
}
