<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>

</head>
<body>

<div style="margin: 0 !important; padding: 0 !important; width: 100%; max-width: 816px;">
    <!-- Imagen superior -->
    <img src="https://apicontafacil.internow.com.mx/images_uploads/logos/logo_base.png" alt="Imagen Superior"
        style="display: block;
            margin: 0 auto;
            width: 150px;
            height: auto;" 
    >

    <div style="background-color: #FFFFFF;
        padding: 20px;
        border: 20px solid #4285CB;">

      <div style="text-align: center;">
          Emitido por Conta Fácil AM
      </div>
      <br><br><br>

      @if ($user != null)
      <div style="text-align: center;">
          Hola {{$user}}, gracias por su compra.
      </div>
      @endif

      @if ($email != null)
      <div style="text-align: center;">
          Nueva compra del usuario {{$email}}
      </div>
      @endif

      <br>

        <table width="100%">
            <tr>
                <td style="width: 50%; text-align: center;">
                    <strong>Paquete:</strong>
                </td>
                <td style="width: 50%;">
                    {{$paquete}}
                </td>
            </tr>
            <!-- <tr>
                <td style="width: 50%; text-align: center;">
                    <strong>Desc.:</strong>
                </td>
                <td style="width: 50%;">
                    {{$descripcion}}
                </td>
            </tr> -->
            <tr>
                <td style="width: 50%; text-align: center;">
                    <strong>Pago:</strong>
                </td>
                <td style="width: 50%;">
                    {{$api_tipo_pago}}
                </td>
            </tr>
            <tr>
                <td style="width: 50%; text-align: center;">
                    <strong>Total:</strong>
                </td>
                <td style="width: 50%;">
                    {{$total}} MXN
                </td>
            </tr>
        </table>
   
      <br><br>
      <div style="text-align: center; font-size: 18px;">
          Estamos a tu servicio.
      </div>

      <br><br><br>
      <div style="text-align: center;">
        <a href="https://wa.me/5217751936000" target="_blank" style="color: #000000;">
          Internow IA
        </a>
      </div>

    </div>

</div>

</body>
</html>