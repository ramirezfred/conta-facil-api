<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.2/css/bootstrap.min.css" integrity="sha384-PsH8R72JQ3SOdhVi3uxftmaW6Vc51MKb0q5P2rRUpPvrszuE4W1povHYgTpBfshb" crossorigin="anonymous">

    <style>
      /* Configuración general para cada página */
      @page { margin: 10mm 0mm; } /* Márgenes en cada página */
      body { font-family: Arial, sans-serif; }

      /* Encabezado y pie de página en posición fija */
      .top-image {
          position: fixed;
          top: -10mm;
          left: 0;
          width: 100%;
          height: auto;
      }

      .bottom-image {
          position: fixed;
          bottom: -10mm;
          left: 0;
          width: 100%;
          height: 50px;
      }

      /* Margen superior para evitar que el contenido se oculte tras el encabezado */
      .content {
          margin-top: 60px; /* Ajusta el valor para que se vea el encabezado */
          margin-bottom: 60px; /* Ajusta el valor para que se vea el pie de página */
      }

      /* Estilos de párrafos y contenido */
      p {
          page-break-inside: avoid; /* Evita cortes de párrafos entre páginas */
      }

      /* CSS para permitir que un párrafo se corte en varias líneas */
      .parrafo-multilinea {
            word-wrap: break-word; /* Permite que las palabras se dividan en múltiples líneas si no caben. */
            overflow-wrap: break-word; /* Otra opción compatible con navegadores modernos. */
            max-width: 600px; /* Puedes ajustar esto según tus necesidades. */
        }

    </style>

</head>
<body>

    <div class="container" style="margin: 0 !important; padding: 0 !important; width: 100%; max-width: 816px;">
        
        <!-- Encabezado que se repetirá en cada página -->
        <img src={{$header}} alt="Encabezado" class="top-image">
        
        <!-- Contenido que fluye entre páginas -->
        <div class="content">

          <div style="padding: 15px; font-size: 12px;height: 25px;">

            <div style="margin-top: 60px; text-align: center;"> 
              <h5><strong>{{$titulo}}</strong></h5>
            </div>

            <table width="100%">
              <tr>
                <td style="width: 70%; margin: 0px; padding: 0px;">
                  <strong>Usuario:</strong> {{$user->nombre}}
                </td>
                <td style="width: 30%; margin: 0px; padding: 0px;">
                  <strong>Fecha:</strong> {{$fecha}}
                </td>
              </tr>
            </table>

            <table width="100%">
              <tr>
                <td style="width: 70%; margin: 0px; padding: 0px;">
                  <strong>Email:</strong> {{$user->email}}
                </td>
                <td style="width: 30%; margin: 0px; padding: 0px;">
                  <strong>RFC:</strong> {{$user->cfdi_empresa->Rfc ?? ''}}
                </td>
              </tr>
            </table>

            <table width="100%">
              <tr>
                <td style="width: 70%; margin: 0px; padding: 0px;">
                  <strong>Razón social:</strong> {{$user->cfdi_empresa->RazonSocial ?? ''}}
                </td>
                <td style="width: 30%; margin: 0px; padding: 0px;">
                  <strong>Código postal:</strong> {{$user->cfdi_empresa->CP ?? ''}}
                </td>
              </tr>
            </table>

            <br>

            <div style="text-align: center;"> 
              <strong>Detalles</strong>    
            </div>

            <br>

            <table class="table table-sm" style="width: 100%;">
              <thead>
                <tr style="background-color: rgba({{$r}}, {{$g}}, {{$b}}, 0.2);">
                    <th scope="col">Folio</th>
                    <th scope="col">Serie</th>
                    <th scope="col">Fecha</th>
                    <th scope="col">Hora</th>
                    <th scope="col">Rfc receptor</th>
                    <th scope="col">Nombre receptor</th>
                    <th scope="col">Total</th>
                    <!-- <th scope="col">Status</th> -->
                    <th scope="col">Estado</th>
                </tr>
              </thead>
              <tbody style="font-size: 12px;height: 25px;">
                @foreach ($coleccion as $item)
                    <tr>
                      <td>
                        {{$item->Folio}}
                      </td>
                      <td>
                        {{$item->Serie}}
                      </td>
                      <td>
                        {{ \Carbon\Carbon::parse($item->Fecha)->format('d/m/Y') }}
                      </td>
                      <td>
                        {{ \Carbon\Carbon::parse($item->Fecha)->format('H:i:s') }}
                      </td>
                      <td>
                        {{$item->receptor->Rfc}}
                      </td>
                      <td style="white-space: normal; word-break: break-word;">
                        {{$item->receptor->Nombre}}
                      </td>
                      <td>
                        {{ number_format($item->Total, 2, '.', ',') }}
                      </td>
                      <!-- <td style="white-space: normal; word-break: break-word;">
                        @if ($item->status_pay == 0)
                          Pendiente por pagar
                        @endif

                        @if ($item->status_pay == 1)
                          Pagada
                        @endif
                      </td> -->
                      <td style="white-space: normal; word-break: break-word;">
                        @if ($item->status == 1)
                          Emitida
                        @endif

                        @if ($item->status == 2)
                          Cancelada
                        @endif
                      </td>
                    </tr>
                @endforeach
              </tbody>
            </table> 

          </div>

        </div>

        <!-- Pie de página que se repetirá en cada página -->
        <img src={{$footer}} alt="Pie de página" class="bottom-image">
        
    </div>
    
</body>
</html>
