<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Crypt;

use Illuminate\Support\Facades\Auth;

use Exception;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

//use Hash;
use DB;
//use Validator;

use Carbon\Carbon;

use Mail;
use Session;
use Redirect;
use Swift_SmtpTransport;
use Swift_Mailer;

use App\Models\User;
use App\Models\CfdiEmpresa;
use App\Models\CfdiProducto;

date_default_timezone_set('America/Mexico_City');

class UsuarioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $coleccion = User::all();

        return response()->json(['usuarios'=>$coleccion], 200);
    }

    public function indexRol($rol)
    {

        $coleccion = User::
            whereNull('flag_eliminado')
            ->where('rol', $rol)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['usuarios'=>$coleccion], 200);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeSuperAdmin(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'email'=>'required|string',
            'password'=>'required|string',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $aux2 = User::where('email', $request->input('email'))->get();
        if(count($aux2)!=0){
            return response()->json(['error'=>'Ya existe un usuario con esas credenciales.'], 409);    
        }

        /*Primero creo una instancia en la tabla usuarios*/
        $usuario = new User;
        $usuario->rol = 1; //1=SuperAdmin
        $usuario->status = 1;
        $usuario->email = $request->input('email');
        $usuario->password = Hash::make($request->input('password'));
        
        if($usuario->save()){

           return response()->json(['message'=>'Usuario creado con éxito.',
             'usuario'=>$usuario], 200);
        }else{
            return response()->json(['error'=>'Error al crear el usuario.'], 500);
        }
    }

    public function storeCliente(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'email'=>'required|string',
            'password'=>'required|string',
            'nombre'=>'required|string',
            //'telefono'=>'required|numeric|digits:10',
            //'rol'=>'required|numeric|digits:1',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $aux2 = User::whereNull('flag_eliminado')
            ->where('email', $request->input('email'))
            ->get();
        if(count($aux2)!=0){
            return response()->json(['error'=>'Ya existe un usuario con esas credenciales.'], 409);    
        }

        /*Primero creo una instancia en la tabla usuarios*/
        $usuario = new User;
        $usuario->rol = 2; //Cliente 
        $usuario->status = 0;
        $usuario->email = $request->input('email');
        $usuario->password = Hash::make($request->input('password'));
        $usuario->nombre = $request->input('nombre');
        $usuario->color_a = '#4285cb';
        $usuario->color_b = '#4285cb';
        $usuario->color_c = '#ffffff';
        $usuario->header = 'https://apicontafacil.internow.com.mx/images_uploads/header_footer/header_base.png';
        $usuario->footer = 'https://apicontafacil.internow.com.mx/images_uploads/header_footer/footer_base.png';
        $usuario->logo = 'https://apicontafacil.internow.com.mx/images_uploads/logos/logo_base.png';
        $usuario->logo_allow_origin = 'https://apicontafacil.internow.com.mx/api/usuarios/logo/allow_origin/logo_base.png';
        $usuario->count_timbres = 10;

        if($usuario->save()){

            // $claveAdicional = config('app.lada_d');
            // $cadenaEncriptada = Crypt::encrypt($request->input('pass'), $claveAdicional);

            $count_empresas = CfdiEmpresa::count();

            // Aseguramos que el número tenga un máximo de 8 dígitos
            $folio_venta = str_pad($count_empresas+1, 8, '0', STR_PAD_LEFT);

            //Crear la empresa emisora para las facturas
            $nuevaEmpresa=CfdiEmpresa::create([
                'user_id'=>$usuario->id,
                'tipo_persona'=>null,
                'Rfc'=>null,
                'RazonSocial'=>null,
                'RegimenFiscal'=>null,
                'FacAtrAdquirente'=>null,
                'CP'=>null,
                'cer'=>null,
                'key'=>null,
                'pass'=>null,
                'flag_descuento'=>0,
                'flag_objetoImp'=>1,
                'flag_retencion'=>0,
                'flag_producto'=>0,
                'folio_venta'=>$folio_venta,

            ]);

            //Crear el producto asociado a la empresa
            $nuevoProducto=CfdiProducto::create([
                'empresa_id'=>$nuevaEmpresa->id,
                'ClaveProdServ'=>null,
                'NoIdentificacion'=>null,
                'Cantidad'=>null,
                'ClaveUnidad'=>null,
                'Unidad'=>null,
                'Descripcion'=>null,
                'ValorUnitario'=>null,
                'Importe'=>null,
                'Descuento'=>null,
                'ObjetoImp'=>null,
                'ObjetoImpRet'=>null,
                
            ]);

            try {
                $this->emailAdminNewUser($usuario->id); 
            } catch (Exception $e) {
                
            }

            try {
                $this->emailUserBienvenida($usuario->id); 
            } catch (Exception $e) {
                
            }

           return response()->json(['message'=>'Usuario creado con éxito.',
             'usuario'=>$usuario], 200);
        }else{
            return response()->json(['error'=>'Error al crear el usuario.'], 500);
        }
    }

    public function show($id)
    {
        // Comprobamos si lo que nos están pasando existe o no.
        $registro = User::whereNull('flag_eliminado')->find($id);

        $registro->logo_base64 = $this->obtenerLogoBase64($registro->logo);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el Usuario con id '.$id], 404);
        }

        return response()->json(['registro'=>$registro], 200);
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function showAlgoritmoFactura($user_id)
    {
        $usuario = User::select('id','tipo_algoritmo_factura')->find($user_id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el usuario con id '.$user_id], 404);
        }

        return response()->json(['usuario'=>$usuario], 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $usuario = User::find($id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el usuario con id '.$id], 404);
        }

        //SuperAdmin
        if($usuario->rol == 1){
            return response()->json(['error'=>'Permisos inválidos.'], 401);
        }

        // Listado de campos recibidos teóricamente.
        $email=$request->input('email'); 
        $password=$request->input('password'); 
        //$status=$request->input('status');
        $nombre=$request->input('nombre'); 
        $flag_aprobado=$request->input('flag_aprobado');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos de usuario.
        if ($email != null && $email!='')
        {
            $aux = User::whereNull('flag_eliminado')
                ->where('email', $request->input('email'))
                ->where('id', '<>', $usuario->id)->get();

            if(count($aux)!=0){
               // Devolvemos un código 409 Conflict. 
                return response()->json(['error'=>'Ya existe otro usuario con ese email.'], 409);
            }

            $usuario->email = $email;
            $bandera=true;
        }

        if ($password != null && $password!='')
        {
            $usuario->password = Hash::make($request->input('password'));
            $bandera=true;
        }

        if ($nombre != null && $nombre!='')
        {
            $usuario->nombre = $nombre;
            $bandera=true;
        }

        if (($flag_aprobado != null && $flag_aprobado!='') || $flag_aprobado===0)
        {
            $usuario->flag_aprobado = $flag_aprobado;
            $bandera=true;
        }

        if ($bandera)
        {

            // Almacenamos en la base de datos el registro.
            if ($usuario->save()) {
                return response()->json(['message'=>'Usuario editado con éxito.',
                    'usuario'=>$usuario], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el usuario.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al usuario.'],409);
        }
    }

    public function updatePassword(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $usuario = User::find($id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el usuario con id '.$id], 404);
        }


        // Listado de campos recibidos teóricamente.
        $password=$request->input('password'); 

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos de usuario.

        if ($password != null && $password!='')
        {
            $usuario->password = Hash::make($request->input('password'));
            $bandera=true;
        }


        if ($bandera)
        {

            // Almacenamos en la base de datos el registro.
            if ($usuario->save()) {
                return response()->json(['message'=>'Usuario editado con éxito.',
                    'usuario'=>$usuario], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el usuario.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al usuario.'],409);
        }
    }

    public function updateStatus(Request $request, $user_id)
    {
        // Comprobamos si el usuario que nos están pasando existe o no.
        $usuario=User::find($user_id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado.'], 404);
        }    
        
        // Listado de campos recibidos teóricamente.
        $status=$request->input('status');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.
        if (($status != null && $status!='') || $status === 0)
        {
            $usuario->status = $status;
            $bandera=true;
        }
       
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($usuario->save()) {
                return response()->json(['message'=>'Usuario actualizado.',
                 'usuario'=>$usuario], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el usuario.'], 500);
            }           
        }
        else
        {
            // Se devuelve un array error con los error encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún al usuario.'],500);
        }
    }

    public function destroy($id)
    {
        $usuario=User::find($id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado.'], 404);
        } 

        if($usuario->rol == 1){
            return response()->json(['error'=>'Permisos inválidos.'], 401);
        }

        // Eliminamos el usuario
        //$usuario->delete();

        $usuario->flag_eliminado = 1;
        $usuario->save();

        return response()->json(['message'=>'Se ha eliminado correctamente el usuario.'], 200);
    }

    public function destroyCuenta(Request $request, $id)
    {
        
        $usuario=User::find($id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado.'], 404);
        } 

        // Construir el array de credenciales
        $credentials = [
            'email' => $usuario->email,
            'password' => $request->input('password')
        ];

        try {

            if (! $token = auth()->attempt($credentials)) {
                return response()->json(['error' => 'Password inválido.'], 401);
            }

            //$user = JWTAuth::toUser($token);
            
        } catch (JWTException $ex) {
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        // if($usuario->rol == 1){
        //     return response()->json(['error'=>'Permisos inválidos.'], 401);
        // }

        //Cliente
        if($usuario->rol != 2){
            return response()->json(['error'=>'Solo se puden eliminar cuentas de clientes.'], 401);
        }

        // Eliminamos el usuario
        // $usuario->delete();

        $usuario->flag_eliminado = 1;
        $usuario->save();

        //para que no aparezca en doctoralia
        DB::table('doctoralia')
            ->where('id', $usuario->id)
            ->update([
                'flag_eliminado' => 1,
            ]);

        return response()->json(['message'=>'Se ha eliminado correctamente la cuenta.'], 200);
    }

    public function imagenAllowOrigin($imagen)
    {

        // Formar la ruta de la imagen
        $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."logos".DIRECTORY_SEPARATOR;
        $archivo_ruta = $destinationPath.$imagen;

        if (!file_exists($archivo_ruta)) {
            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."logos".DIRECTORY_SEPARATOR;
            $archivo_ruta = $destinationPath.$imagen;
        }

        // Establecer el encabezado de acceso de origen cruzado
        header("Access-Control-Allow-Origin: *");

        // Obtener el tipo MIME de la imagen
        $mime_type = mime_content_type($archivo_ruta);

        // Establecer el encabezado de tipo MIME
        header("Content-Type: $mime_type");

        // Enviar los datos de la imagen al navegador
        readfile($archivo_ruta);
    }

    public function storeLinkLogo(Request $request)
    {
        try{

            if (!$request->hasFile('archivo')) {
                return response()->json(['error'=>'Archivo no detectado.'], 422);
            }

            set_time_limit(500);
        
            $carpeta = 'images_uploads/logos/';
            $url_base = 'https://apicontafacil.internow.com.mx/';

            $hoy = date("m.d.y.H.i.s");

            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."logos".DIRECTORY_SEPARATOR;

            $fileName = $hoy.'.png';

            $request->file('archivo')->move($destinationPath,$fileName);

            $archivo_ruta = $url_base.$carpeta.$fileName;

            // $color_a = null;
            // $color_b = null;
            // $colors = $this->extractColors($archivo_ruta);
            // for ($i=0; $i < count($colors); $i++) { 
            //     if(!$color_a && $colors[$i] != '#ffffff' && $colors[$i] != '#000000'){
            //         $color_a = $colors[$i];
            //         $color_b = $colors[$i];
            //     }
            // }

            // if(!$color_a){
            //     $color_a = '#4285cb';
            // }
            // if(!$color_b){
            //     $color_b = '#4285cb';
            // }

            return response()->json([
                'message'=>'Imagen cargada con éxito.',
                'imagen'=>$archivo_ruta,
                // 'color_a'=>$color_a,
                // 'color_b'=>$color_b,
            ], 200);

        } catch ( Exception $e ){

            //return $e->getMessage();
            //return null;
            return response()->json([
                'error'=>'Error al cargar la imagen.',
                //'e'=>$e->getMessage()
            ], 400);

        }
        
    }

    public function storeLinkHeaderFooter(Request $request)
    {
        try{

            if (!$request->hasFile('archivo')) {
                return response()->json(['error'=>'Archivo no detectado.'], 422);
            }

            set_time_limit(500);
        
            $carpeta = 'images_uploads/header_footer/';
            $url_base = 'https://apicontafacil.internow.com.mx/';

            $hoy = date("m.d.y.H.i.s");

            $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."header_footer".DIRECTORY_SEPARATOR;

            $fileName = $hoy.'.png';

            $request->file('archivo')->move($destinationPath,$fileName);

            $archivo_ruta = $url_base.$carpeta.$fileName;

            return response()->json([
                'message'=>'Imagen cargada con éxito.',
                'imagen'=>$archivo_ruta,
            ], 200);

        } catch ( Exception $e ){

            //return $e->getMessage();
            //return null;
            return response()->json([
                'error'=>'Error al cargar la imagen.',
                //'e'=>$e->getMessage()
            ], 400);

        }
        
    }

    public function extractColors($imagePath) {

        $numColors = 5;

        $colors = [];

        // Cargar la imagen
        $img = imagecreatefromstring(file_get_contents($imagePath));
        $width = imagesx($img);
        $height = imagesy($img);

        // Iterar a través de cada píxel
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                // Obtener el color del píxel en formato RGB
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // Convertir a formato hexadecimal
                $hex = sprintf("#%02x%02x%02x", $r, $g, $b);

                // Agregar el color al array si no está presente
                if (!in_array($hex, $colors)) {
                    $colors[] = $hex;
                }
            }
        }

        // Ordenar los colores por frecuencia de aparición
        $colorCount = array_count_values($colors);
        arsort($colorCount);
        $sortedColors = array_keys($colorCount);

        // Limitar el número de colores extraídos
        $sortedColors = array_slice($sortedColors, 0, $numColors);

        // Liberar memoria
        imagedestroy($img);

        return $sortedColors;
    }

    public function emailAdminNewUser($user_id)
    {

        $obj = User::find($user_id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado'], 404);
        }
        

        $details = [

            'logo' => 'https://apicontafacil.internow.com.mx/images_uploads/logos/logo_base.png',

            'color_a' => '#4285cb',

            'color_b' => '#ffffff',

            'color_c' => '#ffffff',

            'nombre' => $obj->nombre,

            'email' => $obj->email,

        ];

        $email = 'contacto@aymcorporativo.com';

        \Mail::to($email)->send(new \App\Mail\AdminNewUserEmail($details));

        return 1;

    }

    public function emailUserBienvenida($user_id)
    {

        $obj = User::find($user_id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado'], 404);
        }
        

        $details = [

            'logo' => 'https://apicontafacil.internow.com.mx/images_uploads/logos/logo_base.png',

            'color_a' => '#4285cb',

            'color_b' => '#ffffff',

            'color_c' => '#ffffff',

            'nombre' => $obj->nombre,

            'email' => $obj->email,

        ];

        $email = $obj->email;

        \Mail::to($email)->send(new \App\Mail\BienvenidaUserEmail($details));

        return 1;

    }

    public function getCountTimbres($user_id)
    {
        $registro = User::find($user_id);

        if (!$registro)
        {
            return response()->json(['count_timbres'=>0], 200);
        }

        return response()->json(['count_timbres'=>$registro->count_timbres], 200);
    }

    public function updatePersonalizar(Request $request, $user_id)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[

            'logo'=>'required|string',
            'header'=>'required|string',
            'footer'=>'required|string',
            'color_a'=>'required|string',
            'color_b'=>'required|string',
            'color_c'=>'required|string',

        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        // Comprobamos si lo que nos están pasando existe o no.
        $usuario = User::whereNull('flag_eliminado')->find($user_id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el usuario con id '.$user_id], 404);
        }        

        // Listado de campos recibidos teóricamente. 
        $logo=$request->input('logo');
        $header=$request->input('header');
        $footer=$request->input('footer');
        $color_a=$request->input('color_a');
        $color_b=$request->input('color_b');
        $color_c=$request->input('color_c');      

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos de usuario.

        if ($logo != null && $logo!='')
        {
            if(
                $usuario->logo != null && 
                $usuario->logo !='' && 
                $usuario->logo != $logo
            ){
                //Eliminar la imagen vieja
                $cadenas = explode('/',$usuario->logo);
                $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."logos".DIRECTORY_SEPARATOR;
                $fileName = $cadenas[count($cadenas)-1];
                if($fileName != 'logo_base.png'){
                    $archivo_ruta = $destinationPath.$fileName;
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar la imagen
                    }    
                }
                
            }

            $cadenas = explode('/',$logo);
            $fileName = $cadenas[count($cadenas)-1];
            $logo_allow_origin = 'https://apicontafacil.internow.com.mx/api/usuarios/logo/allow_origin/'.$fileName;

            $usuario->logo = $request->input('logo');
            $usuario->logo_allow_origin = $logo_allow_origin;
            $bandera=true;
        }

        if ($header != null && $header != '')
        {
            if(
                $usuario->header != null && 
                $usuario->header !='' && 
                $usuario->header != $header
            ){
                //Eliminar la imagen vieja
                $cadenas = explode('/',$usuario->header);
                $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."header_footer".DIRECTORY_SEPARATOR;
                $fileName = $cadenas[count($cadenas)-1];
                $archivo_ruta = $destinationPath.$fileName;
                if($fileName != 'header_base.png'){
                    $archivo_ruta = $destinationPath.$fileName;
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar la imagen
                    }    
                }
            }

            $usuario->header = $request->input('header');
            $bandera=true;
        }

        if ($footer != null && $footer != '')
        {
            if(
                $usuario->footer != null && 
                $usuario->footer !='' && 
                $usuario->footer != $footer
            ){
                //Eliminar la imagen vieja
                $cadenas = explode('/',$usuario->footer);
                $destinationPath = public_path().DIRECTORY_SEPARATOR."images_uploads".DIRECTORY_SEPARATOR."header_footer".DIRECTORY_SEPARATOR;
                $fileName = $cadenas[count($cadenas)-1];
                $archivo_ruta = $destinationPath.$fileName;
                if($fileName != 'footer_base.png'){
                    $archivo_ruta = $destinationPath.$fileName;
                    if (file_exists($archivo_ruta)) {
                        unlink($archivo_ruta); // Eliminar la imagen
                    }    
                }
            }

            $usuario->footer = $request->input('footer');
            $bandera=true;
        }

        if ($color_a != null && $color_a!='')
        {
            $usuario->color_a = $color_a;
            $bandera=true;
        }

        if ($color_b != null && $color_b!='')
        {
            $usuario->color_b = $color_b;
            $bandera=true;
        }

        if ($color_c != null && $color_c!='')
        {
            $usuario->color_c = $color_c;
            $bandera=true;
        }

        if ($bandera)
        {

            // Almacenamos en la base de datos el registro.
            if ($usuario->save()) {
                return response()->json([
                    'message'=>'Usuario configurado con éxito.',
                    'usuario'=>$usuario
                ], 200);
            }else{
                return response()->json(['error'=>'Error al configurado el usuario.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al usuario.'],409);
        }
    }

    public function obtenerImagenBase64($nombreImagen)
    {
        // Definir la URL base de las imágenes
        $urlBase = 'https://apicontafacil.internow.com.mx/images_uploads/logos/';
        
        // Construir la URL completa de la imagen
        $urlImagen = $urlBase . $nombreImagen;
    
        // Verificar si la imagen existe en la URL
        if (@getimagesize($urlImagen)) {
            // Obtener el contenido de la imagen
            $contenidoImagen = file_get_contents($urlImagen);
    
            // Obtener el tipo MIME de la imagen desde los headers
            $headers = get_headers($urlImagen, 1);
            $mimeType = $headers["Content-Type"];
    
            // Convertir el contenido de la imagen a base64
            $base64 = base64_encode($contenidoImagen);
    
            // Retornar la imagen en formato base64, incluyendo el tipo MIME
            return 'data:' . $mimeType . ';base64,' . $base64;
        } else {
            // Si la imagen no existe, retornar null o manejar el error
            return null;
        }
    }

    public function obtenerLogoBase64($urlImagen)
    {
    
        // Verificar si la imagen existe en la URL
        if (@getimagesize($urlImagen)) {
            // Obtener el contenido de la imagen
            $contenidoImagen = file_get_contents($urlImagen);
    
            // Obtener el tipo MIME de la imagen desde los headers
            $headers = get_headers($urlImagen, 1);
            $mimeType = $headers["Content-Type"];
    
            // Convertir el contenido de la imagen a base64
            $base64 = base64_encode($contenidoImagen);
    
            // Retornar la imagen en formato base64, incluyendo el tipo MIME
            return 'data:' . $mimeType . ';base64,' . $base64;
        } else {
            // Si la imagen no existe, retornar null o manejar el error
            return null;
        }
    }

    public function updateTelefono(Request $request, $user_id)
    {
        // Comprobamos si el usuario que nos están pasando existe o no.
        $usuario=User::find($user_id);

        if (!$usuario)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado.'], 404);
        }    
        
        // Listado de campos recibidos teóricamente.
        $telefono=$request->input('telefono');

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos.
        if ($telefono != null && $telefono!='')
        {
            $aux = User::whereNull('flag_eliminado')
                ->where('telefono', $telefono)
                ->get();
            if(count($aux)!=0){
                return response()->json(['error'=>'Ya existe otro registro con ese número de teléfono.'], 409);    
            }

            $usuario->telefono = $telefono;
            $bandera=true;
        }
       
        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($usuario->save()) {
                return response()->json(['message'=>'Usuario actualizado.',
                 'usuario'=>$usuario], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el usuario.'], 500);
            }           
        }
        else
        {
            // Se devuelve un array error con los error encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún al usuario.'],500);
        }
    }

    public function updateImgPerfil(Request $request, $user_id)
    {
        try{

            // Comprobamos si el usuario que nos están pasando existe o no.
            $usuario=User::find($user_id);

            if (!$usuario)
            {
                // Devolvemos error codigo http 404
                return response()->json(['error'=>'Usuario no encontrado.'], 404);
            }  

            if (!$request->hasFile('archivo')) {
                return response()->json(['error'=>'Archivo no detectado.'], 422);
            }

            // Obtiene el archivo de la solicitud
            $archivo = $request->file('archivo');
        
            // Genera un nombre único para el archivo utilizando el timestamp y el nombre original del archivo
            $fileName = time() . '_' . $archivo->getClientOriginalName();

            $destinationPath = public_path().'/images_uploads/perfil/';
            //$destinationPath = public_path('calculadoras');
            $archivo->move($destinationPath,$fileName);

            // Obtiene la URL del archivo guardado
            $url = asset('images_uploads/perfil/' . $fileName);

            $usuario->imagen = $url;
            $usuario->save();

            return response()->json([
                'message'=>'Archivo cargado y configurado con éxito.',
                'url'=>$url,
                'fileName'=>$fileName,
            ], 200);

        } catch ( Exception $e ){

            //return $e->getMessage();
            //return null;
            return response()->json([
                'error'=>'Error al cargar la imagen.',
                //'e'=>$e->getMessage()
            ], 400);

        }
        
    }


  
}
