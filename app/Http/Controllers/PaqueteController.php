<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use DB;

use Carbon\Carbon;

use App\Models\Paquete;

class PaqueteController extends Controller
{
    public function index()
    {

        $coleccion = Paquete::whereNull('flag_eliminado')
            ->get();

        return response()->json([
            'coleccion'=>$coleccion
        ], 200);
        
    }

    public function indexCliente()
    {

        $coleccion = Paquete::whereNull('flag_eliminado')
            ->where('status',1)
            ->get();

        return response()->json([
            'coleccion'=>$coleccion
        ], 200);
        
    }

    public function storeArchivo(Request $request)
    {

        if (!$request->hasFile('archivo')) {
            return response()->json(['error'=>'Archivo no detectado.'], 422);
        }

        // Obtiene el archivo de la solicitud
        $archivo = $request->file('archivo');

        // Genera un nombre único para el archivo utilizando el timestamp y el nombre original del archivo
        $fileName = time() . '_' . $archivo->getClientOriginalName();
        
        $destinationPath = public_path().'/paquetes/';
        //$destinationPath = public_path('paquetes');
        $archivo->move($destinationPath,$fileName);

        // Obtiene la URL del archivo guardado
        $url = asset('paquetes/' . $fileName);

        return response()->json([
            'message'=>'Archivo cargado y configurado con éxito.',
            'url'=>$url,
            'fileName'=>$fileName,
         ], 200);
    }

    public function store(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'nombre'=>'required|string',
            'descripcion'=>'required|string',
            'precio'=>'required|numeric',
            'tipo'=>'required|numeric',
            'cantidad'=>'required|numeric',
            'imagen'=>'required|string',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $aux = Paquete::whereNull('flag_eliminado')
            ->where('nombre', $request->input('nombre'))
            ->get();
        if(count($aux)!=0){
            return response()->json(['error'=>'Ya existe un registro con ese nombre.'], 409);    
        }
        
        if($newObj=Paquete::create([
            'nombre'=> $request->input('nombre'),
            'descripcion'=> $request->input('descripcion'),
            'precio'=> $request->input('precio'),
            'tipo'=> $request->input('tipo'),
            'cantidad'=> $request->input('cantidad'),
            'imagen'=> $request->input('imagen'),
            'status'=> 1
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

        $obj = Paquete::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el registro con id '.$id], 404);
        }

        // Listado de campos recibidos teóricamente.
        $nombre=$request->input('nombre'); 
        $descripcion=$request->input('descripcion'); 
        $precio=$request->input('precio'); 
        $cantidad=$request->input('cantidad'); 
        $imagen=$request->input('imagen'); 

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos de usuario.

        if ($nombre != null && $nombre!='')
        {
            $aux = Paquete::whereNull('flag_eliminado')
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

        if (($cantidad != null && $cantidad!='')||$cantidad===0)
        {
            if($cantidad==0){
                return response()->json(['error'=>'Cantidad debe ser mayor a cero.'], 409);    
            }

            $obj->cantidad = $cantidad;
            $bandera=true;
        }

        if ($imagen != null && $imagen!='')
        {
            $obj->imagen = $imagen;
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

    public function updateStatus(Request $request, $id)
    {
        // Comprobamos si el usuario que nos están pasando existe o no.
        $obj=Paquete::find($id);

        if (!$obj)
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
            $obj->status = $status;
            $bandera=true;
        }
       
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($obj->save()) {
                return response()->json(['message'=>'Registro actualizado.',
                 'registro'=>$obj], 200);
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
        $obj=Paquete::find($id);

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
