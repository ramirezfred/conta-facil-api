<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\View;
//use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

use Exception;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;


use DB;
use DateTime;
use DateInterval;

use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;

use Illuminate\Support\Facades\Crypt;

use CURLFILE;

use Barryvdh\DomPDF\Facade\Pdf;

use Smalot\PdfParser\Parser;

use Mail;
use Session;
use Redirect;
use Swift_SmtpTransport;
use Swift_Mailer;

use App\Models\User;
use App\Models\Cfdi40UsoCfdi;
use App\Models\BotMessage;

class PruebasController extends Controller
{
    
    public function testImage()
    {

        //$image = Image::make($image->getRealPath());
        $image = Image::make("https://apicontafacil.internow.com.mx/images_uploads/header_footer/05.02.24.12.55.14.png");

        $image->resize(300, 300, function ($constraint) {
            $constraint->aspectRatio();
        });

        $palette = $image->palette();

        $dominantColors = $palette->getDominantColors(5); // Obtener 5 colores dominantes

        foreach ($dominantColors as $color) {
            $hex = $color['hex'];
            $rgb = $color['rgb'];
            $count = $color['count'];

            // Procesar información de color individual
            echo "Color: $hex ($rgb) - Cantidad: $count\n";
        }

        // Regresar una respuesta exitosa
        //return response('OK', 200);
        
    }

    public function extractColors() {

        $imagePath = 'https://apicontafacil.internow.com.mx/images_uploads/header_footer/05.02.24.12.55.14.png';
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

    public function fecha(){
        //$fecha = date('Y-m-d\TH:i:s', time() - 120);

        $fechaActual = date('Y-m-d\TH:i:s'); // Obtener la fecha y hora actual en formato ISO 8601

        // Restar dos horas a la fecha actual
        $dosHorasAtras = strtotime($fechaActual) - 7200; // Restar 7200 segundos (2 horas)

        // Formatear la fecha y hora dos horas atrás en formato ISO 8601
        $fechaDosHorasAtras = date('Y-m-d\TH:i:s', $dosHorasAtras);

        // return $fechaDosHorasAtras;

        $fechaActual = now()->toDateString();

        $mensajes = BotMessage::select('id', 'user_id', 'text', 'autor', 'created_at')
            // ->where('status', 0)
            // ->where('autor', 1)
            ->get();

        return response()->json([
            'fechaDosHorasAtras'=>$fechaDosHorasAtras,
            'fechaActual'=>$fechaActual,
            'mensajes'=>$mensajes
        ], 200);
    }

     public function upload(Request $request)
    {
        $request->validate([
            'pdf' => 'required|mimes:pdf|max:10000',
        ]);

        $pdf = $request->file('pdf');
        $path = $pdf->getPathName();

        $parser = new Parser();
        $pdf = $parser->parseFile($path);

        $text = $pdf->getText();

        // $imgs = [];
        // $imagenes = $pdf->getObjectsByType('XObject', 'Image');
        // foreach ($imagenes as $imagen) {
        //     //printf("<h1>Una imagen</h1><img src=\"data:image/jpg;base64,%s\"/>", base64_encode($imagen->getContent()));

        //     array_push($imgs,base64_encode($imagen->getContent()));
        // }

        return response()->json([
            'text'=>$text,
            //'imgs'=>$imgs,
        ], 200);


        // Aquí debes implementar la lógica para extraer los datos específicos del texto
        //$data = $this->extractDataFromText($text);

        //return response()->json($data);
    }

    private function extractDataFromText($text)
    {
        // Implementa la lógica para extraer los datos necesarios
        // Por ejemplo, usar expresiones regulares para encontrar datos fiscales específicos
        $data = [];

        // Ejemplo básico (ajusta según tus necesidades)
        if (preg_match('/Nombre: (.+)/', $text, $matches)) {
            $data['nombre'] = trim($matches[1]);
        }
        if (preg_match('/RFC: (.+)/', $text, $matches)) {
            $data['rfc'] = trim($matches[1]);
        }
        // Añade más patrones según los datos que necesites extraer

        return $data;
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

        $email = 'ramirez.fred@hotmail.com';

        \Mail::to($email)->send(new \App\Mail\AdminNewUserEmail($details));

        return 1;

    }

    public function testFecha()
    {
        $fechaActual = date('Y-m-d\TH:i:s'); // Obtener la fecha y hora actual en formato ISO 8601
        // Restar dos horas a la fecha actual
        $dosHorasAtras = strtotime($fechaActual) - 7200; // Restar 7200 segundos (2 horas)
        // Formatear la fecha y hora dos horas atrás en formato ISO 8601
        $fechaDosHorasAtras = date('Y-m-d\TH:i:s', $dosHorasAtras);

        // return $fechaDosHorasAtras;

        $fechaActual = now()->toDateString();

        return response()->json([
            'fechaDosHorasAtras'=>$fechaDosHorasAtras,
            'fechaActual'=>$fechaActual
        ], 200);

    }

    public function testCatalogosSat(Request $request)
    {
        $uso_cfdi_input = rtrim(trim($request->input('texto')), '.');
        $receptor_uso_cfdi = 
            Cfdi40UsoCfdi::whereRaw("REPLACE(texto, '.', '') = ?", [str_replace('.', '', $uso_cfdi_input)])
            ->first();

        return response()->json([
            'texto'=>$request->input('texto'),
            'texto_tratado'=>$uso_cfdi_input,
            'registro'=>$receptor_uso_cfdi
        ], 200);

    }

}
