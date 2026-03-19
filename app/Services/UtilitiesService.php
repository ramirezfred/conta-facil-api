<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Exception;

class UtilitiesService
{
    public static function hexToRgb($hex) {
        // Elimina cualquier carácter no deseado del valor hexadecimal
        $hex = preg_replace('/[^a-f0-9]/i', '', $hex);

        // Verifica si el valor hexadecimal tiene 3 o 6 caracteres y ajusta si es necesario
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        // Convierte el valor hexadecimal a valores RGB
        $r = hexdec($hex[0] . $hex[1]);
        $g = hexdec($hex[2] . $hex[3]);
        $b = hexdec($hex[4] . $hex[5]);

        // Devuelve un arreglo con los valores RGB
        return array('r' => $r, 'g' => $g, 'b' => $b);
    }
}
