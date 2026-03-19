<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;
use Exception;

use App\Models\User;
use App\Models\ErpSupplier;

class ErpSupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = ErpSupplier::noEliminados();

        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('razon_social', 'like', "%$search%")
                ->orWhere('nombre_comercial', 'like', "%$search%")
                ->orWhere('rfc', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%");
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $proveedores = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $proveedores,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'razon_social' => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'rfc' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'telefono' => 'nullable|string|max:30',
            'direccion' => 'nullable|string',
            'contacto' => 'nullable|string|max:255',
            'status' => 'boolean',
            'user_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // $user = User::where('id', $request->input('user_id'))
        //     ->whereNull('flag_eliminado')
        //     ->first();
        $user = User::buscarPorId($request->input('user_id'));
        if (!$user)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'Usuario no encontrado.'
            ], 404);
        }

        // $razon_social = ErpSupplier::noEliminados()
        //     ->where('razon_social', $request->input('razon_social'))
        //     ->where('user_id', $request->input('user_id'))
        //     ->first();
        if(ErpSupplier::existeDuplicado('razon_social', $request->input('razon_social'), $request->input('user_id'), null)){
            return response()->json([
                'success' => false,
                'message'=>'Ya existe un proveedor con esa Razón Social.'
            ], 409);    
        }

        if ($request->filled('nombre_comercial')) {
            // $nombre_comercial = ErpSupplier::noEliminados()
            //     ->where('nombre_comercial', $request->input('nombre_comercial'))
            //     ->where('user_id', $request->input('user_id'))
            //     ->first();
            if(ErpSupplier::existeDuplicado('nombre_comercial', $request->input('nombre_comercial'), $request->input('user_id'), null)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe un proveedor con ese Nombre Comercial.'
                ], 409);    
            }
        }

        if ($request->filled('rfc')) {
            
            // Eliminar espacios en blanco y guiones si los hay
            $Rfc = str_replace([' ', '-'], '', $request->input('rfc'));
            $Rfc = strtoupper($Rfc);

            $rfcValido = "/^[A-Z0-9]{12,13}$/";

            if (preg_match($rfcValido, $Rfc)) {

                // $rfc = ErpSupplier::noEliminados()
                //     ->where('rfc', $request->input('rfc'))
                //     ->where('user_id', $request->input('user_id'))
                //     ->first();
                if(ErpSupplier::existeDuplicado('rfc', $Rfc, $request->input('user_id'), null)){
                    return response()->json([
                        'success' => false,
                        'message'=>'Ya existe un proveedor con ese RFC.'
                    ], 409);    
                }

            } else {
                // El Rfc es inválido
                $message = 'Por favor, verifica el Rfc. En el caso de que sea una persona física, este campo debe contener una longitud de 13 posiciones, si se trata de personas morales debe contener una longitud de 12 posiciones.';
                return response()->json([
                    'success' => false,
                    'message'=>$message
                ],422);
            }
        }

        if ($request->filled('email')) {
            // $email = ErpSupplier::noEliminados()
            //     ->where('email', $request->input('email'))
            //     ->where('user_id', $request->input('user_id'))
            //     ->first();
            if(ErpSupplier::existeDuplicado('email', $request->input('email'), $request->input('user_id'), null)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe un proveedor con ese Email.'
                ], 409);    
            }
        }

        if ($request->filled('telefono')) {
            // $telefono = ErpSupplier::noEliminados()
            //     ->where('telefono', $request->input('telefono'))
            //     ->where('user_id', $request->input('user_id'))
            //     ->first();
            if(ErpSupplier::existeDuplicado('telefono', $request->input('telefono'), $request->input('user_id'), null)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe un proveedor con ese Teléfono.'
                ], 409);    
            }
        }

        // $proveedor = ErpSupplier::create([
        //     ...$validator->validated(),
        //     'user_id' => auth()->id() ?? null,
        // ]);

        $proveedor = ErpSupplier::create($validator->validated());

        $proveedor = ErpSupplier::find($proveedor->id);

        return response()->json([
            'success' => true,
            'message' => 'Registro creado con éxito.',
            'data' => $proveedor,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $registro = ErpSupplier::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        $validator = Validator::make($request->all(),[
            'razon_social' => 'sometimes|required|string|max:255',
            'nombre_comercial' => 'sometimes|nullable|string|max:255',
            'rfc' => 'sometimes|nullable|string|max:20',
            'email' => "sometimes|nullable|string|max:150",
            'telefono' => 'sometimes|nullable|string',
            'direccion' => 'sometimes|nullable|string|max:150',
            'contacto' => 'sometimes|nullable|string|max:255',
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

        // Listado de campos recibidos teóricamente.
        $razon_social = $request->input('razon_social');
        $nombre_comercial = $request->input('nombre_comercial');
        $rfc = $request->input('rfc');
        $email = $request->input('email');
        $telefono = $request->input('telefono');
        $direccion = $request->input('direccion');
        $contacto = $request->input('contacto');
        $status = $request->input('status');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        if ($razon_social != null && $razon_social != '')
        {
            // $aux = ErpSupplier::noEliminados()
            //     ->where('id', '<>', $id)
            //     ->where('razon_social', $razon_social)
            //     ->where('user_id', $registro->user_id)
            //     ->first();

            if(ErpSupplier::existeDuplicado('razon_social', $razon_social, $registro->user_id, $id)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe otro proveedor con esa Razón Social.'
                ], 409);    
            }

            $registro->razon_social = $razon_social;
            $bandera=true;
        }

        if ($nombre_comercial != null && $nombre_comercial != '')
        {
            // $aux = ErpSupplier::noEliminados()
            //     ->where('id', '<>', $id)
            //     ->where('nombre_comercial', $nombre_comercial)
            //     ->where('user_id', $registro->user_id)
            //     ->first();

            if(ErpSupplier::existeDuplicado('nombre_comercial', $nombre_comercial, $registro->user_id, $id)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe otro proveedor con ese Nombre Comercial.'
                ], 409);    
            }

            $registro->nombre_comercial = $nombre_comercial;
            $bandera=true;
        }

        if ($rfc != null && $rfc != '')
        {
            // Eliminar espacios en blanco y guiones si los hay
            $Rfc = str_replace([' ', '-'], '', $request->input('rfc'));
            $Rfc = strtoupper($Rfc);

            $rfcValido = "/^[A-Z0-9]{12,13}$/";

            if (preg_match($rfcValido, $Rfc)) {

                // $rfc_aux = ErpSupplier::noEliminados()
                //     ->where('rfc', $Rfc)
                //     ->where('user_id', $registro->user_id)
                //     ->first();
                if(ErpSupplier::existeDuplicado('rfc', $Rfc, $registro->user_id, $id)){
                    return response()->json([
                        'success' => false,
                        'message'=>'Ya existe otro proveedor con ese RFC.'
                    ], 409);    
                }else{
                    $registro->rfc = $Rfc;
                    $bandera=true; 
                }

            } else {
                // El Rfc es inválido
                $message = 'Por favor, verifica el Rfc. En el caso de que sea una persona física, este campo debe contener una longitud de 13 posiciones, si se trata de personas morales debe contener una longitud de 12 posiciones.';
                return response()->json([
                    'success' => false,
                    'message'=>$message
                ],422);
            }
            
        }

        if ($email != null && $email != '')
        {
            // $aux = ErpSupplier::noEliminados()
            //     ->where('id', '<>', $id)
            //     ->where('email', $email)
            //     ->where('user_id', $registro->user_id)
            //     ->first();

            if(ErpSupplier::existeDuplicado('email', $email, $registro->user_id, $id)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe otro proveedor con ese Email.'
                ], 409);    
            }

            $registro->email = $email;
            $bandera=true;
        }

        if ($telefono != null && $telefono != '')
        {
            // $aux = ErpSupplier::noEliminados()
            //     ->where('id', '<>', $id)
            //     ->where('telefono', $telefono)
            //     ->where('user_id', $registro->user_id)
            //     ->first();

            if(ErpSupplier::existeDuplicado('telefono', $telefono, $registro->user_id, $id)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe otro proveedor con ese Teléfono.'
                ], 409);    
            }

            $registro->telefono = $telefono;
            $bandera=true;
        }

        if ($direccion != null && $direccion != '')
        {
            $registro->direccion = $direccion;
            $bandera=true;
        }

        if ($contacto != null && $contacto != '')
        {
            $registro->contacto = $contacto;
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

                $supplier = ErpSupplier::where('id', $id)
                    ->first();

                return response()->json([
                    'success' => true,
                    'message'=>'Registro editado con éxito.',
                    'data'=>$supplier
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
        $registro = ErpSupplier::find($id);

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
