<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensaje</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.2/css/bootstrap.min.css" integrity="sha384-PsH8R72JQ3SOdhVi3uxftmaW6Vc51MKb0q5P2rRUpPvrszuE4W1povHYgTpBfshb" crossorigin="anonymous">

    <link href="https://fonts.googleapis.com/css?family=Roboto:400,700" rel="stylesheet">

    <style>
        @media print {
            @page { margin: 0; padding: 0; }
            body { margin: 0; padding: 0; }
            html { margin: 0; padding: 0; }
        }

        @page { margin: 0; padding: 0; }
        body { margin: 0; padding: 0; }
        html { margin: 0; padding: 0; }

        /* Estilos para el div contenedor */
        .container {
            width: 8.5in;
            height: 6in;
            position: relative;
            margin: 0 auto;
            padding: 0;
            overflow: hidden;
        }

        /* Estilos para la imagen superior */
        .top-image {
            display: block;
            margin: 0 auto;
            width: 100px;
            height: auto;
            border-radius: 10px;
        }

        /* Estilos para la imagen inferior */
        .bottom-image {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
        }

        /* Estilos para el contenido del div */
        .content {
            padding: 15px;
            font-size: 12px;
            height: 25px;
            font-family: 'Roboto', sans-serif;
        }

        /* Color de fondo personalizado para el contenedor */
        .container {
            background-color: #FFFFFF;
        }

        #div1 {
            background-color: #FFFFFF;
            padding: 20px;
            border: 20px solid #3D992E;

        }

    </style>

</head>
<body>

<div class="container" style="margin: 0 !important; padding: 0 !important; width: 100%; max-width: 816px;">
    

    <!-- Contenido del div -->
    <div class="content">

        <div id="div1">

          <!-- Imagen superior -->
          <img class="top-image" src="https://apitree.internow.com.mx/images/vector-logo-cimmytree.png" alt="Imagen">

          <br>

          <div style="text-align: center;">
              <strong>Nombre:</strong> {{$Nombre}}
          </div>

          <div style="text-align: center;">
              <strong>Email:</strong> {{$Email}}
          </div>

          <br>
          <div style="text-align: center;">
              <strong>Asunto:</strong> {{$Asunto}}
          </div>

          <br>
          <div style="text-align: center; font-size: 18px;">
              Mensaje
          </div>

          <br>
          <div style="text-align: center;">
              <p>{{$Mensaje}}</p>
          </div>


          </div>
        </div>
    </div>

</div>

</body>
</html>