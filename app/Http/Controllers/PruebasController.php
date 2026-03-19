<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\View;
//use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

use Exception;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;


use DB;
use DateTime;
use DateInterval;

use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;

use Illuminate\Support\Facades\Crypt;

use CURLFILE;

use Barryvdh\DomPDF\Facade\Pdf;

use Smalot\PdfParser\Parser;

use Illuminate\Support\Facades\Cache;

use Mail;
use Session;
use Redirect;
use Swift_SmtpTransport;
use Swift_Mailer;

use App\Models\User;
use App\Models\BotMessage;

use App\Models\PosCashRegister;
use App\Models\PosOrder;
use App\Models\PosOrderDetail;
use App\Models\PosOrderPayment;
use App\Models\PosCashRegisterMovement;
use App\Models\ErpProduct;
use App\Models\ErpCategory;
use App\Models\Ingreso;

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

use Illuminate\Support\Facades\Validator;

class PruebasController extends Controller
{
    
    public function testImage()
    {

        //$image = Image::make($image->getRealPath());
        $image = Image::make("https://apicontafacil.internow.com.mx/images_uploads/header_footer/05.02.24.12.55.14.png");

        $image->resize(300, 300, function ($constraint) {
            $constraint->aspectRatio();
        });

        $palette = $image->palette();

        $dominantColors = $palette->getDominantColors(5); // Obtener 5 colores dominantes

        foreach ($dominantColors as $color) {
            $hex = $color['hex'];
            $rgb = $color['rgb'];
            $count = $color['count'];

            // Procesar información de color individual
            echo "Color: $hex ($rgb) - Cantidad: $count\n";
        }

        // Regresar una respuesta exitosa
        //return response('OK', 200);
        
    }

    public function extractColors() {

        $imagePath = 'https://apicontafacil.internow.com.mx/images_uploads/header_footer/05.02.24.12.55.14.png';
        $numColors = 5;

        $colors = [];

        // Cargar la imagen
        $img = imagecreatefromstring(file_get_contents($imagePath));
        $width = imagesx($img);
        $height = imagesy($img);

        // Iterar a través de cada píxel
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                // Obtener el color del píxel en formato RGB
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // Convertir a formato hexadecimal
                $hex = sprintf("#%02x%02x%02x", $r, $g, $b);

                // Agregar el color al array si no está presente
                if (!in_array($hex, $colors)) {
                    $colors[] = $hex;
                }
            }
        }

        // Ordenar los colores por frecuencia de aparición
        $colorCount = array_count_values($colors);
        arsort($colorCount);
        $sortedColors = array_keys($colorCount);

        // Limitar el número de colores extraídos
        $sortedColors = array_slice($sortedColors, 0, $numColors);

        // Liberar memoria
        imagedestroy($img);

        return $sortedColors;
    }

    public function fecha(){
        //$fecha = date('Y-m-d\TH:i:s', time() - 120);

        $fechaActual = date('Y-m-d\TH:i:s'); // Obtener la fecha y hora actual en formato ISO 8601

        // Restar dos horas a la fecha actual
        $dosHorasAtras = strtotime($fechaActual) - 7200; // Restar 7200 segundos (2 horas)

        // Formatear la fecha y hora dos horas atrás en formato ISO 8601
        $fechaDosHorasAtras = date('Y-m-d\TH:i:s', $dosHorasAtras);

        // return $fechaDosHorasAtras;

        $fechaActual = now()->toDateString();

        $user_id = 6;

        BotMessage::where('user_id', $user_id)->delete();

        // Cache::forget("config_datos_6");

        $mensajes = BotMessage::select('id', 'user_id', 'text', 'autor', 'created_at')
            // ->where('status', 0)
            // ->where('autor', 1)
            ->get();

        // DB::table('users')
        //     ->where('id', $user_id)
        //     ->update([
        //         'aviso_privacidad' => false,
        //     ]);

        // DB::table('users')
        //     ->where('id', $user_id)
        //     ->update([
        //         'email' => 'ramirez.fred@contafacil.com',
        //     ]);

        $cfdi_empresa = CfdiEmpresa::
            where('user_id',$user_id)
            ->first();

        if (!$cfdi_empresa)
        {
            return [
                'status'=>'error',
                'message'=>'Empresa no encontrada.'
            ];
        }

        // $cfdi_empresa->Rfc = '';
        // $cfdi_empresa->save();

        $cfdi_producto = CfdiProducto::
            where('empresa_id',$cfdi_empresa->id)
            ->first();

        if (!$cfdi_producto)
        {
            return [
                'status'=>'error',
                'message'=>'Producto no encontrado.'
            ];
        }

        // $cfdi_producto->ClaveProdServ = '';
        // $cfdi_producto->save();

        // $cfdi_clientes = CfdiCliente::with('empresa')->get();

        // for ($i=0; $i < count($cfdi_clientes); $i++) { 
        //     if($cfdi_clientes[$i]->empresa){
        //         $cfdi_clientes[$i]->user_id = $cfdi_clientes[$i]->empresa->user_id;
        //         $cfdi_clientes[$i]->save();
        //     }
        // }

        return response()->json([
            'fechaDosHorasAtras'=>$fechaDosHorasAtras,
            'fechaActual'=>$fechaActual,
            'mensajes'=>$mensajes
        ], 200);
    }

     public function upload(Request $request)
    {
        $request->validate([
            'pdf' => 'required|mimes:pdf|max:10000',
        ]);

        $pdf = $request->file('pdf');
        $path = $pdf->getPathName();

        $parser = new Parser();
        $pdf = $parser->parseFile($path);

        $text = $pdf->getText();

        // $imgs = [];
        // $imagenes = $pdf->getObjectsByType('XObject', 'Image');
        // foreach ($imagenes as $imagen) {
        //     //printf("<h1>Una imagen</h1><img src=\"data:image/jpg;base64,%s\"/>", base64_encode($imagen->getContent()));

        //     array_push($imgs,base64_encode($imagen->getContent()));
        // }

        return response()->json([
            'text'=>$text,
            //'imgs'=>$imgs,
        ], 200);


        // Aquí debes implementar la lógica para extraer los datos específicos del texto
        //$data = $this->extractDataFromText($text);

        //return response()->json($data);
    }

    private function extractDataFromText($text)
    {
        // Implementa la lógica para extraer los datos necesarios
        // Por ejemplo, usar expresiones regulares para encontrar datos fiscales específicos
        $data = [];

        // Ejemplo básico (ajusta según tus necesidades)
        if (preg_match('/Nombre: (.+)/', $text, $matches)) {
            $data['nombre'] = trim($matches[1]);
        }
        if (preg_match('/RFC: (.+)/', $text, $matches)) {
            $data['rfc'] = trim($matches[1]);
        }
        // Añade más patrones según los datos que necesites extraer

        return $data;
    }

    public function emailAdminNewUser($user_id)
    {

        $obj = User::find($user_id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado'], 404);
        }
        

        $details = [

            'logo' => 'https://apicontafacil.internow.com.mx/images_uploads/logos/logo_base.png',

            'color_a' => '#4285cb',

            'color_b' => '#ffffff',

            'color_c' => '#ffffff',

            'nombre' => $obj->nombre,

            'email' => $obj->email,

        ];

        $email = 'ramirez.fred@hotmail.com';

        \Mail::to($email)->send(new \App\Mail\AdminNewUserEmail($details));

        return 1;

    }

    public function testCatalogosSat(Request $request)
    {
        $uso_cfdi_input = rtrim(trim($request->input('texto')), '.');
        $receptor_uso_cfdi = 
            Cfdi40UsoCfdi::whereRaw("REPLACE(texto, '.', '') = ?", [str_replace('.', '', $uso_cfdi_input)])
            ->first();

        return response()->json([
            'texto'=>$request->input('texto'),
            'texto_tratado'=>$uso_cfdi_input,
            'registro'=>$receptor_uso_cfdi
        ], 200);

    }

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
            ->with('cfdi_empresa')
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

        if (!$cliente->cfdi_empresa)
        {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada.'
            ], 404);
        }

        $empresa = $cliente->cfdi_empresa;

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
                $pedidoCurso->receptor->delete();
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
                'link'=>$document
            ], 200);
        }


        return response()->json([
            'success' => true,
            'forma_pago'=> $forma_pago,
            'comprobante_forma_pago'=>$comprobante_forma_pago,
            'metodo_pago'=>$MetodoPago,
            'tipo_factura'=>$Tipo,
            'tasa_iva'=>$TasaIva,
            'tasa_isr'=>$TasaIsr,
            'data' => $orden,
            'factura_data' => $datos
        ]);
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

    public function setTimbresUser(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'email' => 'required|string|max:255',
            'timbres' => 'required|numeric',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data'=>$validator->errors(),
            ],422);
        }

        $user = User::
            where('email', $request->input('email'))
            ->first();

        if(!$user){
            return response()->json(['error'=>'No existe el usuario con ese correo.'], 409);    
        }

        // $user->count_timbres = $user->count_timbres + $request->input('timbres');
        // $user->save();

        return response()->json([
            'user' => $user
        ], 200);
    }

}
