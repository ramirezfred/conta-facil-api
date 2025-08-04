<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Constancia de Situación Fiscal</title>
    <style>
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            width: 900px;
            background: #fff;
            border: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }

        .header h2 {
            font-size: 18px;
            font-weight: bold;
        }

        .content {
            display: flex;
            justify-content: space-between;
        }

        .left, .right {
            width: 48%;
        }

        .logos {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .logos img {
            width: 200px;
        }

        .qr-code img {
            width: 150px;
            margin-bottom: 10px;
        }

        .info {
            font-size: 14px;
            line-height: 1.6;
        }

        .info strong {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .right h2 {
            font-size: 18px;
            text-align: center;
            margin-bottom: 10px;
        }

        .right p {
            font-size: 14px;
            text-align: center;
            margin-bottom: 10px;
        }

        .barcode {
            text-align: center;
            margin-top: 20px;
            font-size: 16px;
            font-family: 'Courier New', Courier, monospace;
        }

    </style>
</head>
<body>
    
    <div class="container">
        <div class="header">
            <h2>CÉDULA DE IDENTIFICACIÓN FISCAL</h2>
        </div>
        <div class="content">
            <div class="left" style="border: 1px solid #000;">
                <!-- <div class="logos">
                    <img src="hacienda.png" alt="Hacienda">
                    <img src="sat.png" alt="SAT">
                    <img src={{$shcp_sat}} alt="SAT">
                </div> -->
                <div class="qr-code">
                    <img src="https://cdn.pixabay.com/photo/2013/07/12/14/45/qr-code-148732_640.png" alt="Código QR">
                </div>
                <div class="info">
                    <p>ICO2209056Y1</p>
                    <p>Registro Federal de Contribuyentes</p>
                    <p><strong>INTERNOW CORP</strong></p>
                    <p>Nombre, denominación o razón social</p>
                    <p>idCIF: 23050300979</p>
                    <p>VALIDA TU INFORMACIÓN FISCAL</p>
                </div>
            </div>
            <div class="right">
                <h2>CONSTANCIA DE SITUACIÓN FISCAL</h2>
                <p>Lugar y Fecha de Emisión</p>
                <p><strong>TULANCINGO DE BRAVO, HIDALGO A 17 DE JULIO DE 2023</strong></p>
                <div class="barcode">
                    <p>ICO2209056Y1</p>
                </div>
            </div>

            <table width="50%">
              <tr>
                <tr>
                    CÉDULA DE IDENTIFICACIÓN FISCAL
                </tr>
              </tr>
              <tr>
                <td>
                  
                </td>
              </tr>
            </table>
        </div>
    </div>
    
</body>
</html>