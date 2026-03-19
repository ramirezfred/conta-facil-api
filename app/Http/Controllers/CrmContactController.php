<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;
use Exception;

use App\Models\User;
use App\Models\CfdiEmpresa;
use App\Models\CfdiCliente;

use App\Models\Cfdi40RegimenFiscal;
use App\Models\Cfdi40CodigoPostal;
use App\Models\Cfdi40UsoCfdi;

class CrmContactController extends Controller
{
    /**
     * Muestra una lista de clientes o proveedores (CRM y POS)
     */
    public function index(Request $request)
    {
        $query = CfdiCliente::query()->noEliminados()
            ->with('mi_regimen_fiscal')
            ->with('mi_uso_cfdi');

        // --- Filtros opcionales ---
        if ($request->filled('tipo_entidad') && in_array($request->tipo_entidad, ['cliente', 'proveedor', 'ambos'])) {
            $query->where('tipo_entidad', $request->tipo_entidad);
        }

        if ($request->filled('tipo_cliente')) {
            $query->where('tipo_cliente', $request->tipo_cliente);
        }

        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('Nombre', 'LIKE', "%$search%")
                  ->orWhere('Rfc', 'LIKE', "%$search%")
                  ->orWhere('Email', 'LIKE', "%$search%")
                  ->orWhere('telefono', 'LIKE', "%$search%");
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $clientes = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $clientes,
        ]);
    }

    public function indexClientes(Request $request)
    {
        $query = CfdiCliente::query()->noEliminados()
            ->whereIn("tipo_entidad", ["cliente", "ambos"])
            ->with('mi_regimen_fiscal')
            ->with('mi_uso_cfdi');

        if ($request->filled('tipo_cliente')) {
            $query->where('tipo_cliente', $request->tipo_cliente);
        }

        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('Nombre', 'LIKE', "%$search%")
                  ->orWhere('Rfc', 'LIKE', "%$search%")
                  ->orWhere('Email', 'LIKE', "%$search%")
                  ->orWhere('telefono', 'LIKE', "%$search%");
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $clientes = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $clientes,
        ]);
    }

    public function indexProveedores(Request $request)
    {
        $query = CfdiCliente::query()->noEliminados()
            ->whereIn("tipo_entidad", ["proveedor", "ambos"])
            ->with('mi_regimen_fiscal')
            ->with('mi_uso_cfdi');


        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('Nombre', 'LIKE', "%$search%")
                  ->orWhere('Rfc', 'LIKE', "%$search%")
                  ->orWhere('Email', 'LIKE', "%$search%")
                  ->orWhere('telefono', 'LIKE', "%$search%");
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $clientes = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $clientes,
        ]);
    }

    /**
     * Guarda un nuevo cliente o proveedor
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'user_id' => 'required|numeric',
            'tipo_entidad' => 'required|in:cliente,proveedor,ambos',
            'tipo_cliente' => 'nullable|in:prospecto,cliente',
            'origen' => 'required|in:crm,pos,erp,api',

            'Rfc' => 'required|string|max:13',
            'Nombre' => 'required|string|max:255', //Razon Social
            'RegimenFiscalReceptor' => 'required|numeric',
            'DomicilioFiscalReceptor' => 'required|numeric',
            'direccion' => 'nullable|string',

            'contacto' => 'nullable|string|max:255', //nombre de contacto
            'Email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:30',
            
            'UsoCFDI' => 'nullable|numeric',
            'tipo_operacion' => 'nullable|string',

            'status' => 'boolean',
            
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'errors' => $validator->errors(),
            ], 422);
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

        $empresa = CfdiEmpresa::
            where('user_id',$user->id)
            ->first();

        if (!$empresa)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Empresa no encontrada.'], 404);
        }

        if ($request->filled('Rfc')) {
            
            // Eliminar espacios en blanco y guiones si los hay
            $Rfc = str_replace([' ', '-'], '', $request->input('Rfc'));
            $Rfc = strtoupper($Rfc);

            $rfcValido = "/^[A-Z0-9]{12,13}$/";

            if (preg_match($rfcValido, $Rfc)) {

                if(CfdiCliente::existeDuplicado('Rfc', $Rfc, $request->input('user_id'), null)){
                    return response()->json([
                        'success' => false,
                        'message'=>'Ya existe un contacto con ese RFC.'
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

        if(CfdiCliente::existeDuplicado('Nombre', $request->input('Nombre'), $request->input('user_id'), null)){
            return response()->json([
                'success' => false,
                'message'=>'Ya existe un contacto con ese Nombre (Razón Social).'
            ], 409);    
        }

        if ($request->filled('RegimenFiscalReceptor'))
        {
            //checar si existe en el catalogo
            $RegimenFiscalBD = Cfdi40RegimenFiscal::find($request->input('RegimenFiscalReceptor'));

            if(!$RegimenFiscalBD){
                // El RegimenFiscal no existe en el catalogo
                $message = 'El Régimen fiscal que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un Régimen fiscal diferente.';

                return response()->json([
                    'success' => false,
                    'message'=>$message
                ],409);
            }
            
        }

        if ($request->filled('DomicilioFiscalReceptor'))
        {
            
            // Eliminar espacios en blanco y guiones si los hay
            $CP = str_replace([' ', '-'], '', $request->input('DomicilioFiscalReceptor'));

            $cpValido = "/^[0-9]{5}$/";

            if (preg_match($cpValido, $CP)) {

                //checar si existe en el catalogo
                $CpBD = Cfdi40CodigoPostal::find($CP);

                if(!$CpBD){
                    // El CP no existe en el catalogo
                    $message = 'El código postal que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un código postal diferente.';
                    return response()->json(['error'=>$message],409);
                }
            } else {
                // El CP es inválido
                $message = 'Por favor, verifica el Código Postal. Este campo es el código postal del domicilio fiscal del contribuyente y debe contener una longitud de 5 posiciones.';
                return response()->json([
                    'success' => false,
                    'message'=>$message
                ],409);
            }
        }

        if ($request->filled('Email')) {
            if(CfdiCliente::existeDuplicado('Email', $request->input('Email'), $request->input('user_id'), null)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe un contacto con ese Email.'
                ], 409);    
            }
        }

        if ($request->filled('telefono')) {
            if(CfdiCliente::existeDuplicado('telefono', $request->input('telefono'), $request->input('user_id'), null)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe un contacto con ese Teléfono.'
                ], 409);    
            }
        }

        if ($request->filled('UsoCFDI'))
        {
            //checar si existe en el catalogo
            $UsoCFDIBD = Cfdi40UsoCfdi::find($request->input('UsoCFDI'));

            if(!$UsoCFDIBD){
                // El UsoCFDI no existe en el catalogo
                $message = 'El Uso de CFDI que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un Uso de CFDI diferente.';

                return response()->json([
                    'success' => false,
                    'message'=>$message
                ],409);
            }
            
        }

        $datos = $validator->validated();
        $datos['empresa_id'] = $empresa->id;

        $cliente = CfdiCliente::create($datos);

        $cliente = CfdiCliente::
            with('mi_regimen_fiscal')
            ->with('mi_uso_cfdi')
            ->find($cliente->id);

        return response()->json([
            'success' => true,
            'message' => 'Registro creado con éxito.',
            'data' => $cliente,
        ], 201);
    }

    /**
     * Muestra un cliente por ID
     */
    public function show($id)
    {
        $registro = CfdiCliente::
            with('mi_regimen_fiscal')
            ->with('mi_uso_cfdi')
            ->find($id);

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

    /**
     * Actualiza un cliente existente
     */
    public function update(Request $request, $id)
    {
        $registro = CfdiCliente::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        if ($registro->Rfc == 'XAXX010101000')
        {
            return response()->json([
                'success' => false,
                'message'=>'No es posible editar el registro de Cliente Público en General.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'tipo_entidad' => 'sometimes|required|in:cliente,proveedor,ambos',
            'tipo_cliente' => 'sometimes|nullable|in:prospecto,cliente',
            'origen' => 'sometimes|required|in:crm,pos,erp,api',

            'Rfc' => 'sometimes|required|string|max:13',
            'Nombre' => 'sometimes|required|string|max:255', //Razon Social
            'RegimenFiscalReceptor' => 'sometimes|required|numeric',
            'DomicilioFiscalReceptor' => 'sometimes|required|numeric',
            'direccion' => 'sometimes|nullable|string',

            'contacto' => 'sometimes|nullable|string|max:255', //nombre de contacto
            'Email' => 'sometimes|nullable|email|max:255',
            'telefono' => 'sometimes|nullable|string|max:30',
            
            'UsoCFDI' => 'sometimes|nullable|numeric',
            'tipo_operacion' => 'sometimes|nullable|string',

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
        $tipo_entidad = $request->input('tipo_entidad');
        $tipo_cliente = $request->input('tipo_cliente');
        $origen = $request->input('origen');
        $Rfc = $request->input('Rfc');
        $Nombre = $request->input('Nombre');
        $RegimenFiscalReceptor = $request->input('RegimenFiscalReceptor');
        $DomicilioFiscalReceptor = $request->input('DomicilioFiscalReceptor');
        $direccion = $request->input('direccion');
        $contacto = $request->input('contacto');
        $Email = $request->input('Email');
        $telefono = $request->input('telefono');
        $UsoCFDI = $request->input('UsoCFDI');
        $tipo_operacion = $request->input('tipo_operacion');
        $status = $request->input('status');
        

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        if ($tipo_entidad != null && $tipo_entidad != '')
        {
            $registro->tipo_entidad = $tipo_entidad;
            $bandera=true;
        }

        if ($tipo_cliente != null && $tipo_cliente != '')
        {
            $registro->tipo_cliente = $tipo_cliente;
            $bandera=true;
        }

        if ($origen != null && $origen != '')
        {
            $registro->origen = $origen;
            $bandera=true;
        }

        if ($Rfc != null && $Rfc != '')
        {
            // Eliminar espacios en blanco y guiones si los hay
            $Rfc = str_replace([' ', '-'], '', $request->input('Rfc'));
            $Rfc = strtoupper($Rfc);

            $rfcValido = "/^[A-Z0-9]{12,13}$/";

            if (preg_match($rfcValido, $Rfc)) {

                if(CfdiCliente::existeDuplicado('Rfc', $Rfc, $registro->user_id, $id)){
                    return response()->json([
                        'success' => false,
                        'message'=>'Ya existe otro contacto con ese RFC.'
                    ], 409);    
                }else{
                    $registro->Rfc = $Rfc;
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

        if ($Nombre != null && $Nombre != '')
        {
            if(CfdiCliente::existeDuplicado('Nombre', $Nombre, $registro->user_id, $id)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe otro contacto con ese Nombre (Razón Social).'
                ], 409);    
            }else{
                $registro->Nombre = $Nombre;
                $bandera=true; 
            }
        }

        if ($RegimenFiscalReceptor != null && $RegimenFiscalReceptor != '')
        {

            //checar si existe en el catalogo
            $RegimenFiscalBD = Cfdi40RegimenFiscal::find($RegimenFiscalReceptor);

            if($RegimenFiscalBD){
                $registro->RegimenFiscalReceptor = $RegimenFiscalReceptor;
                $bandera=true; 
            }else{
                // El RegimenFiscal no existe en el catalogo
                $message = 'El Régimen fiscal que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un Régimen fiscal diferente.';

                return response()->json([
                    'success' => false,
                    'message'=>$message
                ],409);
            }
            
        }

        if ($DomicilioFiscalReceptor != null && $DomicilioFiscalReceptor != '')
        {
            
            // Eliminar espacios en blanco y guiones si los hay
            $CP = str_replace([' ', '-'], '', $DomicilioFiscalReceptor);

            $cpValido = "/^[0-9]{5}$/";

            if (preg_match($cpValido, $CP)) {

                //checar si existe en el catalogo
                $CpBD = Cfdi40CodigoPostal::find($CP);

                if($CpBD){
                    $registro->DomicilioFiscalReceptor = $CP;
                    $bandera=true;
                }else{
                    // El CP no existe en el catalogo
                    $message = 'El código postal que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un código postal diferente.';
                    return response()->json([
                        'success' => false,
                        'message'=>$message
                    ],409);
                }
            } else {
                // El CP es inválido
                $message = 'Por favor, verifica el Código Postal. Este campo es el código postal del domicilio fiscal del contribuyente y debe contener una longitud de 5 posiciones.';
                return response()->json([
                    'success' => false,
                    'message'=>$message
                ],409);
            }
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

        if ($Email != null && $Email != '')
        {
            if(CfdiCliente::existeDuplicado('Email', $Email, $registro->user_id, $id)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe otro contacto con ese Email.'
                ], 409);    
            }

            $registro->Email = $Email;
            $bandera=true;
        }

        if ($telefono != null && $telefono != '')
        {
            if(CfdiCliente::existeDuplicado('telefono', $telefono, $registro->user_id, $id)){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe otro contacto con ese Teléfono.'
                ], 409);    
            }

            $registro->telefono = $telefono;
            $bandera=true;
        }

        if ($UsoCFDI != null && $UsoCFDI != '')
        {

            //checar si existe en el catalogo
            $RegimenFiscalBD = Cfdi40UsoCfdi::find($UsoCFDI);

            if($RegimenFiscalBD){
                $registro->UsoCFDI = $UsoCFDI;
                $bandera=true; 
            }else{
                // El RegimenFiscal no existe en el catalogo
                $message = 'El Uso de CFDI que ingresaste no está disponible en nuestro catálogo. Por favor, intenta ingresar un Uso de CFDI diferente.';

                return response()->json([
                    'success' => false,
                    'message'=>$message
                ],409);
            }
            
        }

        if ($tipo_operacion != null && $tipo_operacion != '')
        {
            $registro->tipo_operacion = $tipo_operacion;
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

                $cliente = CfdiCliente::where('id', $id)
                    ->with('mi_regimen_fiscal')
                    ->with('mi_uso_cfdi')
                    ->first();

                return response()->json([
                    'success' => true,
                    'message'=>'Registro editado con éxito.',
                    'data'=>$cliente
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

    /**
     * Eliminado lógico
     */
    public function destroy($id)
    {
        $registro = CfdiCliente::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        if ($registro->Rfc == 'XAXX010101000')
        {
            return response()->json([
                'success' => false,
                'message'=>'No es posible eliminar el registro de Cliente Público en General.'
            ], 403);
        }

        $registro->eliminado = true;
        $registro->save();

        return response()->json([
            'success' => true,
            'message'=>'Se ha eliminado correctamente el registro.'
        ], 200);
    }

}
