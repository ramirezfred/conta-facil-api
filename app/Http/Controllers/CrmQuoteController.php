<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

use App\Models\User;
use App\Models\CrmOpportunity;
use App\Models\CrmQuote;
use App\Models\CrmQuoteDetail;
use App\Models\ErpProduct;

use Exception;

use Carbon\Carbon;

use App\Services\UtilitiesService;

class CrmQuoteController extends Controller
{
    /**
     * Listar todas las cotizaciones (no eliminadas)
     */
    public function index()
    {
        $coleccion = CrmQuote::with(['opportunity', 'detalles.product'])
            ->withSum('detalles as nro_productos', 'cantidad')
            ->noEliminados()
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data'=>$coleccion
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'opportunity_id' => 'required|exists:crm_opportunities,id',
            'folio' => 'nullable|string',
            'fecha_emision' => 'required|date',
            'fecha_vencimiento' => 'nullable|date',
            'notas' => 'nullable|string',
            'detalles' => 'required|array|min:1',
            'detalles.*.product_id' => 'required|exists:erp_products,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.0001',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.porcentaje_desc' => 'required|numeric|min:0',
            'detalles.*.porcentaje_impuesto' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data' => $validator->errors()
            ], 422);
        }

        $oportunidad = CrmOpportunity::noEliminados()
            ->where('id', $request->input('opportunity_id'))
            ->with('quote')
            ->with('nextTask')
            ->first();
        if (!$oportunidad)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe la Oportunidad con id '.$request->input('opportunity_id')
            ], 404);
        }

        if ($oportunidad->quote)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'La Oportunidad ya tiene una cotización asociada.'
            ], 404);
        }

        foreach ($request->detalles as $detalle) {
            $producto = ErpProduct::noEliminados()
                ->where('id', $detalle['product_id'])
                ->first();
            if (!$producto)
            {
                // Devolvemos error codigo http 404
                return response()->json([
                    'success' => false,
                    'message'=>'No existe el producto con ID '.$detalle['product_id']
                ], 404);
            }
        }

        DB::beginTransaction();

        try {

            // Generar folio manualmente
            $contador = CrmQuote::whereHas('opportunity', function ($q) use ($oportunidad) {
                $q->where('user_id', $oportunidad->user_id);
            })
            ->count() + 1;

            $folio = 'COT-' . str_pad($oportunidad->user_id, 3, '0', STR_PAD_LEFT)
               . str_pad($contador, 4, '0', STR_PAD_LEFT);

            $quote = CrmQuote::create([
                'opportunity_id' => $request->opportunity_id,
                'fecha_emision' => $request->fecha_emision,
                'fecha_vencimiento' => $request->fecha_vencimiento,
                'estado' => 'borrador',
                'notas' => $request->notas ?? null,
                'folio' => $folio,
            ]);

            $subtotalGeneral = $descuentoGeneral = $impuestoGeneral = $totalGeneral = 0;

            foreach ($request->detalles as $detalle) {
                $producto = ErpProduct::find($detalle['product_id']);

                if (!$producto) {
                    throw new \Exception("El producto con ID {$detalle['product_id']} no existe.");
                }

                // Porcentajes de descuento e impuesto
                $porcentaje_desc = isset($detalle['porcentaje_desc']) ? round($detalle['porcentaje_desc'], 2) : 0;
                $porcentaje_impuesto = isset($detalle['porcentaje_impuesto']) ? round($detalle['porcentaje_impuesto'], 2) : 0;

                // Cálculos base
                $cantidad = round($detalle['cantidad'], 4);
                $precio_unitario = round($detalle['precio_unitario'], 4);
                $subtotal = round($cantidad * $precio_unitario, 4);

                // Descuento calculado
                $descuento = round($subtotal * ($porcentaje_desc / 100), 4);
                $base = $subtotal - $descuento;

                // Impuesto calculado
                $impuesto = round($base * ($porcentaje_impuesto / 100), 4);

                // Total por línea
                $total = round($base + $impuesto, 4);

                // Guardar detalle de compra
                $detalleCompra = CrmQuoteDetail::create([
                    'quote_id' => $quote->id,
                    'product_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio_unitario,
                    'porcentaje_desc' => $porcentaje_desc,
                    'porcentaje_impuesto' => $porcentaje_impuesto,
                    'subtotal' => $subtotal,
                    'descuento' => $descuento,
                    'impuesto' => $impuesto,
                    'total' => $total,
                ]);

                // Totales globales
                $subtotalGeneral += $subtotal;
                $descuentoGeneral += $descuento;
                $impuestoGeneral += $impuesto;
                $totalGeneral += $total;
            }

            // Actualizar totales de la cotizacion
            $quote->update([
                'subtotal' => round($subtotalGeneral, 2),
                'descuento' => round($descuentoGeneral, 2),
                'impuesto' => round($impuestoGeneral, 2),
                'total' => round($totalGeneral, 2)
            ]);

            $document = $this->comprobantePdf($quote->id);

            $quote->update([
                'pdf' => $document,
            ]);

            // Actulizar el valor de la oportunidad con el total de la cotización
            $oportunidad->monto_estimado = round($totalGeneral, 2);
            $oportunidad->save();

            DB::commit();

            // $quote->load('opportunity', 'detalles.product');

            $quote = CrmQuote::with('opportunity', 'detalles.product')
                ->withSum('detalles as nro_productos', 'cantidad')
                ->find($quote->id);

            $opportunity = CrmOpportunity::with('contacto')
                ->with('quote')
                ->with('nextTask')
                ->find($quote->opportunity_id);

            return response()->json([
                'success' => true,
                'message' => 'Cotización registrada con éxito.',
                'data' => $opportunity
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la Cotización. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar una cotización (y sus detalles)
     */
    public function update(Request $request, $id)
    {

        $quote = CrmQuote::find($id);

        if (!$quote) {
            return response()->json([
                'success' => false,
                'message' => 'Cotización no encontrada.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'fecha_emision' => 'sometimes|required|date',
            'fecha_vencimiento' => 'sometimes|nullable|date',
            'estado' => 'sometimes|nullable|string|in:borrador,enviada,aceptada,rechazada',
            'notas' => 'sometimes|nullable|string',

            'detalles' => 'sometimes|array|min:1',

            'detalles.*.product_id' => 'required_with:detalles|exists:erp_products,id',
            'detalles.*.cantidad' => 'required_with:detalles|numeric|min:0.0001',
            'detalles.*.precio_unitario' => 'required_with:detalles|numeric|min:0',
            'detalles.*.porcentaje_desc' => 'nullable|numeric|min:0',
            'detalles.*.porcentaje_impuesto' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data' => $validator->errors()
            ], 422);
        }

        // capturar el estado anterior
        $oldEstado = $quote->estado;

        // impedir editar cotización enviada
        if ($quote->estado === 'enviada' && $request->estado !== 'enviada') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede editar una cotización enviada'
            ], 422);
        }

        // impedir editar cotización que ya fue procesada en POS
        if ($quote->processed_at) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede editar la cotización porque ya fue procesada en el POS.'
            ], 422);
        }

        DB::beginTransaction();

        try {

            // Actualizar cabecera
            $quote->update($request->only([
                'fecha_emision',
                'fecha_vencimiento',
                // 'estado',
                'notas',
            ]));

            // === Cambió el estado? Llama al workflow ===
            if ($request->filled('estado') && $oldEstado !== $request->estado) {

                // Llama al servicio centralizado
                $this->onStateChanged($quote, $request->estado);

                // Recargar modelo con datos actualizados en DB
                $quote->refresh();
            }

            // Si vienen nuevos detalles, se reemplazan
            if ($request->filled('detalles')) {

                foreach ($request->detalles as $detalle) {
                    $producto = ErpProduct::noEliminados()
                        ->where('id', $detalle['product_id'])
                        ->first();
                    if (!$producto)
                    {
                        // Devolvemos error codigo http 404
                        return response()->json([
                            'success' => false,
                            'message'=>'No existe el producto con ID '.$detalle['product_id']
                        ], 404);
                    }
                }

                $quote->detalles()->delete();

                $subtotalGeneral = $descuentoGeneral = $impuestoGeneral = $totalGeneral = 0;

                foreach ($request->detalles as $detalle) {
                    $producto = ErpProduct::find($detalle['product_id']);

                    if (!$producto) {
                        throw new \Exception("El producto con ID {$detalle['product_id']} no existe.");
                    }

                    // Porcentajes de descuento e impuesto
                    $porcentaje_desc = isset($detalle['porcentaje_desc']) ? round($detalle['porcentaje_desc'], 2) : 0;
                    $porcentaje_impuesto = isset($detalle['porcentaje_impuesto']) ? round($detalle['porcentaje_impuesto'], 2) : 0;

                    // Cálculos base
                    $cantidad = round($detalle['cantidad'], 4);
                    $precio_unitario = round($detalle['precio_unitario'], 4);
                    $subtotal = round($cantidad * $precio_unitario, 4);

                    // Descuento calculado
                    $descuento = round($subtotal * ($porcentaje_desc / 100), 4);
                    $base = $subtotal - $descuento;

                    // Impuesto calculado
                    $impuesto = round($base * ($porcentaje_impuesto / 100), 4);

                    // Total por línea
                    $total = round($base + $impuesto, 4);

                    // Guardar detalle de compra
                    $detalleCompra = CrmQuoteDetail::create([
                        'quote_id' => $quote->id,
                        'product_id' => $producto->id,
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precio_unitario,
                        'porcentaje_desc' => $porcentaje_desc,
                        'porcentaje_impuesto' => $porcentaje_impuesto,
                        'subtotal' => $subtotal,
                        'descuento' => $descuento,
                        'impuesto' => $impuesto,
                        'total' => $total,
                    ]);

                    // Totales globales
                    $subtotalGeneral += $subtotal;
                    $descuentoGeneral += $descuento;
                    $impuestoGeneral += $impuesto;
                    $totalGeneral += $total;
                }

                // Actualizar totales de la cotizacion
                $quote->update([
                    'subtotal' => round($subtotalGeneral, 2),
                    'descuento' => round($descuentoGeneral, 2),
                    'impuesto' => round($impuestoGeneral, 2),
                    'total' => round($totalGeneral, 2),
                ]);

                // Actulizar el valor de la oprtunidad con el total de la cotización
                $oportunidad = $quote->opportunity;
                $oportunidad->monto_estimado = round($totalGeneral, 2);
                $oportunidad->save();
            }

            $document = $this->comprobantePdf($quote->id);

            // Actualizar totales de la cotizacion
            $quote->update([
                'pdf' => $document,
            ]);

            DB::commit();

            $quote = CrmQuote::with('opportunity', 'detalles.product')
                ->withSum('detalles as nro_productos', 'cantidad')
                ->find($quote->id);

            $opportunity = CrmOpportunity::with('contacto')
                ->with('quote')
                ->with('nextTask')
                ->find($quote->opportunity_id);

            return response()->json([
                'success' => true,
                'message' => 'Cotización actualizada correctamente',
                'data' => $opportunity
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la cotización. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mostrar una cotización específica
     */
    public function show($id)
    {
        $quote = CrmQuote::with('opportunity.contacto', 'detalles.product')
            ->withSum('detalles as nro_productos', 'cantidad')
            ->find($id);

        if (!$quote) {
            return response()->json([
                'success' => false,
                'message' => 'Cotización no encontrada.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $quote
        ], 200);
    }

    /**
     * Eliminación lógica
     */
    public function destroy($id)
    {
        $registro = CrmQuote::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        // impedir eliminar cotización enviada/aceptada
        if ($quote->estado === 'enviada' || $quote->estado === 'aceptada') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una cotización '.$quote->estado
            ], 422);
        }

        // $registro->eliminado = true;
        // $registro->save();

        $registro->detalles()->delete();
        $registro->delete();

        return response()->json([
            'success' => true,
            'message'=>'Se ha eliminado correctamente el registro.'
        ], 200);
    }

    public function comprobantePdf($id)
    {
        set_time_limit(500);

        $quote = CrmQuote::with('opportunity.contacto', 'detalles.product')
            ->withSum('detalles as nro_productos', 'cantidad')
            ->find($id);

        $usuario = User::find($quote->opportunity->user_id);

        $rgb = UtilitiesService::hexToRgb('#4285cb');

        $data = [
            'r' => $rgb['r'],
            'g' => $rgb['g'],
            'b' => $rgb['b'],
            'header' => $usuario->header,
            'footer' => $usuario->footer,

            'emisor' => $usuario,
            'data' => $quote,
        ];

        //$pdf = Pdf::loadView('cotizaciones.cotizacion', $data);
        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('comprobantes.cotizacion', $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs/comprobantes/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs/comprobantes/' . $nombreArchivo);

        return $url;
    }

    /**
     * Cambiar estado o etapa de oportunidad (acción rápida)
     */
    public function cambiarEstado(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|string|max:50'
        ]);

        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data'=>$validator->errors(),
            ],422);
        }

        $registro = CrmQuote::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        // Lógica de workflow (cambia estado + efectos secundarios)
        $this->onStateChanged($registro, $request->estado);

        $opportunity = CrmOpportunity::with('contacto')
            ->with('quote')
            ->with('nextTask')
            ->find($registro->opportunity_id);

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado correctamente',
            'data' => $opportunity,
        ]);
    }

    /**
     * Maneja los efectos secundarios cuando la cotización cambia de estado.
     */
    public function onStateChanged(CrmQuote $quote, string $newState)
    {
        $oldState = $quote->estado;

        if ($oldState === $newState) {
            return; // No hay cambio real
        }

        DB::transaction(function () use ($quote, $newState) {

            // Actualiza la cotización
            $quote->estado = $newState;
            $quote->save();

            // Reglas: actualizar etapa de la oportunidad
            $op = $quote->opportunity;

            switch ($newState) {
                case 'enviada':

                    if($op->etapa == 'negociacion'){
                        $op->etapa = 'revision';
                        $op->probabilidad = '75%';
                    }else{
                        $op->etapa = 'negociacion';
                        $op->probabilidad = '60%';
                    }
                    
                    break;

                case 'aceptada':
                    $op->etapa = 'ganada';
                    $op->probabilidad = '100%';
                    $op->fecha_cierre_estimada = now()->toDateString(); //para enviar solo YYYY-MM-DD
                    break;

                case 'rechazada':
                    $op->etapa = 'perdida';
                    $op->probabilidad = '0%';
                    break;

                default:
                    // borrador → no mueve la oportunidad
                    break;
            }

            $op->save();
        });
    }

    public function cargarCotizacionToPos($id)
    {
        $quote = CrmQuote::with('opportunity.contacto', 'detalles.product')
            ->withSum('detalles as nro_productos', 'cantidad')
            ->find($id);

        if (!$quote) {
            return response()->json([
                'success' => false,
                'message' => 'Cotización no encontrada.'
            ], 404);
        }

        // impedir procesar dos veces una cotización 
        if ($quote->processed_at) {
            return response()->json([
                'success' => false,
                'message' => 'La cotización ya fue procesada en el POS.'
            ], 422);
        }

        // Validar stock
        foreach ($quote->detalles as $detail) {
            if ($detail->product->stock < $detail->cantidad) {
                return response()->json([
                    'success' => false,
                    'message' => "Stock insuficiente para {$detail->product->name}. Disponible: {$detail->product->stock}, Requerido: {$detail->cantidad}"
                ], 404);
            }
        }

        // Validar producto activo y no eliminado
        foreach ($quote->detalles as $detail) {
            if (!$detail->product->status || $detail->product->eliminado) {
                return response()->json([
                    'success' => false,
                    'message' => "Producto {$detail->product->name} no disponible."
                ], 404);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $quote
        ], 200);
    }

}
