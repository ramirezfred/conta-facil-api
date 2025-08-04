<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use DB;

use DateTime;
use Carbon\Carbon;

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

class DashboardController extends Controller
{
    /*Retorna los contadores del mes actual*/
    public function contadores($user_id)
    {
        $dia_actual = date("d"); //j  Día del mes sin ceros iniciales 1 a 31
                                //d Día del mes, 2 dígitos con ceros iniciales  01 a 31
        $mes_actual = date("m");
        $anio_actual = date("Y");

        /*return response()->json(['date'=>date("Y-m-d H:i:s"),
            'dia_actual' => date("d"),
        'mes_actual' => date("m"),
        'anio_actual' => date("Y")], 200);*/

        $usuario = User::with('cfdi_empresa')->find($user_id);

        if (!$usuario)
        {
            return response()->json([

                'cfdi_Empresa_id'=>null,
                'cfdi_Rfc'=>null,
                'cfdi_RazonSocial'=>null,
                'cfdi_CP'=>null,
                'cfdi_id_cif'=>null,

                'emitidas'=>0,
                'canceladas'=>0,
                'total'=>0,

                'user_id'=>null,
                'user_nombre'=>null,
                'user_email'=>null,
                'user_telefono'=>"00000000000",

                'flag_puede_facturar'=>false,
                //'puede_facturar'=>sprintf('%.2f',290000),
                'puede_facturar'=>number_format(0, 0, '', ' '),
                'porcentaje_facturado'=>0,

                'total_gastos'=>0,
                'total_ingresos_contables'=>0,
            ], 200);
        }


        $emitidas = CfdiComprobante::
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
            ->where('emisor_id',$usuario->cfdi_empresa->id)
            ->where('status',1)
            ->count();

        $canceladas = CfdiComprobante::
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
            ->where('emisor_id',$usuario->cfdi_empresa->id)
            ->where('status',2)
            ->count();

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

        $flag_puede_facturar = false;
        $limite_facturacion = $this->determinarLimiteFacturacion($usuario->cfdi_empresa->Rfc,$usuario->cfdi_empresa->RegimenFiscal);

        $puede_facturar = 0;
        $porcentaje_facturado = 0;

        if($limite_facturacion != null && $limite_facturacion != 0){
            $flag_puede_facturar = true;

            $puede_facturar = $limite_facturacion - ($total + $total_por_facturar);
            if($puede_facturar < 0){
                $puede_facturar = 0;
            }

            $porcentaje_facturado = (($total + $total_por_facturar)*100)/$limite_facturacion;

            $puede_facturar = number_format($puede_facturar, 0, '', ' ');
        }else{

            /*Los users q no tienen limite se va a mostrar es el total facturado*/
            $puede_facturar = $total;
            $porcentaje_facturado = 100;

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


        return response()->json([

            'cfdi_Empresa_id'=>$usuario->cfdi_empresa->id,
            'cfdi_Rfc'=>$usuario->cfdi_empresa->Rfc,
            'cfdi_RazonSocial'=>$usuario->cfdi_empresa->RazonSocial,
            'cfdi_CP'=>$usuario->cfdi_empresa->CP,
            'cfdi_id_cif'=>$usuario->cfdi_empresa->id_cif,

            'emitidas'=>$emitidas,
            'canceladas'=>$canceladas,
            'total'=>$total,

            'user_id'=>$usuario->id,
            'user_nombre'=>$usuario->nombre,
            'user_email'=>$usuario->email,
            'user_telefono'=>$usuario->telefono,

            'flag_puede_facturar'=>$flag_puede_facturar,
            //'puede_facturar'=>sprintf('%.2f',$puede_facturar),
            'puede_facturar'=>$puede_facturar,
            'porcentaje_facturado'=>$porcentaje_facturado,
            'total_gastos'=>$total_gastos,
            'total_ingresos_contables'=>$total_ingresos_contables + $total,
            'count_timbres'=>$usuario->count_timbres,

        ], 200);

    }

    /*Retorna los contadores del mes actual*/
    public function contadoresRfc($Rfc)
    {
        $dia_actual = date("d"); //j  Día del mes sin ceros iniciales 1 a 31
                                //d Día del mes, 2 dígitos con ceros iniciales  01 a 31
        $mes_actual = date("m");
        $anio_actual = date("Y");

        /*return response()->json(['date'=>date("Y-m-d H:i:s"),
            'dia_actual' => date("d"),
        'mes_actual' => date("m"),
        'anio_actual' => date("Y")], 200);*/

        $cfdi_empresa = CfdiEmpresa::where('Rfc',$Rfc)->first();

        if (!$cfdi_empresa)
        {
            return response()->json([

                'cfdi_Empresa_id'=>null,
                'cfdi_Rfc'=>null,
                'cfdi_RazonSocial'=>null,
                'cfdi_CP'=>null,
                'cfdi_id_cif'=>null,

                'emitidas'=>0,
                'canceladas'=>0,
                'total'=>0,

                'user_id'=>null,
                'user_nombre'=>null,
                'user_email'=>null,

                'flag_puede_facturar'=>false,
                //'puede_facturar'=>sprintf('%.2f',290000),
                'puede_facturar'=>number_format(0, 0, '', ' '),
                'porcentaje_facturado'=>0,

                'total_gastos'=>0,
                'total_ingresos_contables'=>0,
                'count_timbres'=>0,

            ], 200);
        }


        $emitidas = CfdiComprobante::
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
            ->where('emisor_id',$cfdi_empresa->id)
            ->where('status',1)
            ->count();

        $canceladas = CfdiComprobante::
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
            ->where('emisor_id',$cfdi_empresa->id)
            ->where('status',2)
            ->count();

        //total facturado
        $total = CfdiComprobante::
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
            ->where('emisor_id',$cfdi_empresa->id)
            ->where(function ($query) {
                $query
                    ->where('status',1)
                    ->orWhere('status',2);
            })
            ->sum('Total');

        //total pendiente por facturar
        $total_por_facturar = Ingreso::whereNull('flag_eliminado')
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            ->where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
            ->where('user_id',$cfdi_empresa->user_id)
            ->where('tipo_id',1)
            ->whereNull('factura_id')
            ->sum('total');

        $flag_puede_facturar = false;
        $limite_facturacion = $this->determinarLimiteFacturacion($cfdi_empresa->Rfc,$cfdi_empresa->RegimenFiscal);

        $puede_facturar = 0;
        $porcentaje_facturado = 0;

        if($limite_facturacion != null && $limite_facturacion != 0){
            $flag_puede_facturar = true;

            $puede_facturar = $limite_facturacion - ($total + $total_por_facturar);
            if($puede_facturar < 0){
                $puede_facturar = 0;
            }

            $porcentaje_facturado = (($total + $total_por_facturar)*100)/$limite_facturacion;

            $puede_facturar = number_format($puede_facturar, 0, '', ' ');
        }else{

            /*Los users q no tienen limite se va a mostrar es el total facturado*/
            $puede_facturar = $total;
            $porcentaje_facturado = 100;

        }

        $total_gastos = Gasto::whereNull('flag_eliminado')
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            ->where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
            ->where('user_id',$cfdi_empresa->user_id)
            ->sum('total');

        $total_ingresos_contables = Ingreso::whereNull('flag_eliminado')
            //where(DB::raw('DAY(created_at)'),$dia_actual)
            ->where(DB::raw('MONTH(created_at)'),$mes_actual)
            ->where(DB::raw('YEAR(created_at)'),$anio_actual)
            ->where('user_id',$usuario->id)
            ->where('tipo_id',1)
            ->sum('total');

        return response()->json([

            'cfdi_Empresa_id'=>$cfdi_empresa->id,
            'cfdi_Rfc'=>$cfdi_empresa->Rfc,
            'cfdi_RazonSocial'=>$cfdi_empresa->RazonSocial,
            'cfdi_CP'=>$cfdi_empresa->CP,
            'cfdi_id_cif'=>$cfdi_empresa->id_cif,

            'emitidas'=>$emitidas,
            'canceladas'=>$canceladas,
            'total'=>$total,

            'user_id'=>$cfdi_empresa->user_id,
            'user_nombre'=>null,
            'user_email'=>null,

            'flag_puede_facturar'=>$flag_puede_facturar,
            //'puede_facturar'=>sprintf('%.2f',$puede_facturar),
            'puede_facturar'=>$puede_facturar,
            'porcentaje_facturado'=>$porcentaje_facturado,

            'total_gastos'=>$total_gastos,
            'total_ingresos_contables'=>$total_ingresos_contables,
            'count_timbres'=>0,

        ], 200);

    }

    /*Retorna los contadores del mes actual*/
    public function contadoresTermino($termino)
    {
        $usuario = User::select('id', 'email', 'nombre')
            ->whereNull('flag_eliminado')
            ->where(function ($query) use ($termino) {
                $query->where("email", "like", '%'.$termino.'%')
                    ->orWhere("nombre", "like", '%'.$termino.'%')
                    // ->orWhere(function ($query) use ($termino) {
                    //     $query->whereHas('cfdi_empresa', function ($query) use ($termino) {
                    //         $query->where("Rfc", "like", '%'.$termino.'%');
                    //     });
                    // });
                    ->orWhereHas('cfdi_empresa', function ($query) use ($termino) {
                        $query->where('Rfc', 'like', '%' . $termino . '%');
                    });
            })
            ->with(['cfdi_empresa' => function ($query) {
                $query->select('id', 'user_id', 'Rfc');
            }])
            ->first();

        if (!$usuario)
        {
            return response()->json([

                'cfdi_Empresa_id'=>null,
                'cfdi_Rfc'=>null,
                'cfdi_RazonSocial'=>null,
                'cfdi_CP'=>null,
                'cfdi_id_cif'=>null,

                'emitidas'=>0,
                'canceladas'=>0,
                'total'=>0,

                'user_id'=>null,
                'user_nombre'=>null,
                'user_email'=>null,

                'flag_puede_facturar'=>false,
                //'puede_facturar'=>sprintf('%.2f',290000),
                'puede_facturar'=>number_format(0, 0, '', ' '),
                'porcentaje_facturado'=>0,

                'total_gastos'=>0,
                'total_ingresos_contables'=>0,
                'count_timbres'=>0,

            ], 200);
        }


        return $this->contadores($usuario->id);

    }

    public function actividadFilter(Request $request, $cliente_id)
    {

        $obj = User::
            select('id')
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

        //facturas en emitidas y canceladas
        $facturas = CfdiComprobante::select('id','emisor_id','status','Serie','Folio','Fecha','Total','created_at')
            ->where('emisor_id',$emisor->id)
            ->where(function ($query) {
                $query
                    ->where('status',1)
                    ->orWhere('status',2);
            })
            //->where('Fecha', 'like', '%'.$fecha.'%')
            ->where('created_at', 'like', '%'.$fecha.'%')
            ->with(['receptor' => function ($query){
                $query->select('id','comprobante_id','Rfc','Nombre');
            }])
            // ->with(['archivo' => function ($query){
            //     $query->select('id','comprobante_id','xml_archivo','pdf');
            // }])
            ->orderBy('id', 'desc')
            ->get();

        $filtrados = [];

        for ($i=0; $i < count($facturas); $i++) { 

            $id = $facturas[$i]->id;
            $tipo = null; //1=factura emitida 2=factura cancelada 3=gasto no contable 4=ingreso contable 5=ingreso no contable
            if($facturas[$i]->status == 1){
                $tipo = 1;
            }else if($facturas[$i]->status == 2){
                $tipo = 2;
            }

            $conceptos = CfdiConcepto::where('comprobante_id',$facturas[$i]->id)->count(); //contador de conceptos
            $detalle = $facturas[$i]->receptor->Rfc; //en factura RFC, en gasto Tipo, en ingreso Tipo
            $fecha_aux = $facturas[$i]->created_at;
            $total = $facturas[$i]->Total;

            $resul = (object) [
                'id' => $id,
                'tipo' => $tipo,
                'conceptos' => $conceptos,
                'detalle' => $detalle,
                'fecha' => $fecha_aux,
                'total' => $total,
            ];

            array_push($filtrados,$resul);

        }

        $gastos = Gasto::whereNull('flag_eliminado')
            ->with(['tipo' => function ($query){
                $query->select('id','clave');
            }])
            ->where('user_id',$obj->id)
            ->where('created_at', 'like', '%'.$fecha.'%')
            ->orderBy('id', 'desc')
            ->get();

        for ($i=0; $i < count($gastos); $i++) { 

            $id = $gastos[$i]->id;
            $tipo = 3; //1=factura emitida 2=factura cancelada 3=gasto no contable 4=ingreso contable

            $conceptos = GastoConcepto::where('gasto_id',$gastos[$i]->id)->count(); //contador de conceptos
            $detalle = $gastos[$i]->tipo->clave; //en factura RFC, en gasto Tipo, en ingreso Tipo
            $fecha_aux = $gastos[$i]->created_at;
            $total = $gastos[$i]->total;

            $resul = (object) [
                'id' => $id,
                'tipo' => $tipo,
                'conceptos' => $conceptos,
                'detalle' => $detalle,
                'fecha' => $fecha_aux,
                'total' => $total,
            ];

            array_push($filtrados,$resul);

        }

        $ingresos = Ingreso::whereNull('flag_eliminado')
            ->where('user_id',$obj->id)
            ->where('created_at', 'like', '%'.$fecha.'%')
            ->orderBy('id', 'desc')
            ->get();

        for ($i=0; $i < count($ingresos); $i++) { 

            $id = $ingresos[$i]->id;
            $tipo = 4; //1=factura emitida 2=factura cancelada 3=gasto no contable 4=ingreso contable

            $conceptos = IngresoConcepto::where('ingreso_id',$ingresos[$i]->id)->count(); //contador de conceptos
            $detalle = ''; //en factura RFC, en gasto Tipo, en ingreso Tipo
            if($ingresos[$i]->tipo_id == 1){
                $detalle = 'Contable';
            }else if($ingresos[$i]->tipo_id == 2){
                $detalle = 'No Contable';
            }
            $fecha_aux = $ingresos[$i]->created_at;
            $total = $ingresos[$i]->total;

            $resul = (object) [
                'id' => $id,
                'tipo' => $tipo,
                'conceptos' => $conceptos,
                'detalle' => $detalle,
                'fecha' => $fecha_aux,
                'total' => $total,
            ];

            array_push($filtrados,$resul);

        }

        // Función de comparación para ordenar por fecha de mayor a menor
        usort($filtrados, function ($a, $b) {
            return strtotime($b->fecha) - strtotime($a->fecha);
        });

        return response()->json([
            'filtrados'=>$filtrados
        ], 200);
        
    }

    public function getCatalogoUsers(Request $request)
    {
        $termino = $request->input('termino');

        $coleccion = User::select('id', 'email', 'nombre')
            ->whereNull('flag_eliminado')
            ->where(function ($query) use ($termino) {
                $query->where("email", "like", '%'.$termino.'%')
                    ->orWhere("nombre", "like", '%'.$termino.'%')
                    // ->orWhere(function ($query) use ($termino) {
                    //     $query->whereHas('cfdi_empresa', function ($query) use ($termino) {
                    //         $query->where("Rfc", "like", '%'.$termino.'%');
                    //     });
                    // });
                    ->orWhereHas('cfdi_empresa', function ($query) use ($termino) {
                        $query->where('Rfc', 'like', '%' . $termino . '%');
                    });
            })
            ->with(['cfdi_empresa' => function ($query) {
                $query->select('id', 'user_id', 'Rfc');
            }])
            ->get();


        return response()->json(['coleccion'=>$coleccion], 200);
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
}
