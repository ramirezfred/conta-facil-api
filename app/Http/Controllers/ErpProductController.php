<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;
use Exception;

use App\Models\User;
use App\Models\ErpProduct;
use App\Models\ErpCategory;
use App\Models\ErpStockMovement;

class ErpProductController extends Controller
{
    public function index(Request $request)
    {
        $query = ErpProduct::noEliminados();

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('sku', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->has('is_service')) {
            $query->where('is_service', $request->boolean('is_service'));
        }

        // Solo productos con stock
        if ($request->has('con_stock')) {
            $query->where(function ($q) {
                $q->where('is_service', true) // Incluir todos los servicios (sin importar el stock)
                ->orWhere(function ($q2) {
                    $q2->where('is_service', false) // SOLO a los que NO son servicios
                        ->where('stock', '>', 0);  // Aplicarles el filtro de stock > 0
                });
            });
        }

        $coleccion = $query->with('category')
            ->with('mi_clave_prod_serv')
            ->with('mi_clave_unidad')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'=>$coleccion
        ], 200);
    }

    public function show($id)
    {
        $registro = ErpProduct::with('category')
            ->with('mi_clave_prod_serv')
            ->with('mi_clave_unidad')
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

    public function store(Request $request)
    {
        try {
            $response = DB::transaction(function () use ($request) {

                // Primero comprobaremos si estamos recibiendo todos los campos.
                $validator = Validator::make($request->all(),[
                    'name' => 'required|string|max:255',
                    // 'sku' => 'required|string|max:50|unique:erp_products',
                    'sku' => 'required|string|max:50',
                    'description' => 'nullable|string',
                    'purchase_price' => 'required|numeric|min:0',
                    'sale_price' => 'required|numeric|min:0',
                    'impuesto' => 'required|numeric|min:0',
                    'stock' => 'nullable|numeric|min:0',
                    'stock_minimum' => 'nullable|numeric|min:0',
                    'ClaveProdServ' => 'required|numeric',
                    'ClaveUnidad' => 'required|numeric',
                    'Unidad' => 'required|string|max:100',
                    'is_service' => 'boolean',
                    'status' => 'boolean',
                    // 'category_id' => 'nullable|exists:erp_categories,id',
                    'category_id' => 'required|numeric',
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

                // $data['user_id'] = auth()->id();

                $user = User::buscarPorId($request->input('user_id'));
                if (!$user)
                {
                    // Devolvemos error codigo http 404
                    return response()->json([
                        'success' => false,
                        'message'=>'Usuario no encontrado.'
                    ], 404);
                }

                $categoria = ErpCategory::noEliminados()
                    ->where('id', $request->input('category_id'))
                    ->first();
                if (!$categoria)
                {
                    // Devolvemos error codigo http 404
                    return response()->json([
                        'success' => false,
                        'message'=>'Categoría no encontrada.'
                    ], 404);
                }

                $producto = ErpProduct::noEliminados()
                    ->where('name', $request->input('name'))
                    ->where('user_id', $request->input('user_id'))
                    ->first();
                if($producto){
                    return response()->json([
                        'success' => false,
                        'message'=>'Ya existe un producto con ese Nombre.'
                    ], 409);    
                }

                $sku = ErpProduct::noEliminados()
                    ->where('sku', $request->input('sku'))
                    ->where('user_id', $request->input('user_id'))
                    ->first();
                if($sku){
                    return response()->json([
                        'success' => false,
                        'message'=>'Ya existe un producto con ese Código.'
                    ], 409);    
                }

                $product=ErpProduct::create($validator->validated());

                // $registro = ErpCategory::find('id', $category->id);

                // Si no es un servicio y tiene stock inicial, creamos un movimiento de inventario inicial
                if ($product->is_service === false && $product->stock > 0) {
                    ErpStockMovement::create([
                        'product_id' => $product->id,
                        'tipo' => 'inventario_inicial',
                        'cantidad' => $product->stock,
                        'stock_resultante' => $product->stock,
                        // 'referencia_type' => 'App\\Models\\ErpProduct',
                        'referencia_type' => ErpProduct::class,
                        'referencia_id' => $product->id,
                        'user_id' => $product->user_id,
                    ]);

                }

                $registro = ErpProduct::where('id', $product->id)
                    ->with('category')
                    ->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad')
                    ->first();

                return response()->json([
                    'success' => true,
                    'message'=>'Registro creado con éxito.',
                    'data'=>$registro
                ], 201);

            });

            return $response;

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }

    }

    public function update(Request $request, $id)
    {
        $registro = ErpProduct::find($id);

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
            // 'sku' => "sometimes|required|string|max:50|unique:erp_products,sku,{$id}",
            'sku' => 'sometimes|required|string|max:50',
            'description' => 'nullable|string',
            'purchase_price' => 'sometimes|required|numeric|min:0',
            'sale_price' => 'sometimes|required|numeric|min:0',
            'impuesto' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|numeric|min:0',
            'stock_minimum' => 'sometimes|required|numeric|min:0',
            'ClaveProdServ' => 'sometimes|required|numeric',
            'ClaveUnidad' => 'sometimes|required|numeric',
            'Unidad' => 'sometimes|required|string|max:100',
            'is_service' => 'boolean',
            'status' => 'boolean',
            // 'category_id' => 'nullable|exists:erp_categories,id',
            'category_id' => 'sometimes|required|numeric',
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
        $name = $request->input('name');
        $sku = $request->input('sku');
        $description = $request->input('description');
        $purchase_price = $request->input('purchase_price');
        $sale_price = $request->input('sale_price');
        $impuesto = $request->input('impuesto');
        // $stock = $request->input('stock');
        $stock_minimum = $request->input('stock_minimum');
        $ClaveProdServ = $request->input('ClaveProdServ');
        $ClaveUnidad = $request->input('ClaveUnidad');
        $Unidad = $request->input('Unidad');
        $is_service = $request->input('is_service');
        $status = $request->input('status');
        $category_id = $request->input('category_id');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        if ($name != null && $name != '')
        {
            $aux = ErpProduct::noEliminados()
                ->where('id', '<>', $id)
                ->where('name', $name)
                ->where('user_id', $registro->user_id)
                ->first();

            if($aux){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe otro producto con ese nombre.'
                ], 409);    
            }

            $registro->name = $name;
            $bandera=true;
        }

        if ($sku != null && $sku != '')
        {
            $aux = ErpProduct::noEliminados()
                ->where('id', '<>', $id)
                ->where('sku', $sku)
                ->where('user_id', $registro->user_id)
                ->first();

            if($aux){
                return response()->json([
                    'success' => false,
                    'message'=>'Ya existe otro producto con ese sku.'
                ], 409);    
            }

            $registro->sku = $sku;
            $bandera=true;
        }

        if($request->has('description')){
            $registro->description = $description;
            $bandera=true;
        }

        if (($purchase_price != null && $purchase_price != '') || $purchase_price === 0)
        {
            $registro->purchase_price = $purchase_price;
            $bandera=true;
        }

        if (($sale_price  != null && $sale_price != '') || $sale_price === 0)
        {
            $registro->sale_price = $sale_price;
            $bandera=true;
        }

        if (($impuesto  != null && $impuesto != '') || $impuesto === 0)
        {
            $registro->impuesto = $impuesto;
            $bandera=true;
        }

        // if (($stock != null && $stock != '') || $stock === 0)
        // {
        //     $registro->stock = $stock;
        //     $bandera=true;
        // }

        if (($stock_minimum != null && $stock_minimum != '') || $stock_minimum === 0)
        {
            $registro->stock_minimum = $stock_minimum;
            $bandera=true;
        }

        if ($ClaveProdServ != null && $ClaveProdServ != '')
        {
            $registro->ClaveProdServ = $ClaveProdServ;
            $bandera=true;
        }

        if ($ClaveUnidad != null && $ClaveUnidad != '')
        {
            $registro->ClaveUnidad = $ClaveUnidad;
            $bandera=true;
        }

        if ($Unidad != null && $Unidad != '')
        {
            $registro->Unidad = $Unidad;
            $bandera=true;
        }

        if($request->has('is_service')){
            $registro->is_service = $is_service;
            $bandera=true;
        }

        if($request->has('status')){
            $registro->status = $status;
            $bandera=true;
        }

        if ($category_id != null && $category_id != '')
        {
            $categoria = ErpCategory::noEliminados()
                ->where('id', $category_id)
                ->first();
            if (!$categoria)
            {
                // Devolvemos error codigo http 404
                return response()->json([
                    'success' => false,
                    'message'=>'Categoría no encontrada.'
                ], 404);
            }

            $registro->category_id = $category_id;
            $bandera=true;
        }

        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($registro->save()) {

                $product = ErpProduct::where('id', $id)
                    ->with('category')
                    ->with('mi_clave_prod_serv')
                    ->with('mi_clave_unidad')
                    ->first();

                return response()->json([
                    'success' => true,
                    'message'=>'Registro editado con éxito.',
                    'data'=>$product
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
        $registro = ErpProduct::find($id);

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
