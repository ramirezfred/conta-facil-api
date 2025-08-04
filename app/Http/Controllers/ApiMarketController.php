<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

use App\Models\CfdiEmpresa;

class ApiMarketController extends Controller
{
    
    public $base_url = "https://apimarket.mx/api";
    public $path = "/sat/grupo";

    //Cuenta Antonio
    // public $token = "334a65e7-9574-48bd-af34-1a347a2a1222";

    //Cuenta CorporativoAM
    public $token = "a485f542-da9e-4d22-9a01-1c1b1be133b1";

    public function obtenerDatos($Rfc)
    {

        // Eliminar espacios en blanco y guiones si los hay
        $Rfc = str_replace([' ', '-'], '', $Rfc);
        $Rfc = strtoupper($Rfc);

        $rfcValido = "/^[A-Z0-9]{12,13}$/";

        if (!preg_match($rfcValido, $Rfc)) {
            // El Rfc es inválido
            $message = 'Por favor, verifica el Rfc. En el caso de que sea una persona física, este campo debe contener una longitud de 13 posiciones, si se trata de personas morales debe contener una longitud de 12 posiciones.';
            return response()->json(['error'=>$message],409);
        }

        $query = "?rfc=".$Rfc;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url.$this->path."/obtener-datos".$query);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer '.$this->token,
            'Accept: application/json',
            // 'x-sandbox: true',

        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return response()->json([
                'error'=>'Error al conectar con ApiMarket.',
                'data'=>$err
            ], 500);

        } else {

            $data = json_decode($response);

            if (property_exists($data, 'success')) {

                if ($data->success && $data->status == 200 && property_exists($data, 'data')) {

                    return response()->json([
                        'message'=>$data->message,
                        'data'=>$data
                    ], 200);

                }else{
                    return response()->json([
                        'error'=>$data->message,
                        'data'=>$data
                    ], 409);
                }

            }else{
                return response()->json([
                    'error'=>'Error en response de ApiMarket.',
                    'data'=>$data
                ], 409);
            }

        }

    }

    public function obtenerDatosIdcif($Idcif, $Rfc)
    {

        // Eliminar espacios en blanco y guiones si los hay
        $Idcif = str_replace([' ', '-'], '', $Idcif);
        // $Rfc = strtoupper($Rfc);

        $idcifValido = "/^[0-9]{11}$/";

        if (!preg_match($idcifValido, $Idcif)) {
            // El Rfc es inválido
            $message = 'Por favor, verifica el idCIF. Este campo es numérico y debe contener una longitud de 11 posiciones.';
            return response()->json(['error'=>$message],409);
        }

        // Eliminar espacios en blanco y guiones si los hay
        $Rfc = str_replace([' ', '-'], '', $Rfc);
        $Rfc = strtoupper($Rfc);

        $rfcValido = "/^[A-Z0-9]{12,13}$/";

        if (!preg_match($rfcValido, $Rfc)) {
            // El Rfc es inválido
            $message = 'Por favor, verifica el Rfc. En el caso de que sea una persona física, este campo debe contener una longitud de 13 posiciones, si se trata de personas morales debe contener una longitud de 12 posiciones.';
            return response()->json(['error'=>$message],409);
        }

        $query = "?idcif=".$Idcif."&rfc=".$Rfc;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url.$this->path."/obtener-datos-idcif".$query);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer '.$this->token,
            'Accept: application/json',
            // 'x-sandbox: true',

        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            //echo "cURL Error #:" . $err;
            return response()->json([
                'error'=>'Error al conectar con ApiMarket.',
                'data'=>$err
            ], 500);

        } else {

            $data = json_decode($response);

            if (property_exists($data, 'success')) {

                if ($data->success && $data->status == 200 && property_exists($data, 'data')) {

                    return response()->json([
                        'message'=>$data->message,
                        'data'=>$data
                    ], 200);

                }else{
                    return response()->json([
                        'error'=>$data->message,
                        'data'=>$data
                    ], 409);
                }

            }else{
                return response()->json([
                    'error'=>'Error en response de ApiMarket.',
                    'data'=>$data
                ], 409);
            }

        }

    }

    public function mostrarVistaCosntancia()
    {
        $data = [
            'shcp_sat' => asset('images/shcp_sat.png')
        ];

        // return view('pedidos.pedidoA', $datos);

        // return view('constancia_sf.constancia_sf', $data);

        //$pdf = Pdf::loadView('cotizaciones.cotizacion', $data);
        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('constancia_sf.mia_constanica_sf', $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs_constancia_sf/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs_constancia_sf/' . $nombreArchivo);

        return $url;
    }

    public function generarConstancia($empresa_id, $Idcif)
    {

        // Comprobamos si la empresa que nos están pasando existe o no.
        $empresa=CfdiEmpresa::find($empresa_id);

        if (!$empresa)
        {
            // Devolvemos error codigo http 404
            return response()->json(['error'=>'Empresa no encontrada.'], 404);
        } 

        $Rfc = $empresa->Rfc;

        $response = $this->obtenerDatosIdcif($Idcif, $Rfc);
        $responseData  = $response->getData(); // Obtener los datos de la respuesta JSON

        file_put_contents('log_csf.txt', print_r($responseData, true), FILE_APPEND);

        $data1 = $responseData->data;

        if ($data1->success && $data1->status == 200 && property_exists($data1, 'data')) {
            // return response()->json([
            //     'message' => $data1->message,
            //     'data' => $data1
            // ], 200);

            $response = $this->obtenerDatos($Rfc);
            $responseData  = $response->getData(); // Obtener los datos de la respuesta JSON

            file_put_contents('log_csf.txt', print_r($responseData, true), FILE_APPEND);

            $data2 = $responseData->data;

            if ($data2->success && $data2->status == 200 && property_exists($data2, 'data')) {
                // return response()->json([
                //     'message' => $data2->message,
                //     'data1' => $data1,
                //     'data2' => $data2
                // ], 200);

                $qrData = "https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10&D2=1&D3=".$data1->data->rfc."_".$data1->data->id_cif; // Los datos que irán dentro del QR
                // $qrData = "https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10%26D2=1%26D3=".$data1->data->rfc."_".$data1->data->id_cif;
                // // $qrData = "https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10%26D2=1%26D3=23050300979_ICO2209056Y1";
                // // $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . rawurlencode($qrData);
                // $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrData);

                // $qrData = "https://siat.sat.gob.mx/app/qr/faces/pages/mobile/validadorqr.jsf?D1=10&D2=1&D3=23050300979_ICO2209056Y1"; 
                // $qrUrl = "https://quickchart.io/qr?size=200x200&data=" . urlencode($qrData);

                $qrData = $this->shortenURL($qrData);
                $qrUrl = "https://api.qrcode-monkey.com/qr/custom?size=200&data=" . urlencode($qrData);

                $barcodeData = $data1->data->rfc; // Número del código de barras
                $barcodeUrl = "https://bwipjs-api.metafloor.com/?bcid=code128&text=" . urlencode($barcodeData) . "&scale=3&height=10&includetext";

                // $barcodeData = "123456789012";
                // $barcodeUrl = "https://barcode.tec-it.com/barcode.ashx?data=" . urlencode($barcodeData) . "&code=Code128&multiplebarcodes=false&translate-esc=false";

                $data = [
                    'shcp_sat' => asset('images/shcp_sat.png'),
                    'qrUrl' => $qrUrl, // URL generada del QR
                    'barcodeUrl' => $barcodeUrl,
                    'data1' => $data1->data,
                    'data2' => $data2->data
                ];
        
                // return view('constancia_sf.constancia_sf', $data);
        
                //$pdf = Pdf::loadView('cotizaciones.cotizacion', $data);
                // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
                $pdf = Pdf::loadView('constancia_sf.constancia_sf', $data)->setPaper('letter');
                $pdfContent = $pdf->output();
        
                // Genera un nombre de archivo único
                $nombreArchivo = 'pdf_' . uniqid() . '.pdf';
        
                // Guarda el PDF en la carpeta "public" del directorio raíz
                Storage::disk('public_root')->put('pdfs_constancia_sf/'.$nombreArchivo, $pdf->output());
        
                // Obtiene la URL del archivo guardado
                $url = asset('pdfs_constancia_sf/' . $nombreArchivo);

                $empresa->id_cif = $Idcif;
                $empresa->save();
        
                return response()->json([
                    'url' => $url
                ], 200);
    
            } else {
                return response()->json([
                    'error' => $data2->message,
                    'data2' => $data2
                ], 409);
            }

        } else {
            return response()->json([
                'error' => $data1->message,
                'data' => $data1
            ], 409);
        }
    }

    public function shortenURL($url)
    {
        // return $url;

        $apiUrl = 'https://is.gd/api.php';
        $response = file_get_contents($apiUrl . '?longurl=' . urlencode($url));

        // Verificar si se obtuvo una respuesta válida
        if (filter_var($response, FILTER_VALIDATE_URL)) {
            return $response; // Devuelve el enlace acortado
        } else {
            // Manejar el error en caso de no obtener un enlace acortado válido
            return $url; // Devuelve la URL original sin acortar
        }
    }

}
