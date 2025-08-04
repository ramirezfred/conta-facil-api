<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use DB;

use Carbon\Carbon;

use App\Models\User;
use App\Models\CfdiEmpresa;
use App\Models\CalendarioFiscal;
use App\Models\Cfdi40RegimenFiscal;

class CalendarioFiscalController extends Controller
{

    public function index(Request $request){

        $mes = $request->input('mes');
        $anio = $request->input('anio');

        $query = CalendarioFiscal::with('mi_regimen_fiscal');

        // Filtrar por año si se proporciona
        if ($anio) {
            $query->whereYear('fecha', $anio);
        }

        // Filtrar por mes si se proporciona
        if ($mes) {
            $query->whereMonth('fecha', $mes);
        }

        $coleccion = $query->get();

        // Transformar la colección para agregar el parámetro "dia"
        $coleccion = $coleccion->map(function ($obligacion) {
            return [
                'id' => $obligacion->id,
                'titulo' => $obligacion->titulo,
                'descripcion' => $obligacion->descripcion,
                'tipo' => $obligacion->tipo,
                'fecha' => $obligacion->fecha,
                'dia' => Carbon::parse($obligacion->fecha)->day, // Extraer el día de la fecha
                'RegimenFiscal' => $obligacion->RegimenFiscal,
                'mi_regimen_fiscal' => $obligacion->mi_regimen_fiscal,
            ];
        });

        return response()->json(['coleccion' => $coleccion], 200);
    }

    public function indexCliente(Request $request){
        
        $mes = $request->input('mes');
        $anio = $request->input('anio');
        $user_id = $request->input('user_id');
        //$RegimenFiscal = $request->input('RegimenFiscal');
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

        // Filtrar por año si se proporciona
        if ($anio) {
            $query->whereYear('fecha', $anio);
        }

        // Filtrar por mes si se proporciona
        if ($mes) {
            $query->whereMonth('fecha', $mes);
        }

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

        // Transformar la colección para agregar el parámetro "dia"
        $coleccion = $coleccion->map(function ($obligacion) {
            return [
                'id' => $obligacion->id,
                'titulo' => $obligacion->titulo,
                'descripcion' => $obligacion->descripcion,
                'tipo' => $obligacion->tipo,
                'fecha' => $obligacion->fecha,
                'dia' => Carbon::parse($obligacion->fecha)->day, // Extraer el día de la fecha
                'RegimenFiscal' => $obligacion->RegimenFiscal,
                'mi_regimen_fiscal' => $obligacion->mi_regimen_fiscal,
            ];
        });

        return response()->json(['coleccion' => $coleccion], 200);
    }

    public function store(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'titulo'=>'required|string',
            'descripcion'=>'required|string',
            'fecha'=>'required|date',
            'tipo'=>'required|string'
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        //validar tipos
        if ($request->input('tipo') != 'Declaración' && $request->input('tipo') != 'Pago' && $request->input('tipo') != 'Vencimiento') {
            return response()->json(['error'=>'Tipo inválido.'], 409);
        }

        //validar regimen fiscal
        if ($request->input('RegimenFiscal') != null && $request->input('RegimenFiscal') != '')
        {
            //checar si existe en el catalogo
            $RegimenFiscalBD = Cfdi40RegimenFiscal::find($request->input('RegimenFiscal'));

            if(!$RegimenFiscalBD){
                // El RegimenFiscal no existe en el catalogo
                $message = 'El Régimen fiscal que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un Régimen fiscal diferente.';

                return response()->json(['error'=>$message],409);
            }
        }
        
        if($newObj=CalendarioFiscal::create($request->all())){

            $newObj->dia = Carbon::parse($newObj->fecha)->day;
            $newObj->mi_regimen_fiscal = $newObj->mi_regimen_fiscal;

            return response()->json([
                'message'=>'Registro creado con éxito.',
                'registro'=>$newObj
            ], 200);

        }else{
            return response()->json(['error'=>'Error al crear el registro.'], 500);
        }

    }

    public function update(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $obj = CalendarioFiscal::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el registro con id '.$id], 404);
        }

        // Listado de campos recibidos teóricamente.
        $titulo = $request->input('titulo');
        $descripcion = $request->input('descripcion');
        $fecha = $request->input('fecha');
        $tipo = $request->input('tipo'); 
        $RegimenFiscal = $request->input('RegimenFiscal');  

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos de usuario.
        if ($titulo != null && $titulo!='')
        {
            $obj->titulo = $titulo;
            $bandera=true;
        }

        if ($descripcion != null && $descripcion!='')
        {
            $obj->descripcion = $descripcion;
            $bandera=true;
        }

        if ($fecha != null && $fecha!='')
        {
            $obj->fecha = $fecha;
            $bandera=true;
        }

        if ($tipo != null && $tipo!='')
        {
            //validar tipos
            if ($tipo != 'Declaración' && $tipo != 'Pago' && $tipo != 'Vencimiento') {
                return response()->json(['error'=>'Tipo inválido.'], 409);
            }

            $obj->tipo = $tipo;
            $bandera=true;
        }

        if ($RegimenFiscal != null && $RegimenFiscal!='')
        {
            //checar si existe en el catalogo
            $RegimenFiscalBD = Cfdi40RegimenFiscal::find($RegimenFiscal);

            if($RegimenFiscalBD){
                $obj->RegimenFiscal = $RegimenFiscal;
                $bandera=true; 
            }else{
                // El RegimenFiscal no existe en el catalogo
                $message = 'El Régimen fiscal que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un Régimen fiscal diferente.';

                return response()->json(['error'=>$message],409);
            }
        }

        if ($bandera)
        {

            // Almacenamos en la base de datos el registro.
            if ($obj->save()) {

                $obj->dia = Carbon::parse($obj->fecha)->day;
                $obj->mi_regimen_fiscal = $obj->mi_regimen_fiscal;
                
                return response()->json(['message'=>'Registro editado con éxito.',
                    'registro'=>$obj], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el registro.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al registro.'],409);
        }
    }

    public function destroy($id)
    {
        $obj=CalendarioFiscal::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Registro no encontrado.'], 404);
        } 

        // Eliminamos el obj
        $obj->delete();

        // $obj->flag_eliminado = 1;
        // $obj->save();

        return response()->json(['message'=>'Se ha eliminado correctamente el registro.'], 200);
    }
}
