<?php

namespace App\Services;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

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


use App\Models\User;
use App\Models\BotMessage;
use App\Models\BotSistema;

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

//gastos
use App\Models\CatGasto;
use App\Models\Gasto;
use App\Models\GastoConcepto;
use App\Models\GastoRecurrente;

//ingresos
use App\Models\Ingreso;
use App\Models\IngresoConcepto;
use App\Models\IngresoRecurrente;

//calendario fiscal
use App\Models\CalendarioFiscal;

//calculadoras fiscales
use App\Models\Carpeta;
use App\Models\Documento;

//ejemplo factura cfdi 4.0
// Se desactivan los mensajes de debug
//error_reporting(~(E_WARNING|E_NOTICE));
error_reporting(E_ERROR);

// Se especifica la zona horaria
date_default_timezone_set('America/Mexico_City');

// Se incluye el SDK
//require_once 'sdk2/sdk2.php';
require_once public_path('sdk2/sdk2.php');

class BotService
{
    // --- Funciones de negocio ---
    public function crear_gasto($args, $user_id)
    {
        $obj = User::whereNull('flag_eliminado')
            ->where('id', $user_id)
            ->first();
        if (!$obj)
        {
            return [
                'status'=>'error',
                'message'=>'Usuario no encontrado'
            ];
        }

        $aux = CatGasto::whereNull('flag_eliminado')
            ->where('clave',$args->categoria)
            ->first();
        if(!$aux){
            return [
                'status'=>'error',
                'message'=>'Categoría no disponible en el catálogo de gastos.'
            ];
        }
 
        
        if($newObj=Gasto::create([
            'user_id'=> $user_id,
            'tipo_id'=> $aux->id,
            'total'=> number_format($args->monto, 2, '.', '')
        ])){

            $newObjTipo=GastoConcepto::create([
                'gasto_id'=> $newObj->id,
                'Descripcion'=> $args->descripcion,
                'Cantidad'=> 1,
                'ValorUnitario'=> number_format($args->monto, 2, '.', ''),
                'Importe'=> number_format($args->monto, 2, '.', ''),
            ]);

            $document = $this->comprobantePdf($newObj->id, 1);
            $newObj->pdf = $document;
            $newObj->save();

            return [
                'status'=>'ok',
                'categoria'=>$args->categoria,
                'monto'=>$args->monto,
                'descripcion'=>$args->descripcion,
                'link'=>$document
            ];

        }else{
            return [
                'status'=>'error',
                'message'=>'Error al crear el registro.'
            ];
        }
    }

    public function crear_ingreso($args, $user_id) 
    {

        $obj = User::whereNull('flag_eliminado')
            ->where('id', $user_id)
            ->with('cfdi_empresa')
            ->first();
        if (!$obj)
        {
            return [
                'status'=>'error',
                'message'=>'Usuario no encontrado'
            ];
        }

        if($args->categoria == 'Contable'){
            $tipo_id = 1;
        }else if($args->categoria == 'No Contable'){
            $tipo_id = 2;
        }else{
            return [
                'status'=>'error',
                'message'=>'Categoría no disponible en el catálogo de ingresos.'
            ];
        }

        //Si el ingreso es contable, Validacion para user resico
        if($args->categoria == 'Contable' && $obj->cfdi_empresa){

            $limite_facturacion = $this->determinarLimiteFacturacion($obj->cfdi_empresa->Rfc,$obj->cfdi_empresa->RegimenFiscal);
            if($limite_facturacion != null && $limite_facturacion != 0){

                $total_facturado = $this->getTotalFacturado($obj->id);

                if($total_facturado >= $limite_facturacion){
                    return [
                        'status'=>'error',
                        'message'=>'Ya alcanzaste el límite de $'.$limite_facturacion.' pesos mensuales facturables.'
                    ];
                }else if(($total_facturado + $args->monto) >= $limite_facturacion){
                    return [
                        'status'=>'error',
                        'message'=>'El total del ingreso excede el límite de $'.$limite_facturacion.' pesos mensuales facturables.'
                    ];
                }

            }

        }
 
        
        if($newObj=Ingreso::create([
            'user_id'=> $user_id,
            'tipo_id'=> $tipo_id,
            'total'=> number_format($args->monto, 2, '.', '')
        ])){

            $newObjTipo=IngresoConcepto::create([
                'ingreso_id'=> $newObj->id,
                'Descripcion'=> $args->descripcion,
                'Cantidad'=> 1,
                'ValorUnitario'=> number_format($args->monto, 2, '.', ''),
                'Importe'=> number_format($args->monto, 2, '.', ''),
            ]);

            $document = $this->comprobantePdf($newObj->id, 2);
            $newObj->pdf = $document;
            $newObj->save();

            return [
                'status'=>'ok',
                'categoria'=>$args->categoria,
                'monto'=>$args->monto,
                'descripcion'=>$args->descripcion,
                'link'=>$document
            ];

        }else{
            return [
                'status'=>'error',
                'message'=>'Error al crear el registro.'
            ];
        }
    }

    public function listar_receptores($args, $user_id) 
    {

        $user = User::whereNull('flag_eliminado')
            ->where('id', $user_id)
            ->with('cfdi_empresa')
            ->first();

        if (!$user || !$user->cfdi_empresa)
        {
            return [
                'status'=>'error',
                'message' => 'No tienes receptores registrados. Por favor, proporcióname todos los datos para emitir la factura.'
            ];
        }

        $receptores = CfdiCliente::where('empresa_id', $user->cfdi_empresa->id)
            ->where('status', 1)
            ->with('mi_regimen_fiscal')
            ->with('mi_uso_cfdi')
            ->orderByDesc('id') // opcional: los más recientes primero
            ->take(10)
            ->get();

        if (count($receptores) == 0)
        {
            return [
                'status'=>'error',
                'message' => 'No tienes receptores registrados. Por favor, proporcióname todos los datos para emitir la factura.'
            ];
        }

        $message = "Estos son tus últimos receptores registrados:\n\n";

        foreach ($receptores as $index => $r) {
            $message .= "*" . ($index + 1) . ".* " .
                ($r->Nombre ?? '-') . "\n" .
                "RFC: " . ($r->Rfc ?? '-') . "\n" .
                "Email: " . ($r->Email ?? '-') . "\n" .
                "CP: " . ($r->DomicilioFiscalReceptor ?? '-') . "\n" .
                "Régimen: " . ($r->mi_regimen_fiscal->texto ?? '-') . "\n" .
                "Uso CFDI: " . ($r->mi_uso_cfdi->texto ?? '-') . "\n\n";
        }

        $message .= "*Indícame cuál vas a usar en esta oportunidad (por número o nombre).*";

        $receptores_validos = $receptores->map(function ($r) {
            return [
                'id' => $r->id,
                'nombre' => $r->Nombre,
                'rfc' => $r->Rfc,
                'email' => $r->Email,
                'cp' => $r->DomicilioFiscalReceptor,
                'regimen_fiscal' => $r->mi_regimen_fiscal->texto ?? null,
                'uso_cfdi' => $r->mi_uso_cfdi->texto ?? null
            ];
        })->toArray();

        return [
            'status'=>'ok',
            'message'=>$message,
            'receptores_validos' => $receptores_validos
        ];
    }

    public function crear_factura($args, $user_id) 
    {
        // file_put_contents('log_functions.txt', print_r($args, true), FILE_APPEND);

        // return [
        //     'status'=>'ok',
        //     'forma_pago'=>$args->forma_pago,
        //     'receptor'=>$args->receptor,
        //     'conceptos'=>$args->conceptos
        // ];

        // =============================
        // VALIDACIÓN DE DATOS ENTRANTES (IA)
        // =============================

        // =============================
        // FACTURA A PUBLICO EN GENERAL
        // =============================

        if(
            strtoupper(str_replace([' ', '-'], '', $args->receptor->rfc)) == 'XAXX010101000' ||
            strtoupper($args->receptor->razon_social) == 'PUBLICO EN GENERAL' ||
            strtoupper($args->receptor->razon_social) == 'PÚBLICO EN GENERAL' 
        ){
            $args->receptor->rfc = 'XAXX010101000';
            $args->receptor->razon_social = 'PUBLICO EN GENERAL';
            $args->receptor->uso_cfdi = 'Sin efectos fiscales.';
            $args->retenciones == 'Sin retenciones';
            $MetodoPago = 2; //Pago en una sola exhibición
        }

        // =============================
        // DATOS DEL COMPROBANTE
        // =============================

        // --- Forma de pago ---
        $forma_pago = Cfdi40FormaPago::where('texto', $args->forma_pago)
            ->orWhere('texto', 'like', '%'.$args->forma_pago.'%')
            ->first();

        if (!$forma_pago) {
            return [
                'status' => 'error',
                'message' => 'Forma de Pago no disponible en el catálogo. Por favor, intenta ingresar una Forma de Pago diferente.'
            ];
        }

        $comprobante_forma_pago = $forma_pago->id;

        // --- Metodo de pago ---
        $MetodoPago = 2; //Pago en una sola exhibición

        // --- Tipo de factura ---
        $Tipo = 2; //1 = factura neta 2 = factura mas iba

        // --- Retenciones ---
        $TasaIva = 0;
        $TasaIsr = 0;

        if ($args->retenciones == 'Con retenciones') {
            $TasaIva = 16;
            $TasaIsr = 1.25;
        }

        // =============================
        // DATOS DEL RECEPTOR
        // =============================

        // --- RFC ---
        $receptor_rfc = $args->receptor->rfc;

        // Normalizar: eliminar espacios o guiones y convertir a mayúsculas
        $receptor_rfc = strtoupper(str_replace([' ', '-'], '', $receptor_rfc));

        // Validar formato RFC
        $rfcValido = "/^[A-Z0-9]{12,13}$/";

        if (!preg_match($rfcValido, $receptor_rfc)) {
            $message = 'Por favor, verifica el Rfc. En el caso de que sea una persona física, este campo debe contener una longitud de 13 posiciones, si se trata de personas morales debe contener una longitud de 12 posiciones.';
            return [
                'status'=>'error',
                'message'=>$message
            ];
        }

        // --- Razón social ---
        $receptor_razon_social = strtoupper($args->receptor->razon_social);

        // --- Régimen fiscal ---
        // $regimen_fiscal = Cfdi40RegimenFiscal::
        //     where('texto', $args->receptor->regimen_fiscal)
        //     ->orWhere('texto', 'like', '%'.$args->receptor->regimen_fiscal.'%')
        //     ->first();

        $regimen_fiscal_input = rtrim(trim($args->receptor->regimen_fiscal), '.');
        $regimen_fiscal = 
            Cfdi40RegimenFiscal::whereRaw("REPLACE(texto, '.', '') = ?", [str_replace('.', '', $regimen_fiscal_input)])
            ->first();

        if (!$regimen_fiscal) {
            return [
                'status'=>'error',
                'message'=>'El Régimen fiscal que ingresaste *'.$args->receptor->regimen_fiscal.'* no está disponible en nuestro catálogo. Por favor, intenta ingresar un Régimen fiscal diferente.'
            ];
        }

        $receptor_regimen_fiscal = $regimen_fiscal->id;

        // --- Uso de CFDI ---

        // $receptor_uso_cfdi = Cfdi40UsoCfdi::
        //     where('texto', $args->receptor->uso_cfdi)
        //     ->orWhere('texto', 'like', '%'.$args->receptor->uso_cfdi.'%')
        //     ->first();

        $uso_cfdi_input = rtrim(trim($args->receptor->uso_cfdi), '.');
        $uso_cfdi = 
            Cfdi40UsoCfdi::whereRaw("REPLACE(texto, '.', '') = ?", [str_replace('.', '', $uso_cfdi_input)])
            ->first();

        if (!$uso_cfdi) {
            return [
                'status'=>'error',
                'message'=>'El Uso de CFDI que ingresaste *'.$args->receptor->uso_cfdi.'* no está disponible en nuestro catálogo. Por favor, intenta ingresar un Uso de CFDI diferente.'
            ];
        }

        $receptor_uso_cfdi = $uso_cfdi->id_aux;

        // --- Código Postal ---
        $receptor_codigo_postal = str_replace([' ', '-'], '', $args->receptor->codigo_postal);

        // Validar código postal
        $cpValido = "/^[0-9]{5}$/";

        if (!preg_match($cpValido, $receptor_codigo_postal)) {
            $message = 'Por favor, verifica el Código Postal *'.$args->receptor->codigo_postal.'*. Este campo es el código postal del domicilio fiscal del receptor y debe contener una longitud de 5 posiciones.';
            return [
                'status'=>'error',
                'message'=>$message
            ];
        }

        // --- Email ---
        $receptor_email = $args->receptor->email;

        // Validar sintaxis del email
        if (!filter_var($receptor_email, FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => 'error',
                'message' => 'El email del receptor no es válido. Verifica que tenga el formato correcto (usuario@dominio.com).'
            ];
        }

        // =============================
        // DATOS DE LOS CONCEPTOS
        // =============================

        // --- Conceptos ---
        $conceptos = $args->conceptos;
        if (count($conceptos) == 0) {
            return [
                'status'=>'error',
                'message'=>'Factura sin conceptos.'
            ];
        }

        $Subtotal = 0;
        $Descuento = 0;
        $Total = 0;
        $TotalImpuestosTrasladados = 0;
        $TotalImpuestosRetenidos = 0;

        for ($i=0; $i < count($conceptos); $i++) { 

            // --- Clave de Producto/Servicio ---
            $concepto_clave_prod_serv = Cfdi40ProductoServicio::
                where('id', $conceptos[$i]->clave_prod_serv)->first();

            if (!$concepto_clave_prod_serv) {
                return [
                    'status'=>'error',
                    'message'=>'La Clave de Producto/Servicio que ingresaste *'.$conceptos[$i]->clave_prod_serv.'* no está disponible en nuestro catálogo. Por favor, intenta ingresar una Clave de Producto/Servicio diferente.'
                ];
            }

            // --- Clave de Unidad ---
            $concepto_clave_unidad = Cfdi40ClaveUnidad::
                where('id', $conceptos[$i]->clave_unidad)
                ->orWhere('texto', $conceptos[$i]->clave_unidad)
                ->first();

            if (!$concepto_clave_unidad) {
                return [
                    'status'=>'error',
                    'message'=>'La Clave de Unidad que ingresaste *'.$conceptos[$i]->clave_unidad.'* no está disponible en nuestro catálogo. Por favor, intenta ingresar una Clave de Unidad diferente.'
                ];
            }

            // --- Limpieza de descripción ---
            $descripcionSinAcentos = iconv('UTF-8', 'ASCII//TRANSLIT', $conceptos[$i]->descripcion);
            $descripcionSinAcentos = preg_replace('/[^A-Za-z0-9 ]/', '', $descripcionSinAcentos);
            $conceptos[$i]->descripcion = $descripcionSinAcentos;

            // --- Ajuste de valores ---
            $conceptos[$i]->clave_prod_serv = $concepto_clave_prod_serv->id_aux;
            $conceptos[$i]->clave_unidad = $concepto_clave_unidad->id_aux; 
            $conceptos[$i]->unidad = $concepto_clave_unidad->texto;
            $conceptos[$i]->valor_unitario = round($conceptos[$i]->valor_unitario, 2);
            $conceptos[$i]->cantidad = round($conceptos[$i]->cantidad, 2);
            $conceptos[$i]->importe = round($conceptos[$i]->valor_unitario * $conceptos[$i]->cantidad, 2);
            $conceptos[$i]->descuento = 0;
            $conceptos[$i]->objeto_imp = 1;
            $conceptos[$i]->objeto_imp_ret = ($args->retenciones == 'Con retenciones') ? 1 : 0;
            $conceptos[$i]->producto_id = null;

            // --- Cálculo de totales ---
            $Subtotal += $conceptos[$i]->importe;
            $Descuento += $conceptos[$i]->descuento;

            if ($conceptos[$i]->objeto_imp == 1) {
                $Base = $conceptos[$i]->importe - $conceptos[$i]->descuento;
                $TotalImpuestosTrasladados += round($Base * 0.16, 2);

                if ($conceptos[$i]->objeto_imp_ret == 1) {
                    $retIva = round($Base * ($TasaIva / 100), 2);
                    $retIsr = round($Base * ($TasaIsr / 100), 2);
                    $TotalImpuestosRetenidos += $retIva + $retIsr;
                }
            }
        }

        // --- Redondeo final ---
        $Subtotal = round($Subtotal, 2);
        $Descuento = round($Descuento, 2);
        $TotalImpuestosTrasladados = round($TotalImpuestosTrasladados, 2);
        $TotalImpuestosRetenidos = round($TotalImpuestosRetenidos, 2);

        $Total = round($Subtotal - $Descuento + $TotalImpuestosTrasladados - $TotalImpuestosRetenidos, 2);


        $cliente = User::whereNull('flag_eliminado')
            ->where('id', $user_id)
            ->with('cfdi_empresa')
            ->first();

        if (!$cliente)
        {
            return [
                'status'=>'error',
                'message'=>'Usuario no encontrado.'
            ];
        }

        if ($cliente->status != 1)
        {
            return [
                'status'=>'error',
                'message'=>'Emisor inhabilitado para generar timbre electrónico.'
            ];
        }

        if (!$cliente->cfdi_empresa)
        {
            return [
                'status'=>'error',
                'message'=>'Empresa no encontrada.'
            ];
        }

        $empresa = $cliente->cfdi_empresa;

        // --- Validar datos de Emisor ---
        $camposRequeridosEmisor = [
            'Rfc', 'RazonSocial', 'RegimenFiscal',
            'CP', 'cer', 'key', 'pass'
        ];

        foreach ($camposRequeridosEmisor as $campo) {
            if (empty($empresa->$campo)) {
                $message = 'Para crear una factura, primero debes configurar tus datos de emisor desde el panel administrativo. 🏠';
                return [
                    'status'=>'error',
                    'message'=>$message
                ];
            }
        }

        $limite_facturacion = $this->determinarLimiteFacturacion($empresa->Rfc,$empresa->RegimenFiscal);
        if($limite_facturacion != null && $limite_facturacion != 0){

            $total_facturado = $this->getTotalFacturado($empresa->user_id);

            if($total_facturado >= $limite_facturacion){
                return [
                    'status'=>'error',
                    'message'=>'Ya alcanzaste el límite de $'.$limite_facturacion.' pesos mensuales facturables.'
                ];
            }else if(($total_facturado + $Total) >= $limite_facturacion){
                return [
                    'status'=>'error',
                    'message'=>'El total de la factura excede el límite de $'.$limite_facturacion.' pesos mensuales facturables.'
                ];
            }

        }

        if ($cliente->count_timbres < 1) {
            return [
                'status'=>'error',
                'message'=>'No cuentas con timbres disponibles. Te recomendamos adquirir un paquete para continuar disfrutando de nuestros servicios de timbrado.'
            ];
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
            'FormaPago'=>$comprobante_forma_pago,
            'NoCertificado'=>"",
            'Certificado'=>"",
            'CondicionesDePago'=>"",
            'Subtotal'=>$Subtotal,
            'Descuento'=>$Descuento,
            'Moneda'=>"MXN",
            'TipoCambio'=>"",
            'Total'=>$Total,
            'TipoDeComprobante'=>"I",
            'Exportacion'=>"01",
            'MetodoPago'=>$MetodoPago,
            'LugarExpedicion'=>$empresa->CP,
            'Confirmacion'=>"",
            'TasaIva'=>$TasaIva,
            'TasaIsr'=>$TasaIsr,
            'Tipo'=>$Tipo,
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
                'Importe' => $conceptos[$i]->importe,
                'Descuento' => $conceptos[$i]->descuento,
                'ObjetoImp' => $conceptos[$i]->objeto_imp,
                'ObjetoImpRet' => $conceptos[$i]->objeto_imp_ret,
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

            return [
                'status'=>'error',
                'message'=>$message
            ];

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
                ->where('Rfc', $receptor_rfc)
                ->with('mi_regimen_fiscal')
                ->with('mi_uso_cfdi')
                ->first();

            if(!$clienteExiste){
                $newCliente=CfdiCliente::create([
                    'empresa_id'=>$empresa_id,
                    'status'=>1,
                    'Rfc'=>$receptor_rfc,
                    'Nombre'=>$receptor_razon_social,
                    'DomicilioFiscalReceptor'=>$receptor_codigo_postal,
                    'ResidenciaFiscal'=>null,
                    'NumRegIdTrib'=>null,
                    'RegimenFiscalReceptor'=>$receptor_regimen_fiscal,
                    'UsoCFDI'=>$receptor_uso_cfdi,
                    'Email'=>$receptor_email,
                ]);
            }else if($clienteExiste){
                $clienteExiste->Nombre = $receptor_razon_social;
                $clienteExiste->DomicilioFiscalReceptor = $receptor_codigo_postal;
                $clienteExiste->RegimenFiscalReceptor = $receptor_regimen_fiscal;
                $clienteExiste->UsoCFDI = $receptor_uso_cfdi;
                $clienteExiste->Email = $receptor_email;
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

            return [
                'status'=>'ok',
                'message'=>'Factura timbrada exitosamente.',
                // 'factura_id'=>$pedidoCurso->id,
                'receptor'=>$args->receptor,
                'link'=>$document
            ];
        }
    }

    public function historial($args, $user_id) { 

        $user = User::whereNull('flag_eliminado')
            ->where('id', $user_id)
            ->first();
        if (!$user)
        {
            return [
                'status'=>'error',
                'message'=>'Usuario no encontrado'
            ];
        }

        $user_token=User::find($user->id);
        $token = JWTAuth::fromUser($user_token);

        $claveAdicional = config('app.lada_a');
        $cadenaEncriptada = Crypt::encrypt($user->id, $claveAdicional);

        $link = "";
        $short_link = "";
        $message = "";

        
        //Ingresos
        if($args->lista == "Ingresos"){

            // $link = 'https://contafacil.internow.com.mx/#/bot-ingresos/1/'.$cadenaEncriptada.'/'.$token;

            return $this->historialPdf($user_id, "Ingresos"); 
            
        }
        //Gastos
        else if($args->lista == "Gastos"){

            // $link = 'https://contafacil.internow.com.mx/#/bot-gastos/1/'.$cadenaEncriptada.'/'.$token;

            return $this->historialPdf($user_id, "Gastos");
            
        }
        //Facturas
        else if($args->lista == "Facturas"){

            // $link = 'https://contafacil.internow.com.mx/#/bot-facturacion/2/'.$cadenaEncriptada.'/'.$token;

            return $this->historialFacturasPdf($user_id, "Facturas");
            
        }else{
            return [
                'status'=>'error',
                'message'=>'Historial no disponible',
            ];
        }

        $message = 'Aquí está tu historial.';

        // if($link != ""){
        //     $short_link = $this->shortenURL($link);
        // }

        // $message = str_replace("{{short_link}}", $short_link, $message);

        return [
            'status'=>'ok',
            'message'=>$message,
            'link'=>$link,
        ];
       
    }

    public function obtener_eventos_fiscales($args, $user_id){
        

        $RegimenFiscal = null;

        $usuario = User::whereNull('flag_eliminado')->find($user_id);

        if ($usuario)
        {
            $empresa = CfdiEmpresa::
                where('user_id',$user_id)
                ->first();

            if ($empresa && $empresa->RegimenFiscal)
            {
                $RegimenFiscal = $empresa->RegimenFiscal;
            }
        }

        $query = CalendarioFiscal::with('mi_regimen_fiscal');

        $query->where('fecha', $args->fecha);

        // Filtrar por regimen si se proporciona
        if ($RegimenFiscal) {
            $query->where(function($subquery) use ($RegimenFiscal) {
                $subquery->where('RegimenFiscal', $RegimenFiscal)
                         ->orWhereNull('RegimenFiscal');
            });
        }else{
            $query->whereNull('RegimenFiscal');
        }

        $coleccion = $query->get();

        if(count($coleccion)==0){
            return [
                'status'=>'error',
                'message'=>'No se encontraron eventos fiscales para la fecha solicitada.'
            ];
        }

        $coleccion = $coleccion->map(function ($obligacion) {
            $item = [
                'titulo' => $obligacion->titulo,
                'tipo' => $obligacion->tipo,
                'descripcion' => $obligacion->descripcion,
                
            ];

            if ($obligacion->RegimenFiscal) {
                $item['RegimenFiscal'] = $obligacion->mi_regimen_fiscal->texto;
            }

            return $item;
        });

        return [
            'status'=>'ok',
            'eventos'=>$coleccion
        ];
    }

    public function listar_calculadoras_fiscales($args, $user_id) 
    {

        $coleccion = Carpeta::with('documentos')
            ->has('documentos') // Filtra carpetas con al menos un documento
            ->get();

        if ($coleccion->isEmpty()) {
            return [
                'status'=>'error',
                'message' => 'Actualmente no hay calculadoras fiscales disponibles.'
            ];
        }

        $message = "Estas son las carpetas disponibles con calculadoras fiscales 📁:\n\n";
        foreach ($coleccion as $index => $carpeta) {
            $message .= "*" . ($index + 1) . ".* " . $carpeta->texto . "\n";
        }

        $message .= "\n*Por favor, indícame el nombre o número de la carpeta que deseas consultar.*";

        return [
            'status'=>'ok',
            'message'=>$message,
            'carpetas_validas' => $coleccion->pluck('texto')->values()->toArray()
        ];
    }

    public function listar_documentos_de_carpeta($args, $user_id)
    {
        $carpeta = Carpeta::
            // where('texto', 'like', $args->nombre_carpeta)
            whereRaw('LOWER(texto) = ?', [strtolower($args->nombre_carpeta)])
            ->with('documentos')
            ->first();

        if (!$carpeta) {
            return [
                'status'=>'error',
                'message' => 'No encontré una carpeta con ese nombre. Asegúrate de escribirlo exactamente como aparece.'
            ];
        }

        $documentos = $carpeta->documentos;
        if ($documentos->isEmpty()) {
            return [
                'status'=>'error',
                'message' => 'Esta carpeta no tiene documentos disponibles.'
            ];
        }

        $mensaje = "Documentos disponibles en *" . $carpeta->texto . "* 📄:\n\n";
        foreach ($documentos as $index => $doc) {
            $mensaje .= "*" . ($index + 1) . ".* " . $doc->texto . "\n";
        }

        $message .= "\n*Por favor, indícame el nombre o número del documento que deseas consultar.*";

        return [
            'status'=>'ok',
            'message'=>$message,
            'documentos_validos' => $documentos->pluck('texto')->values()->toArray()
        ];

    }

    public function seleccionar_calculadora($args, $user_id)
    {
        // Buscar carpeta por nombre (insensible a mayúsculas)
        $carpeta = Carpeta::whereRaw('LOWER(texto) = ?', [strtolower($args->nombre_carpeta)])->first();

        if (!$carpeta) {
            return [
                'status'=>'error',
                'message' => "No encontré la carpeta *$args->nombre_carpeta*. Asegúrate de escribir el nombre correctamente."
            ];
        }

        // Buscar documento por nombre dentro de esa carpeta (insensible a mayúsculas)
        $documento = Documento::where('carpeta_id', $carpeta->id)
            ->whereRaw('LOWER(texto) = ?', [strtolower($args->nombre_documento)])
            ->first();

        if (!$documento) {
            return [
                'status'=>'error',
                'message' => "No encontré el documento *$args->nombre_documento* en la carpeta *$args->nombre_carpeta*."
            ];
        }

        return [
            'status'=>'ok',
            'message'=>"Aquí tienes el documento *" . $documento->texto . "* 📊",
            'link'=>$documento->url,
        ];
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

    public function getTinyUrl(string $longUrl): string
    {
        $apiUrl = 'https://tinyurl.com/api-create.php?url=' . urlencode($longUrl);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $apiUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        $shortUrl = curl_exec($curl);
        curl_close($curl);

        return trim($shortUrl);
    }

    public function comprobantePdf($id, $comprobante_tipo)
    {

        set_time_limit(500);

        $tipo = '';

        //gastos
        if($comprobante_tipo == 1){
            $obj = Gasto::
                with('conceptos')
                ->find($id);

            $tipo = $obj->tipo->clave;
        }
        //ingresos
        else if($comprobante_tipo == 2){
            $obj = Ingreso::
                with('conceptos')
                ->find($id);

            if($obj->tipo_id == 1){
                $tipo = 'Ingreso contable';
            }else if($obj->tipo_id == 2){
                $tipo = 'Ingreso no contable';
            }
        }


        $cliente = User::find($obj->user_id);

        $rgb = $this->hexToRgb('#4285cb');

        $data = [
            'r' => $rgb['r'],
            'g' => $rgb['g'],
            'b' => $rgb['b'],
            'header' => $cliente->header,
            'footer' => $cliente->footer,

            'nombre' => $cliente->nombre,
            'email' => $cliente->email,
            'tipo' => $tipo,
            'created_at' => $obj->created_at,
            'detalle' => $obj->conceptos,
            'total' => $obj->total,
        ];

        //$pdf = Pdf::loadView('cotizaciones.cotizacion', $data);
        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('comprobantes.comprobante', $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs/comprobantes/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs/comprobantes/' . $nombreArchivo);

        return $url;
    }

    public function hexToRgb($hex) {
        // Elimina cualquier carácter no deseado del valor hexadecimal
        $hex = preg_replace('/[^a-f0-9]/i', '', $hex);

        // Verifica si el valor hexadecimal tiene 3 o 6 caracteres y ajusta si es necesario
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        // Convierte el valor hexadecimal a valores RGB
        $r = hexdec($hex[0] . $hex[1]);
        $g = hexdec($hex[2] . $hex[3]);
        $b = hexdec($hex[4] . $hex[5]);

        // Devuelve un arreglo con los valores RGB
        return array('r' => $r, 'g' => $g, 'b' => $b);
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

    public function historialPdf($user_id, $historial_tipo)
    {

        $user = User::whereNull('flag_eliminado')
            ->where('id', $user_id)
            ->first();

        if (!$user)
        {
            // return [
            //     'status'=>'error',
            //     'message'=>'Usuario no encontrado.'
            // ];

            return [
                'status'=>'error',
                'message'=>'No se encontraron registros en el mes en curso.'
            ];
        }

        // Obtener el primer y último día del mes actual
        $inicioMes = Carbon::now()->startOfMonth()->startOfDay();
        $finMes = Carbon::now()->endOfMonth()->endOfDay();

        // Si más adelante quieres permitir opcionalmente anio y mes por 
        // parámetros (por ejemplo, para un filtro de historial anterior), puedes hacer:
        // $anio = $request->input('anio', Carbon::now()->year);
        // $mes = $request->input('mes', Carbon::now()->month);
        // $inicioMes = Carbon::createFromDate($anio, $mes, 1)->startOfDay();
        // $finMes = Carbon::createFromDate($anio, $mes, 1)->endOfMonth()->endOfDay();

        $coleccion = [];

        if($historial_tipo == 'Gastos'){
            $coleccion = Gasto::whereNull('flag_eliminado')
            ->with(['tipo' => function ($query){
                $query->select('id','clave');
            }])
            ->where('user_id',$user->id)
            ->whereBetween('created_at', [$inicioMes, $finMes])
            ->orderBy('id', 'desc')
            ->get();
        }else{
            $coleccion = Ingreso::whereNull('flag_eliminado')
                ->where('user_id',$user->id)
                ->whereBetween('created_at', [$inicioMes, $finMes])
                ->orderBy('id', 'desc')
                ->get();
        }
        
        if (count($coleccion) == 0)
        {
            return [
                'status'=>'error',
                'message'=>'No se encontraron registros en el mes en curso.'
            ];
        }

        $rgb = $this->hexToRgb('#4285cb');

        $data = [

            'titulo' => $historial_tipo,

            'r' => $rgb['r'],
            'g' => $rgb['g'],
            'b' => $rgb['b'],
            'header' => $user->header,
            'footer' => $user->footer,

            'fecha' => date('d/m/Y'),

            'user' => $user,
            'coleccion' => $coleccion
            
        ];

        // return view('historial.historial_gastos_ingresos', $data);

        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('historial.historial_gastos_ingresos', $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs_reportes/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs_reportes/' . $nombreArchivo);

        return [
            'status'=>'ok',
            'message'=>count($coleccion) . 'registros encontrados.',
            'link'=>$url
        ];
        
    }

    public function historialFacturasPdf($user_id, $historial_tipo)
    {

        $user = User::whereNull('flag_eliminado')
            ->where('id', $user_id)
            ->with('cfdi_empresa')
            ->first();

        if (!$user || !$user->cfdi_empresa)
        {
            // return [
            //     'status'=>'error',
            //     'message'=>'Usuario no encontrado.'
            // ];

            return [
                'status'=>'error',
                'message'=>'No se encontraron registros en el mes en curso.'
            ];
        }

        // Obtener el primer y último día del mes actual
        $inicioMes = Carbon::now()->startOfMonth()->startOfDay();
        $finMes = Carbon::now()->endOfMonth()->endOfDay();

        // Si más adelante quieres permitir opcionalmente anio y mes por 
        // parámetros (por ejemplo, para un filtro de historial anterior), puedes hacer:
        // $anio = $request->input('anio', Carbon::now()->year);
        // $mes = $request->input('mes', Carbon::now()->month);
        // $inicioMes = Carbon::createFromDate($anio, $mes, 1)->startOfDay();
        // $finMes = Carbon::createFromDate($anio, $mes, 1)->endOfMonth()->endOfDay();

        $coleccion = [];

        //facturas en emitidas/canceladas
        $coleccion = CfdiComprobante::select('id','emisor_id','status','Serie','Folio','Fecha','Total','status_pay')
            ->where('emisor_id',$user->cfdi_empresa->id)
            ->whereIn('status', [1, 2])
            ->whereRaw("STR_TO_DATE(Fecha, '%Y-%m-%dT%H:%i:%s') BETWEEN ? AND ?", [$inicioMes, $finMes])
            ->with(['receptor' => function ($query){
                $query->select('id','comprobante_id','Rfc','Nombre');
            }])
            ->with(['archivo' => function ($query){
                $query->select('id','comprobante_id','xml_archivo','pdf');
            }])
            ->orderBy('id', 'desc')
            ->get();
        
        if (count($coleccion) == 0)
        {
            return [
                'status'=>'error',
                'message'=>'No se encontraron registros en el mes en curso.'
            ];
        }

        $rgb = $this->hexToRgb('#4285cb');

        $data = [

            'titulo' => $historial_tipo,

            'r' => $rgb['r'],
            'g' => $rgb['g'],
            'b' => $rgb['b'],
            'header' => $user->header,
            'footer' => $user->footer,

            'fecha' => date('d/m/Y'),

            'user' => $user,
            'coleccion' => $coleccion
            
        ];

        // return view('historial.historial_facturas', $data);

        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('historial.historial_facturas', $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs_reportes/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs_reportes/' . $nombreArchivo);

        return [
            'status'=>'ok',
            'message'=>count($coleccion) . 'registros encontrados.',
            'link'=>$url
        ];
        
    }
}