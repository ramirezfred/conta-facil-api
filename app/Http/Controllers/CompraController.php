<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\View;

use Illuminate\Support\Facades\Validator;

use Mail;
use Session;
use Redirect;
use Swift_SmtpTransport;
use Swift_Mailer;

use DB;

use Carbon\Carbon;

use Exception;

use App\Models\User;
use App\Models\Paquete;
use App\Models\Compra;

// Se especifica la zona horaria
date_default_timezone_set('America/Mexico_City');

class CompraController extends Controller
{
    //produccion conekta
    //public $conekta_key_privada = "key_2iNIGX3sxbxlikaDAnYJMPE";
    //conekta_key_publica = key_anCayuKKnrhk9wUX40aLm7F
    public $conekta_key_privada = "key_yauwrnENPEy8RolXswmAXlf";

    //pruebas conekta
    // public $conekta_key_privada = "key_LtGFYFRqrKrYchrXBRHVdA";

    //produccion paypal
    //client id : AQvwFtJTdPAiAOXzaoFBI01vKlKLi8qtG84mGzNDr0sC2ck2TraCCVLhhUvt3wd66bxuOqn6rDlHnytO
    //secret : EAl4nalIFJyDuYWvvGhIHNHfkOTsBGuYKZM4c7He__KFfnIRt1fC90Jp8MO-y8GxldClvCMCGod87A4i

    public function postCustomerConekta(
        $name, $email, $phone, $reference, $random_key, $token_id, $key
    )
    {
        
        //Armando la peticion cURL
        $fields = array(
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            "metadata" => array(
                'reference' => $reference,
                'random_key' => $random_key,
            ),
            "payment_sources" => array(
                array(
                'type' => 'card', 
                'token_id' => $token_id,
                )
            )
        );
            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.conekta.io/customers");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/vnd.conekta-v2.0.0+json',
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic '.base64_encode($key.":")
        ));
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);

        //print($response); 
        //dd($response);
        /* $conekta = json_decode($response);

        if (property_exists($conekta, 'id')) {
            return $conekta->id;
        }else{
            return 0;
        } */

        return $response;

    }

    public function postOrderConekta(Request $request)
    {
        $user = User::whereNull('flag_eliminado')
            ->find($request->input('user_id'));
        if (!$user)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado'], 404);
        }
       
        $paquete=Paquete::whereNull('flag_eliminado')->find($request->input('paquete_id'));
        if (!$paquete)
        {
            return response()->json(['error'=>'Paquete no encontrado.'], 500);
        }
        if ($paquete->flag_eliminado != null)
        {
            return response()->json(['error'=>'Paquete no encontrado.'], 500);
        }
        if ($paquete->status != 1)
        {
            return response()->json(['error'=>'Paquete no disponible.'], 500);
        }

        //para evitar fraude en los montos
        $subtotal = $paquete->precio;

        //4% del total
        $comision = ($subtotal * 4)/100;

        $total = $subtotal + $comision; 

        $amount = 100;
        $unit_price = (($subtotal+$comision) - 1)*100;
        //$unit_price = $subtotal*100 - 100;
        $quantity = "1";

        $name = $request->input('name');
        if ($request->input('name') == '' || $request->input('name') == null) {
            $name = 'User ContaFacil';
        }

        // Si no tiene conekta_customer_id
        $conekta_customer_id = null;
        $conekta_obj = $this->postCustomerConekta(
            $name,
            $request->input('email'),
            //$request->input('phone'),
            '5541065769',
            $request->input('reference'),
            $request->input('random_key'),
            $request->input('token_id'),
            $this->conekta_key_privada
        );

        $conekta_obj = json_decode($conekta_obj);

        file_put_contents('log_compras.txt', print_r($conekta_obj, true), FILE_APPEND);

        if($conekta_obj->object == 'error'){
            return response()->json([
                'error'=>$conekta_obj->details[0]->message,
                'conekta'=>$conekta_obj
            ], 500);
        }

        if (property_exists($conekta_obj, 'id')) {

            $conekta_customer_id = $conekta_obj->id;

        }else{
            return response()->json([
                'error'=>'Error al conectar con Conekta.',
                'conekta'=>$conekta_obj
            ], 500);
        }
       
        //Armando la peticion cURL
        $fields = array(
            "line_items" => array(
                array(
                'name' => $request->input('name_order'), 
                'unit_price' => $unit_price,
                'quantity' => $quantity,
                )
            ),
            "shipping_lines" => array(
                array(
                'amount' => $amount, 
                'carrier' => $request->input('carrier'),
                )
            ),
            "currency" => 'MXN',
            "customer_info" => array(
                'customer_id' => $conekta_customer_id,
            ),
            "shipping_contact" => array(
                'address' => array(
                    'street1' => $request->input('street1'), 
                    'postal_code' => $request->input('postal_code'),
                    'country' => 'MX',
                )
            ),
            "metadata" => array(
                'reference' => $request->input('reference'), 
                'more_info' => $request->input('more_info'),
            ),
            "charges" => array(
                array(
                    'payment_method' => array(
                    'type' => 'default', 
                    )
                )
            ),
        );
            
        $fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        file_put_contents('log_compras.txt', print_r($fields, true), FILE_APPEND);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.conekta.io/orders");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/vnd.conekta-v2.0.0+json',
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic '.base64_encode($this->conekta_key_privada.":")
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {

            $res = [
                'error'=>'Error al conectar con Conekta.',
                'conekta'=>$err
            ];

            file_put_contents('log_compras.txt', print_r($res, true), FILE_APPEND);

            //echo "cURL Error #:" . $err;
            return response()->json([
                'error'=>'Error al conectar con Conekta.',
                'conekta'=>$err
            ], 500);
        } else {

            //print($response); 
            //dd($response);
            $conekta = json_decode($response);

            file_put_contents('log_compras.txt', print_r($conekta, true), FILE_APPEND);

            if($conekta->object == 'error'){
                return response()->json([
                    'error'=>$conekta->details[0]->message,
                    'conekta'=>$conekta
                ], 500);
            }

            if (property_exists($conekta, 'id')) {

                $conekta_id = $conekta->id;

                if($conekta->object == 'order'){

                    if($conekta->payment_status == 'paid'){

                        $newObj=Compra::create([
                            'user_id'=> $request->input('user_id'),
                            'estado_pago'=> 'Aprobado',
                            'api_tipo_pago'=> 'TDD/TDC',
                            'conekta_id'=> $conekta_id,
                            'paquete_id'=> $request->input('paquete_id'),
                            'subtotal'=> $subtotal,
                            'comision'=> $comision,
                            'total'=> $total
                        ]);

                        //si es paquete de timbres, aumentar el contador
                        if($paquete->tipo == 1){
                            $count_timbres = $user->count_timbres + $paquete->cantidad;
                            DB::table('users')
                            ->where('id', $user->id)
                            ->update([
                                'count_timbres' => $count_timbres,
                            ]);
                        }

                        try {
                            $this->emailCompra($newObj->id); 
                        } catch (Exception $e) {
                            
                        }

                        try {
                            $this->emailCompraAdmin($newObj->id); 
                        } catch (Exception $e) {
                            
                        }

                        return response()->json([
                            'message'=>'Compra registrada con éxito.',
                            'conekta'=>$conekta,
                            'registro'=>$newObj
                        ], 200);

                    }else{
                        return response()->json([
                            'error'=>'Error al procesar la compra.',
                            'conekta'=>$conekta
                        ], 500);
                    }

                }else if($conekta->object == 'error'){
                    return response()->json([
                        'error'=>$conekta->details[0]->message,
                        'conekta'=>$conekta
                    ], 500);
                }else{
                    return response()->json([
                        'error'=>'Error al procesar la compra.',
                        'conekta'=>$conekta
                    ], 500);
                }

            }else{

                return response()->json([
                    'error'=>'Error al conectar con Conekta.',
                    'conekta'=>$conekta
                ], 500);

            }

        }


    }

    public function postOrderPaypal(Request $request)
    {
        $user = User::whereNull('flag_eliminado')
            ->find($request->input('user_id'));
        if (!$user)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado'], 404);
        }
       
        $paquete=Paquete::whereNull('flag_eliminado')->find($request->input('paquete_id'));
        if (!$paquete)
        {
            return response()->json(['error'=>'Paquete no encontrado.'], 500);
        }
        if ($paquete->flag_eliminado != null)
        {
            return response()->json(['error'=>'Paquete no encontrado.'], 500);
        }
        if ($paquete->status != 1)
        {
            return response()->json(['error'=>'Paquete no disponible.'], 500);
        }

        $newObj=Compra::create([
            'user_id'=> $request->input('user_id'),
            'estado_pago'=> 'Aprobado',
            'api_tipo_pago'=> 'PayPal',
            'paypal_id'=> $request->input('paypal_id'),
            'paquete_id'=> $request->input('paquete_id'),
            'subtotal'=> $request->input('subtotal'),
            'comision'=> $request->input('comision'),
            'total'=> $request->input('total')
        ]);

        //si es paquete de timbres, aumentar el contador
        if($paquete->tipo == 1){
            $count_timbres = $user->count_timbres + $paquete->cantidad;
            DB::table('users')
            ->where('id', $user->id)
            ->update([
                'count_timbres' => $count_timbres,
            ]);
        }

        try {
            $this->emailCompra($newObj->id); 
        } catch (Exception $e) {
            
        }

        try {
            $this->emailCompraAdmin($newObj->id); 
        } catch (Exception $e) {
            
        }

        return response()->json([
            'message'=>'Compra registrada con éxito.',
            'registro'=>$newObj
        ], 200);


    }

    public function indexFilter(Request $request, $user_id)
    {

        $obj = User::whereNull('flag_eliminado')
            ->find($user_id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado'], 404);
        }

        $anio = $request->input('anio');
        $mes = $request->input('mes');
        //$dia = $request->input('dia');

        if($mes >= 1 && $mes <= 9){
            $mes = '0'.$mes;
        }

        // if($dia >= 1 && $dia <= 9){
        //     $dia = '0'.$dia;
        // }

        //$fecha = $anio.'-'.$mes.'-'.$dia;
        $fecha = $anio.'-'.$mes.'-';

        $coleccion = Compra::where('user_id',$obj->id)
            ->where('created_at', 'like', '%'.$fecha.'%')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'coleccion'=>$coleccion
        ], 200);
        
    }

    public function indexFilterAdmin(Request $request)
    {

        $anio = $request->input('anio');
        $mes = $request->input('mes');
        //$dia = $request->input('dia');

        if($mes >= 1 && $mes <= 9){
            $mes = '0'.$mes;
        }

        // if($dia >= 1 && $dia <= 9){
        //     $dia = '0'.$dia;
        // }

        //$fecha = $anio.'-'.$mes.'-'.$dia;
        $fecha = $anio.'-'.$mes.'-';

        $coleccion = Compra::select('id','user_id','api_tipo_pago','paquete_id',
            'subtotal','comision','total','created_at')
            ->with(['user' => function ($query){
                $query->select('id','email','nombre');
            }])
            ->with(['paquete' => function ($query){
                $query->select('id','nombre','tipo');
            }])
            ->where('created_at', 'like', '%'.$fecha.'%')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'coleccion'=>$coleccion
        ], 200);
        
    }

    public function show($id)
    {
        $registro = Compra::
            with(['user' => function ($query){
                $query->select('id','email','nombre');
            }])
            ->with('paquete')
            ->find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la Compra con id '.$id], 404);
        }

        return response()->json(['registro'=>$registro], 200);
    }

    public function emailCompra($compra_id)
    {
        $registro = Compra::
            with(['user' => function ($query){
                $query->select('id','email','nombre');
            }])
            ->with('paquete')
            ->find($compra_id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la Compra con id '.$compra_id], 404);
        }

        $details = [

            'logo' => 'https://apicontafacil.internow.com.mx/images_uploads/logos/logo_base.png',

            'color_a' => '#4285cb',

            'color_b' => '#ffffff',

            'color_c' => '#ffffff',

            'email' => null,

            'user' => $registro->user->nombre,

            'paquete' => $registro->paquete->nombre,

            'descripcion' => $registro->paquete->nombre,

            'api_tipo_pago' => $registro->api_tipo_pago,

            'total' => $registro->total

        ];

        //return response()->json(['registro'=>$registro], 200);

        //return view('emails-compras.new-compra', $details);

        //$email = 'contacto@aymcorporativo.com';
        $email = $registro->user->email;

        \Mail::to($email)->send(new \App\Mail\NewCompraEmail($details));

        return 1;
    }

    public function emailCompraAdmin($compra_id)
    {
        $registro = Compra::
            with(['user' => function ($query){
                $query->select('id','email','nombre');
            }])
            ->with('paquete')
            ->find($compra_id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe la Compra con id '.$compra_id], 404);
        }

        $details = [

            'logo' => 'https://apicontafacil.internow.com.mx/images_uploads/logos/logo_base.png',

            'color_a' => '#4285cb',

            'color_b' => '#ffffff',

            'color_c' => '#ffffff',

            'email' => $registro->user->email,

            'user' => null,

            'paquete' => $registro->paquete->nombre,

            'descripcion' => $registro->paquete->nombre,

            'api_tipo_pago' => $registro->api_tipo_pago,

            'total' => $registro->total

        ];

        //return response()->json(['registro'=>$registro], 200);

        //return view('emails-compras.new-compra', $details);

        $email = 'contacto@aymcorporativo.com';
        //$email = $registro->user->email;

        \Mail::to($email)->send(new \App\Mail\NewCompraEmail($details));

        return 1;
    }

}
