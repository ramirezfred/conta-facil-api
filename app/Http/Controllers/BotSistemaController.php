<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use DB;
use Exception;
use Carbon\Carbon;

use App\Models\BotSistema;

use App\Http\Traits\ApiGoogleAITrait;

// Se especifica la zona horaria
date_default_timezone_set('America/Mexico_City');

class BotSistemaController extends Controller
{
    use ApiGoogleAITrait;

    private const BASE_URL = 'https://generativelanguage.googleapis.com';
    private const CHUNK_SIZE = 8388608; // 8 MiB

    private $variableGlobal; // Variable de clase

    public function __construct()
    {
        $this->variableGlobal = ''; // Inicializar la variable
    }

    public function handleRequest()
    {
        try {
            // Obtener la clave activa o rotada
            $apiKey = BotSistema::getActiveKey();

            // Fecha inicial
            $fechaInicio = Carbon::parse('2024-12-15 12:00:00');

            // Fecha final (fecha y hora actual)
            $fechaFinal = Carbon::now();

            // Calcular minutos transcurridos
            $minutosTranscurridos = $fechaInicio->diffInMinutes($fechaFinal);

            // Realiza la solicitud a la API externa usando la clave activa
            return response()->json([
                'message' => 'Solicitud procesada correctamente.',
                'api_key' => $apiKey,
                'minutosTranscurridos' => $minutosTranscurridos,
                'fechaFinal' => $fechaFinal->format('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function index()
    {
        $coleccion = BotSistema::all();

        return response()->json([
            'coleccion'=>$coleccion
        ], 200);
        
    }

    public function store(Request $request)
    {
        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            'key'=>'required|string',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json([
                'error'=>'Error de validación',
                'detalle'=>$validator->errors(),
            ],422);
        }

        $aux = BotSistema::where('key', $request->input('key'))->get();
        if(count($aux)!=0){
            return response()->json(['error'=>'Ya existe un registro con esa key.'], 409);    
        }

        //Eliminar archivos previos de API Files
        $resp = $this->_getFilesGoogleAI($request->input('key'));
        if ($resp['status'] == 200) {
            
            $files = $resp['files'];

            for ($i=0; $i < count($files); $i++) { 
                
                $respB = $this->_deleteFileGoogleAI($request->input('key'), $files[$i]->name);

            }

        }else{
           return response()->json([
                'error'=>$resp['error'],
                'google_ai'=>$resp['google_ai']
            ], $resp['status']); 
        }
        
        try {
            // Configuración
            $apiKey = $request->input('key');  // Reemplaza con tu API key
            $pdfPath = public_path('archivos_uploads/contexto_ai/contexto.pdf');  // Ruta completa al archivo PDF
            $displayName = 'AudiBotAM_contexto_' . uniqid() . '.pdf';  // Nombre para mostrar en la API
        
            $resul = $this->upload($apiKey, $pdfPath, $displayName);

            if($this->variableGlobal != "¡Subida completada con éxito!"){
                return response()->json(['error'=>$this->variableGlobal], 409);
            }

        } catch (Exception $e) {
            // echo "Error en la inicialización: " . $e->getMessage() . "\n";
            return response()->json(['error'=>"Error en la inicialización: " . $e->getMessage()], 409); 
        }

        $file_create_at = "";
        $file_uri = "";
        $file_state = "";

        //Tomar datos del primer achivo de API files
        $resp = $this->_getFilesGoogleAI($request->input('key'));
        if ($resp['status'] == 200) {
            
            $files = $resp['files'];

            if(count($files) == 0){
                return response()->json(['error'=>'Archivo de contexto no detectado en API Files.'], 409);
            }

            if($files[0]->state != "ACTIVE"){
                return response()->json(['error'=>'Archivo de contexto no procesado en API Files.'], 409);
            }

            $file_create_at = Carbon::now()->format('Y-m-d H:i:s');
            $file_uri = $files[0]->uri;
            $file_state = $files[0]->state;

        }else{
           return response()->json([
                'error'=>$resp['error'],
                'google_ai'=>$resp['google_ai']
            ], $resp['status']); 
        }

        // Obtiene la URL del archivo
        $url = asset('archivos_uploads/contexto_ai/contexto.pdf');
        
        if($newObj=BotSistema::create([
            'key'=> $request->input('key'),
            'pdf_url'=> $url,
            'file_create_at'=> $file_create_at,
            'file_uri'=> $file_uri,
            'file_state'=> $file_state,
        ])){

            return response()->json([
                'message'=>'Registro creado con éxito.',
                'registro'=>$newObj
            ], 200);

        }else{
            return response()->json(['error'=>'Error al crear el registro.'], 500);
        }
    }

    private function validateFile($pdfPath): void {
        if (!file_exists($pdfPath)) {
            $this->variableGlobal = "El archivo PDF no existe en la ruta especificada";
            throw new Exception("El archivo PDF no existe en la ruta especificada");
        }
        
        if (mime_content_type($pdfPath) !== 'application/pdf') {
            $this->variableGlobal = "El archivo no es un PDF válido";
            throw new Exception("El archivo no es un PDF válido");
        }
    }

    private function initiateUpload($apiKey, $pdfPath, $displayName): string {
        $mimeType = mime_content_type($pdfPath);
        $fileSize = filesize($pdfPath);

        // echo "Iniciando subida del archivo PDF...\n";
        // echo "Archivo: {$pdfPath}\n";
        // echo "Tamaño: {$fileSize} bytes\n";

        $ch = curl_init();
        $headers = [
            "X-Goog-Upload-Protocol: resumable",
            "X-Goog-Upload-Command: start",
            "X-Goog-Upload-Header-Content-Length: {$fileSize}",
            "X-Goog-Upload-Header-Content-Type: {$mimeType}",
            "Content-Type: application/json"
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => self::BASE_URL . "/upload/v1beta/files?key=" . $apiKey,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['file' => ['display_name' => $displayName]])
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->variableGlobal = "Error al iniciar la subida. HTTP Code: " . $httpCode;
            throw new Exception("Error al iniciar la subida. HTTP Code: " . $httpCode);
        }

        preg_match('/x-goog-upload-url: (.+)/', $response, $matches);
        if (empty($matches[1])) {
            $this->variableGlobal = "Error al obtener la URL de subida";
            throw new Exception("Error al obtener la URL de subida");
        }

        return trim($matches[1]);
    }

    private function uploadChunk(string $uploadUrl, string $chunk, int $offset, bool $isLastChunk): void {
        $uploadCommand = $isLastChunk ? "upload, finalize" : "upload";
        $chunkSize = strlen($chunk);

        // echo "Subiendo bytes {$offset} - " . ($offset + $chunkSize) . "...\n";

        $ch = curl_init();
        $headers = [
            "Content-Length: {$chunkSize}",
            "X-Goog-Upload-Offset: {$offset}",
            "X-Goog-Upload-Command: {$uploadCommand}"
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $uploadUrl,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $chunk
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->variableGlobal = "Error en la subida del chunk. HTTP Code: " . $httpCode;
            throw new Exception("Error en la subida del chunk. HTTP Code: " . $httpCode);
        }
    }

    public function upload($apiKey, $pdfPath, $displayName) {
        try {
            // Validar el archivo
            $this->validateFile($pdfPath);

            // Iniciar la subida
            $uploadUrl = $this->initiateUpload($apiKey, $pdfPath, $displayName);
            
            // Leer y subir el archivo en chunks
            $fileHandle = fopen($pdfPath, 'rb');
            $fileSize = filesize($pdfPath);
            $numChunks = ceil($fileSize / self::CHUNK_SIZE);
            $offset = 0;

            for ($i = 0; $i < $numChunks; $i++) {
                $chunk = fread($fileHandle, self::CHUNK_SIZE);
                $isLastChunk = ($i == $numChunks - 1);
                $this->uploadChunk($uploadUrl, $chunk, $offset, $isLastChunk);
                $offset += strlen($chunk);
            }

            fclose($fileHandle);
            // echo "¡Subida completada con éxito!\n";
            $this->variableGlobal = "¡Subida completada con éxito!";
            
        } catch (Exception $e) {
            // echo "Error: " . $e->getMessage() . "\n";
            $this->variableGlobal = "Error: " . $e->getMessage();
            if (isset($fileHandle) && is_resource($fileHandle)) {
                fclose($fileHandle);
            }
        }

        return 1;
    }

    public function getFilesGoogleAI(Request $request)
    {
        $resp = $this->_getFilesGoogleAI($request->input('key'));
        if ($resp['status'] == 200) {
            return response()->json([
                'files'=>$resp['files']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'google_ai'=>$resp['google_ai']
            ], $resp['status']); 
        }
    }
    
    public function deleteFileGoogleAI(Request $request)
    {
        $resp = $this->_deleteFileGoogleAI($request->input('key'), $request->input('file_name'));
        if ($resp['status'] == 200) {
            return response()->json([
                'message'=>$resp['message']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'google_ai'=>$resp['google_ai']
            ], $resp['status']); 
        }
    }

    public function destroy($id)
    {
        $obj=BotSistema::find($id);

        if (!$obj)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Registro no encontrado.'], 404);
        } 

        // Eliminamos el obj
        $obj->delete();

        return response()->json(['message'=>'Se ha eliminado correctamente el registro.'], 200);
    }

    public function updateContext()
    {
        set_time_limit(500);  
        
        $coleccion = BotSistema::all();

        for ($i=0; $i < count($coleccion); $i++) { 
            
            //Eliminar archivos de API Files
            $resp = $this->_getFilesGoogleAI($coleccion[$i]->key);
            if ($resp['status'] == 200) {
                
                $files = $resp['files'];

                for ($j=0; $j < count($files); $j++) { 
                    
                    $respB = $this->_deleteFileGoogleAI($coleccion[$i]->key, $files[$j]->name);

                }

            }else{
                // return response()->json([
                //     'error'=>$resp['error'],
                //     'google_ai'=>$resp['google_ai']
                // ], $resp['status']); 

                $coleccion[$i]->file_state = $resp['error'];
                $coleccion[$i]->save();
            }

            $coleccion[$i]->file_create_at = null;
            $coleccion[$i]->file_uri = null;
            $coleccion[$i]->file_state = null;
            $coleccion[$i]->cache_name = null;
            $coleccion[$i]->cache_create_at = null;
            $coleccion[$i]->save();

            try {
                // Configuración
                $apiKey = $coleccion[$i]->key;  // Reemplaza con tu API key
                $pdfPath = public_path('archivos_uploads/contexto_ai/contexto.pdf');  // Ruta completa al archivo PDF
                $displayName = 'AudiBotAM_contexto_' . uniqid() . '.pdf';  // Nombre para mostrar en la API
            
                $this->variableGlobal = '';

                $resul = $this->upload($apiKey, $pdfPath, $displayName);
    
                if($this->variableGlobal != "¡Subida completada con éxito!"){
                    // return response()->json(['error'=>$this->variableGlobal], 409);

                    $coleccion[$i]->file_state = $this->variableGlobal;
                    $coleccion[$i]->save();
                }
    
            } catch (Exception $e) {
                // echo "Error en la inicialización: " . $e->getMessage() . "\n";
                // return response()->json(['error'=>"Error en la inicialización: " . $e->getMessage()], 409); 

                $coleccion[$i]->file_state = "Error en la inicialización: " . $e->getMessage();
                $coleccion[$i]->save();
            }

            if($this->variableGlobal == "¡Subida completada con éxito!"){
    
                $file_create_at = "";
                $file_uri = "";
                $file_state = "";
        
                //Tomar datos del primer achivo de API files
                $resp = $this->_getFilesGoogleAI($coleccion[$i]->key);
                if ($resp['status'] == 200) {
                    
                    $files = $resp['files'];
        
                    if(count($files) == 0){
                        // return response()->json(['error'=>'Archivo de contexto no detectado en API Files.'], 409);

                        $coleccion[$i]->file_state = 'Archivo de contexto no detectado en API Files.';
                        $coleccion[$i]->save();
                    }
        
                    if($files[0]->state != "ACTIVE"){
                        // return response()->json(['error'=>'Archivo de contexto no procesado en API Files.'], 409);

                        $coleccion[$i]->file_state = 'Archivo de contexto no procesado en API Files.';
                        $coleccion[$i]->save();
                    }

                    if($files[0]->state == "ACTIVE"){

                        $file_create_at = Carbon::now()->format('Y-m-d H:i:s');
                        $file_uri = $files[0]->uri;
                        $file_state = $files[0]->state;

                        // Obtiene la URL del archivo
                        $url = asset('archivos_uploads/contexto_ai/contexto.pdf');

                        $coleccion[$i]->pdf_url = $url;
                        $coleccion[$i]->file_create_at = $file_create_at;
                        $coleccion[$i]->file_uri = $file_uri;
                        $coleccion[$i]->file_state = $file_state;
                        $coleccion[$i]->save();

                    }
        
                    
        
                }else{
                    // return response()->json([
                    //     'error'=>$resp['error'],
                    //     'google_ai'=>$resp['google_ai']
                    // ], $resp['status']); 

                    $coleccion[$i]->file_state = $resp['error'];
                    $coleccion[$i]->save();
                }

            }

        }

        return response()->json([
            'coleccion'=>$coleccion
        ], 200);
        
    }

}
