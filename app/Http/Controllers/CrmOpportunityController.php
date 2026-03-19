<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;
use Exception;

use App\Models\User;
use App\Models\CfdiEmpresa;
use App\Models\CfdiCliente;
use App\Models\CrmOpportunity;

use Carbon\Carbon;

class CrmOpportunityController extends Controller
{
    /**
     * Listado de oportunidades con filtros opcionales
     */
    public function index(Request $request)
    {
        $query = CrmOpportunity::with('contacto')
            ->with('quote')
            ->with('nextTask')
            ->noEliminados();

        // === Filtros dinámicos ===
        if ($request->filled('etapa') && $request->etapa !== 'todas') {
            $query->where('etapa', $request->etapa);
        }

        if ($request->filled('fuente_lead')) {
            $query->where('fuente_lead', $request->fuente_lead);
        }

        if ($request->filled('contacto_id')) {
            $query->where('contacto_id', $request->contacto_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%")
                  ->orWhereHas('contacto', function ($qc) use ($search) {
                      $qc->where('Nombre', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled(['fecha_inicio', 'fecha_fin'])) {
            $start = Carbon::parse($request->fecha_inicio)->startOfDay();
            $end   = Carbon::parse($request->fecha_fin)->endOfDay();

            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $oportunidades = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $oportunidades
        ]);
    }

    public function estadisticas(Request $request)
    {
        $query = CrmOpportunity::noEliminados();

        // === Filtros dinámicos ===
        if ($request->filled('etapa') && $request->etapa !== 'todas') {
            $query->where('etapa', $request->etapa);
        }

        if ($request->filled('fuente_lead')) {
            $query->where('fuente_lead', $request->fuente_lead);
        }

        if ($request->filled('contacto_id')) {
            $query->where('contacto_id', $request->contacto_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%")
                  ->orWhereHas('contacto', function ($qc) use ($search) {
                      $qc->where('Nombre', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled(['fecha_inicio', 'fecha_fin'])) {
            $start = Carbon::parse($request->fecha_inicio)->startOfDay();
            $end   = Carbon::parse($request->fecha_fin)->endOfDay();

            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // === Clonamos la query para estadísticas sin alterar la principal ===
        $statsQuery = clone $query;

        $total = $statsQuery->count();
        $ganadas = (clone $statsQuery)->where('etapa', 'ganada')->count();
        $perdidas = (clone $statsQuery)->where('etapa', 'perdida')->count();
        $valorTotal = (clone $statsQuery)->sum('monto_estimado');
        $valorGanado = (clone $statsQuery)->where('etapa', 'ganada')->sum('monto_estimado');

        $porEtapa = (clone $statsQuery)
            ->select('etapa', DB::raw('COUNT(*) as total'))
            ->groupBy('etapa')
            ->get();

        $porFuente = (clone $statsQuery)
            ->select('fuente_lead', DB::raw('COUNT(*) as total'))
            ->groupBy('fuente_lead')
            ->get();

        $tasaCierre = $total > 0 ? round(($ganadas / $total) * 100, 2) : 0;

        // === Oportunidades finales ===
        $oportunidades = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => null,
            'stats' => [
                'total' => $total,
                'ganadas' => $ganadas,
                'perdidas' => $perdidas,
                'valor_total' => $valorTotal,
                'valor_ganado' => $valorGanado,
                'por_etapa' => $porEtapa,
                'por_fuente' => $porFuente,
                'tasa_cierre' => $tasaCierre,
            ]
        ]);
    }

    /**
     * Crear una nueva oportunidad
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'user_id' => 'required|numeric',
            // 'contacto_id' => 'required|exists:cfdi_clientes,id',
            'contacto_id' => 'required|numeric',
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'monto_estimado' => 'nullable|numeric|min:0',
            'fuente_lead' => 'nullable|string|max:100',
            'etapa' => 'nullable|string|max:50',
            'probabilidad' => 'nullable|string|max:10',
            'fecha_cierre_estimada' => 'nullable|date',
            'comentarios' => 'nullable|string',
            
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

        $contacto = CfdiCliente::noEliminados()
            ->where('id', $request->input('contacto_id'))
            ->first();
        if (!$contacto)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Contacto con id '.$request->input('contacto_id')
            ], 404);
        }

        $opportunity = CrmOpportunity::create($validator->validated());

        $opportunity = CrmOpportunity::with('contacto')
            ->with('quote')
            ->with('nextTask')
            ->find($opportunity->id);

        return response()->json([
            'success' => true,
            'message' => 'Oportunidad creada correctamente',
            'data' => $opportunity,
        ], 201);
    }

    /**
     * Mostrar una oportunidad específica
     */
    public function show($id)
    {
        $registro = CrmOpportunity::with('contacto')
            ->with('quote')
            ->with('nextTask')
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
     * Actualizar una oportunidad
     */
    public function update(Request $request, $id)
    {
        $registro = CrmOpportunity::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'contacto_id' => 'sometimes|required|numeric',
            'titulo' => 'sometimes|string|max:255',
            'descripcion' => 'sometimes|nullable|string',
            'monto_estimado' => 'sometimes|nullable|numeric|min:0',
            'fuente_lead' => 'sometimes|nullable|string|max:100',
            'etapa' => 'sometimes|nullable|string|max:50',
            'probabilidad' => 'sometimes|nullable|string|max:10',
            'fecha_cierre_estimada' => 'sometimes|nullable|date',
            'comentarios' => 'sometimes|nullable|string',
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
        $contacto_id = $request->input('contacto_id');
        $titulo = $request->input('titulo');
        $descripcion = $request->input('descripcion');
        $monto_estimado = $request->input('monto_estimado');
        $fuente_lead = $request->input('fuente_lead');
        $etapa = $request->input('etapa');
        $probabilidad = $request->input('probabilidad');
        $fecha_cierre_estimada = $request->input('fecha_cierre_estimada');
        $comentarios = $request->input('comentarios');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        if ($contacto_id != null && $contacto_id != '')
        {
            $contacto = CfdiCliente::noEliminados()
                ->where('id', $request->input('contacto_id'))
                ->first();
            if (!$contacto)
            {
                // Devolvemos error codigo http 404
                return response()->json([
                    'success' => false,
                    'message'=>'No existe el Contacto con id '.$request->input('contacto_id')
                ], 404);
            }

            $registro->contacto_id = $contacto_id;
            $bandera=true;
        }

        if ($titulo != null && $titulo != '')
        {
            $registro->titulo = $titulo;
            $bandera=true;
        }

        if ($descripcion != null && $descripcion != '')
        {
            $registro->descripcion = $descripcion;
            $bandera=true;
        }

        if (($monto_estimado != null && $monto_estimado != '') || $monto_estimado === 0)
        {
            $registro->monto_estimado = $monto_estimado;
            $bandera=true;
        }

        if ($fuente_lead != null && $fuente_lead != '')
        {
            $registro->fuente_lead = $fuente_lead;
            $bandera=true;
        }

        if ($etapa != null && $etapa != '')
        {
            if ($etapa !== $registro->etapa) {
                $this->onStageChanged($registro, $etapa);
            }
        }

        if ($probabilidad != null && $probabilidad != '')
        {
            $registro->probabilidad = $probabilidad;
            $bandera=true;
        }

        if ($fecha_cierre_estimada != null && $fecha_cierre_estimada != '')
        {
            $registro->fecha_cierre_estimada = $fecha_cierre_estimada;
            $bandera=true;
        }

        if ($comentarios != null && $comentarios != '')
        {
            $registro->comentarios = $comentarios;
            $bandera=true;
        }

        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($registro->save()) {

                $opportunity = CrmOpportunity::with('contacto')
                    ->with('quote')
                    ->with('nextTask')
                    ->find($id);

                return response()->json([
                    'success' => true,
                    'message'=>'Registro editado con éxito.',
                    'data'=>$opportunity
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
                'message'=>'No se han detectado cambios para actualizar el registro.'
            ], 200);
        }
    }

    /**
     * Eliminar lógicamente una oportunidad
     */
    public function destroy($id)
    {
        $registro = CrmOpportunity::find($id);

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

    /**
     * Cambiar estado o etapa de oportunidad (acción rápida)
     */
    public function cambiarEtapa(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'etapa' => 'required|string|max:50',
            'probabilidad' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data'=>$validator->errors(),
            ],422);
        }

        $registro = CrmOpportunity::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        // $registro->update([
        //     'etapa' => $request->etapa,
        //     'probabilidad' => $request->probabilidad ?? $registro->probabilidad,
        // ]);

        // Lógica de workflow (cambia etapa + efectos secundarios)
        $this->onStageChanged($registro, $request->etapa);

        // Actualizar probabilidad aparte:
        if ($request->filled('probabilidad')) {
            $registro->probabilidad = $request->probabilidad;
            $registro->save();
        }

        $opportunity = CrmOpportunity::with('contacto')
            ->with('quote')
            ->with('nextTask')
            ->find($id);

        return response()->json([
            'success' => true,
            'message' => 'Etapa actualizada correctamente',
            'data' => $opportunity,
        ]);
    }

    /**
     * Maneja los efectos secundarios cuando la oportunidad cambia de etapa.
     */
    public function onStageChanged(CrmOpportunity $op, string $newStage)
    {
        $oldStage = $op->etapa;

        if ($oldStage === $newStage) {
            return;
        }

        $op->etapa = $newStage;
        $op->save();

        $quote = $op->quote ?? null; // si usas hasOne()

        if (!$quote) {
            return; // La oportunidad no tiene cotización
        }

        // === Ajustes automáticos ===
        switch ($newStage) {

            case 'ganada':
                // Si la cotización estaba enviada → debe aceptarse
                if ($quote->estado === 'enviada') {
                    $quote->estado = 'aceptada';
                    $quote->save();
                }
                break;

            case 'perdida':
                // Si estaba enviada → rechazar
                if ($quote->estado === 'enviada') {
                    $quote->estado = 'rechazada';
                    $quote->save();
                }
                break;

            case 'pospuesta':
                // No afecta la cotización
                break;

            default:
                // Otros cambios no afectan cotización
                break;
        }
    }
}
