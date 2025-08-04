<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Http;

class ProxyController extends Controller
{

    public function proxy2(Request $request)
    {
        $url = $request->query('url');
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $response = Http::get($url);

            return response($response->body(), $response->status())
                ->header('Content-Type', $response->header('Content-Type'));
        } else {
            return response('Invalid URL', 400);
        }
    }

    public function proxy(Request $request)
    {
        $url = $request->query('url');

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_FAILONERROR, 1); // Fail on HTTP errors

            $response = curl_exec($ch);

            if ($response === false) {
                $error = curl_error($ch); // Get the error message
                curl_close($ch);
                return response("cURL error: $error", 500);
            }

            // Split the header and body
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            // Get HTTP status code
            $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            // Extract Content-Type from header
            $content_type = 'text/html'; // default content type
            if (preg_match('/Content-Type:\s([a-zA-Z0-9\/\-;]+)/i', $header, $matches) && isset($matches[1])) {
                $content_type = $matches[1];
            }

            curl_close($ch);

            return response($body, $http_status)
                ->header('Content-Type', $content_type);
        } else {
            return response('Invalid URL', 400);
        }
    }


    
}
