<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;
use Exception;

use App\Models\CrmOpportunity;
use App\Models\CrmTask;

use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

class CrmTaskController extends Controller
{
    // LISTAR tareas de una oportunidad
    public function index($opportunityId)
    {
        $tasks = CrmTask::deOpportunity($opportunityId)
            ->orderBy('fecha_programada', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tasks
        ]);
    }

    // CREAR tarea
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'opportunity_id' => 'nullable|exists:crm_opportunities,id',
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha_programada' => 'required|date',
            
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $opportunity = CrmOpportunity::noEliminados()
            ->where('id', $request->input('opportunity_id'))
            ->first();
        if (!$opportunity)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe la oportunidad con id '.$request->input('opportunity_id')
            ], 404);
        }

        $task = CrmTask::create($validator->validated());

        // $task = CrmTask::find($task->id);

        $opportunity = CrmOpportunity::with('contacto')
                ->with('quote')
                ->with('nextTask')
                ->find($task->opportunity_id);

        return response()->json([
            'success' => true,
            'message' => 'Actividad creada correctamente',
            'data' => $opportunity,
        ], 201);
    }

    // VER una tarea
    public function show($id)
    {
        $registro = CrmTask::find($id);

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

    // ACTUALIZAR
    public function update(Request $request, $id)
    {
        $task = CrmTask::find($id);

        if (!$task)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        $validator = Validator::make($request->all(), [

            'titulo' => 'sometimes|required|string|max:255',
            'descripcion' => 'sometimes|nullable|string',
            'fecha_programada' => 'sometimes|required|date',
            
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($task->estado === 'completada') {
            return response()->json([
                'success' => false,
                'message' => 'La actividad ya está completada y no se puede modificar'
            ], 403);
        }

        $task->update($validator->validated());

        // $task = CrmTask::find($id);

        $opportunity = CrmOpportunity::with('contacto')
                ->with('quote')
                ->with('nextTask')
                ->find($task->opportunity_id);

        return response()->json([
            'success' => true,
            'message'=>'Actividad editada.',
            'data'=>$opportunity
        ], 200);
    }

    // ELIMINAR
    public function destroy($id)
    {
        $registro = CrmTask::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        $registro->delete();

        return response()->json([
            'success' => true,
            'message'=>'Se ha eliminado correctamente el registro.'
        ], 200);
    }

    // MARCAR COMO COMPLETADA
    public function completar(Request $request, $id)
    {
        $task = CrmTask::find($id);

        if (!$task)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        if ($task->estado === 'completada') {
            return response()->json([
                'success' => false,
                'message' => 'La actividad ya está completada'
            ], 400);
        }

        $task->estado = 'completada';
        $task->fecha_completada = now();
        $task->notas = $request->notas ?? $task->notas;
        $task->save();

        $opportunity = CrmOpportunity::with('contacto')
                ->with('quote')
                ->with('nextTask')
                ->find($task->opportunity_id);

        return response()->json([
            'success' => true,
            'message'=>'Actividad Completada.',
            'data'=>$opportunity
        ], 200);
    }

    // MARCAR COMO CANCELADA
    public function cancelar(Request $request, $id)
    {
        $task = CrmTask::find($id);

        if (!$task)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        if ($task->estado === 'cancelada') {
            return response()->json([
                'success' => false,
                'message' => 'La actividad ya está cancelada'
            ], 400);
        }

        $task->estado = 'cancelada';
        $task->save();

        $opportunity = CrmOpportunity::with('contacto')
                ->with('quote')
                ->with('nextTask')
                ->find($task->opportunity_id);

        return response()->json([
            'success' => true,
            'message'=>'Actividad Cancelada.',
            'data'=>$opportunity
        ], 200);
    }

    // OBTENER próxima actividad pendiente de una oportunidad
    public function getProximaActividad($opportunityId)
    {
        $task = CrmTask::deOpportunity($opportunityId)
            ->pendientes()
            ->orderBy('fecha_programada', 'asc')
            ->first();

        return $task;
    }
}
