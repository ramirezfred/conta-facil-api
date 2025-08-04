<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;

use App\Http\Traits\ApiChatPdfTrait;
use App\Http\Traits\ApiGoogleAITrait;

class AiController extends Controller
{
    use ApiChatPdfTrait;
    use ApiGoogleAITrait;

    public function message(Request $request)
    {

        $admin = User::select('id','rol','pdf_id','pdf_url','flag_eliminado')
            ->whereNull('flag_eliminado')
            ->where('rol', 1)
            ->first();

        if(!$admin){
            return response()->json(['error'=>'Administrador no encontrado.'], 409);    
        }

        if($admin->pdf_id == null || $admin->pdf_id == ''){
            return response()->json(['error'=>'Contexto no configurado.'], 409);    
        }

        $mensajes = [$request->input('message')];
        $resp = $this->_messageChatPdf($mensajes, $admin->pdf_id);
        if ($resp['status'] == 200) {
            return response()->json([
                'response_ai'=>$resp['response_ai']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'chat_pdf'=>$resp['chat_pdf']
            ], $resp['status']); 
        }
    }

    public function deleteFile($pdf_id)
    {
        $resp = $this->_deleteFileChatPdf($pdf_id);
        if ($resp['status'] == 200) {
            return response()->json([
                'message'=>'PDF eliminado de ChatPdf.'
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'chat_pdf'=>$resp['chat_pdf']
            ], $resp['status']); 
        }
    }

    public function addFile(Request $request)
    {
        $admin = User::select('id','rol','pdf_id','pdf_url','flag_eliminado')
            ->whereNull('flag_eliminado')
            ->where('rol', 1)
            ->first();

        if(!$admin){
            return response()->json(['error'=>'Administrador no encontrado.'], 409);    
        }

        if (!$request->hasFile('archivo')) {
            return response()->json(['error'=>'Archivo no detectado.'], 422);
        }

        $fileName = 'pdf_' . uniqid() . '.pdf';
        
        $destinationPath = public_path().'/archivos_uploads/contexto_ai/';
        $request->file('archivo')->move($destinationPath,$fileName);

        // Obtiene la URL del archivo guardado
        $url = asset('archivos_uploads/contexto_ai/' . $fileName);

        $resp = $this->_addFileChatPdf($url);
        if ($resp['status'] == 200) {

            if($admin->pdf_id != null && $admin->pdf_id != ''){
                $borrar = $this->_deleteFileChatPdf($admin->pdf_id);
            }
            
            //actualizar el contexto
            $admin->pdf_id = $resp['pdf_id'];
            $admin->pdf_url = $url;
            $admin->save();

            return response()->json([
                'message'=>'Archivo cargado y configurado con éxito.',
                'url'=>$url,
                'fileName'=>$fileName,
                'pdf_id'=>$resp['pdf_id'],
            ], $resp['status']);

        }else{
            return response()->json([
                'error'=>$resp['error'],
                'chat_pdf'=>$resp['chat_pdf']
            ], $resp['status']); 
        }

        
    }

    public function addFileGoogleapis(Request $request)
    {
        // $apiKey = 'AIzaSyD_1NjI45cgz82yuuzGuWmW2cmLu4FHBpo';
        $apiKey = 'AIzaSyDTKpHyUvqayNsdPWyy5ItqLcN67CIlNV4';

        //https://apicontafacil.internow.com.mx/archivos_uploads/contexto_ai/pdf_67522eedc72fc.pdf
        $fileUrl = "https://apicontafacil.internow.com.mx/archivos_uploads/contexto_ai/pdf_67522eedc72fc.pdf"; // Ruta pública
        $fileBinary = file_get_contents($fileUrl); // Leer el archivo desde la URL
        $numBytes = strlen($fileBinary); // Tamaño del archivo en bytes

        // $filePath = public_path('archivos_uploads/contexto_ai/pdf_67522eedc72fc.pdf'); // Ruta absoluta al archivo
        // $fileBinary = file_get_contents($filePath); // Leer el archivo
        // $numBytes = filesize($filePath); // Tamaño del archivo en bytes

        // Configurar encabezados y enviar el archivo (similar al ejemplo anterior)
        $headers = [
            "X-Goog-Upload-Command: start, upload, finalize",
            "X-Goog-Upload-Header-Content-Length: $numBytes",
            "X-Goog-Upload-Header-Content-Type: application/pdf",
            "Content-Type: application/json"
        ];

        $data = json_encode(["file" => [
            "display_name" => "file_contexto"
            ]]);

        $ch = curl_init("https://generativelanguage.googleapis.com/upload/v1beta/files?key=" . $apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileBinary); // Enviar el contenido binario del archivo
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        echo "Respuesta: " . $response;

        
    }

    public function preguntaGoogleapis(Request $request)
    {
        // $apiKey = 'AIzaSyD_1NjI45cgz82yuuzGuWmW2cmLu4FHBpo';
        $apiKey = 'AIzaSyDTKpHyUvqayNsdPWyy5ItqLcN67CIlNV4';

        // $fileUri = 'https://generativelanguage.googleapis.com/v1beta/files/ouffgs8fp9c8';
        $fileUri = 'https://generativelanguage.googleapis.com/v1beta/files/9es9fsdn7yej';
        $mimeType = 'application/pdf';

        // Paso 2: Generar contenido
        $generateHeaders = [
            "Content-Type: application/json"
        ];

        // $generateData = json_encode([
        //     'contents' => [
        //         [
        //             'parts' => [
        //                 [
        //                     'fileData' => [
        //                         'fileUri' => $fileUri,
        //                         'mimeType' => $mimeType
        //                     ]
        //                 ],
        //                 [
        //                     'text' => 'cuantas paginas tiene el documento'
        //                 ]
        //             ]
        //         ]
        //     ]
        // ]);

        $generateData = json_encode([
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => 'cuantas paginas tiene el documento' ],
                        [ 'file_data' => [
                            'mime_type' => 'application/pdf',
                            'file_uri' => $fileUri
                        ]]
                    ]
                ]
            ]
        ]);

        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$apiKey");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $generateHeaders);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $generateData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;

        
    }

    public function messageGoogleAI(Request $request)
    {

        $mensajes = [$request->input('message')];
        $resp = $this->_messageGoogleAI($mensajes);
        if ($resp['status'] == 200) {
            return response()->json([
                'response_ai'=>$resp['response_ai']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'google_ai'=>$resp['google_ai']
            ], $resp['status']); 
        }
    }

    public function messagePDFGoogleAI(Request $request)
    {

        $file_uri = "https://generativelanguage.googleapis.com/v1beta/files/cbys9yjxsftu";

        $mensajes = [$request->input('message')];
        $resp = $this->_messagePDFGoogleAI($mensajes, $file_uri);
        if ($resp['status'] == 200) {
            return response()->json([
                'response_ai'=>$resp['response_ai']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'google_ai'=>$resp['google_ai']
            ], $resp['status']); 
        }
    }

    public function storeCacheGoogleAI(Request $request)
    {

        //$file_uri = "https://generativelanguage.googleapis.com/v1beta/files/cbys9yjxsftu";
        //$file_uri = "https://generativelanguage.googleapis.com/v1beta/files/cfbdnbeg16cy";
        $file_uri = "https://generativelanguage.googleapis.com/v1beta/files/jfndiidydnud";

        $mensajes = [$request->input('message')];
        $resp = $this->_storeCacheGoogleAI($file_uri, '2000s', 'gemini-1.5-flash-001');

        if ($resp['status'] == 200) {
            return response()->json([
                'response_ai'=>$resp['response_ai']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'google_ai'=>$resp['google_ai']
            ], $resp['status']); 
        }
    }

    public function messageWhitCacheGoogleAI(Request $request)
    {

        $mensajes = [$request->input('message')];
        $resp = $this->_messageWhitCacheGoogleAI($mensajes, 'cachedContents/j4pj2ewl234j', 'gemini-1.5-flash-001');
        if ($resp['status'] == 200) {
            return response()->json([
                'response_ai'=>$resp['response_ai']
            ], $resp['status']);
        }else{
           return response()->json([
                'error'=>$resp['error'],
                'google_ai'=>$resp['google_ai']
            ], $resp['status']); 
        }
    }
}
