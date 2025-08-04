<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\Crypt;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

use Illuminate\Support\Facades\Validator;

use Exception;

use App\Models\User;
use App\Models\CatGasto;
use App\Models\Gasto;
use App\Models\GastoConcepto;
use App\Models\GastoRecurrente;

use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

class GastoController extends Controller
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

        $coleccion = Gasto::whereNull('flag_eliminado')
            ->with(['tipo' => function ($query){
                $query->select('id','clave');
            }])
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
            ->find($request->input('user_id'));
        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado'], 404);
        }

        $aux = CatGasto::whereNull('flag_eliminado')
            ->find($request->input('tipo_id'));
        if(!$aux){
            return response()->json(['error'=>'Tipo no disponible en el catálogo.'], 404); 
        }

        try {
            $conceptos = json_decode($request->input('conceptos'));

            if(count($conceptos)==0){
                return response()->json(['error'=>'Debe agregar al menos un concepto.'], 409);    
            }

        } catch (Exception $e) {
            return response()->json(['error'=>'Conceptos debe ser un array.'], 409);
        }
 
        
        if($newObj=Gasto::create([
            'user_id'=> $request->input('user_id'),
            'tipo_id'=> $request->input('tipo_id'),
            'total'=> $request->input('total')
        ])){

            for ($i=0; $i < count($conceptos); $i++) { 
                $newObjTipo=GastoConcepto::create([
                    'gasto_id'=> $newObj->id,
                    'Descripcion'=> $conceptos[$i]->Descripcion,
                    'Cantidad'=> $conceptos[$i]->Cantidad,
                    'ValorUnitario'=> $conceptos[$i]->ValorUnitario,
                    'Importe'=> $conceptos[$i]->Importe,
                ]);
            }

            $document = $this->gastoPdf($newObj->id);
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
        $registro = Gasto::whereNull('flag_eliminado')
            ->with('tipo')
            ->with('conceptos')
            ->find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el Gasto con id '.$id], 404);
        }

        return response()->json(['registro'=>$registro], 200);
    }

    public function destroy($id)
    {
        $obj=Gasto::find($id);

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

    public function gastoPdf($id)
    {

        set_time_limit(500);

        $obj = Gasto::
            with('conceptos')
            ->find($id);

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
            'tipo' => $obj->tipo->clave,
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

    public function correrGastosRecurrentes()
    {

        set_time_limit(500);

        $hoy = Carbon::now();
        $dia_mes = $hoy->day;
        $dia_semana = $hoy->dayOfWeek;
        $hora = $hoy->hour;
        $minutos = $hoy->minute;
        $fecha_actual = $hoy->format('Y-m-d');
        
        $gastos_recurrentes = GastoRecurrente::where('status', 1)
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

        $array_gastos = [];

        $hora1 = Carbon::createFromTimeString($hora.':'.$minutos);

        //Logica para la hora del gasto
        foreach ($gastos_recurrentes as $gasto) {

            $hora2 = Carbon::parse($gasto->hora);
            //si la hora actual ($hora1) es mayor o igual a la hora de la gasto ($hora2)
            if ($hora1->greaterThanOrEqualTo($hora2)) {
                array_push($array_gastos,$gasto);
            }
        }  

        // Lógica para crear el gasto y actualizar date_last_run y log_run
        foreach ($array_gastos as $gasto) {
            
            $gasto->date_last_run = $fecha_actual;
            $gasto->save();

            $this->crearGastoRecurrente($gasto->id);
        }

        return response()->json([
            'message'=>'Gastos generados',
            'dia_mes' => $dia_mes,
            'dia_semana' => $dia_semana,
            'hora' => $hora,
            'minutos' => $minutos,
            'fecha_actual' => $fecha_actual,
            // 'gastos_recurrentes' => $gastos_recurrentes,
            'array_gastos' => $array_gastos
        ], 200);
    }

    public function crearGastoRecurrente($recurrente_id){

        $recurrente=GastoRecurrente::find($recurrente_id);

        if (!$recurrente)
        {
            $recurrente->log_run = 'Registro recurrente no encontrado.';
            $recurrente->save();

            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Registro no encontrado.'], 404);
        } 

        $gasto = Gasto::whereNull('flag_eliminado')
            ->with('tipo')
            ->with('conceptos')
            ->find($recurrente->gasto_id);

        if (!$gasto)
        {
            $recurrente->log_run = 'Gasto base no encontrado.';
            $recurrente->save();

            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Gasto base no encontrado.'], 404);
        }

        $obj = User::whereNull('flag_eliminado')
            ->find($recurrente->user_id);
        if (!$obj)
        {
            $recurrente->log_run = 'Usuario no encontrado.';
            $recurrente->save();

            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado.'], 404);
        }

        $aux = CatGasto::whereNull('flag_eliminado')
            ->find($gasto->tipo_id);
        if(!$aux){

            $recurrente->log_run = 'Tipo no disponible en el catálogo.';
            $recurrente->save();

            return response()->json(['error'=>'Tipo no disponible en el catálogo.'], 404); 
        }

        $conceptos = $gasto->conceptos;
        if (count($conceptos) == 0) {

            $recurrente->log_run = 'Gasto base sin conceptos.';
            $recurrente->save();

            // Devolvemos un código 409 Conflict. 
            return response()->json(['error'=>'Gasto base sin conceptos.'], 409);
        }

        if($newObj=Gasto::create([
            'user_id'=> $gasto->user_id,
            'tipo_id'=> $gasto->tipo_id,
            'total'=> $gasto->total
        ])){

            for ($i=0; $i < count($conceptos); $i++) { 
                $newObjTipo=GastoConcepto::create([
                    'gasto_id'=> $newObj->id,
                    'Descripcion'=> $conceptos[$i]->Descripcion,
                    'Cantidad'=> $conceptos[$i]->Cantidad,
                    'ValorUnitario'=> $conceptos[$i]->ValorUnitario,
                    'Importe'=> $conceptos[$i]->Importe,
                ]);
            }

            $document = $this->gastoPdf($newObj->id);
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

        $coleccion = Gasto::select('id','tipo_id','total','created_at')
            ->whereNull('flag_eliminado')
            ->whereIn('id', $ids)
            ->with(['tipo' => function ($query){
                $query->select('id','clave');
            }])
            ->with(['conceptos' => function ($query){
                $query->select('id','gasto_id','Descripcion','Cantidad','ValorUnitario','Importe');
            }])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'coleccion'=>$coleccion
        ], 200);
        
    }

    
}
