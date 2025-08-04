<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Constancia de Situación Fiscal</title>
    <style>
        /* Configuración general para cada página */
        @page { margin: 5mm 5mm; } /* Márgenes en cada página */

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #fff;
            color: #000;
        }
        .container {
            max-width: 800px;
            margin: auto;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 24px;
        }
        .section {
            margin-bottom: 20px;
        }
        .section h2 {
            font-size: 20px;
            margin-bottom: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }
        .section div, .section p {
            margin: 5px 0;
        }
        .section table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .section table, .section th, .section td {
            border: 1px solid #000;
        }
        .section th, .section td {
            padding: 8px;
            text-align: left;
        }
        .footer {
            font-size: 12px;
        }
        .footer a {
            color: #000;
        }

        .qr-code img {
            width: 110px;
        }

    </style>
</head>
<body>
    <div class="container">

        <table width="100%">
            <tr>
                <td style="width: 50%; margin: 0px; padding: 0px;">

                    <table width="100%" style="border-collapse: collapse;">
                        <tr>
                            <th style="border: 1px solid #000; background-color: #d3d3d3; text-align: center; font-size: 14px; padding: 6px;">
                                CÉDULA DE IDENTIFICACIÓN FISCAL
                            </th>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #000;">

                                <img src={{$shcp_sat}} with="50" height="50" alt="SHCP SAT">

                                <table width="100%">
                                    <tr>
                                        <td style="width: 50%; text-align: center;">
                                        
                                            <div class="qr-code">
                                                <img src="https://cdn.pixabay.com/photo/2013/07/12/14/45/qr-code-148732_640.png" alt="Código QR">
                                            </div>

                                        </td>
                                        <td style="width: 50%; text-align: center; font-size: 10px;">
                                        
                                            <p style="margin-bottom: 0; color: #808080;"><strong>{{$data1->rfc}}</strong></p>
                                            <p style="margin-top: 0; color: #808080;">Registro Federal de Contribuyentes</p>
                                            <p style="margin-bottom: 0; color: #808080;"><strong>{{$data1->nombre}}</strong></p>
                                            <p style="margin-top: 0; color: #808080;">Nombre, denominación o razón social</p>
                                            <p style="margin-bottom: 0; color: #808080;"><strong>idCIF: {{$data1->id_cif}}</strong></p>
                                            <p style="margin-top: 0; color: #808080;">VALIDA TU INFORMACIÓN FISCAL</p>

                                        </td>
                                    </tr>
                                </table>

                            </td>
                        </tr>
                    </table>

                </td>
                <td style="width: 50%; margin: 0px; padding: 0px; text-align: center;">
                    <p style="background-color: #d3d3d3; padding: 20px; border: 1px solid #000;">
                        <strong>CONSTANCIA DE SITUACIÓN FISCAL</strong>
                    </p>
                    <!-- <p>Lugar y Fecha de Emisión: TULANCINGO DE BRAVO, HIDALGO A 17 DE JULIO DE 2023</p> -->
                     <p style="font-size: 10px;">
                        {{$data1->rfc}}
                     </p>
                </td>
            </tr>
        </table>


        


        <div class="header">
            <h1>CÉDULA DE IDENTIFICACIÓN FISCAL</h1>
            <img src={{$shcp_sat}} with="50" height="50" alt="SHCP SAT">
            <p><strong>{{$data1->rfc}}</strong></p>
            <p>Registro Federal de Contribuyentes</p>
            <p><strong>{{$data1->nombre}}</strong></p>
            <p>Nombre, denominación o razón social</p>
            <p><strong>idCIF: {{$data1->id_cif}}</strong></p>
            <p>VALIDA TU INFORMACIÓN FISCAL</p>
            <p>CONSTANCIA DE SITUACIÓN FISCAL</p>
            <!-- <p>Lugar y Fecha de Emisión: TULANCINGO DE BRAVO, HIDALGO A 17 DE JULIO DE 2023</p> -->
        </div>

        <div class="section">
            <h2>Datos de Identificación del Contribuyente</h2>
            <table>
                <tr>
                    <th>RFC:</th>
                    <td>{{$data1->rfc}}</td>
                </tr>
                <tr>
                    <th>Denominación/Razón Social:</th>
                    <td>{{$data1->nombre}}</td>
                </tr>
                <tr>
                    <th>Régimen Capital:</th>
                    <td>{{$data1->regimen_capital}}</td>
                </tr>
                <tr>
                    <th>Nombre Comercial:</th>
                    <td>{{$data1->nombre}}</td>
                </tr>
                <tr>
                    <th>Fecha Inicio de operaciones:</th>
                    <td>{{ \Carbon\Carbon::parse($data1->inicio_operacion)->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <th>Estatus en el padrón:</th>
                    <td>{{$data1->status}}</td>
                </tr>
                <tr>
                    <th>Fecha de último cambio de estado:</th>
                    <td>{{ \Carbon\Carbon::parse($data1->ultimo_cambio_status)->format('d/m/Y') }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2>Datos del domicilio registrado</h2>
            <table>

                
                <tr>
                    <td><strong>Código Postal:</strong> {{$data1->domicilio->cp}}</td>
                    <td><strong>Tipo de Vialidad:</strong> {{$data1->domicilio->tipo_vialidad}}</td>
                </tr>

                <tr>
                    <td><strong>Nombre de Vialidad:</strong> {{$data1->domicilio->calle}}</td>
                    <td><strong>Número Exterior:</strong> {{$data1->domicilio->exterior}}</td>
                </tr>

                <tr>
                    <td><strong>Número Interior:</strong> {{$data1->domicilio->interior}}</td>
                    <td><strong>Nombre de la Colonia:</strong> {{$data1->domicilio->colonia}}</td>
                </tr>

                <tr>
                    <td><strong>Nombre de la Localidad:</strong> {{$data1->domicilio->ciudad}}</td>
                    <td><strong>Nombre del Municipio o Demarcación Territorial:</strong> {{$data1->domicilio->demarcacion_territorial}}</td>
                </tr>

            </table>
        </div>

        <!-- <div class="section">
            <h2>Actividades Económicas</h2>
            <table>
                <thead>
                    <tr>
                        <th>Orden</th>
                        <th>Actividad Económica</th>
                        <th>Porcentaje</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>Comercio al por mayor de ropa</td>
                        <td>30%</td>
                        <td>05/09/2022</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Servicios de consultoría en computación</td>
                        <td>20%</td>
                        <td>05/09/2022</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Creación y difusión de contenido exclusivamente a través de Internet</td>
                        <td>20%</td>
                        <td>05/09/2022</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>4</td>
                        <td>Agencias de publicidad</td>
                        <td>15%</td>
                        <td>05/09/2022</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>5</td>
                        <td>Agencias de compra de medios a petición del cliente</td>
                        <td>10%</td>
                        <td>05/09/2022</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>6</td>
                        <td>Comercio al por menor de computadoras y sus accesorios</td>
                        <td>5%</td>
                        <td>05/09/2022</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div> -->

        <div class="section">
            <h2>Regímenes</h2>
            <table>
                <thead>
                    <tr>
                        <th>Régimen</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($data2->regimenes as $item)
                    <tr>
                        <td>{{$item->dregimen}}</td>
                        <td>{{ \Carbon\Carbon::parse($item->faltaReg)->format('d/m/Y') }}</td>
                        <td></td>
                    </tr>
                @endforeach
                </tbody>

            </table>
        </div>

        <div class="section">
            <h2>Obligaciones</h2>
            <table>
                <thead>
                    <tr>
                        <th>Descripción de la Obligación</th>
                        <th>Descripción</th>
                        <th>Vencimiento</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($data2->obligaciones as $item)
                    <tr>
                        <td>{{$item->dobligacion}}</td>
                        <td></td>
                        <td></td>
                        <td>{{ \Carbon\Carbon::parse($item->finiLegal)->format('d/m/Y') }}</td>
                        <td></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="footer">
            <p>Sus datos personales son incorporados y protegidos en los sistemas del SAT, de conformidad con los Lineamientos de Protección de Datos Personales y con diversas disposiciones fiscales y legales sobre confidencialidad y protección de datos, a fin de ejercer las facultades conferidas a la autoridad fiscal.</p>
            <p>Si desea modificar o corregir sus datos personales, puede acudir a cualquier Módulo de Servicios Tributarios y/o a través de la dirección <a href="http://sat.gob.mx">http://sat.gob.mx</a></p>
            <p>"La corrupción tiene consecuencias ¡denúnciala! Si conoces algún posible acto de corrupción o delito presenta una queja o denuncia a través de: <a href="http://www.sat.gob.mx">www.sat.gob.mx</a>, <a href="mailto:denuncias@sat.gob.mx">denuncias@sat.gob.mx</a>, desde México: (55) 8852 2222, desde el extranjero: + 55 8852 2222, SAT móvil o <a href="http://www.gob.mx/sfp">www.gob.mx/sfp</a>".</p>
        </div>
    </div>
</body>
</html>