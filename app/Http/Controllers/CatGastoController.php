<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use App\Models\CatGasto;

class CatGastoController extends Controller
{
    public function index()
    {
        $coleccion = CatGasto::whereNull('flag_eliminado')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json(['coleccion'=>$coleccion], 200);
    }

    public function store(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'clave'=>'required|string',
            'descripcion'=>'required|string',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $aux = CatGasto::whereNull('flag_eliminado')
            ->where('clave', $request->input('clave'))
            ->get();
        if(count($aux)!=0){
            return response()->json(['error'=>'Ya existe un Tipo de Gasto con esa clave.'], 409);    
        }

        $aux2 = CatGasto::whereNull('flag_eliminado')
            ->where('descripcion', $request->input('descripcion'))
            ->get();
        if(count($aux2)!=0){
            return response()->json(['error'=>'Ya existe un Tipo de Gasto con esa descripción.'], 409);    
        }
        
        if($newObj=CatGasto::create([
            'clave'=> $request->input('clave'),
            'descripcion'=> $request->input('descripcion'),
        ])){

           return response()->json(['message'=>'Registro creado con éxito.',
             'registro'=>$newObj], 200);
        }else{
            return response()->json(['error'=>'Error al crear el registro.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $obj = CatGasto::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el registro con id '.$id], 404);
        }

        // Listado de campos recibidos teóricamente.
        $clave=$request->input('clave'); 
        $descripcion=$request->input('descripcion');  

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos de usuario.
        if ($clave != null && $clave!='')
        {
            $aux = CatGasto::whereNull('flag_eliminado')
                ->where('clave', $clave)
                ->where('id', '<>', $obj->id)->get();

            if(count($aux)!=0){
               // Devolvemos un código 409 Conflict. 
                return response()->json(['error'=>'Ya existe otro Tipo de Gasto con esa clave.'], 409);
            }

            $obj->clave = $clave;
            $bandera=true;
        }

        if ($descripcion != null && $descripcion!='')
        {
            $aux = CatGasto::whereNull('flag_eliminado')
                ->where('descripcion', $descripcion)
                ->where('id', '<>', $obj->id)->get();

            if(count($aux)!=0){
               // Devolvemos un código 409 Conflict. 
                return response()->json(['error'=>'Ya existe otro Tipo de Gasto con esa descripción.'], 409);
            }

            $obj->descripcion = $descripcion;
            $bandera=true;
        }

        if ($bandera)
        {

            // Almacenamos en la base de datos el registro.
            if ($obj->save()) {
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
        $obj=CatGasto::find($id);

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
}
