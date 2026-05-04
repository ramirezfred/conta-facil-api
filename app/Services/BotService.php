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
use App\Models\Producto;

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

//paquetes
use App\Models\Paquete;

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
    //----------Inicio Solicitar Aviso Privacidad
    public function solicitar_aviso_privacidad($args, $user_id)
    {
        return [
            'status'=>'ok',
            'message'=>$args->mensaje,
            'link'=>'https://apicontafacil.internow.com.mx/archivos_uploads/contexto_ai/AvisoSimplificado.pdf'
        ];
    }
    //----------Fin Solicitar Aviso Privacidad

    //----------Inicio Configurar Aviso de Privacidad
    public function configurar_aviso_privacidad($args, $user_id)
    {
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

        // --- Aviso de Privacidad ---
        $acepta_aviso_privacidad = $args->acepta_aviso_privacidad;

        if (!$acepta_aviso_privacidad) {
            return [
                'status' => 'error',
                'message' => 'Para poder continuar debes aceptar el Aviso de Privacidad.'
            ];
        }

        // Guardar datos de usuario
        $user->aviso_privacidad = $acepta_aviso_privacidad;
        $user->save();

        // Obtener datos de configuración 
        $datos_configuracion = $this->getStatusConfiguracion($user_id);
        
        return [
            'status'=>'ok',
            'datos_recolectados'=>$datos_configuracion['datos_recolectados'],
            'datos_faltantes'=>$datos_configuracion['datos_faltantes']
        ];
    }
    //----------Fin Configurar Aviso de Privacidad

    //----------Inicio Configurar Usuario
    public function configurar_usuario($args, $user_id)
    {
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

        // --- Email ---
        $email = $args->email;

        $aux = User::whereNull('flag_eliminado')
            ->where('email', $email)
            ->where('id', '<>', $user_id)
            ->first();
        if($aux){
            return [
                'status' => 'error',
                'message' => 'Ya existe un usuario con ese email.'
            ];
        }

        // Validar sintaxis del email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => 'error',
                'message' => 'El email no es válido. Verifica que tenga el formato correcto (usuario@dominio.com).'
            ];
        }

        // Guardar datos de usuario
        $user->email = $email;
        $user->save();

        // Obtener datos de configuración 
        $datos_configuracion = $this->getStatusConfiguracion($user_id);
        
        return [
            'status'=>'ok',
            'datos_recolectados'=>$datos_configuracion['datos_recolectados'],
            'datos_faltantes'=>$datos_configuracion['datos_faltantes']
        ];
    }
    //----------Fin Configurar Usuario

    //----------Inicio Configurar Emisor de CFDI
    public function configurar_emisor_cfdi($args, $user_id)
    {
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

        // =============================
        // VALIDAR DATOS DE ENTRADA
        // =============================

        // --- RFC ---
        $rfc = $args->rfc;

        // Normalizar: eliminar espacios o guiones y convertir a mayúsculas
        $rfc = strtoupper(str_replace([' ', '-'], '', $rfc));

        // Validar formato RFC
        $rfcValido = "/^[A-Z0-9]{12,13}$/";

        if (!preg_match($rfcValido, $rfc)) {
            $message = 'Por favor, verifica el Rfc. En el caso de que sea una persona física, este campo debe contener una longitud de 13 posiciones, si se trata de personas morales debe contener una longitud de 12 posiciones.';
            return [
                'status'=>'error',
                'message'=>$message
            ];
        }

        $Rfc_aux = CfdiEmpresa::
            where('Rfc',$rfc)
            ->with('user')
            ->first();

        if($Rfc_aux && $Rfc_aux->user->flag_eliminado == null){
            $message = 'Ya existe otro usuario con ese RFC.';
            return [
                'status'=>'error',
                'message'=>$message
            ];
        }

        // --- Razón social ---
        $razon_social = strtoupper($args->razon_social);

        // --- Régimen fiscal ---
        $regimen_fiscal_input = rtrim(trim($args->regimen_fiscal), '.');
        $regimen_fiscal = 
            Cfdi40RegimenFiscal::whereRaw("REPLACE(texto, '.', '') = ?", [str_replace('.', '', $regimen_fiscal_input)])
            ->first();

        if (!$regimen_fiscal) {
            return [
                'status'=>'error',
                'message'=>'El Régimen fiscal que ingresaste *'.$args->regimen_fiscal.'* no está disponible en nuestro catálogo. Por favor, intenta ingresar un Régimen fiscal diferente.'
            ];
        }

        // --- Código Postal ---
        $codigo_postal = str_replace([' ', '-'], '', $args->codigo_postal);

        // Validar código postal
        $cpValido = "/^[0-9]{5}$/";

        if (!preg_match($cpValido, $codigo_postal)) {
            $message = 'Por favor, verifica el Código Postal *'.$args->codigo_postal.'*. Este campo es el código postal del domicilio fiscal del emisor y debe contener una longitud de 5 posiciones.';
            return [
                'status'=>'error',
                'message'=>$message
            ];
        }

        // --- Certificado CSD (.cer) ---
        $certificado_csd = $this->moverArchivoCertificado($args->ruta_certificado_csd);

        if($certificado_csd['status'] == 'error'){
            return $certificado_csd;
        }

        // --- Clave privada CSD (.key) ---
        $clave_privada_csd = $this->moverArchivoCertificado($args->ruta_clave_privada_csd);

        if($clave_privada_csd['status'] == 'error'){
            return $clave_privada_csd;
        }

        // --- Password de clave privada ---
        $claveAdicional = config('app.lada_d');
        $cadenaEncriptada = Crypt::encrypt($args->password_clave_privada, $claveAdicional);

        // --- Timbrado de prueba para validar datos ---
        $emisor = [
            'Rfc' => $rfc,
            'RazonSocial' => $razon_social,
            'RegimenFiscal' => $regimen_fiscal->id,
            'CP' => $codigo_postal,
            'cer' => $certificado_csd['url'],
            'key' => $clave_privada_csd['url'],
            'pass' => $cadenaEncriptada
        ];

        $resTimbrado = $this->timbrarFacturaDePrueba($emisor);

        if($resTimbrado['status'] == 'error'){
            return $resTimbrado;
        }

        // Guardar datos de emisor_cfdi
        $tieneCfdiEmpresas = CfdiEmpresa::
            where('user_id', $user_id)
            ->exists();

        $esPrimerEmisor = !$tieneCfdiEmpresas;

        // 5. Guardar en Base de Datos
        $emisor = CfdiEmpresa::create([
            'user_id'        => $user_id,
            'Rfc'            => $rfc,
            'RazonSocial'   => $razon_social,
            'RegimenFiscal' => $regimen_fiscal->id,
            'CP'             => $codigo_postal,
            'pass'   => $cadenaEncriptada,
            'cer'       => $certificado_csd['url'],
            'key'       => $clave_privada_csd['url'],

            'flag_descuento'=>0,
            'flag_objetoImp'=>1,
            'flag_retencion'=>0,
            'flag_producto'=>0,

            // Activar funcionalidades adicionales solo para el primer emisor registrado por el usuario
            'emisor_bot'      => true,
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
            'Email'=>$user->email,
            'user_id'=>$user->id,
            'tipo_entidad'=>'cliente',
            'tipo_cliente'=>'cliente',
            'origen'=>'pos',
        ]);

        // Obtener datos de configuración 
        $datos_configuracion = $this->getStatusConfiguracion($user_id);
        
        return [
            'status'=>'ok',
            'datos_recolectados'=>$datos_configuracion['datos_recolectados'],
            'datos_faltantes'=>$datos_configuracion['datos_faltantes']
        ];
    }
    //----------Fin Configurar Emisor de CFDI

    //----------Inicio Configurar Facturación de Ingresos Contables
    public function configurar_facturacion_ingresos($args, $user_id)
    {
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

        // =============================
        // VALIDAR DATOS DE ENTRADA
        // =============================

        // --- Frecuencia ---
        if (strtoupper($args->frecuencia) != 'SEMANAL' && strtoupper($args->frecuencia) != 'MENSUAL') {
            return [
                'status'=>'error',
                'message'=>'La Frecuencia que ingresaste *'.$args->frecuencia.'* no está disponible en nuestro catálogo. Por favor, intenta ingresar una Frecuencia diferente.'
            ];
        }

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

        // --- Clave de Producto/Servicio ---
        $clave_producto_servicio = Cfdi40ProductoServicio::
            where('id', $args->clave_producto_servicio)->first();

        if (!$clave_producto_servicio) {
            return [
                'status'=>'error',
                'message'=>'La Clave de Producto/Servicio que ingresaste *'.$args->clave_producto_servicio.'* no está disponible en nuestro catálogo. Por favor, intenta ingresar una Clave de Producto/Servicio diferente.'
            ];
        }

        // --- Clave de Unidad ---
        $clave_unidad = Cfdi40ClaveUnidad::
            where('id', $args->clave_unidad)
            ->orWhere('texto', $args->clave_unidad)
            ->first();

        if (!$clave_unidad) {
            return [
                'status'=>'error',
                'message'=>'La Clave de Unidad que ingresaste *'.$args->clave_unidad.'* no está disponible en nuestro catálogo. Por favor, intenta ingresar una Clave de Unidad diferente.'
            ];
        }

        $cfdi_producto = CfdiProducto::
            where('user_id',$user_id)
            ->first();

        if (!$cfdi_producto)
        {
            return [
                'status'=>'error',
                'message'=>'Producto no encontrado.'
            ];
        }

        // Guardar datos de facturacion_ingresos
        $user->tipo_algoritmo_factura = strtoupper($args->frecuencia) == 'SEMANAL' ? 1 : 2;
        $user->save();

        $cfdi_producto->FormaPago = $forma_pago->id;
        $cfdi_producto->ClaveProdServ = $clave_producto_servicio->id_aux;
        $cfdi_producto->ClaveUnidad = $clave_unidad->id_aux;
        $cfdi_producto->Unidad = $clave_unidad->id;

        $acentos  = ['á','à','ä','â','Á','À','Ä','Â',
                    'é','è','ë','ê','É','È','Ë','Ê',
                    'í','ì','ï','î','Í','Ì','Ï','Î',
                    'ó','ò','ö','ô','Ó','Ò','Ö','Ô',
                    'ú','ù','ü','û','Ú','Ù','Ü','Û',
                    'ñ','Ñ','ç','Ç'];
        $sinAcento= ['a','a','a','a','A','A','A','A',
                    'e','e','e','e','E','E','E','E',
                    'i','i','i','i','I','I','I','I',
                    'o','o','o','o','O','O','O','O',
                    'u','u','u','u','U','U','U','U',
                    'n','N','c','C'];
        $Descripcion = str_replace($acentos, $sinAcento, $clave_producto_servicio->texto);

        $cfdi_producto->Descripcion = $Descripcion;
        $cfdi_producto->save();

        // Obtener datos de configuración 
        $datos_configuracion = $this->getStatusConfiguracion($user_id);
        
        return [
            'status'=>'ok',
            'datos_recolectados'=>$datos_configuracion['datos_recolectados'],
            'datos_faltantes'=>$datos_configuracion['datos_faltantes']
        ];
    }
    //----------Fin Configurar Facturación de Ingresos Contables

    //----------Inicio Gastos
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
    //----------Fin Gastos

    //----------Inicio Ingresos
    public function crear_ingreso($args, $user_id) 
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

        $cfdi_empresa = CfdiEmpresa::
            where('user_id', $user_id)
            ->where('emisor_ingresos', true)
            ->first();

        //Si el ingreso es contable, Validacion para user resico
        if($args->categoria == 'Contable' && $cfdi_empresa){

            $limite_facturacion = $this->determinarLimiteFacturacion($cfdi_empresa->Rfc,$cfdi_empresa->RegimenFiscal);
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
    //----------Fin Ingresos

    //----------Inicio Facturas
    public function listar_receptores($args, $user_id) 
    {

        $cfdi_empresa = CfdiEmpresa::
            where('user_id', $user_id)
            ->where('emisor_bot', true)
            ->first();

        if (!$cfdi_empresa)
        {
            return [
                'status'=>'error',
                'message' => 'No tienes receptores registrados. Por favor, proporcióname todos los datos para emitir la factura.'
            ];
        }

        $receptores = CfdiCliente::activos()
            ->where('empresa_id', $cfdi_empresa->id)
            ->whereIn("tipo_entidad", ["cliente", "ambos"])
            ->with('mi_regimen_fiscal')
            ->with('mi_uso_cfdi')
            ->orderByDesc('id') // opcional: los más recientes primero
            ->take(20)
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

    public function resumen_factura($args, $user_id)
    {

        $resumen = [];

        // Validar conceptos
        if (empty($args->conceptos)) {
            return [
                "status" => "error",
                "mensaje" => "No se recibieron conceptos para la factura"
            ];
        }

        // --- Retenciones ---
        $TasaIva = 0;
        $TasaIsr = 0;

        if ($args->retenciones == 'Con retenciones') {
            $TasaIva = 16;
            $TasaIsr = 1.25;
        }

        // Inicializar totales
        $TotalImpuestosTrasladados = 0;
        $TotalImpuestosRetenidos = 0;
        $TotalImpuestosRetenidosIva = 0;
        $TotalImpuestosRetenidosIsr = 0;
        $totalDescuentos = 0;
        $subtotal = 0;

        // Se agregan los conceptos
        for ($i=0; $i < count($args->conceptos); $i++) { 
            $concepto = $args->conceptos[$i];

            // Usamos 4 decimales como lo indicaste
            $cantidad = round($concepto->cantidad, 4);
            $valorUnitario = round($concepto->valor_unitario, 4);
            $importeConcepto = round($cantidad * $valorUnitario, 4);
            // $descuentoConcepto = round($concepto->Descuento, 4);
            $descuentoConcepto = 0;

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

            if (/*$concepto->ObjetoImp == 1*/true) {
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
                if (/*$concepto->ObjetoImpRet == 1*/isset($args->retenciones) && $args->retenciones === "Con retenciones") {
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

        // Procesar conceptos
        $conceptosResumen = [];
        foreach ($datos['conceptos'] as $concepto) {

            $conceptosResumen[] = [
                "descripcion"      => $concepto['descripcion'],
                "clave_prod_serv"  => $concepto['ClaveProdServ'],
                "clave_unidad"     => $concepto['ClaveUnidad'],
                "cantidad"         => $concepto['cantidad'],
                "valor_unitario"   => $concepto['valorunitario'],
                "importe"          => $concepto['importe']
            ];

        }

        if ($TotalImpuestosRetenidosIva > 0 || $TotalImpuestosRetenidosIsr > 0) {
            $retenciones = $datos['impuestos']['TotalImpuestosRetenidos'];
        }else{
            $retenciones = 0;
        }

        $resumen = [
            "receptor" => $args->receptor,
            "forma_pago" => $args->forma_pago,
            "retenciones" => $args->retenciones,
            "conceptos" => $conceptosResumen,
            "totales" => [
                "subtotal" => $datos['factura']['subtotal'],
                "IVA" => $datos['impuestos']['TotalImpuestosTrasladados'], //iva (impuestos_trasladados)
                "retenciones" => $retenciones, //retenciones (impuestos_retenidos)
                "total" => $datos['factura']['total']
            ],
            "leyenda" => "Por defecto, la factura se emitirá en modalidad Más IVA."

        ];

        return [
            "status" => "ok",
            "resumen" => $resumen,
            "mensaje_confirmacion" => "¿Confirmas que todos los datos y cálculos son correctos? Responde 'Sí, confirmo' para crear la factura."
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

        // Verificar valores
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
            // Usamos 4 decimales como lo indicaste
            $conceptos[$i]->cantidad = round($conceptos[$i]->cantidad, 4);
            $conceptos[$i]->valor_unitario = round($conceptos[$i]->valor_unitario, 4);
            $conceptos[$i]->importeConcepto = round($conceptos[$i]->cantidad * $conceptos[$i]->valor_unitario, 4);

            $conceptos[$i]->clave_prod_serv = $concepto_clave_prod_serv->id_aux;
            $conceptos[$i]->clave_unidad = $concepto_clave_unidad->id_aux; 
            $conceptos[$i]->unidad = $concepto_clave_unidad->texto;

            $conceptos[$i]->descuento = 0;
            $conceptos[$i]->ObjetoImp = 1;
            $conceptos[$i]->ObjetoImpRet = ($args->retenciones == 'Con retenciones') ? 1 : 0;
            $conceptos[$i]->producto_id = null;
 
        }

        // Inicializar totales
        $TotalImpuestosTrasladados = 0;
        $TotalImpuestosRetenidos = 0;
        $TotalImpuestosRetenidosIva = 0;
        $TotalImpuestosRetenidosIsr = 0;
        $totalDescuentos = 0;
        $subtotal = 0;

        // Se agregan los conceptos
        for ($i=0; $i < count($args->conceptos); $i++) { 
            $concepto = $args->conceptos[$i];

            // Usamos 4 decimales como lo indicaste
            $cantidad = $concepto->cantidad;
            $valorUnitario = $concepto->valor_unitario;
            $importeConcepto = round($cantidad * $valorUnitario, 4);
            // $descuentoConcepto = round($concepto->Descuento, 4);
            $descuentoConcepto = 0;

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


        $cliente = User::whereNull('flag_eliminado')
            ->where('id', $user_id)
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

        $cfdi_empresa = CfdiEmpresa::
            where('user_id', $user_id)
            ->where('emisor_pos', true)
            ->first();

        if (!$cfdi_empresa)
        {
            return [
                'status'=>'error',
                'message'=>'Emisor no encontrado.'
            ];
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
            }else if(($total_facturado + $datos['factura']['total']) >= $limite_facturacion){
                return [
                    'status'=>'error',
                    'message'=>'El total de la factura excede el límite de $'.$limite_facturacion.' pesos mensuales facturables.'
                ];
            }

        }

        if ($cliente->count_timbres < 1) {
            return [
                'status'=>'error',
                'message'=>'No cuentas con timbres disponibles. Te recomendamos adquirir un paquete de timbres para continuar disfrutando de nuestros servicios de timbrado.'
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
            // 'Serie'=>"S-".$empresa->id."-".$Serie,
            // 'Folio'=>"F-".$empresa->id."-".$Folio,
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
                'Descuento' => $conceptos[$i]->descuento,
                'ObjetoImp' => $conceptos[$i]->ObjetoImp,
                'ObjetoImpRet' => $conceptos[$i]->ObjetoImpRet,
                'producto_id' => $conceptos[$i]->producto_id,
            ]);
        }

        $resTimbrado = $this->timbrarFactura($pedidoCurso->id);

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
                if ($pedidoCurso->emisor) {
                    $pedidoCurso->emisor->delete();
                }
                if ($pedidoCurso->receptor) {
                    $pedidoCurso->receptor->delete();
                }
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
                    'origen'=>'cfdi',
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

            return [
                'status'=>'ok',
                'message'=>'Factura timbrada exitosamente.',
                // 'factura_id'=>$pedidoCurso->id,
                'receptor'=>$args->receptor,
                'link'=>$document
            ];
        }
    }

    public function cancelar_factura($args, $user_id)
    {

        $cliente = User::whereNull('flag_eliminado')
            ->where('id', $user_id)
            ->first();

        if (!$cliente)
        {
            return [
                'status'=>'error',
                'message'=>'Usuario no encontrado.'
            ];
        }

        $Serie = strtoupper($args->serie);

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
            ->where('user_id',$user_id)
            ->where('Serie',$Serie)
            ->first();
            
        if(!$factura){
            return [
                'status'=>'error',
                'message'=>'Factura no encontrada.'
            ];
        }

        if($factura->status == 2){
            return [
                'status'=>'error',
                'message'=>'Su factura ya está marcada como cancelada.'
            ];
        }

        if(!$factura->timbre_fiscal_digital){
            return [
                'status'=>'error',
                'message'=>'Su factura no tiene un timbre para cancelar.'
            ];
        }

        $emisor = CfdiEmpresa::
            with('mi_regimen_fiscal')
            ->find($factura->emisor_id);

        if (!$emisor)
        {
            return [
                'status'=>'error',
                'message'=>'Emisor no encontrado.'
            ];
        }

        // --- Validar datos de Emisor ---
        $camposRequeridosEmisor = [
            'Rfc', 'RazonSocial', 'RegimenFiscal',
            'CP', 'cer', 'key', 'pass'
        ];

        foreach ($camposRequeridosEmisor as $campo) {
            if (empty($emisor->$campo)) {
                $message = 'Para cancelar una factura, primero debes configurar tus datos de emisor.';
                return [
                    'status'=>'error',
                    'message'=>$message
                ];
            }
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

        $datos["motivo"]="03";
        // $datos["motivo"]=$request->input('motivo');
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

            return [
                'status'=>'ok',
                'message'=>'Factura cancelada con éxito.'
            ];
        }
        else if(
            isset($res['codigo_mf_texto']) &&
            isset($res['codigo_respuesta_sat_texto']) &&
            $res['codigo_mf_texto'] == "OK" &&
            $res['codigo_respuesta_sat_texto'] == "No Existe" 
        )
        {

            $message = 'Su factura no existe en el portal del SAT. Si emites una factura electrónica y quieres cancelarla, debes esperar al menos 72 horas antes de hacerlo.';

            return [
                'status'=>'error',
                'message'=>$message
            ];

        }
        else {

            $message = 'Error al conectar con la librería de timbrado.';

            return [
                'status'=>'error',
                'message'=>$message
            ];
        }
        
    }
    //----------Fin Facturas

    //----------Inicio Historial
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
    //----------Fin Historial

    //----------Inicio Calendario Fiscal
    public function obtener_eventos_fiscales($args, $user_id){
        

        $RegimenFiscal = null;

        $usuario = User::whereNull('flag_eliminado')->find($user_id);

        if ($usuario)
        {
            $empresa = CfdiEmpresa::
                where('user_id',$user_id)
                ->where('emisor_bot', true)
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
    //----------Fin Calendario Fiscal

    //----------Inicio Calculadoras
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
    //----------Fin Calculadoras

    //----------Inicio Paquetes
    public function obtener_categorias_paquetes()
    {

        return [
            'status'=>'ok',
            'categorias' => ['Timbres', 'Asesorias', 'Servicios']
        ];
    }

    public function listar_paquetes_por_categoria($args, $user_id)
    {

        $categoria = strtolower($args->categoria);

        $tipo = null; 

        if($categoria == 'timbres'){
            $tipo = 1;
        }else if($categoria == 'asesorias' || $categoria == 'asesorías'){
            $tipo = 2;
        }else if($categoria == 'servicios'){
            $tipo = 3;
        }

        if (!$tipo) {
            return [
                'status'=>'error',
                'message' => 'Categoría no válida.'
            ];
        }

        $coleccion = Paquete::whereNull('flag_eliminado')
            ->where('status',1)
            ->where('tipo',$tipo)
            ->get();

        if ($coleccion->isEmpty()) {
            return [
                'status'=>'error',
                'message' => 'Actualmente no hay paquetes disponibles en la categoría ' . $args->categoria
            ];
        }

        $message = "Paquetes disponibles en la categoría *" . $args->categoria . "*:\n\n";
        foreach ($coleccion as $index => $p) {
            $message .= "*" . ($index + 1) . ".* " . ($p->nombre ?? '-') . "\n\n";

            if ($tipo == 1) {
                $message .= "Timbres: " . ($p->cantidad ?? '-') . "\n";
            }

            $message .= "Precio: $" . ($p->precio ?? '-') . "\n\n";
        }

        $message .= "\n*¿Te gustaría ver más detalles de alguno de ellos o deseas comprar uno en particular?*";

        $paquetes_validos = $coleccion->map(function ($p) use ($tipo) {
            $item = [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'precio' => $p->precio
            ];

            if ($tipo == 1) {
                $item['timbres'] = $p->cantidad;
            }

            return $item;
        })->toArray();

        return [
            'status'=>'ok',
            'message'=>$message,
            'paquetes_validos' => $paquetes_validos
        ];
    }

    public function obtener_detalle_paquete($args, $user_id)
    {
        // Buscar paquete por nombre (insensible a mayúsculas)
        $paquete = Paquete::whereNull('flag_eliminado')
            ->where('status',1)
            ->whereRaw('LOWER(nombre) = ?', [strtolower($args->nombre_paquete)])
            ->first();

        if (!$paquete) {
            return [
                'status'=>'error',
                'message' => "No encontré el paquete *$args->nombre_paquete*. Asegúrate de escribir el nombre correctamente."
            ];
        }

        return [
            'status'=>'ok',
            'nombre' => $paquete->nombre,
            'descripcion' => $paquete->descripcion,
            'precio' => $paquete->precio,
            'cantidad' => $paquete->tipo === 1 ? $paquete->cantidad : null,
            'link'=>$paquete->imagen
        ];
    }

    public function generar_link_pago_paquete($args, $user_id)
    {
        // Buscar paquete por nombre (insensible a mayúsculas)
        $paquete = Paquete::whereNull('flag_eliminado')
            ->where('status',1)
            ->whereRaw('LOWER(nombre) = ?', [strtolower($args->nombre_paquete)])
            ->first();

        if (!$paquete) {
            return [
                'status'=>'error',
                'message' => "No encontré el paquete *$args->nombre_paquete*. Asegúrate de escribir el nombre correctamente."
            ];
        }

        $user_token=User::find($user_id);
        $token = JWTAuth::fromUser($user_token);

        $claveAdicional = config('app.lada_a');
        $cadenaEncriptada = Crypt::encrypt($user_id, $claveAdicional);

        $link = 'https://contafacil.internow.com.mx/#/bot-paquetes/'. $paquete->id .'/'.$cadenaEncriptada.'/'.$token;

        return [
            'status'=>'ok',
            'nombre' => $paquete->nombre,
            'precio' => $paquete->precio,
            'cantidad' => $paquete->tipo === 1 ? $paquete->cantidad : null,
            'link'=>$link
        ];
    }
    //----------Fin Paquetes

    //----------Inicio Resumen Financiero
    public function obtener_resumen_financiero($args, $user_id)
    {
        // Fechas de referencia
        $mesActualInicio = Carbon::now()->startOfMonth();
        $mesActualFin = Carbon::now()->endOfMonth();
        $mesAnteriorInicio = Carbon::now()->subMonth()->startOfMonth();
        $mesAnteriorFin = Carbon::now()->subMonth()->endOfMonth();

        // Ingresos
        $ingresosMesActual = Ingreso::whereNull('flag_eliminado')
            ->where('user_id', $user_id)
            ->whereBetween('created_at', [$mesActualInicio, $mesActualFin])
            ->sum('total');

        $ingresosMesAnterior = Ingreso::whereNull('flag_eliminado')
            ->where('user_id', $user_id)
            ->whereBetween('created_at', [$mesAnteriorInicio, $mesAnteriorFin])
            ->sum('total');

        // Gastos
        $gastosMesActual = Gasto::whereNull('flag_eliminado')
            ->where('user_id', $user_id)
            ->whereBetween('created_at', [$mesActualInicio, $mesActualFin])
            ->sum('total');

        $gastosMesAnterior = Gasto::whereNull('flag_eliminado')
            ->where('user_id', $user_id)
            ->whereBetween('created_at', [$mesAnteriorInicio, $mesAnteriorFin])
            ->sum('total');

        $emisor_id = null;
        

        // Facturación
        $facturacionMesActual = CfdiComprobante::where('user_id', $user_id)
            ->where('status', 1)
            ->whereBetween('created_at', [$mesActualInicio, $mesActualFin])
            ->sum('Total');

        $facturacionMesAnterior = CfdiComprobante::where('user_id', $user_id)
            ->where('status', 1)
            ->whereBetween('created_at', [$mesAnteriorInicio, $mesAnteriorFin])
            ->sum('Total');

        // Facturación total por cliente
        $facturacionPorCliente = CfdiComprobante::
            select('cfdi_receptor.Rfc', 'cfdi_receptor.Nombre', DB::raw('SUM(cfdi_comprobante.Total) as total'))
            ->join('cfdi_receptor', 'cfdi_receptor.comprobante_id', '=', 'cfdi_comprobante.id')
            ->where('cfdi_comprobante.status', 1)
            ->where('cfdi_comprobante.user_id', $user_id)
            ->whereBetween('cfdi_comprobante.created_at', [$mesActualInicio, $mesActualFin])
            ->groupBy('cfdi_receptor.Rfc', 'cfdi_receptor.Nombre')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) {
                return [
                    'cliente' => $item->Nombre ?? 'Desconocido',
                    'total' => round($item->total, 2),
                ];
            });

        // Top 5 categorías de gasto
        $topCategorias = Gasto::select('tipo_id', DB::raw('SUM(total) as total'))
            ->whereNull('flag_eliminado')
            ->whereBetween('created_at', [$mesActualInicio, $mesActualFin])
            // ->whereHas('tipo', function ($query) {
            //     $query->whereNull('flag_eliminado');
            // })
            ->where('user_id', $user_id)
            ->groupBy('tipo_id')
            ->with(['tipo:id,clave'])
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'categoria' => $item->tipo->clave ?? 'Sin categoría',
                    'total' => round($item->total, 2),
                ];
            });

        // Cálculo de variaciones
        $variacionIngresos = $this->calcularVariacion($ingresosMesActual, $ingresosMesAnterior);
        $variacionGastos = $this->calcularVariacion($gastosMesActual, $gastosMesAnterior);
        $variacionFacturacion = $this->calcularVariacion($facturacionMesActual, $facturacionMesAnterior);

        // Texto de análisis
        $analisisTexto = sprintf(
            "Este mes tus ingresos %s un %.2f%% respecto al mes anterior, tus gastos %s un %.2f%%, y la facturación %s un %.2f%%. El mayor gasto fue en %s.",
            $variacionIngresos >= 0 ? "aumentaron" : "disminuyeron",
            abs($variacionIngresos),
            $variacionGastos >= 0 ? "aumentaron" : "disminuyeron",
            abs($variacionGastos),
            $variacionFacturacion >= 0 ? "aumentó" : "disminuyó",
            abs($variacionFacturacion),
            $topCategorias->first()['categoria'] ?? 'N/A'
        );

        return [
            "mes_actual" => [
                "ingresos_totales" => round($ingresosMesActual, 2),
                "gastos_totales" => round($gastosMesActual, 2),
                "facturacion_total" => round($facturacionMesActual, 2),
                "facturacion_por_cliente" => $facturacionPorCliente,
                "top_categorias_gasto" => $topCategorias
            ],
            "comparativa" => [
                "ingresos" => [
                    "mes_actual" => round($ingresosMesActual, 2),
                    "mes_anterior" => round($ingresosMesAnterior, 2),
                    "variacion_pct" => round($variacionIngresos, 2)
                ],
                "gastos" => [
                    "mes_actual" => round($gastosMesActual, 2),
                    "mes_anterior" => round($gastosMesAnterior, 2),
                    "variacion_pct" => round($variacionGastos, 2)
                ],
                "facturacion" => [
                    "mes_actual" => round($facturacionMesActual, 2),
                    "mes_anterior" => round($facturacionMesAnterior, 2),
                    "variacion_pct" => round($variacionFacturacion, 2)
                ]
            ],
            "analisis_texto" => $analisisTexto
        ];
    }

    private function calcularVariacion($actual, $anterior)
    {
        if ($anterior == 0) {
            return $actual > 0 ? 100 : 0;
        }
        return (($actual - $anterior) / $anterior) * 100;
    }
    //----------Fin Resumen Financiero

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

        //total facturado
        $total = CfdiComprobante::
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
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
            ->where('user_id',$user_id)
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

    public function timbrarFactura($factura_id)
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

        $historial = [];
        if($historial_tipo == 'Gastos'){

            $historial = $coleccion->map(function ($r) {
                return [
                    'fecha' => $r->created_at,
                    'total' => $r->total,
                    'categoria' => $r->tipo->clave,
                ];
            })->toArray();

        }else{
            $historial = $coleccion->map(function ($r) {
                return [
                    'fecha' => $r->created_at,
                    'total' => $r->total,
                    'categoria' => $r->tipo_id == 1 ? 'Contable' : ($r->tipo_id == 2 ? 'No Contable' : 'Desconocido'),
                ];
            })->toArray();
        }

        return [
            'status'=>'ok',
            'message'=>count($coleccion) . 'registros encontrados.',
            'historial'=>$historial,
            'link'=>$url
        ];
        
    }

    public function historialFacturasPdf($user_id, $historial_tipo)
    {

        $user = User::whereNull('flag_eliminado')
            ->where('id', $user_id)
            ->with(['cfdi_empresa' => function ($query) {
                // Aquí agregas la condición a la relación
                $query->where('emisor_bot', true);
            }])
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

        $historial = $coleccion->map(function ($r) {
            return [
                'fecha' => $r->Fecha,
                'total' => $r->Total,
                'receptor_rfc' => $r->receptor->Rfc,
                // 'receptor_nombre' => $r->receptor->Nombre
            ];
        })->toArray();

        return [
            'status'=>'ok',
            'message'=>count($coleccion) . 'registros encontrados.',
            'historial'=>$historial,
            'link'=>$url
        ];
        
    }

    public function getStatusConfiguracion($user_id)
    {
        $user = User::find($user_id);

        $config_completed = false;
        $config_aviso_privacidad_completed = false;
        $config_usuario_completed = false;
        $config_emisor_cfdi_completed = false;
        $config_facturacion_ingresos_completed = false;

        $datos_recolectados = [];

        if ($user->aviso_privacidad) {
            $datos_recolectados['aviso_privacidad'] = [];
            $datos_recolectados['aviso_privacidad']['acepta_aviso_privacidad'] = $user->aviso_privacidad;

            $config_aviso_privacidad_completed = true;
        }

        if (strpos($user->email, "@contafacil.com") === false) {
            // echo "La cadena 1 NO contiene '@contafacil.com'.";
            $datos_recolectados['usuario'] = [];
            $datos_recolectados['usuario']['email'] = $user->email;
            $config_usuario_completed = true;
        }

        $cfdi_empresa = CfdiEmpresa::
            where('user_id',$user_id)
            ->where('emisor_bot', true)
            ->with('mi_regimen_fiscal')
            ->first();

        if (
            !empty($cfdi_empresa) && !empty($cfdi_empresa->Rfc)
        ) {

            $datos_recolectados['emisor_cfdi'] = [];
            $datos_recolectados['emisor_cfdi']['rfc'] = $cfdi_empresa->Rfc;
            $datos_recolectados['emisor_cfdi']['razon_social'] = $cfdi_empresa->RazonSocial;
            $datos_recolectados['emisor_cfdi']['regimen_fiscal'] = $cfdi_empresa->mi_regimen_fiscal->texto;
            $datos_recolectados['emisor_cfdi']['codigo_postal'] = $cfdi_empresa->CP;
            $datos_recolectados['emisor_cfdi']['clave_privada_csd'] = '(Archivo cargado)';
            $datos_recolectados['emisor_cfdi']['certificado_csd'] = '(Archivo cargado)';
            $datos_recolectados['emisor_cfdi']['password_clave_privada'] = '(Configurada)';

            $config_emisor_cfdi_completed = true;
        }

        $cfdi_producto = CfdiProducto::
            where('user_id',$user_id)
            ->with('mi_clave_prod_serv')
            ->with('mi_clave_unidad')
            ->with('mi_forma_pago')
            ->first();

        if (
            !empty($user->tipo_algoritmo_factura) && 
            !empty($cfdi_producto) &&
            !empty($cfdi_producto->ClaveProdServ)
        ) {

            
            $datos_recolectados['facturacion_ingresos'] = [];
            $datos_recolectados['facturacion_ingresos']['frecuencia'] = $user->tipo_algoritmo_factura == 1 ? 'Semanal' : 'Mensual';
            
            $datos_recolectados['facturacion_ingresos']['clave_producto_servicio'] = $cfdi_producto->mi_clave_prod_serv->id;
            $datos_recolectados['facturacion_ingresos']['clave_unidad'] = $cfdi_producto->mi_clave_unidad->id;

            // $forma_pago = Cfdi40FormaPago::find($cfdi_producto->FormaPago);

            if ($cfdi_producto->mi_forma_pago)
            {
                $datos_recolectados['facturacion_ingresos']['forma_pago'] = $cfdi_producto->mi_forma_pago->texto;
            }

            $config_facturacion_ingresos_completed = true;

        }

        if(
            $config_aviso_privacidad_completed &&
            $config_usuario_completed &&
            $config_emisor_cfdi_completed &&
            $config_facturacion_ingresos_completed 
        )
        {
            $config_completed = true;
        }

        $datos_faltantes = [];

        // Revisar datos faltantes de Emisor CFDI
        if(!isset($datos_recolectados['emisor_cfdi'])){
            $datos_faltantes['emisor_cfdi'] = ["rfc", "razon_social", "regimen_fiscal", 
            "codigo_postal", "ruta_certificado_csd", "ruta_clave_privada_csd", "password_clave_privada"];
        }

        // Revisar datos faltantes de Facturación Ingresos
        if(!isset($datos_recolectados['facturacion_ingresos'])){
            $datos_faltantes['facturacion_ingresos'] = ["frecuencia", "forma_pago", "clave_producto_servicio", "clave_unidad"];
        }

        // Revisar datos faltantes de Usuario
        if(!isset($datos_recolectados['usuario'])){
            $datos_faltantes['usuario'] = ["email"];
        }

        // Revisar datos faltantes de Aviso de Privacidad
        if(!isset($datos_recolectados['aviso_privacidad'])){
            $datos_faltantes['aviso_privacidad'] = ["acepta_aviso_privacidad"];
        }

        return [
            'datos_recolectados'=>$datos_recolectados,
            'datos_faltantes'=>$datos_faltantes,
            'config_completed'=>$config_completed
        ];
    }

    public function moverArchivoCertificado($sourcePath)
    {
        try {
            // Obtener el nombre del archivo desde la ruta
            $fileName = basename($sourcePath);

            // Ruta absoluta al archivo en storage
            $absoluteSource = storage_path('app/' . str_replace('storage/app/', '', $sourcePath));

            if (!file_exists($absoluteSource)) {
                return [
                    'status'=>'error',
                    'message'=>"El archivo de origen no existe en {$absoluteSource}"
                ];
            }

            // Definir destino en public/
            $destinationPath = public_path('sdk2/certificados/');
            
            // Crear la carpeta si no existe
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }

            // Ruta final
            $destinationFile = $destinationPath . $fileName;

            // Mover archivo
            if (!copy($absoluteSource, $destinationFile)) {
                return [
                    'status'=>'error',
                    'message'=>"Error copiando archivo de {$absoluteSource} a {$destinationFile}"
                ];
            }

            // URL accesible públicamente
            $url = asset('sdk2/certificados/' . $fileName);

            return [
                'status'=>'ok',
                'url'=>$url
            ];

        } catch (\Exception $e) {
            return [
                'status'=>'error',
                'message'=>$e->getMessage()
            ];
        }
    }

}