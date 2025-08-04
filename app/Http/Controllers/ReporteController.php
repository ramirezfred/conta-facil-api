<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use DB;

use DateTime;
use Carbon\Carbon;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\Crypt;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

use Illuminate\Support\Facades\Validator;

use App\Models\User;

//facturas
use App\Models\CfdiComprobante;
use App\Models\CfdiEmpresa;
use App\Models\CfdiConcepto;
use App\Models\Gasto;
use App\Models\GastoConcepto;
use App\Models\Ingreso;
use App\Models\IngresoConcepto;

date_default_timezone_set('America/Mexico_City');

class ReporteController extends Controller
{
    public function estadoDeCuenta(Request $request, $user_id)
    {

        set_time_limit(500);

        // $dia_actual = date("d"); //j  Día del mes sin ceros iniciales 1 a 31
        //                         //d Día del mes, 2 dígitos con ceros iniciales  01 a 31
        // $mes_actual = date("m");
        // $anio_actual = date("Y");

        /*return response()->json(['date'=>date("Y-m-d H:i:s"),
            'dia_actual' => date("d"),
        'mes_actual' => date("m"),
        'anio_actual' => date("Y")], 200);*/

        $mes_actual = $request->input('mes');
        $anio_actual = $request->input('anio');

        if($mes_actual >= 1 && $mes_actual <= 9){
            $mes_actual = '0'.$mes_actual;
        }

        $usuario = User::with('cfdi_empresa')->find($user_id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado'], 404);
        }

        $user_id = $usuario->id;
        $user_nombre = $usuario->nombre;
        $user_email = $usuario->email;

        $cfdi_Empresa_id = '';
        $cfdi_Rfc = '';
        $cfdi_RazonSocial = '';
        $cfdi_CP = '';

        $total_facturado = 0;
        $total_gastos = 0;
        $total_ingresos_contables = 0;
        $total_ingresos_no_contables = 0;
        $total_general = 0;

        if($usuario->cfdi_empresa && $usuario->cfdi_empresa->id){

            $cfdi_Empresa_id = $usuario->cfdi_empresa->id;
            $cfdi_Rfc = $usuario->cfdi_empresa->Rfc;
            $cfdi_RazonSocial = $usuario->cfdi_empresa->RazonSocial;
            $cfdi_CP = $usuario->cfdi_empresa->CP;

            //total facturado
            $total_facturado = CfdiComprobante::
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

        }

        $total_gastos = Gasto::whereNull('flag_eliminado')
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            ->where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
            ->where('user_id',$usuario->id)
            ->sum('total');

        $total_ingresos_contables = Ingreso::whereNull('flag_eliminado')
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            ->where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
            ->where('user_id',$usuario->id)
            ->where('tipo_id',1)
            ->sum('total');

        $total_ingresos_no_contables = Ingreso::whereNull('flag_eliminado')
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            ->where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
            ->where('user_id',$usuario->id)
            ->where('tipo_id',2)
            ->sum('total');

        $total_general = $total_facturado + $total_gastos + $total_ingresos_contables + $total_ingresos_no_contables;

        /*return response()->json([

            'fecha' => date('d/m/Y'),

            'user_id' => $user_id,
            'user_nombre' => $user_nombre,
            'user_email' => $user_email,

            'cfdi_Empresa_id' => $cfdi_Empresa_id,
            'cfdi_Rfc' => $cfdi_Rfc,
            'cfdi_RazonSocial' => $cfdi_RazonSocial,
            'cfdi_CP' => $cfdi_CP,

            'total_facturado' => number_format($total_facturado, 2, '.', ''),
            'total_gastos' => number_format($total_gastos, 2, '.', ''),
            'total_ingresos_contables' => number_format($total_ingresos_contables, 2, '.', ''),
            'total_ingresos_no_contables' => number_format($total_ingresos_no_contables, 2, '.', ''),
            'total_general' => number_format($total_general, 2, '.', '')

        ], 200);*/

        $rgb = $this->hexToRgb('#4285cb');

        $data = [

            'r' => $rgb['r'],
            'g' => $rgb['g'],
            'b' => $rgb['b'],
            'header' => 'https://apicontafacil.internow.com.mx/images_uploads/header_footer/header_base.png',
            'footer' => 'https://apicontafacil.internow.com.mx/images_uploads/header_footer/footer_base.png',

            'fecha' => date('d/m/Y'),

            'user_id' => $user_id,
            'user_nombre' => $user_nombre,
            'user_email' => $user_email,

            'cfdi_Empresa_id' => $cfdi_Empresa_id,
            'cfdi_Rfc' => $cfdi_Rfc,
            'cfdi_RazonSocial' => $cfdi_RazonSocial,
            'cfdi_CP' => $cfdi_CP,

            'total_facturado' => number_format($total_facturado, 2, '.', ''),
            'total_gastos' => number_format($total_gastos, 2, '.', ''),
            'total_ingresos_contables' => number_format($total_ingresos_contables, 2, '.', ''),
            'total_ingresos_no_contables' => number_format($total_ingresos_no_contables, 2, '.', ''),
            'total_general' => number_format($total_general, 2, '.', '')
        ];

        //$pdf = Pdf::loadView('cotizaciones.cotizacion', $data);
        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('reportes.estado_cuenta', $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs_reportes/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs_reportes/' . $nombreArchivo);

        //return $url;

        return response()->json(['message'=>'Reporte generado.',
             'url'=>$url], 200);

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

   
}
