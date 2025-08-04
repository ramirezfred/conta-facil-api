<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Sistema;

class SistemaController extends Controller
{
    public function index()
    {
        $coleccion = Sistema::all();

        if (count($coleccion) > 0) {
            return response()->json(['sistema'=>$coleccion[0]], 200);
        }else{
            return response()->json(['sistema'=>null], 200);
        }

    }

    public function update(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $obj = Sistema::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el registro con id '.$id], 404);
        }

        // Listado de campos recibidos teóricamente.
        $url_video=$request->input('url_video'); 
        $url_manual=$request->input('url_manual'); 

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos de usuario.
        if ($url_video != null && $url_video!='')
        {
            $obj->url_video = $url_video;
            $bandera=true;
        }

        if ($url_manual != null && $url_manual!='')
        {
            $obj->url_manual = $url_manual;
            $bandera=true;
        }


        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($obj->save()) {
                return response()->json(['message'=>'Sistema configurado con éxito.',
                    'sistema'=>$obj], 200);
            }else{
                return response()->json(['error'=>'Error al configurar el sistema.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al sistema.'],409);
        }
    }
}
