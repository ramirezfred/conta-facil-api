<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

use App\Http\Requests;

use DB;

use Exception;
use CURLFILE;

trait NotificacionTrait
{

    public static $base_url = "https://panel.internow.com.mx/api";



    public function _notificationPush($title,$description)
    {

        set_time_limit(500);

        //Armando la peticion cURL
        $fields = array(
            'image'=> new CURLFILE('https://apitree.internow.com.mx/images/vector-logo-cimmytree-50px.png'),
            'title' => $title,
            'description' => $description,
            //'app' => $request->input('app'),
            'app' => 'cimmytree',
        );
            
        //$fields = json_encode($fields);
        /* print("\nJSON sent:\n");
        print($fields); */

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, static::$base_url."/send_notification_push");
        // curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        //     'Content-Type: application/json; charset=utf-8',
        //     ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
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

    

}
