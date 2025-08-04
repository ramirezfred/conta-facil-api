<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Crypt;

use Illuminate\Http\Request;

use Exception;

class CryptController extends Controller
{
    public function encrypt($cadena)
    {
        try {
            $claveAdicional = config('app.lada_a');

            $cadenaEncriptada = Crypt::encrypt($cadena, $claveAdicional);

            $cadenaDesencriptada = Crypt::decrypt($cadenaEncriptada, $claveAdicional);

            return response()->json([
                'cadenaEncriptada'=>$cadenaEncriptada,
                //'cadenaDesencriptada'=>$cadenaDesencriptada,
            ], 200);

        } catch (Exception $e) {
            return response()->json(['error'=>$e->getMessage()], 409); 
        } 
    }

    public function decrypt($cadena)
    {
        try {
            $claveAdicional = config('app.lada_a');

            $cadenaDesencriptada = Crypt::decrypt($cadena, $claveAdicional);

            return response()->json([
                'cadenaDesencriptada'=>$cadenaDesencriptada,
            ], 200);

        } catch (Exception $e) {
            return response()->json(['error'=>$e->getMessage()], 409); 
        }        
    }
}
