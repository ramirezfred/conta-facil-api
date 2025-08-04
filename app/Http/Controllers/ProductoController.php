<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use App\Models\User;
use App\Models\Producto;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'user_id'=>'required|numeric',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'detail'=>$validator->errors(),
            ],422);
        }

        $user_id = $request->input('user_id');

        $coleccion = Producto::whereNull('flag_eliminado')
            ->where('user_id',$user_id)
            ->with('mi_clave_prod_serv')
            ->with('mi_clave_unidad')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $coleccion
        ], 200);
    }

    public function store(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'user_id'=>'required|numeric',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',

            'ClaveProdServ' => 'required|integer',
            'ClaveUnidad' => 'required|integer',
            'Unidad' => 'required|string',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'detail'=>$validator->errors(),
            ],422);
        }

        $obj = User::where('id', $request->input('user_id'))
            ->whereNull('flag_eliminado')
            ->first();
        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'Usuario no encontrado'
            ], 404);
        }

        if($newObj=Producto::create($validator->validated())){

            $registro = Producto::whereNull('flag_eliminado')
                ->where('id', $newObj->id)
                ->with('mi_clave_prod_serv')
                ->with('mi_clave_unidad')
                ->first();

           return response()->json([
                'success' => true,
                'message'=>'Registro creado con éxito.',
                'data'=>$registro
            ], 200);

        }else{
            return response()->json([
                'success' => false,
                'message'=>'Error al crear el registro.'
            ], 500);
        }
    }

    public function show($id)
    {
        $registro = Producto::where('id', $id)
            ->whereNull('flag_eliminado')
            ->first();

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Producto con id '.$id
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'=>$registro
        ], 200);
    }

    public function update(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.
        $obj = Producto::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el registro con id '.$id
            ], 404);
        }

        // Listado de campos recibidos teóricamente.
        $nombre = $request->input('nombre'); 
        $descripcion = $request->input('descripcion'); 
        $precio = $request->input('precio'); 
        $stock = $request->input('stock'); 
        $ClaveProdServ = $request->input('ClaveProdServ'); 
        $ClaveUnidad = $request->input('ClaveUnidad'); 
        $Unidad = $request->input('Unidad'); 

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos de usuario.

        if ($nombre != null && $nombre!='')
        {
            $aux = Producto::whereNull('flag_eliminado')
                ->where('nombre', $nombre)
                ->where('id', '<>', $obj->id)
                ->get();
            if(count($aux)!=0){
                return response()->json(['error'=>'Ya existe otro registro con ese nombre.'], 409);    
            }

            $obj->nombre = $nombre;
            $bandera=true;
        }

        if ($descripcion != null && $descripcion!='')
        {
            $obj->descripcion = $descripcion;
            $bandera=true;
        }

        if (($precio != null && $precio!='')||$precio===0)
        {
            $obj->precio = $precio;
            $bandera=true;
        }

        if (($stock != null && $stock!='')||$stock===0)
        {
            $obj->stock = $stock;
            $bandera=true;
        }

        if ($ClaveProdServ != null && $ClaveProdServ!='')
        {
            $obj->ClaveProdServ = $ClaveProdServ;
            $bandera=true;
        }

        if ($ClaveUnidad != null && $ClaveUnidad!='')
        {
            $obj->ClaveUnidad = $ClaveUnidad;
            $bandera=true;
        }

        if ($Unidad != null && $Unidad!='')
        {
            $obj->Unidad = $Unidad;
            $bandera=true;
        }


        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($obj->save()) {

                $registro = Producto::whereNull('flag_eliminado')
                    ->where('id', $id)
                    ->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad')
                    ->first();

                return response()->json([
                    'success' => true,
                    'message'=>'Registro editado con éxito.',
                    'data'=>$registro
                ], 200);
            }else{
                return response()->json([
                    'success' => false,
                    'message'=>'Error al actualizar el registro.'
                ], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json([
                'success' => false,
                'message'=>'No se ha modificado ningún dato al registro.'
            ],409);
        }
    }

    public function destroy($id)
    {
        $obj=Producto::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'Registro no encontrado.'
            ], 404);
        } 

        // Eliminamos el obj
        //$obj->delete();

        $obj->flag_eliminado = 1;
        $obj->save();

        return response()->json([
            'success' => true,
            'message'=>'Se ha eliminado correctamente el registro.'
        ], 200);
    }
}
