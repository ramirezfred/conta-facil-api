<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use DB;

use Carbon\Carbon;

use App\Models\Carpeta;
use App\Models\Documento;

class CalculadorasController extends Controller
{
    public function index()
    {

        $coleccion = Carpeta::with('documentos')
            ->get();

        return response()->json([
            'coleccion'=>$coleccion
        ], 200);
        
    }

    public function indexCliente()
    {
        $coleccion = Carpeta::with('documentos')
            ->has('documentos') // Filtra carpetas con al menos un documento
            ->get();

        return response()->json([
            'coleccion' => $coleccion
        ], 200);
    }

    public function storeCarpeta(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'texto'=>'required|string',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $aux = Carpeta::
            where('texto', $request->input('texto'))
            ->get();
        if(count($aux)!=0){
            return response()->json(['error'=>'Ya existe una carpeta con ese nombre.'], 409);    
        }

        if($newObj=Carpeta::create([
            'texto'=> $request->input('texto')
        ])){

           return response()->json(['message'=>'Registro creado con éxito.',
             'registro'=>$newObj], 200);

        }else{
            return response()->json(['error'=>'Error al crear el registro.'], 500);
        }
    }

    public function storeDocumento(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'carpeta_id'=>'required|numeric',
            'texto'=>'required|string',
            'url'=>'required|string',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $aux = Carpeta::find($request->input('carpeta_id'));
        if(!$aux){
            return response()->json(['error'=>'No existe la carpeta a la cual se quiere asociar el documento.'], 404);    
        }

        $aux2 = Documento::
            where('carpeta_id', $request->input('carpeta_id'))
            ->where('texto', $request->input('texto'))
            ->get();
        if(count($aux2)!=0){
            return response()->json(['error'=>'Ya existe un documento con ese nombre asociado a la carpeta.'], 409);    
        }

        if($newObj=Documento::create([
            'carpeta_id'=> $request->input('carpeta_id'),
            'texto'=> $request->input('texto'),
            'url'=> $request->input('url')
        ])){

           return response()->json(['message'=>'Registro creado con éxito.',
             'registro'=>$newObj], 200);

        }else{
            return response()->json(['error'=>'Error al crear el registro.'], 500);
        }
    }

    public function updateCarpeta(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $obj = Carpeta::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el registro con id '.$id], 404);
        }

        // Listado de campos recibidos teóricamente.
        $texto=$request->input('texto'); 

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos de usuario.
        if ($texto != null && $texto!='')
        {
            $aux = Carpeta::
                where('texto', $texto)
                ->where('id', '<>', $obj->id)->get();

            if(count($aux)!=0){
               // Devolvemos un código 409 Conflict. 
                return response()->json(['error'=>'Ya existe otra carpeta con ese nombre.'], 409);
            }

            $obj->texto = $texto;
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

    public function updateDocumento(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $obj = Documento::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el registro con id '.$id], 404);
        }

        // Listado de campos recibidos teóricamente.
        $texto=$request->input('texto');
        $url=$request->input('url'); 

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos de usuario.
        if ($texto != null && $texto!='')
        {
            $aux = Documento::
                where('texto', $texto)
                ->where('carpeta_id', $obj->carpeta_id)
                ->where('id', '<>', $obj->id)->get();

            if(count($aux)!=0){
               // Devolvemos un código 409 Conflict. 
                return response()->json(['error'=>'Ya existe otro documento con ese nombre asociado a la carpeta.'], 409);
            }

            $obj->texto = $texto;
            $bandera=true;
        }

        if ($url != null && $url!='')
        {
            $url_old = $obj->url;

            $obj->url = $url;
            $bandera=true;

            if($url_old != $url){
                if($url_old != null && $url_old != ''){
                    //Eliminar el archivo viejo
                    $cadenas = explode('/',$url_old);
                    $destinationPath = public_path().DIRECTORY_SEPARATOR."calculadoras".DIRECTORY_SEPARATOR;
                    $fileName = $cadenas[count($cadenas)-1];
                    $archivo_ruta = $destinationPath.$fileName;
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar el archivo
                    }
                }
            }
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

    public function destroyCarpeta($id)
    {
        $obj=Carpeta::with('documentos')->find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Registro no encontrado.'], 404);
        }
        
        for ($i=0; $i < count($obj->documentos); $i++) { 

            if($obj->documentos[$i]->url != null && $obj->documentos[$i]->url != ''){
                //Eliminar el archivo
                $cadenas = explode('/',$obj->documentos[$i]->url);
                $destinationPath = public_path().DIRECTORY_SEPARATOR."calculadoras".DIRECTORY_SEPARATOR;
                $fileName = $cadenas[count($cadenas)-1];
                $archivo_ruta = $destinationPath.$fileName;
                if (file_exists($archivo_ruta)) {
                    unlink($archivo_ruta); // Eliminar el archivo
                }
            }

            $obj->documentos[$i]->delete();
        }

        // Eliminamos el obj
        $obj->delete();

        return response()->json(['message'=>'Se ha eliminado correctamente el registro.'], 200);
    }

    public function destroyDocumento($id)
    {
        $obj=Documento::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Registro no encontrado.'], 404);
        }

        if($obj->url != null && $obj->url != ''){
            //Eliminar el archivo
            $cadenas = explode('/',$obj->url);
            $destinationPath = public_path().DIRECTORY_SEPARATOR."calculadoras".DIRECTORY_SEPARATOR;
            $fileName = $cadenas[count($cadenas)-1];
            $archivo_ruta = $destinationPath.$fileName;
            if (file_exists($archivo_ruta)) {
                unlink($archivo_ruta); // Eliminar el archivo
            }
        }

        // Eliminamos el obj
        $obj->delete();

        return response()->json(['message'=>'Se ha eliminado correctamente el registro.'], 200);
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
        
        $destinationPath = public_path().'/calculadoras/';
        //$destinationPath = public_path('calculadoras');
        $archivo->move($destinationPath,$fileName);

        // Obtiene la URL del archivo guardado
        $url = asset('calculadoras/' . $fileName);

        return response()->json([
            'message'=>'Archivo cargado y configurado con éxito.',
            'url'=>$url,
            'fileName'=>$fileName,
         ], 200);
    }
}
