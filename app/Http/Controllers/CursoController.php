<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use DB;

use Carbon\Carbon;

use App\Models\Curso;
use App\Models\CursoLike;


class CursoController extends Controller
{
    public function index()
    {
        $coleccion = Curso::whereNull('flag_eliminado')->get();

        return response()->json([
            'coleccion' => $coleccion
        ], 200);
    }

    public function indexCliente($user_id)
    {
        $likedCursoIds = CursoLike::where('user_id', $user_id)->pluck('curso_id')->toArray();

        $coleccion = Curso::whereNull('flag_eliminado')
            ->orderBy('id', 'desc')
            ->get();

        foreach ($coleccion as $curso) {
            $curso->liked_by_user = in_array($curso->id, $likedCursoIds);
        }

        return response()->json([
            'coleccion' => $coleccion
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
        
        $destinationPath = public_path().'/cursos/';
        //$destinationPath = public_path('cursos');
        $archivo->move($destinationPath,$fileName);

        // Obtiene la URL del archivo guardado
        $url = asset('cursos/' . $fileName);

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
            'tipo'=>'required|numeric',
            'nombre'=>'required|string',
            'url'=>'required|string',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        
        if($newObj=Curso::create([
            'tipo'=> $request->input('tipo'),
            'nombre'=> $request->input('nombre'),
            'url'=> $request->input('url'),
            'imagen'=> $request->input('imagen'),
            'autor'=> $request->input('autor'),
            'descripcion'=> $request->input('descripcion')
        ])){

           return response()->json(['message'=>'Registro creado con éxito.',
             'registro'=>$newObj], 200);
        }else{
            return response()->json(['error'=>'Error al crear el registro.'], 500);
        }
    }

    public function destroy($id)
    {
        $obj=Curso::find($id);

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

    public function darLike(Request $request)
    {
        // Dar like
        CursoLike::firstOrCreate([
            'curso_id' => $request->input('curso_id'),
            'user_id' => $request->input('user_id')
        ]);

        Curso::where('id', $request->input('curso_id'))->increment('likes_count');

        return response()->json([
            'success' => true,
            // 'data' => null,
            'message' => 'Like agregado correctamente',
        ]);
    }

    public function quitarLike(Request $request)
    {
        // Quitar like
        CursoLike::where('curso_id', $request->input('curso_id'))
            ->where('user_id', $request->input('user_id'))
            ->delete();

        $curso = Curso::find($request->input('curso_id'));
        if ($curso && $curso->likes_count > 0) {
            $curso->decrement('likes_count');
        }

        return response()->json([
            'success' => true,
            // 'data' => null,
            // 'message' => null,
        ]);
    }
}
