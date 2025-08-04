<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Auth;

use Exception;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

use App\Models\User;
//use Hash;
use DB;
//use Validator;

use Carbon\Carbon;

use Mail;
use Session;
use Redirect;
use Swift_SmtpTransport;
use Swift_Mailer;

date_default_timezone_set('America/Mexico_City');

class PasswordController extends Controller
{
    public function emailRecoverPassword(Request $request)
    {    

        $usuario = User::where('email',$request->input('email'))->first();
        if(!$usuario){
            return response()->json(['error'=>'Usuario no encontrado'], 404);
        }

        //Admin
        if($usuario->rol == 1){
            return response()->json(['error'=>'Permisos inválidos.'], 404);
        }

        try{   

            if (!$token = JWTAuth::fromUser($usuario)) {
                return response()->json(['error' => 'could_not_create_token'], 401);
            }

            //$usuario = JWTAuth::toUser($token);

            $link = 'https://contafacil.internow.com.mx/#/recover-password/'.$token;

            $details = [

                'link' => $link,

            ];

            \Mail::to($usuario->email)->send(new \App\Mail\RecoverPasswordEmail($details));

        }catch (Exception $e) {
            return response()->json(['error'=>'Error al enviar email, verifique que el email sea válido.'],500);
        }

        return response()->json([
            'message'=>'Te hemos enviado un email con los pasos para restablecer tu contraseña.',
            //'token' => $token,
            'link' => $link,
        ], 200);


    }

    public function updatePassword(Request $request)
    {
        try{ 
            //$currentUser = JWTAuth::toUser($request->input('token'));
            $currentUser = JWTAuth::parseToken()->authenticate();

            if ($currentUser) { 

                $usuario = User::find($currentUser->id);
                if(!$usuario){
                    return response()->json(['error'=>'Usuario no encontrado'], 404);
                }

                //return response()->json(['usuario'=>$usuario], 200);

            }else{  
                return response()->json(['error'=>'Usuario no autenticado.'], 500);        
            }

        } catch (Exception $e) {
            return response()->json(['error'=>'Error al autenticar.'], 500);
        }

        //Admin
        if($usuario->rol == 1){
            return response()->json(['error'=>'Permisos inválidos.'], 404);
        }
        
        // Listado de campos recibidos teóricamente.
        $password=$request->input('password'); 

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.
        if ($password != null && $password != '')
        {
            $usuario->password = Hash::make($password);
            $bandera=true;
        }

        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($usuario->save()) {
                return response()->json(['message'=>'Password actualizado con éxito'], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar.'], 500);
            }        
        }
        else
        {
            // Se devuelve un array error con los error encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato.'],500);
        }
    }
}
