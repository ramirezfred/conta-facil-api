<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\CatGasto;
use App\Models\Gasto;
use App\Models\GastoConcepto;
use App\Models\GastoRecurrente;

use Illuminate\Support\Facades\Validator;

use DB;

use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

class GastoRecurrenteController extends Controller
{
    public function index($user_id)
    {
        $coleccion = GastoRecurrente::
            where('user_id', $user_id)
            ->orderBy('id', 'desc')->get();

        return response()->json([
            'coleccion'=>$coleccion
        ], 200);
        
    }

    public function store(Request $request, $gasto_id)
    {

        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'user_id'=>'required|numeric',
            'titulo'=>'required|string',
            'frecuencia'=>'required|numeric',
            'hora'=>'required|string',
            // 'fecha'=>'required|string',
            // 'dia_semana'=>'required|numeric',
            // 'dia_mes'=>'required|numeric',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $user = User::whereNull('flag_eliminado')
            ->find($request->input('user_id'));
        if (!$user)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado'], 404);
        }

        $gasto = Gasto::whereNull('flag_eliminado')
            ->with('tipo')
            ->with('conceptos')
            ->find($gasto_id);

        if (!$gasto)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Gasto no encontrado.'], 404);
        }

        //frecuencia 1=una_vez 2=semanal 3=mensual

        if($request->input('frecuencia') == 1){

            if ($request->input('fecha') == null || $request->input('fecha') == '') {
                return response()->json(['error'=>'Ingrese una fecha válida.'], 409);
            }

            $fecha = Carbon::createFromFormat('Y-m-d', $request->input('fecha')); // Fecha a comparar
            $hoy = Carbon::now(); // Fecha actual

            if (!$fecha->greaterThan($hoy)) {
                return response()->json(['error'=>'La fecha debe ser mayor a la fecha actual.'], 409);
            }

        }else if($request->input('frecuencia') == 2){

            if ($request->input('dia_semana') == null || $request->input('dia_semana') == '') {
                return response()->json(['error'=>'Ingrese un día de la semana válido.'], 409);
            }

        }else if($request->input('frecuencia') == 3){
            
            if ($request->input('dia_mes') == null || $request->input('dia_mes') == '') {
                return response()->json(['error'=>'Ingrese un día del mes válido.'], 409);
            }
        }

        if($newObj=GastoRecurrente::create([
            'gasto_id'=> $gasto_id,
            'user_id'=> $request->input('user_id'),
            'status'=> 1,
            'titulo'=> $request->input('titulo'),
            'frecuencia'=> $request->input('frecuencia'),
            'hora'=> $request->input('hora'),
            'fecha'=> $request->input('fecha'),
            'dia_semana'=> $request->input('dia_semana'),
            'dia_mes'=> $request->input('dia_mes'),
            'registros'=> json_encode([])
        ])){
           return response()->json(['message'=>'Registro creado con éxito.',
             'registro'=>$newObj], 200);
        }else{
            return response()->json(['error'=>'Error al crear el registro.'], 500);
        }
        
    }

    public function updateStatus(Request $request, $id)
    {
        // Comprobamos lo que nos están pasando existe o no.
        $registro=GastoRecurrente::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Registro no encontrado.'], 404);
        }    
        
        // Listado de campos recibidos teóricamente.
        $status=$request->input('status');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.
        if (($status != null && $status!='') || $status === 0)
        {
            $registro->status = $status;
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

    public function destroy($id)
    {
        $obj=GastoRecurrente::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Registro no encontrado.'], 404);
        } 

        // Eliminamos el obj
        $obj->delete();


        return response()->json(['message'=>'Se ha eliminado correctamente el registro.'], 200);
    }

    

    
}
