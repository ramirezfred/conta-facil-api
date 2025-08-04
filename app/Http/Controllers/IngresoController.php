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
use App\Models\Ingreso;
use App\Models\IngresoConcepto;
use App\Models\CfdiComprobante;
use App\Models\IngresoRecurrente;

date_default_timezone_set('America/Mexico_City');

class IngresoController extends Controller
{
    public function indexFilter(Request $request, $user_id)
    {

        $obj = User::whereNull('flag_eliminado')
            ->find($user_id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado'], 404);
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

        $coleccion = Ingreso::whereNull('flag_eliminado')
            ->where('user_id',$obj->id)
            ->where('created_at', 'like', '%'.$fecha.'%')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'coleccion'=>$coleccion
        ], 200);
        
    }

    public function store(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'user_id'=>'required|numeric',
            'tipo_id'=>'required|numeric',
            'total'=>'required|numeric',
            'conceptos'=>'required|string',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $obj = User::whereNull('flag_eliminado')
            ->with('cfdi_empresa')
            ->find($request->input('user_id'));
        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado'], 404);
        }

        //Si el ingreso es contable, Validacion para user resico
        // if($request->input('tipo_id') == 1 && $obj->cfdi_empresa && $obj->cfdi_empresa->RegimenFiscal == '626'){
        //     $total_facturado = $this->getTotalFacturado($obj->id);

        //     if($total_facturado >= 290000){
        //         return response()->json(['error'=>'Ya alcanzaste el límite de $290,000 pesos mensuales facturables para usuarios con Régimen Simplificado de Confianza'], 409);
        //     }else if(($total_facturado+$request->input('total')) >= 290000){
        //         return response()->json(['error'=>'El total del ingreso excede el límite de $290,000 pesos mensuales facturables para usuarios con Régimen Simplificado de Confianza'], 409);
        //     }
        // }

        if($request->input('tipo_id') == 1 && $obj->cfdi_empresa){

            $limite_facturacion = $this->determinarLimiteFacturacion($obj->cfdi_empresa->Rfc,$obj->cfdi_empresa->RegimenFiscal);
            if($limite_facturacion != null && $limite_facturacion != 0){

                $total_facturado = $this->getTotalFacturado($obj->id);

                if($total_facturado >= $limite_facturacion){
                    return response()->json(['error'=>'Ya alcanzaste el límite de $'.$limite_facturacion.' pesos mensuales facturables.'], 409);
                }else if(($total_facturado+$request->input('Total')) >= $limite_facturacion){
                    return response()->json(['error'=>'El total del ingreso excede el límite de $'.$limite_facturacion.' pesos mensuales facturables.'], 409);
                }

            }

        }

        try {
            $conceptos = json_decode($request->input('conceptos'));

            if(count($conceptos)==0){
                return response()->json(['error'=>'Debe agregar al menos un concepto.'], 409);    
            }

        } catch (Exception $e) {
            return response()->json(['error'=>'Conceptos debe ser un array.'], 409);
        }
 
        
        if($newObj=Ingreso::create([
            'user_id'=> $request->input('user_id'),
            'tipo_id'=> $request->input('tipo_id'),
            'total'=> $request->input('total')
        ])){

            for ($i=0; $i < count($conceptos); $i++) { 
                $newObjTipo=IngresoConcepto::create([
                    'ingreso_id'=> $newObj->id,
                    'Descripcion'=> $conceptos[$i]->Descripcion,
                    'Cantidad'=> $conceptos[$i]->Cantidad,
                    'ValorUnitario'=> $conceptos[$i]->ValorUnitario,
                    'Importe'=> $conceptos[$i]->Importe,
                ]);
            }

            $document = $this->ingresoPdf($newObj->id);
            $newObj->pdf = $document;
            $newObj->save();

           return response()->json(['message'=>'Registro creado con éxito.',
             'registro'=>$newObj], 200);
        }else{
            return response()->json(['error'=>'Error al crear el registro.'], 500);
        }
    }

    public function show($id)
    {
        $registro = Ingreso::whereNull('flag_eliminado')
            ->with('conceptos')
            ->find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el Ingreso con id '.$id], 404);
        }

        return response()->json(['registro'=>$registro], 200);
    }

    public function destroy($id)
    {
        $obj=Ingreso::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Registro no encontrado.'], 404);
        } 

        // Eliminamos el obj
        //$obj->delete();

        $obj->flag_eliminado = 1;
        $obj->save();

        return response()->json(['message'=>'Se ha eliminado correctamente el registro.'], 200);
    }

    public function ingresoPdf($id)
    {

        set_time_limit(500);

        $obj = Ingreso::
            with('conceptos')
            ->find($id);

        $tipo = '';
        if($obj->tipo_id == 1){
            $tipo = 'Ingreso contable';
        }else if($obj->tipo_id == 2){
            $tipo = 'Ingreso no contable';
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

    public function correrIngresosRecurrentes()
    {

        set_time_limit(500);

        $hoy = Carbon::now();
        $dia_mes = $hoy->day;
        $dia_semana = $hoy->dayOfWeek;
        $hora = $hoy->hour;
        $minutos = $hoy->minute;
        $fecha_actual = $hoy->format('Y-m-d');
        
        $ingresos_recurrentes = IngresoRecurrente::where('status', 1)
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

        $array_ingresos = [];

        $hora1 = Carbon::createFromTimeString($hora.':'.$minutos);

        //Logica para la hora del ingreso
        foreach ($ingresos_recurrentes as $ingreso) {

            $hora2 = Carbon::parse($ingreso->hora);
            //si la hora actual ($hora1) es mayor o igual a la hora de la ingreso ($hora2)
            if ($hora1->greaterThanOrEqualTo($hora2)) {
                array_push($array_ingresos,$ingreso);
            }
        }  

        // Lógica para crear el ingreso y actualizar date_last_run y log_run
        foreach ($array_ingresos as $ingreso) {
            
            $ingreso->date_last_run = $fecha_actual;
            $ingreso->save();

            $this->crearIngresoRecurrente($ingreso->id);
        }

        return response()->json([
            'message'=>'Ingresos generados',
            'dia_mes' => $dia_mes,
            'dia_semana' => $dia_semana,
            'hora' => $hora,
            'minutos' => $minutos,
            'fecha_actual' => $fecha_actual,
            // 'ingresos_recurrentes' => $ingresos_recurrentes,
            'array_ingresos' => $array_ingresos
        ], 200);
    }

    public function crearIngresoRecurrente($recurrente_id){

        $recurrente=IngresoRecurrente::find($recurrente_id);

        if (!$recurrente)
        {
            $recurrente->log_run = 'Registro recurrente no encontrado.';
            $recurrente->save();

            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Registro no encontrado.'], 404);
        } 

        $ingreso = Ingreso::whereNull('flag_eliminado')
            ->with('conceptos')
            ->find($recurrente->ingreso_id);

        if (!$ingreso)
        {
            $recurrente->log_run = 'Ingreso base no encontrado.';
            $recurrente->save();

            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Ingreso base no encontrado.'], 404);
        }

        $obj = User::whereNull('flag_eliminado')
            ->with('cfdi_empresa')
            ->find($recurrente->user_id);
        if (!$obj)
        {
            $recurrente->log_run = 'Usuario no encontrado.';
            $recurrente->save();

            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado.'], 404);
        }

        if($ingreso->tipo_id == 1 && $obj->cfdi_empresa){

            $limite_facturacion = $this->determinarLimiteFacturacion($obj->cfdi_empresa->Rfc,$obj->cfdi_empresa->RegimenFiscal);
            if($limite_facturacion != null && $limite_facturacion != 0){

                $total_facturado = $this->getTotalFacturado($obj->id);

                if($total_facturado >= $limite_facturacion){

                    $recurrente->log_run = 'Ya alcanzaste el límite de $'.$limite_facturacion.' pesos mensuales facturables.';
                    $recurrente->save();

                    return response()->json(['error'=>'Ya alcanzaste el límite de $'.$limite_facturacion.' pesos mensuales facturables.'], 409);
                }else if(($total_facturado+$ingreso->total) >= $limite_facturacion){

                    $recurrente->log_run = 'El total del ingreso excede el límite de $'.$limite_facturacion.' pesos mensuales facturables.';
                    $recurrente->save();

                    return response()->json(['error'=>'El total del ingreso excede el límite de $'.$limite_facturacion.' pesos mensuales facturables.'], 409);
                }

            }

        }

        $conceptos = $ingreso->conceptos;
        if (count($conceptos) == 0) {

            $recurrente->log_run = 'Ingreso base sin conceptos.';
            $recurrente->save();

            // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Ingreso base sin conceptos.'], 409);
        }

        if($newObj=Ingreso::create([
            'user_id'=> $ingreso->user_id,
            'tipo_id'=> $ingreso->tipo_id,
            'total'=> $ingreso->total
        ])){

            for ($i=0; $i < count($conceptos); $i++) { 
                $newObjTipo=IngresoConcepto::create([
                    'ingreso_id'=> $newObj->id,
                    'Descripcion'=> $conceptos[$i]->Descripcion,
                    'Cantidad'=> $conceptos[$i]->Cantidad,
                    'ValorUnitario'=> $conceptos[$i]->ValorUnitario,
                    'Importe'=> $conceptos[$i]->Importe,
                ]);
            }

            $document = $this->ingresoPdf($newObj->id);
            $newObj->pdf = $document;
            $newObj->save();

            $array_registros = json_decode($recurrente->registros, true);
            array_push($array_registros,$newObj->id);

            $recurrente->registros = json_encode($array_registros);
            $recurrente->log_run = 'Registro creado con éxito.';
            $recurrente->save();

           return response()->json(['message'=>'Registro creado con éxito.',
             'registro'=>$newObj], 200);
        }else{

            $recurrente->log_run = 'Error al crear el registro.';
            $recurrente->save();

            return response()->json(['error'=>'Error al crear el registro.'], 500);
        }

    }

    public function dataToExcel(Request $request)
    {

        $ids = $request->input('ids', []);

        $coleccion = Ingreso::select('id','tipo_id','total','created_at')
            ->whereNull('flag_eliminado')
            ->whereIn('id', $ids)
            ->with(['conceptos' => function ($query){
                $query->select('id','ingreso_id','Descripcion','Cantidad','ValorUnitario','Importe');
            }])
            ->orderBy('id', 'desc')
            ->get();

        // Agregar campo tipo como string
        $coleccion = $coleccion->map(function ($ingreso) {
            $ingreso->tipo = $ingreso->tipo_id == 1 ? 'Contable' : 'No Contable';
            return $ingreso;
        });

        return response()->json([
            'coleccion'=>$coleccion
        ], 200);
        
    }
}
