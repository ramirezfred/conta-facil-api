<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use App\Models\User;
use App\Models\UserSetting;

class UserSettingController extends Controller
{
    public function storeOrUpdate(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'user_id' => 'required|exists:users,id',
            'notificaciones_flag' => 'required|boolean',
            'notificaciones_email' => 'required|email',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $validated = $validator->validated();

        $setting = UserSetting::updateOrCreate(
            ['user_id' => $validated['user_id']], // Condición de búsqueda
            [ // Valores a actualizar/crear
                'notificaciones_flag' => $validated['notificaciones_flag'],
                'notificaciones_email' => $validated['notificaciones_email'],
            ]
        );

        return response()->json([
            'message' => 'Configuración actualizada correctamente',
            'registro' => $setting,
        ]);
    }

    public function show($user_id)
    {
        $setting = UserSetting::where('user_id', $user_id)->first();

        if (!$setting) {

            $user = User::find($user_id);

            if ($user) {
                return response()->json([
                    'setting_id' => null,
                    'user_id' => $user->id,
                    'notificaciones_flag' => 0,
                    'notificaciones_email' => $user->email,
                ]);
            } else {
                return response()->json(['message' => 'No se encontró usuario con este ID'
                ], 404);
            }
        }

        return response()->json([
            'setting_id' => $setting->id,
            'user_id' => $setting->user_id,
            'notificaciones_flag' => $setting->notificaciones_flag,
            'notificaciones_email' => $setting->notificaciones_email,
        ]);
    }

    public function desactivarRecordatorios(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Error de validación',
                'detalle' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $setting = UserSetting::firstOrNew(['user_id' => $validated['user_id']]);
        $setting->notificaciones_flag = false;
        // Nota: no se toca el campo 'notificaciones_email'
        $setting->save();

        return response()->json([
            'message' => 'Recordatorios desactivados correctamente',
            'registro' => $setting,
        ]);
    }

}
