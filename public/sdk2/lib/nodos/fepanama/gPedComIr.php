<?php

function mf_nodo_gpedcomir(array $datos)
{
    $xml = '<gPedComIr>';
    // E151:N�mero del pedido de compra
    $xml .= crea_nodo_simple($datos, 'dNroPed');
    // E152:N�mero secuencial del �tem en el pedido
    $xml .= crea_nodo_simple($datos, 'dSecItemPed');
    // E159:Informaciones de inter�s del emitente con respeto al Pedido Comercial, relacionadas con un �tem de la factura
    $xml .= crea_nodo_simple($datos, 'dNroPed');
    $xml .= '</gPedComIr>';
    return $xml;
}