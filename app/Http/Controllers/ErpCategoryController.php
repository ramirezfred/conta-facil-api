<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use App\Models\User;
use App\Models\ErpCategory;

class ErpCategoryController extends Controller
{
    public function index(Request $request)
    {

        $query = ErpCategory::noEliminados();

        // $request->filled('status')
        // Verifica si el campo status viene en el request y no está vacío.
        // Devuelve true si existe y no está vacío (null, '', no cuenta).
        // Devuelve false si no existe o está vacío.

        // Si status = 0, filled() lo considera vacío (porque 0 es falsy en PHP).
        // ntonces no filtrará los inactivos.
        // if ($request->filled('status')) {
        //     $query->where('status', $request->status);
        // }

        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $coleccion = $query->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'=>$coleccion
        ], 200);
    }

    public function show($id)
    {
        $registro = ErpCategory::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'=>$registro
        ], 200);
    }

    public function store(Request $request)
    {

        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'boolean',
            'user_id' => 'required|numeric',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data'=>$validator->errors(),
            ],422);
        }

        $user = User::buscarPorId($request->input('user_id'));
        if (!$user)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'Usuario no encontrado.'
            ], 404);
        }

        // $aux = ErpCategory::noEliminados()
        //     ->where('name', $request->input('name'))
        //     ->first();
        if(ErpCategory::existeDuplicado('name', $request->input('name'), $request->input('user_id'), null)){
            return response()->json([
                'success' => false,
                'message'=>'Ya existe una categoría con ese nombre.'
            ], 409);    
        }

        if($category=ErpCategory::create($validator->validated())){

            // $registro = ErpCategory::find('id', $category->id);

            return response()->json([
                'success' => true,
                'message'=>'Registro creado con éxito.',
                'data'=>$category
            ], 201);

        }else{
            return response()->json([
                'success' => false,
                'message'=>'Error al crear el registro.'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $registro = ErpCategory::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        $validator = Validator::make($request->all(),[
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'status' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data'=>$validator->errors(),
            ],422);
        }

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Listado de campos recibidos teóricamente.
        $name=$request->input('name'); 
        $description=$request->input('description'); 
        $status=$request->input('status'); 

        if ($name != null && $name != '')
        {
            // $aux = ErpCategory::noEliminados()
            //     ->where('id', '<>', $id)
            //     ->where('name', $name)
            //     ->first();

            if(ErpCategory::existeDuplicado('name', $name, $registro->user_id, $id)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe otra categoría con ese nombre.'
                ], 409);    
            }

            $registro->name = $name;
            $bandera=true;
        }

        if($request->has('description')){
            $registro->description = $description;
            $bandera=true;
        }

        if($request->has('status')){
            $registro->status = $status;
            $bandera=true;
        }

        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($registro->save()) {

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
            return response()->json([
                'success' => false,
                'message' => 'No se ha modificado ningún dato al registro.'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $registro = ErpCategory::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        $registro->eliminado = true;
        $registro->save();

        return response()->json([
            'success' => true,
            'message'=>'Se ha eliminado correctamente el registro.'
        ], 200);
    }
}
