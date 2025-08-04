<?php

/* header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization, Accept,charset,boundary,Content-Length');
header('Access-Control-Allow-Origin: *'); */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PruebasController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\CfdiEmpresaController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CatGastoController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\ApiMarketController;
use App\Http\Controllers\IngresoController;
use App\Http\Controllers\SistemaController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\TimbradoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\PaqueteController;
use App\Http\Controllers\ProxyController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\DoctoraliaController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\WebhooksWhatsappController;
use App\Http\Controllers\BotSistemaController;
use App\Http\Controllers\CryptController;
use App\Http\Controllers\CalculadorasController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\FacturaRecurrenteController;
use App\Http\Controllers\GastoRecurrenteController;
use App\Http\Controllers\IngresoRecurrenteController;
use App\Http\Controllers\GraficasController;
use App\Http\Controllers\CalendarioFiscalController;
use App\Http\Controllers\UserSettingController;
use App\Http\Controllers\ProductoController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::get('/test', function (Request $request) {
    return 1;
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

//----Pruebas AuthController
Route::post('auth/login/web', [AuthController::class, 'loginWeb']);
Route::post('auth/me', [AuthController::class, 'me']);
Route::get('auth/user', [AuthController::class, 'getAuthenticatedUser']);

//----Pruebas UsuarioController
//Route::post('usuarios/crear_superadmin', [UsuarioController::class, 'storeSuperAdmin']);
//Route::get('usuarios', [UsuarioController::class, 'index']);
Route::post('usuarios/crear_cliente', [UsuarioController::class, 'storeCliente']);
Route::get('usuarios/logo/allow_origin/{imagen}', [UsuarioController::class, 'imagenAllowOrigin']);
Route::get('usuarios/email_admin_new_user/{user_id}', [UsuarioController::class, 'emailAdminNewUser']);
Route::get('usuarios/email_bienvenida_user/{user_id}', [UsuarioController::class, 'emailUserBienvenida']);
Route::get('usuarios/logo/base64/{nombreImagen}', [UsuarioController::class, 'obtenerImagenBase64']);


//----Pruebas FacturaController
Route::get('cfdi/get_factura/{factura_id}', [FacturaController::class, 'getFactura']);
Route::post('cfdi/buscar_serie', [FacturaController::class, 'buscarPorSerie']);
Route::get('cfdi/get_codigo_postal/{cp}', [FacturaController::class, 'getCodigoPostal']);
Route::get('cfdi/get_catalogo_regimen', [FacturaController::class, 'getCatalogoRegimen']);
Route::get('cfdi/get_catalogo_productos', [FacturaController::class, 'getCatalogoProductos']);
Route::get('cfdi/get_catalogo_unidades', [FacturaController::class, 'getCatalogoUnidades']);
Route::get('cfdi/get_catalogo_forma_pago', [FacturaController::class, 'getCatalogoFormaPago']);
Route::get('cfdi/get_catalogo_metodo_pago', [FacturaController::class, 'getCatalogoMetodoPago']);
Route::get('cfdi/get_catalogo_uso_cfdi', [FacturaController::class, 'getCatalogoUsoCfdi']);
Route::get('cfdi/email/{factura_id}', [FacturaController::class, 'emailFactura']);
Route::get('cfdi/email/factura_cancelada/{factura_id}', [FacturaController::class, 'emailFacturaCancelada']);
Route::get('cfdi/set_flag_algoritmo_factura', [FacturaController::class, 'setFlagAlgoritmoFactura']);
Route::get('cfdi/aplicar_algoritmo_factura/semanal', [FacturaController::class, 'aplicarAlgoritmoSemanalFactura']);
Route::get('cfdi/aplicar_algoritmo_factura/mensual', [FacturaController::class, 'aplicarAlgoritmoMansualFactura']);

//----Pruebas PruebasController
Route::get('pruebas/test_image', [PruebasController::class, 'testImage']);
Route::get('pruebas/test_image2', [PruebasController::class, 'extractColors']);
Route::get('pruebas/fecha', [PruebasController::class, 'fecha']);
Route::post('pruebas/upload_pdf', [PruebasController::class, 'upload']);
Route::get('pruebas/email_admin_new_user/{user_id}', [PruebasController::class, 'emailAdminNewUser']);
Route::get('pruebas/test_fecha', [PruebasController::class, 'testFecha']);
Route::post('pruebas/test_catalogos_sat', [PruebasController::class, 'testCatalogosSat']);

Route::get('cfdi/factura_pdf/{factura_id}', [FacturaController::class, 'facturaPdf']);

//----Pruebas DashboardController
Route::get('dashboard/cat_usuarios', [DashboardController::class, 'getCatalogoUsers']);

//----Pruebas PasswordController
Route::post('password/email_recover_password', [PasswordController::class, 'emailRecoverPassword']);
Route::put('password/update_password', [PasswordController::class, 'updatePassword']);

Route::get('gastos/set_pdf/{id}', [GastoController::class, 'gastoPdf']);
Route::get('ingresos/set_pdf/{id}', [IngresoController::class, 'ingresoPdf']);

//----Pruebas TimbradoController
// Route::get('timbrado/ingresos_por_timbrar/{user_id}', [TimbradoController::class, 'ingresosContables']);
// Route::get('timbrado/set_flag_algoritmo_factura', [TimbradoController::class, 'setFlagAlgoritmoFactura']);
// Route::get('timbrado/aplicar_algoritmo_factura', [TimbradoController::class, 'aplicarAlgoritmoFactura']);


Route::get('cfdi_empresa/get_emisor/{user_id}', [CfdiEmpresaController::class, 'showUserEmisor']);

//----Pruebas ReporteController
Route::get('reportes/estado_cuenta/{user_id}', [ReporteController::class, 'estadoDeCuenta']);

//----Pruebas ProxyController
Route::get('proxy', [ProxyController::class, 'proxy']);
Route::get('proxy2', [ProxyController::class, 'proxy2']);

//----Pruebas DoctoraliaController
Route::put('doctoralia/update_vistas/{id}', [DoctoraliaController::class, 'updateVistas']);
Route::get('doctoralia/filtrar_contadores', [DoctoraliaController::class, 'indexFiltrado']);
Route::get('doctoralia/show_perfil/{user_id}', [DoctoraliaController::class, 'showPerfil']);
Route::post('doctoralia/store_opinion', [DoctoraliaController::class, 'storeOpinion']);
Route::get('doctoralia/get_mas_opiniones/{doctor_id}/{opinion_id}', [DoctoraliaController::class, 'getMasOpiniones']);

//----Pruebas AuthController
Route::post('ai/message', [AiController::class, 'message']);
Route::post('ai/delete_file/{pdf_id}', [AiController::class, 'deleteFile']);
Route::post('ai/add_file', [AiController::class, 'addFile']);
Route::get('ai/add_file_google', [AiController::class, 'addFileGoogleapis']);
Route::get('ai/pregunta_google', [AiController::class, 'preguntaGoogleapis']);
Route::get('ai/message_google', [AiController::class, 'messageGoogleAI']);
Route::get('ai/message_pdf_google', [AiController::class, 'messagePDFGoogleAI']);
Route::get('ai/store_cache_google', [AiController::class, 'storeCacheGoogleAI']);
Route::get('ai/message_cache_google', [AiController::class, 'messageWhitCacheGoogleAI']);

Route::get('webhooks/handle', [WebhooksWhatsAppController::class, 'handleSuscribe']);
Route::post('webhooks/handle', [WebhooksWhatsAppController::class, 'handleMessage']);
Route::post('webhooks/message/{telefono}', [WebhooksWhatsAppController::class, 'messageText']);
Route::get('webhooks/mensajes/{user_id}', [WebhooksWhatsAppController::class, 'getMensajes']);

//----Pruebas BotSistemaController
Route::get('bot_sistema/update_context', [BotSistemaController::class, 'updateContext']);

//----Pruebas BotController
Route::get('bot_config/get_bot', [BotController::class, 'getBot']);
Route::put('bot_config/set_token', [BotController::class, 'updateTokenBot']);
Route::get('bot_config/alert_token', [BotController::class, 'alertToken']);

//----Pruebas FacturaRecurrenteController
Route::get('cfdi_recurrente/{user_id}', [FacturaRecurrenteController::class, 'index']);
Route::post('cfdi_recurrente/{factura_id}', [FacturaRecurrenteController::class, 'store']);
Route::put('cfdi_recurrente/update_status/{id}', [FacturaRecurrenteController::class, 'updateStatus']);
Route::delete('cfdi_recurrente/{id}', [FacturaRecurrenteController::class, 'destroy']);
Route::get('cfdi/correr/facturas_recurrentes', [FacturaController::class, 'correrFacturasRecurrentes']);

//----Pruebas GastoRecurrenteController
Route::get('gasto_recurrente/{user_id}', [GastoRecurrenteController::class, 'index']);
Route::post('gasto_recurrente/{gasto_id}', [GastoRecurrenteController::class, 'store']);
Route::put('gasto_recurrente/update_status/{id}', [GastoRecurrenteController::class, 'updateStatus']);
Route::delete('gasto_recurrente/{id}', [GastoRecurrenteController::class, 'destroy']);
Route::get('gastos/correr/gastos_recurrentes', [GastoController::class, 'correrGastosRecurrentes']);
Route::post('gastos/data/to_excel', [GastoController::class, 'dataToExcel']);

//----Pruebas IngresoRecurrenteController
Route::get('ingreso_recurrente/{user_id}', [IngresoRecurrenteController::class, 'index']);
Route::post('ingreso_recurrente/{gasto_id}', [IngresoRecurrenteController::class, 'store']);
Route::put('ingreso_recurrente/update_status/{id}', [IngresoRecurrenteController::class, 'updateStatus']);
Route::delete('ingreso_recurrente/{id}', [IngresoRecurrenteController::class, 'destroy']);
Route::get('ingresos/correr/ingresos_recurrentes', [IngresoController::class, 'correrIngresosRecurrentes']);
Route::post('ingresos/data/to_excel', [IngresoController::class, 'dataToExcel']);

//----Pruebas GraficasController
Route::get('graficas/proyectar_flujo_caja', [GraficasController::class, 'proyectarFlujoCaja']);
Route::get('graficas/detectar_variaciones', [GraficasController::class, 'detectarVariaciones']);
Route::get('graficas/evaluar_riesgo', [GraficasController::class, 'evaluarRiesgo']);
Route::get('graficas/historial', [GraficasController::class, 'historial']);
Route::get('graficas/resumen', [GraficasController::class, 'resumen']);
Route::get('graficas/indicadores', [GraficasController::class, 'indicadores']);
Route::get('graficas/comparativa', [GraficasController::class, 'comparativa']);
Route::get('graficas/status_facturas', [GraficasController::class, 'statusFacturas']);


//----Pruebas UserSettingController
Route::post('user-settings', [UserSettingController::class, 'storeOrUpdate']);
Route::get('user-settings/{user_id}', [UserSettingController::class, 'show']);
Route::post('user-settings/desactivar-recordatorio', [UserSettingController::class, 'desactivarRecordatorios']);


Route::group(['middleware' => ['jwt.verify']], function() {

    //----Pruebas UsuarioController
    Route::post('usuarios/destroy_cuenta/{id}', [UsuarioController::class, 'destroyCuenta']);
    Route::get('usuarios/filter_rol/{rol}', [UsuarioController::class, 'indexRol']);
    Route::post('usuarios/link_logo', [UsuarioController::class, 'storeLinkLogo']);
    Route::post('usuarios/link_header_footer', [UsuarioController::class, 'storeLinkHeaderFooter']);
    Route::get('usuarios/algoritmo_factura/{user_id}', [UsuarioController::class, 'showAlgoritmoFactura']);
    Route::get('usuarios/count_timbres/{user_id}', [UsuarioController::class, 'getCountTimbres']);
    Route::put('usuarios/update_telefono/{user_id}', [UsuarioController::class, 'updateTelefono']);
    Route::post('usuarios/update_img_perfil/{user_id}', [UsuarioController::class, 'updateImgPerfil']);

    //----Pruebas CfdiEmpresaController
    Route::put('cfdi_empresa/{user_id}', [CfdiEmpresaController::class, 'updateUserEmisor']);
    Route::post('cfdi_empresa/link_archivo/{ext}', [CfdiEmpresaController::class, 'storeArchivo']);
    //Route::get('cfdi_empresa/get_emisor/{user_id}', [CfdiEmpresaController::class, 'showUserEmisor']);
    Route::get('cfdi_empresa/get_producto_emisor/{user_id}', [CfdiEmpresaController::class, 'showProductoEmisor']);
    Route::put('cfdi_empresa/put_producto_emisor/{producto_id}', [CfdiEmpresaController::class, 'updateProductoEmisor']);

    //----Pruebas FacturaController
    Route::get('cfdi/get_cliente_empresa/{cliente_id}', [FacturaController::class, 'getClienteEmpresa']);
    Route::get('cfdi/get_clientes_rfc', [FacturaController::class, 'getClientesPorRfc']);
    Route::get('cfdi/get_clientes_all', [FacturaController::class, 'getAllClientes']);
    Route::put('cfdi/put_empresa/{empresa_id}', [FacturaController::class, 'update']);
    Route::get('cfdi/get_emitidas/{cliente_id}', [FacturaController::class, 'indexEmitidasFilter']);
    Route::get('cfdi/get_canceladas/{cliente_id}', [FacturaController::class, 'indexCanceladasFilter']);
    Route::post('cfdi/cancelar_factura/{factura_id}', [FacturaController::class, 'cancelarFactura']);
    Route::post('cfdi/timbrar_desde_panel/{empresa_id}', [FacturaController::class, 'timbrarDesdePanel']);
    Route::post('cfdi/timbrar_desde_panel_sandbox/{empresa_id}', [FacturaController::class, 'timbrarDesdePanelSandbox']);
    Route::put('cfdi/update_status_pay/{factura_id}', [FacturaController::class, 'updateStatusPay']);

    //----Pruebas DashboardController
    Route::get('dashboard/contadores/{user_id}', [DashboardController::class, 'contadores']);
    Route::get('dashboard/contadores_termino/{termino}', [DashboardController::class, 'contadoresTermino']);
    Route::get('dashboard/activiadad/filter/{user_id}', [DashboardController::class, 'actividadFilter']);

    Route::get('cat_gastos', [CatGastoController::class, 'index']);

    //----Pruebas GastoController
    Route::get('gastos/filter/{user_id}', [GastoController::class, 'indexFilter']);
    Route::post('gastos', [GastoController::class, 'store']);
    Route::delete('gastos/{id}', [GastoController::class, 'destroy']);
    Route::get('gastos/{id}', [GastoController::class, 'show']);
    

    //----Pruebas IngresoController
    Route::get('ingresos/filter/{user_id}', [IngresoController::class, 'indexFilter']);
    Route::post('ingresos', [IngresoController::class, 'store']);
    Route::delete('ingresos/{id}', [IngresoController::class, 'destroy']);
    Route::get('ingresos/{id}', [IngresoController::class, 'show']);

    //----Pruebas SistemaController
    Route::get('sistema', [SistemaController::class, 'index']);

    //----Pruebas CursoController
    Route::get('cursos/cliente/{user_id}', [CursoController::class, 'indexCliente']);
    Route::post('cursos/dar_like', [CursoController::class, 'darLike']);
    Route::post('cursos/quitar_like', [CursoController::class, 'quitarLike']);

    //----Pruebas PaqueteController
    Route::get('paquetes/cliente', [PaqueteController::class, 'indexCliente']);

    //----Pruebas CompraController
    Route::get('compras/filter/user/{user_id}', [CompraController::class, 'indexFilter']);
    Route::post('compras/order/conekta', [CompraController::class, 'postOrderConekta']);
    Route::post('compras/order/paypal', [CompraController::class, 'postOrderPaypal']);

    //----Pruebas DoctoraliaController
    Route::get('doctoralia/{user_id}', [DoctoraliaController::class, 'show']);
    Route::post('doctoralia', [DoctoraliaController::class, 'store']);
    Route::put('doctoralia/{id}', [DoctoraliaController::class, 'update']);
    Route::post('doctoralia/link_logo', [DoctoraliaController::class, 'storeLinkLogo']);
    Route::post('doctoralia/link_archivo', [DoctoraliaController::class, 'storeArchivo']);
    Route::post('doctoralia/link_galeria', [DoctoraliaController::class, 'storeLinkGaleria']);
    Route::post('doctoralia/store_galeria', [DoctoraliaController::class, 'storeGaleria']);
    Route::delete('doctoralia/destroy_galeria/{id}', [DoctoraliaController::class, 'destroyGaleria']);
    Route::post('doctoralia/link_foto', [DoctoraliaController::class, 'storeLinkFoto']);
    Route::post('doctoralia/link_certificado', [DoctoraliaController::class, 'storeLinkCertificado']);

    //----Pruebas CryptController
    Route::get('crypt/encrypt/{cadena}', [CryptController::class, 'encrypt']);
    Route::get('crypt/decrypt/{cadena}', [CryptController::class, 'decrypt']);

    //----Pruebas CalculadorasController
    Route::get('calculadoras', [CalculadorasController::class, 'index']);
    Route::get('calculadoras/cliente', [CalculadorasController::class, 'indexCliente']);
    Route::post('calculadoras/store_carpeta', [CalculadorasController::class, 'storeCarpeta']);
    Route::post('calculadoras/store_documento', [CalculadorasController::class, 'storeDocumento']);
    Route::put('calculadoras/update_carpeta/{id}', [CalculadorasController::class, 'updateCarpeta']);
    Route::put('calculadoras/update_documento/{id}', [CalculadorasController::class, 'updateDocumento']);
    Route::delete('calculadoras/delete_carpeta/{id}', [CalculadorasController::class, 'destroyCarpeta']);
    Route::delete('calculadoras/delete_documento/{id}', [CalculadorasController::class, 'destroyDocumento']);
    Route::post('calculadoras/store_archivo', [CalculadorasController::class, 'storeArchivo']);

    //----Pruebas ApiMarketController
    Route::get('apimarket/obtener_datos/{Rfc}', [ApiMarketController::class, 'obtenerDatos']);
    Route::get('apimarket/obtener_datos_idcif/{Idcif}/{Rfc}', [ApiMarketController::class, 'obtenerDatosIdcif']);
    Route::get('apimarket/vista/constancia', [ApiMarketController::class, 'mostrarVistaCosntancia']);
    Route::get('apimarket/generar_constancia/{empresa_id}/{Idcif}', [ApiMarketController::class, 'generarConstancia']);

    //----Pruebas CalendarioFiscalController
    Route::get('calendario_fiscal', [CalendarioFiscalController::class, 'index']);
    Route::get('calendario_fiscal/cliente', [CalendarioFiscalController::class, 'indexCliente']);
    Route::post('calendario_fiscal', [CalendarioFiscalController::class, 'store']);
    Route::put('calendario_fiscal/{id}', [CalendarioFiscalController::class, 'update']);
    Route::delete('calendario_fiscal/{id}', [CalendarioFiscalController::class, 'destroy']);

    //----Pruebas ProductoController
    Route::get('productos', [ProductoController::class, 'index']);
    Route::post('productos', [ProductoController::class, 'store']);
    Route::put('productos/{id}', [ProductoController::class, 'update']);
    Route::delete('productos/{id}', [ProductoController::class, 'destroy']);
    Route::get('productos/{id}', [ProductoController::class, 'show']);


});


Route::group(['middleware' => ['jwt.verify.admin']], function() {

    //----Pruebas UsuarioController
    Route::get('usuarios', [UsuarioController::class, 'index']);
    Route::put('usuarios/{id}', [UsuarioController::class, 'update']);
    Route::delete('usuarios/{id}', [UsuarioController::class, 'destroy']);
    Route::put('usuarios/update_status/{id}', [UsuarioController::class, 'updateStatus']);
    Route::put('usuarios/password/{id}', [UsuarioController::class, 'updatePassword']);
    Route::put('usuarios/personalizar/{user_id}', [UsuarioController::class, 'updatePersonalizar']);
    Route::get('usuarios/{id}', [UsuarioController::class, 'show']);

    //----Pruebas CatGastoController
    Route::post('cat_gastos', [CatGastoController::class, 'store']);
    Route::put('cat_gastos/{id}', [CatGastoController::class, 'update']);
    Route::delete('cat_gastos/{id}', [CatGastoController::class, 'destroy']);

    //----Pruebas SistemaController
    Route::put('sistema/{id}', [SistemaController::class, 'update']);

    //----Pruebas CursoController
    Route::get('cursos', [CursoController::class, 'index']);
    Route::post('cursos', [CursoController::class, 'store']);
    Route::delete('cursos/{id}', [CursoController::class, 'destroy']);
    Route::post('cursos/link_archivo', [CursoController::class, 'storeArchivo']);

    //----Pruebas PaqueteController
    Route::get('paquetes', [PaqueteController::class, 'index']);
    Route::post('paquetes', [PaqueteController::class, 'store']);
    Route::delete('paquetes/{id}', [PaqueteController::class, 'destroy']);
    Route::post('paquetes/link_archivo', [PaqueteController::class, 'storeArchivo']);
    Route::put('paquetes/{id}', [PaqueteController::class, 'update']);
    Route::put('paquetes/update_status/{id}', [PaqueteController::class, 'updateStatus']);

    //----Pruebas CompraController
    Route::get('compras/filter/admin', [CompraController::class, 'indexFilterAdmin']);
    Route::get('compras/{id}', [CompraController::class, 'show']);
    Route::get('compras/email/{compra_id}', [CompraController::class, 'emailCompra']);

    //----Pruebas DoctoraliaController
    Route::get('doctoralia', [DoctoraliaController::class, 'index']);
    Route::put('doctoralia/update_status/{id}', [DoctoraliaController::class, 'updateStatus']);

    //----Pruebas BotSistemaController
    Route::get('bot_sistema/test_key', [BotSistemaController::class, 'handleRequest']);
    Route::get('bot_sistema/key', [BotSistemaController::class, 'index']);
    Route::post('bot_sistema/key', [BotSistemaController::class, 'store']);
    Route::delete('bot_sistema/key/{id}', [BotSistemaController::class, 'destroy']);
    Route::get('bot_sistema/get_files', [BotSistemaController::class, 'getFilesGoogleAI']);
    Route::delete('bot_sistema/delete_file', [BotSistemaController::class, 'deleteFileGoogleAI']);

});

    

