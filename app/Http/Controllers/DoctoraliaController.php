<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use Exception;

use DB;

use Mail;
use Session;
use Redirect;
use Swift_SmtpTransport;
use Swift_Mailer;

use App\Models\User;
use App\Models\Doctoralia;
use App\Models\Galeria;
use App\Models\Especialidad;
use App\Models\Servicio;
use App\Models\Certificado;
use App\Models\Opinion;

class DoctoraliaController extends Controller
{
    public function haversine($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 6371; // Radio de la Tierra en kilómetros.
    
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
    
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
    
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
        $distance = $earth_radius * $c;
    
        return $distance;
    }

    public function indexFiltrado(Request $request)
    {

        $coleccion = Doctoralia::select('id','user_id','status','count_vistas','nombre','profesion',
            'cedula','telefono','direccion','lat','lng','costo_asesoria','imagen','foto','flag_eliminado')
            ->withCount(['opiniones as count_opiniones'])
            ->whereNull('flag_eliminado')
            ->with(['user' => function ($query){
                $query->select('id','email');
            }])
            ->where('status',1)
            //->orderBy('id', 'desc')
            ->get();

        for ($i=0; $i < count($coleccion); $i++) { 

            if ($coleccion[$i]) {
                $coleccion[$i]->count_opiniones = (int) $coleccion[$i]->count_opiniones;
            }

            $media = Opinion::where('doctor_id', $coleccion[$i]->id)->avg('puntaje');
            $coleccion[$i]->media_opinones = intval($media);
        }

        // $lat = 8.979778; // Tu latitud actual
        // $lng = -70.739295; // Tu longitud actual

        $lat = $request->input('lat'); // Tu latitud actual
        $lng = $request->input('lng'); // Tu longitud actual

        if($lat == '' || $lat == null){
            $lat = 0;
        }
        if($lng == '' || $lng == null){
            $lng = 0;
        }
        
        // Convertir la colección a un array
        $coleccionArray = $coleccion->toArray();

        // Ordenar el array utilizando usort y la función haversine
        usort($coleccionArray, function ($a, $b) use ($lat, $lng) {
            $distA = $this->haversine($lat, $lng, $a['lat'], $a['lng']);
            $distB = $this->haversine($lat, $lng, $b['lat'], $b['lng']);
            return $distA <=> $distB; // Orden ascendente
        });

        return response()->json([
            //'coleccion' => $coleccion,
            'coleccion' => $coleccionArray
        ], 200);
        
    }

    public function index()
    {

        $coleccion = Doctoralia::whereNull('flag_eliminado')
            ->with(['user' => function ($query){
                $query->select('id','email');
            }])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'coleccion'=>$coleccion
        ], 200);
        
    }

    public function store(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'user_id'=>'required|numeric',
            'nombre'=>'required|string',
            'profesion'=>'required|string',
            'cedula'=>'required|string',
            'telefono'=>'required|numeric|digits:10',
            'direccion'=>'required|string',
            'lat'=>'required|numeric',
            'lng'=>'required|numeric',
            'costo_asesoria'=>'required|numeric',
            'imagen'=>'required|string',
            'ccf'=>'required|string',

            'galeria'=>'required|string',
            'especialidades'=>'required|string',
            'servicios'=>'required|string',
            'certificaciones'=>'required|string',
            
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $obj = User::whereNull('flag_eliminado')
            ->find($request->input('user_id'));
        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Usuario no encontrado'], 404);
        }

        $perfil = Doctoralia::whereNull('flag_eliminado')
            ->where('user_id',$request->input('user_id'))
            ->first();

        if ($perfil)
        {
            return response()->json(['error'=>'Ya tienes un perfil de Doctoralia asociado a tu cuenta'], 404);
        }

        $aux = Doctoralia::whereNull('flag_eliminado')
            ->where('cedula', $request->input('cedula'))
            ->get();
        if(count($aux)!=0){
            return response()->json(['error'=>'Ya existe un registro con ese número de cédula profesional.'], 409);    
        }

        $aux2 = Doctoralia::whereNull('flag_eliminado')
            ->where('telefono', $request->input('telefono'))
            ->get();
        if(count($aux2)!=0){
            return response()->json(['error'=>'Ya existe un registro con ese teléfono.'], 409);    
        }

        $galeria = json_decode($request->input('galeria'));
        $especialidades = json_decode($request->input('especialidades'));
        $servicios = json_decode($request->input('servicios'));
        $certificaciones = json_decode($request->input('certificaciones'));
        
        if($newObj=Doctoralia::create([
            'user_id'=> $request->input('user_id'),
            'count_vistas'=> 0,
            'nombre'=> $request->input('nombre'),
            'profesion'=> $request->input('profesion'),
            'cedula'=> $request->input('cedula'),
            'telefono'=> $request->input('telefono'),
            'direccion'=> $request->input('direccion'),
            'lat'=> $request->input('lat'),
            'lng'=> $request->input('lng'),
            'costo_asesoria'=> $request->input('costo_asesoria'),
            'imagen'=> $request->input('imagen'),
            'ccf'=> $request->input('ccf'),
            'foto'=> $request->input('foto')
        ])){

            for ($i=0; $i < count($galeria); $i++) { 
                $newGaleria=Galeria::create([
                    'doctor_id'=> $newObj->id,
                    'imagen'=> $galeria[$i]->imagen,
                ]);
            }

            for ($i=0; $i < count($especialidades); $i++) { 
                $newEspecialidad=Especialidad::create([
                    'doctor_id'=> $newObj->id,
                    'texto'=> $especialidades[$i]->texto,
                ]);
            }

            for ($i=0; $i < count($servicios); $i++) { 
                $newServicio=Servicio::create([
                    'doctor_id'=> $newObj->id,
                    // 'servicio'=> $servicios[$i]->servicio,
                    'texto'=> $servicios[$i]->texto,
                ]);
            }

            for ($i=0; $i < count($certificaciones); $i++) { 
                $newCertificado=Certificado::create([
                    'doctor_id'=> $newObj->id,
                    'certificado'=> $certificaciones[$i]->certificado,
                    'url'=> $certificaciones[$i]->url,
                ]);
            }

            try {
                $this->emailAdminNewPerfil($newObj->id); 
            } catch (Exception $e) {
                
            }
       
            return response()->json([
            'message'=>'Registro exitoso. En breve se le contactará para una entrevista de aprobación.',
            'registro'=>$newObj
            ], 200);

        }else{
            return response()->json(['error'=>'Error al crear el registro.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $registro = Doctoralia::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el registro con id '.$id], 404);
        }


        // Listado de campos recibidos teóricamente.
        //$status=$request->input('status'); 
        $nombre=$request->input('nombre');
        $profesion=$request->input('profesion'); 
        $cedula=$request->input('cedula'); 
        $telefono=$request->input('telefono'); 
        $direccion=$request->input('direccion');
        $lat=$request->input('lat'); 
        $lng=$request->input('lng');
        $costo_asesoria=$request->input('costo_asesoria');
        $imagen=$request->input('imagen');
        $ccf=$request->input('ccf');
        $foto=$request->input('foto');

        $galeria = json_decode($request->input('galeria'));
        $especialidades = json_decode($request->input('especialidades'));
        $servicios = json_decode($request->input('servicios'));
        $certificaciones = json_decode($request->input('certificaciones'));

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos del registro.

        // if (($status != null && $status!='') || $status===0)
        // {
        //     $registro->status = $status;
        //     $bandera=true;
        // }

        if ($nombre != null && $nombre!='')
        {
            $registro->nombre = $nombre;
            $bandera=true;
        }

        if ($profesion != null && $profesion!='')
        {
            $registro->profesion = $profesion;
            $bandera=true;
        }

        if ($cedula != null && $cedula!='')
        {

            $aux = Doctoralia::whereNull('flag_eliminado')
                ->where('cedula', $request->input('cedula'))
                ->where('id', '<>', $registro->id)
                ->get();
            if(count($aux)!=0){
                return response()->json(['error'=>'Ya existe otro registro con ese número de cédula profesional.'], 409);    
            }

            $registro->cedula = $cedula;
            $bandera=true;
        }

        if ($telefono != null && $telefono!='')
        {
            $aux2 = Doctoralia::whereNull('flag_eliminado')
                ->where('telefono', $request->input('telefono'))
                ->where('id', '<>', $registro->id)
                ->get();
            if(count($aux2)!=0){
                return response()->json(['error'=>'Ya existe otro registro con ese teléfono.'], 409);    
            }

            $registro->telefono = $telefono;
            $bandera=true;
        }

        if ($direccion != null && $direccion!='')
        {
            $registro->direccion = $direccion;
            $bandera=true;
        }

        if ($lat != null && $lat!='')
        {
            $registro->lat = $lat;
            $bandera=true;
        }

        if ($lng != null && $lng!='')
        {
            $registro->lng = $lng;
            $bandera=true;
        }

        if (($costo_asesoria != null && $costo_asesoria!='') || $costo_asesoria===0)
        {
            $registro->costo_asesoria = $costo_asesoria;
            $bandera=true;
        }

        if ($imagen != null && $imagen!='')
        {
            $registro->imagen = $imagen;
            $bandera=true;
        }

        if ($ccf != null && $ccf!='')
        {
            $registro->ccf = $ccf;
            $bandera=true;
        }

        if ($foto != null && $foto!='')
        {
            $registro->foto = $foto;
            $bandera=true;
        }

        //Eliminar la galeria para crear una nueva
        DB::table('galeria')
            ->where('doctor_id', $registro->id)
            ->delete();

        for ($i=0; $i < count($galeria); $i++) { 
            $newGaleria=Galeria::create([
                'doctor_id'=> $registro->id,
                'imagen'=> $galeria[$i]->imagen,
            ]);
        }

        //Eliminar las especialidades para crear nuevas
        DB::table('especialidades')
            ->where('doctor_id', $registro->id)
            ->delete();

        for ($i=0; $i < count($especialidades); $i++) { 
            $newEspecialidad=Especialidad::create([
                'doctor_id'=> $registro->id,
                'texto'=> $especialidades[$i]->texto,
            ]);
        }

        //Eliminar los servicios para crear nuevos
        DB::table('servicios')
            ->where('doctor_id', $registro->id)
            ->delete();

        for ($i=0; $i < count($servicios); $i++) { 
            $newServicio=Servicio::create([
                'doctor_id'=> $registro->id,
                // 'servicio'=> $servicios[$i]->servicio,
                'texto'=> $servicios[$i]->texto,
            ]);
        }

        //Eliminar las certificaciones para crear nuevas
        DB::table('certificaciones')
            ->where('doctor_id', $registro->id)
            ->delete();

        for ($i=0; $i < count($certificaciones); $i++) { 
            $newCertificado=Certificado::create([
                'doctor_id'=> $registro->id,
                'certificado'=> $certificaciones[$i]->certificado,
                'url'=> $certificaciones[$i]->url,
            ]);
        }

        if ($bandera)
        {

            try {
                $this->emailAdminNewPerfil($registro->id); 
            } catch (Exception $e) {
                
            }

            // Almacenamos en la base de datos el registro.
            if ($registro->save()) {
                return response()->json(['message'=>'Registro editado con éxito.',
                    'registro'=>$registro], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el registro.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al registro.'],409);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $registro = Doctoralia::find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el registro con id '.$id], 404);
        }


        // Listado de campos recibidos teóricamente.
        $status=$request->input('status'); 

        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos del registro.

        if (($status != null && $status!='') || $status===0)
        {
            $registro->status = $status;
            $bandera=true;
        }

        if ($bandera)
        {

            // Almacenamos en la base de datos el registro.
            if ($registro->save()) {
                return response()->json(['message'=>'Registro editado con éxito.',
                    'registro'=>$registro], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el registro.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al registro.'],409);
        }
    }

    public function storeLinkLogo(Request $request)
    {
        try{

            if (!$request->hasFile('archivo')) {
                return response()->json(['error'=>'Archivo no detectado.'], 422);
            }
    
            $fileName = 'logo_' . uniqid() . '.png';
            
            $destinationPath = public_path().'/images_uploads/logos_doctoralia/';
            $request->file('archivo')->move($destinationPath,$fileName);
    
            // Obtiene la URL del archivo guardado
            $url = asset('images_uploads/logos_doctoralia/' . $fileName);
    
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

    public function storeArchivo(Request $request)
    {

        if (!$request->hasFile('archivo')) {
            return response()->json(['error'=>'Archivo no detectado.'], 422);
        }

        $fileName = 'pdf_' . uniqid() . '.pdf';
        
        $destinationPath = public_path().'/archivos_uploads/documents/';
        $request->file('archivo')->move($destinationPath,$fileName);

        // Obtiene la URL del archivo guardado
        $url = asset('archivos_uploads/documents/' . $fileName);

        return response()->json([
            'message'=>'Archivo cargado y configurado con éxito.',
            'url'=>$url,
            'fileName'=>$fileName,
         ], 200);
    }

    public function updateVistas(Request $request, $id)
    {
        // Comprobamos si lo que nos están pasando existe o no.

        $registro = Doctoralia::select('id','user_id','count_vistas')->find($id);

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'No existe el registro con id '.$id], 404);
        }


        // Creamos una bandera para controlar si se ha modificado algún dato.
        $bandera = false;

        // Actualización parcial de campos del registro.

        if (true)
        {
            $registro->count_vistas = $registro->count_vistas + 1;
            $bandera=true;
        }

        if ($bandera)
        {
            // Almacenamos en la base de datos el registro.
            if ($registro->save()) {
                return response()->json(['message'=>'Registro editado con éxito.',
                    'registro'=>$registro], 200);
            }else{
                return response()->json(['error'=>'Error al actualizar el registro.'], 500);
            }
            
        }
        else
        {
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 304 Not Modified – [No Modificada] Usado cuando el cacheo de encabezados HTTP está activo
            // Este código 304 no devuelve ningún body, así que si quisiéramos que se mostrara el mensaje usaríamos un código 200 en su lugar.
            return response()->json(['error'=>'No se ha modificado ningún dato al registro.'],409);
        }
    }

    public function show($user_id)
    {
        $registro = Doctoralia::whereNull('flag_eliminado')
            ->where('user_id',$user_id)
            ->with('galeria')
            ->with('especialidades')
            ->with('servicios')
            ->with('certificaciones')
            ->first();

        if (!$registro)
        {
            // // Devolvemos error codigo http 404
            // return response()->json(['error'=>'Reagistro no encontrado'], 404);

            return response()->json(['registro'=>null], 200);
        }

        return response()->json(['registro'=>$registro], 200);
    }

    public function storeLinkGaleria(Request $request)
    {
        try {

            if (!$request->hasFile('archivo')) {
                return response()->json(['error' => 'Archivo no detectado.'], 422);
            }

            // Validación del archivo
            // $request->validate([
            //     'archivo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Máximo 2 MB
            // ]);

            // Obtiene la extensión original del archivo
            $extension = $request->file('archivo')->getClientOriginalExtension();

            // Genera el nombre del archivo con su extensión original
            $fileName = 'img_' . uniqid() . '.' . $extension;

            $destinationPath = public_path() . '/images_uploads/galeria_doctoralia/';
            $request->file('archivo')->move($destinationPath, $fileName);

            // Obtiene la URL del archivo guardado
            $url = asset('images_uploads/galeria_doctoralia/' . $fileName);

            return response()->json([
                'message' => 'Archivo cargado y configurado con éxito.',
                'url' => $url,
                'fileName' => $fileName,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Captura errores de validación
            return response()->json([
                // 'error' => 'Error de validación',
                'error' => $e->validator->errors()->first(), // Obtiene el primer error
            ], 422);
        } catch (Exception $e) {
            // Otros errores
            return response()->json([
                'error' => 'Error al cargar la imagen.',
            ], 400);
        }
    }

    public function storeGaleria(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'doctor_id'=>'required|numeric',
            'imagen'=>'required|string',
            
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $perfil = Doctoralia::find($request->input('doctor_id'));
        if (!$perfil)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Red fiscal no encontrada.'], 404);
        }
        
        if($newObj=Galeria::create([
            'doctor_id'=> $request->input('doctor_id'),
            'imagen'=> $request->input('imagen'),
        ])){
       
           return response()->json(['message'=>'Registro creado con éxito.',
             'registro'=>$newObj], 200);

        }else{
            return response()->json(['error'=>'Error al crear el registro.'], 500);
        }
    }

    public function destroyGaleria($id)
    {
        $obj=Galeria::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Registro no encontrado.'], 404);
        } 

        // Eliminamos el obj
        $obj->delete();

        // $obj->flag_eliminado = 1;
        // $obj->save();

        return response()->json(['message'=>'Se ha eliminado correctamente el registro.'], 200);
    }

    public function storeLinkFoto(Request $request)
    {
        try{

            if (!$request->hasFile('archivo')) {
                return response()->json(['error'=>'Archivo no detectado.'], 422);
            }

            // Obtiene la extensión original del archivo
            $extension = $request->file('archivo')->getClientOriginalExtension();
    
            $fileName = 'logo_' . uniqid() . $extension;
            
            $destinationPath = public_path().'/images_uploads/fotos_doctoralia/';
            $request->file('archivo')->move($destinationPath,$fileName);
    
            // Obtiene la URL del archivo guardado
            $url = asset('images_uploads/fotos_doctoralia/' . $fileName);
    
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

    public function storeLinkCertificado(Request $request)
    {

        if (!$request->hasFile('archivo')) {
            return response()->json(['error'=>'Archivo no detectado.'], 422);
        }

        $fileName = 'pdf_' . uniqid() . '.pdf';
        
        $destinationPath = public_path().'/archivos_uploads/certificados/';
        $request->file('archivo')->move($destinationPath,$fileName);

        // Obtiene la URL del archivo guardado
        $url = asset('archivos_uploads/certificados/' . $fileName);

        return response()->json([
            'message'=>'Archivo cargado y configurado con éxito.',
            'url'=>$url,
            'fileName'=>$fileName,
         ], 200);
    }

    public function showPerfil($user_id)
    {
        $registro = Doctoralia::whereNull('flag_eliminado')
            ->where('user_id',$user_id)
            ->with(['user' => function ($query){
                $query->select('id','email');
            }])
            ->with('galeria')
            ->with('especialidades')
            ->with('servicios')
            ->with('certificaciones')
            ->with(['opiniones' => function ($query){
                $query->orderBy('id', 'desc')
                    ->take(5);
            }])
            ->withCount(['opiniones as count_opiniones'])
            ->first();

        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Registro no encontrado'], 404);
        }

        if ($registro) {
            $registro->count_opiniones = (int) $registro->count_opiniones;
        }

        $media = Opinion::where('doctor_id', $registro->id)->avg('puntaje');
        $registro->media_opinones = intval($media);

        return response()->json(['registro'=>$registro], 200);
    }

    public function storeOpinion(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'doctor_id'=>'required|numeric',
            'puntaje'=>'required|numeric',
            'nombre'=>'required|string',
            'comentario'=>'required|string',
            
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json(['error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $perfil = Doctoralia::find($request->input('doctor_id'));
        if (!$perfil)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Red fiscal no encontrada.'], 404);
        }
        
        if($newObj=Opinion::create([
            'doctor_id'=> $request->input('doctor_id'),
            'puntaje'=> $request->input('puntaje'),
            'nombre'=> $request->input('nombre'),
            'comentario'=> $request->input('comentario'),
        ])){

            $media = Opinion::where('doctor_id', $request->input('doctor_id'))->avg('puntaje');
            $media_opinones = intval($media);
       
           return response()->json([
            'message'=>'Registro creado con éxito.',
            'media_opinones'=>$media_opinones,
            'registro'=>$newObj
            ], 200);

        }else{
            return response()->json(['error'=>'Error al crear el registro.'], 500);
        }
    }

    public function getMasOpiniones($doctor_id, $opinion_id)
    {

        $registros = Opinion::
            where('doctor_id',$doctor_id)
            ->where('id','<',$opinion_id)
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();
        
        return response()->json([
            'registros'=>$registros
        ], 200);

    }

    public function emailAdminNewPerfil($doctor_id)
    {

        $obj = Doctoralia::
            with(['user' => function ($query){
                $query->select('id','email');
            }])
            ->find($doctor_id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Perfil no encontrado'], 404);
        }
        

        $details = [

            'logo' => 'https://apicontafacil.internow.com.mx/images_uploads/logos/logo_base.png',

            'color_a' => '#4285cb',

            'color_b' => '#ffffff',

            'color_c' => '#ffffff',

            'nombre' => $obj->nombre,

            'email' => $obj->user->email,

        ];

        $email = 'contacto@aymcorporativo.com';
        // $email = 'ramirez.fred@hotmail.com';

        \Mail::to($email)->send(new \App\Mail\AdminNewPerfilRedFiscalEmail($details));

        return 1;

    }
}
