<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\CatGasto;
use App\Models\Gasto;
use App\Models\GastoConcepto;
use App\Models\GastoRecurrente;

use App\Models\Ingreso;
use App\Models\IngresoConcepto;
use App\Models\IngresoRecurrente;

use App\Models\CfdiComprobante;
use App\Models\CfdiEmpresa;
// use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Validator;

use DB;

use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

class GraficasController extends Controller
{
    /**
     * 1. Proyección básica: Calcula los ingresos y gastos proyectados basándote en las transacciones programadas (suma los montos según la frecuencia configurada).
     *
     * ¿Qué hace esta función?
     * Proyecta ingresos y gastos al mismo tiempo.
     * Utiliza una función auxiliar (calcularMontoRecurrente()) para evitar código repetido.
     * Calcula el flujo de caja neto, lo que permite ver si habrá superávit o déficit en un mes.
     * Soporta frecuencia única, semanal y mensual.
     */
    public function proyectarFlujoCaja(Request $request) {
        $mesesFuturos = $request->input('meses', 6);
        $userId = $request->input('user_id'); // O el user_id correspondiente
        $proyeccion = [];
    
        // Obtener ingresos y gastos recurrentes activos
        $ingresosRecurrentes = IngresoRecurrente::where('user_id', $userId)
            ->where('status', 1)
            ->with(['ingreso' => function ($query) {
                $query->select('id', 'total', 'flag_eliminado'); // Asegura que solo traiga los campos necesarios
            }])
            ->get();

        $gastosRecurrentes = GastoRecurrente::where('user_id', $userId)
            ->where('status', 1)
            ->with(['gasto' => function ($query) {
                $query->select('id', 'total', 'flag_eliminado');
            }])
            ->get();
    
        foreach (range(1, $mesesFuturos) as $mes) {
            $fechaProyectada = now()->addMonths($mes);
            $mesNombre = $fechaProyectada->format('F Y');
            $totalIngresos = 0;
            $totalGastos = 0;
    
            // Calcular ingresos proyectados
            foreach ($ingresosRecurrentes as $ingreso) {
                if($ingreso->flag_eliminado != 1){
                    $totalIngresos += $this->calcularMontoRecurrente($ingreso, $fechaProyectada);
                }
            }
    
            // Calcular gastos proyectados
            foreach ($gastosRecurrentes as $gasto) {
                if($gasto->flag_eliminado != 1){
                    $totalGastos += $this->calcularMontoRecurrente($gasto, $fechaProyectada);
                }
            }
    
            // Agregar al array de proyección
            $proyeccion[] = [
                'mes' => $mesNombre,
                'ingresos' => $totalIngresos,
                'gastos' => $totalGastos,
                'flujo_neto' => $totalIngresos - $totalGastos, // Resultado final del mes
            ];
        }
    
        return response()->json($proyeccion);
    }
    
    /**
     * Función auxiliar para calcular el monto de un ingreso/gasto según su frecuencia.
     */
    private function calcularMontoRecurrente($registro, $fechaProyectada) {
        $monto = $registro->ingreso_id ? $registro->ingreso->total : $registro->gasto->total;
    
        switch ($registro->frecuencia) {
            case 1: // Una vez
                //return ($fechaProyectada->format('Y-m-d') == $registro->fecha) ? $monto : 0;
                return ($fechaProyectada->format('Y-m') == Carbon::parse($registro->fecha)->format('Y-m')) ? $monto : 0;
    
            case 2: // Semanal
                $ocurrencias = $this->contarDiasEnMes($registro->dia_semana, $fechaProyectada->year, $fechaProyectada->month);
                return $monto * $ocurrencias;
    
            case 3: // Mensual
                return ($registro->dia_mes <= $fechaProyectada->daysInMonth) ? $monto : 0;
        }
    
        return 0;
    }
    
    /**
     * Cuenta cuántas veces un día de la semana ocurre en un mes dado.
     */
    private function contarDiasEnMes($diaSemana, $year, $month) {
        $count = 0;
        $date = Carbon::create($year, $month, 1);
    
        while ($date->month == $month) {
            if ($date->dayOfWeek == $diaSemana) {
                $count++;
            }
            $date->addDay();
        }
    
        return $count;
    }

    /**
     * 2. Identificación de Variaciones en Ingresos/Gastos
     *Para detectar desviaciones entre lo proyectado y lo histórico, podemos:
     *
     *Calcular la media móvil de los últimos N meses.
     *
     *Comparar los ingresos/gastos proyectados con esta media.
     *
     *Detectar desviaciones significativas (por ejemplo, si un mes proyectado está un X% fuera del rango esperado).
     *
     * ¿Qué Logramos?
     * Detectamos anomalías en ingresos/gastos proyectados en comparación con datos históricos.
     * Aplicamos una media móvil para evaluar si los valores proyectados son normales o atípicos.
     */
    public function detectarVariaciones(Request $request) {
        $mesesHistoricos = $request->input('historico', 6); // Últimos 6 meses por defecto
        $mesesFuturos = $request->input('meses', 6); // Proyección a 6 meses
        $userId = $request->input('user_id');
    
        // Obtener ingresos reales de los últimos meses
        $historialIngresos = Ingreso::where('user_id', $userId)
            ->whereNull('flag_eliminado') // Ignorar eliminados
            ->whereBetween('created_at', [now()->subMonths($mesesHistoricos), now()])
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(total) as total')
            ->groupBy('year', 'month')
            ->get()
            ->pluck('total')
            ->toArray();
    
        // Obtener gastos reales de los últimos meses
        $historialGastos = Gasto::where('user_id', $userId)
            ->whereNull('flag_eliminado')
            ->whereBetween('created_at', [now()->subMonths($mesesHistoricos), now()])
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(total) as total')
            ->groupBy('year', 'month')
            ->get()
            ->pluck('total')
            ->toArray();

        //return response()->json($historialIngresos);

        // $historialIngresos = [1000, 1200, 1300, 1500, 1600]; // Datos históricos de ingresos
        // $historialGastos = [800, 900, 950, 1000, 1100]; // Datos históricos de gastos

        // 1. Regresión Lineal para Predicción
        // $ingresosFuturos = $this->regresionLineal($historialIngresos);
        // $gastosFuturos = $this->regresionLineal($historialGastos);

        // 2. Suavizado Exponencial
        // $ingresosSuavizados = $this->suavizadoExponencial($historialIngresos);
        // $gastosSuavizados = $this->suavizadoExponencial($historialGastos);

        // 3. Media Móvil Ponderada
        // $pesos = [0.1, 0.2, 0.3, 0.4]; // Más peso a los valores recientes
        // $pesos = [0.5, 0.3, 0.2]; // Pesos para los últimos 3 meses
        // $ingresosPonderados = $this->mediaMovilPonderada($historialIngresos, $pesos);
        // $gastosPonderados = $this->mediaMovilPonderada($historialGastos, $pesos);
    
        // Calcular media móvil
        $mediaIngresos = count($historialIngresos) ? array_sum($historialIngresos) / count($historialIngresos) : 0;
        $mediaGastos = count($historialGastos) ? array_sum($historialGastos) / count($historialGastos) : 0;
    
        // Obtener proyección de ingresos y gastos
        $proyeccion = $this->proyectarFlujoCaja($request)->getData();
    
        // Detectar desviaciones en la proyección
        $variaciones = [];
        foreach ($proyeccion as $mesProyectado) {
            $desviacionIngresos = (($mesProyectado->ingresos - $mediaIngresos) / max($mediaIngresos, 1)) * 100;
            $desviacionGastos = (($mesProyectado->gastos - $mediaGastos) / max($mediaGastos, 1)) * 100;
    
            $variaciones[] = [
                'mes' => $mesProyectado->mes,
                'ingresos_proyectados' => $mesProyectado->ingresos,
                'gastos_proyectados' => $mesProyectado->gastos,
                'desviacion_ingresos' => round($desviacionIngresos, 2),
                'desviacion_gastos' => round($desviacionGastos, 2),
            ];
        }
    
        return response()->json($variaciones);
    }

    /*

    mes → Indica el mes proyectado (ejemplo: "April 2025").

    ingresos_proyectados → El monto esperado de ingresos para ese mes (1000).

    gastos_proyectados → El monto esperado de gastos para ese mes (200).

    desviacion_ingresos → Cuánto se desvían los ingresos proyectados respecto a la media histórica (150 significa que está por encima del promedio).

    desviacion_gastos → Cuánto se desvían los gastos proyectados respecto a la media histórica (-50 significa que está por debajo del promedio).

    ¿Cómo interpretar estos datos?
    Desviación de ingresos positiva (+) → Se esperan ingresos más altos que el promedio.
    Desviación de ingresos negativa (-) → Se esperan ingresos más bajos que el promedio.
    Desviación de gastos positiva (+) → Se esperan más gastos de lo habitual.
    Desviación de gastos negativa (-) → Se espera menos gasto de lo normal.

     */

    //----- Inicio:  Algoritmos simples para predecir tendencias futuras. -----//

    // Como usarlos algoritmos:

    // $historialIngresos = [1000, 1200, 1300, 1500, 1600]; // Datos históricos de ingresos
    // $historialGastos = [800, 900, 950, 1000, 1100]; // Datos históricos de gastos

    // 1. Regresión Lineal para Predicción
    // $ingresosFuturos = $this->regresionLineal($historialIngresos);
    // $gastosFuturos = $this->regresionLineal($historialGastos);

    // 2. Suavizado Exponencial
    // $ingresosSuavizados = $this->suavizadoExponencial($historialIngresos);
    // $gastosSuavizados = $this->suavizadoExponencial($historialGastos);

    // 3. Media Móvil Ponderada
    // $pesos = [0.1, 0.2, 0.3, 0.4]; // Más peso a los valores recientes
    // $ingresosPonderados = $this->mediaMovilPonderada($historialIngresos, $pesos);
    // $gastosPonderados = $this->mediaMovilPonderada($historialGastos, $pesos);
    
    // $mediaMovilIngresos = $this->mediaMovilPonderada($historialIngresos, [0.5, 0.3, 0.2]); // Pesos para los últimos 3 meses
    // $mediaMovilGastos = $this->mediaMovilPonderada($historialGastos, [0.5, 0.3, 0.2]); // Pesos para los últimos 3 meses

    

    /**
     * 1. Regresión Lineal para Predicción
     * ¿Qué hace?
     *
     *Encuentra una línea que mejor se ajuste a los datos históricos.
     *
     *Nos permite predecir ingresos/gastos futuros basándose en la tendencia pasada.
     *
     *¿Qué logramos?
     *Si los ingresos están creciendo a un ritmo constante, podemos predecir el valor del próximo mes.
     *
     */

    public function regresionLineal($datos) {
        $n = count($datos);
        if ($n < 2) return null; // Necesitamos al menos 2 puntos
    
        $sumX = $sumY = $sumXY = $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $datos[$i];
            $sumXY += $i * $datos[$i];
            $sumX2 += $i * $i;
        }
    
        // Calculamos pendiente (m) y punto de intersección (b)
        $m = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $b = ($sumY - $m * $sumX) / $n;
    
        // Predicción del siguiente valor
        $proximo = $m * $n + $b;
        
        return $proximo;
    }

    /**
     * 2. Suavizado Exponencial
     * ¿Qué hace?
     *
     *En lugar de tomar un simple promedio, da más peso a los datos recientes.
     *
     *Es útil cuando queremos reaccionar rápido a cambios recientes.
     *
     * ¿Qué logramos?
     *Si un mes hay un gasto inesperado alto, este método ajusta la tendencia sin ser demasiado drástico.
     */

    public function suavizadoExponencial($data, $alpha = 0.3) {
        $resultado = [];
        $prev = $data[0] ?? 0; // Primera observación
    
        foreach ($data as $valor) {
            $suavizado = ($alpha * $valor) + ((1 - $alpha) * $prev);
            $resultado[] = $suavizado;
            $prev = $suavizado;
        }
    
        return $resultado;
    }

    /**
     * 3. Media Móvil Ponderada
     * ¿Qué hace?
     *
     *En lugar de promediar todos los datos por igual, da más peso a los valores recientes.
     *
     *Es útil para ver tendencias sin distorsionar por datos muy antiguos.sin ser demasiado drástico.
     *    
     *¿Qué logramos?
     *Si queremos suavizar los datos pero aún dar importancia a los últimos valores, esta técnica es ideal.
     */

    public function mediaMovilPonderada($data, $pesos) {

        $resultado = [];
        $n = count($pesos);
    
        for ($i = $n - 1; $i < count($data); $i++) {
            $sumaPonderada = 0;
            $sumaPesos = 0;
            
            for ($j = 0; $j < $n; $j++) {
                $sumaPonderada += $data[$i - $j] * $pesos[$j];
                $sumaPesos += $pesos[$j];
            }
    
            $resultado[] = $sumaPonderada / $sumaPesos;
        }
    
        return $resultado;
    }


    // Aplicamos Regresión Lineal para proyectar ingresos/gastos futuros.
    // Implementamos Suavizado Exponencial para detectar cambios recientes sin reaccionar demasiado.
    // Usamos Media Móvil Ponderada para tendencias sin perder sensibilidad.

    //----- Fin:  Algoritmos simples para predecir tendencias futuras. -----//

    /*
    ¿Qué sigue en el frontend (Angular)?

    Mostrar un gráfico de flujo de caja 📊 con Chart.js o ngx-charts.

    Agregar alertas en meses con saldo negativo 🚨.

    Filtrar por tipo de gasto/ingreso para análisis detallado.

    -----------------

    Probar la función con datos reales para verificar si las variaciones detectadas son correctas.
    Ajustar los valores de alerta (ejemplo: marcar desviaciones superiores al 20% como "riesgosas").
    ¿Quieres agregar gráficos en Angular para visualizar las desviaciones? 

    Ajustar los valores de "alerta" (por ejemplo, una desviación mayor al 20% es preocupante).

    -----------------

    Probar con datos reales para evaluar si las predicciones son acertadas.

     */

     
    /**
     * Evaluación de Riesgos
     */

    public function evaluarRiesgo(Request $request)
    {

        $userId = $request->input('user_id');
        $mes = $request->input('mes', date('m'));
        $anio = $request->input('anio', date('Y'));

         // Obtener ingresos y gastos reales del mes para el usuario
         $ingresosReales = Ingreso::where('user_id', $userId)
             ->whereMonth('created_at', $mes)
             ->whereYear('created_at', $anio)
             ->whereNull('flag_eliminado') // Ignorar eliminados
             ->sum('total');

        $total_facturado = 0;
        $usuario = User::with('cfdi_empresa')->find($userId);
        if($usuario->cfdi_empresa){
            $total_facturado = CfdiComprobante::
                //where(DB::raw('DAY(created_at)'),$dia_actual)
                where(DB::raw('MONTH(created_at)'),$mes)
                ->where(DB::raw('YEAR(created_at)'),$anio)
                // ->where('emisor_id',$usuario->cfdi_empresa->id)
                ->where('user_id',$userId)
                ->where(function ($query) {
                    $query
                        ->where('status',1)
                        /*->orWhere('status',2)*/;
                })
                ->sum('Total');
        }

        $ingresosReales += $total_facturado;
     
         $gastosReales = Gasto::where('user_id', $userId)
             ->whereMonth('created_at', $mes)
             ->whereYear('created_at', $anio)
             ->whereNull('flag_eliminado') // Ignorar eliminados
             ->sum('total');
     
         // Obtener ingresos recurrentes proyectados para el mes            
         $ingresosProyectados = IngresoRecurrente::where('user_id', $userId)
            ->where('status', 1) // Solo los activos
            ->whereHas('ingreso', function ($query) {
                $query->whereNull('flag_eliminado'); // Ignorar ingresos eliminados
            })
            ->with('ingreso') // Carga la relación con Ingreso
            ->get()
            ->sum(function ($ingreso) use ($mes, $anio) {
                return $this->calcularProyeccionRecurrente(
                    $ingreso->frecuencia, 
                    $ingreso->fecha, 
                    $ingreso->dia_semana, 
                    $ingreso->dia_mes, 
                    $mes, 
                    $anio, 
                    $ingreso->ingreso->total ?? 0
                );
            });
     
         // Obtener gastos recurrentes proyectados para el mes
         $gastosProyectados = GastoRecurrente::where('user_id', $userId)
            ->where('status', 1) // Solo los activos
            ->whereHas('gasto', function ($query) {
                $query->whereNull('flag_eliminado'); // Ignorar gastos eliminados
            })
            ->with('gasto') // Carga la relación con Gasto
            ->get()
            ->sum(function ($gasto) use ($mes, $anio) {
                return $this->calcularProyeccionRecurrente(
                    $gasto->frecuencia, 
                    $gasto->fecha, 
                    $gasto->dia_semana, 
                    $gasto->dia_mes, 
                    $mes, 
                    $anio, 
                    $gasto->gasto->total ?? 0
                );
            });
     
         // Evaluar si hay riesgo financiero
         $riesgo = $gastosReales > $ingresosReales;
         $desviacionIngresos = $ingresosReales - $ingresosProyectados;
         $desviacionGastos = $gastosReales - $gastosProyectados;
     
         return [
             'riesgo' => $riesgo,
             'ingresos_reales' => $ingresosReales,
             'gastos_reales' => $gastosReales,
             'ingresos_proyectados' => $ingresosProyectados,
             'gastos_proyectados' => $gastosProyectados,
             'desviacion_ingresos' => $desviacionIngresos,
             'desviacion_gastos' => $desviacionGastos
         ];
    }
     
    private function calcularProyeccionRecurrente($frecuencia, $fecha, $diaSemana, $diaMes, $mes, $anio, $total)
    {
         $repeticiones = 0;
         $fechaDate = \Carbon\Carbon::parse($fecha);
         $diasEnMes = \Carbon\Carbon::create($anio, $mes, 1)->daysInMonth;
     
         switch ($frecuencia) {
             case 1: // Una vez
                 if ($fechaDate->month == $mes && $fechaDate->year == $anio) {
                     $repeticiones = 1;
                 }
                 break;
     
             case 2: // Semanal
                 for ($dia = 1; $dia <= $diasEnMes; $dia++) {
                     if (\Carbon\Carbon::create($anio, $mes, $dia)->dayOfWeek == $diaSemana) {
                         $repeticiones++;
                     }
                 }
                 break;
     
             case 3: // Mensual
                 if ($diaMes <= $diasEnMes) {
                     $repeticiones = 1;
                 }
                 break;
         }
     
         return $repeticiones * $total;
    }

    public function historial(Request $request)
    {
        $mesesHistoricos = $request->input('historico', 6); // Últimos 6 meses por defecto
        $userId = $request->input('user_id');
        
        // Obtener ingresos reales de los últimos meses
        $historialIngresos = Ingreso::where('user_id', $userId)
            ->whereNull('flag_eliminado') // Ignorar eliminados
            ->whereBetween('created_at', [now()->subMonths($mesesHistoricos), now()])
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(total) as total')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        // Obtener gastos reales de los últimos meses
        $historialGastos = Gasto::where('user_id', $userId)
            ->whereNull('flag_eliminado')
            ->whereBetween('created_at', [now()->subMonths($mesesHistoricos), now()])
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(total) as total')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();
        
        // Formatear resultados
        $ingresosArray = [];
        foreach ($historialIngresos as $item) {
            $fecha = Carbon::create($item->year, $item->month, 1);
            $ingresosArray[] = [
                'mes' => $fecha->format('F Y'),
                'ingresos' => $item->total
            ];
        }
        
        $gastosArray = [];
        foreach ($historialGastos as $item) {
            $fecha = Carbon::create($item->year, $item->month, 1);
            $gastosArray[] = [
                'mes' => $fecha->format('F Y'),
                'gastos' => $item->total
            ];
        }
        
        return response()->json([
            'ingresos' => $ingresosArray,
            'gastos' => $gastosArray,
        ]);
    }

    /**
     * resumen global (ingresos, gastos, facturación por cliente)
     */
    public function resumen(Request $request)
    {
        $userId = $request->input('user_id');
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth());

        // $startDate = Carbon::parse($request->input('start_date', Carbon::now()->startOfMonth()));
        // $endDate = Carbon::parse($request->input('end_date', Carbon::now()->endOfMonth()));

        // $startDate = date_create_from_format('Y-m-d' , $request->input('start_date'));
        // $endDate = date_create_from_format('Y-m-d' , $request->input('end_date'));

        // Total de ingresos
        $ingresos = Ingreso::whereNull('flag_eliminado')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('user_id', $userId)
            ->sum('total');

        // Total de ingresos contables
        $ingresosContables = Ingreso::whereNull('flag_eliminado')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('user_id', $userId)
            ->where('tipo_id', 1)
            ->sum('total');

            // Total de ingresos contables
        $ingresosNoContables = Ingreso::whereNull('flag_eliminado')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->where('user_id', $userId)
        ->where('tipo_id', 2)
        ->sum('total');

        // Total de gastos contables (gastos con categoría válida no eliminada)
        $gastos = Gasto::whereNull('flag_eliminado')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->where('user_id', $userId)
        ->sum('total');

        // Facturación total por cliente
        $facturacion = CfdiComprobante::
            select('cfdi_receptor.Rfc', 'cfdi_receptor.Nombre', DB::raw('SUM(cfdi_comprobante.Total) as total'))
            ->join('cfdi_receptor', 'cfdi_receptor.comprobante_id', '=', 'cfdi_comprobante.id')
            ->where('cfdi_comprobante.status', 1)
            ->where('cfdi_comprobante.user_id', $userId)
            ->whereBetween('cfdi_comprobante.created_at', [$startDate, $endDate])
            ->groupBy('cfdi_receptor.Rfc', 'cfdi_receptor.Nombre')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) {
                return [
                    'cliente' => $item->Nombre ?? 'Desconocido',
                    'total' => round($item->total, 2),
                ];
            });

        return response()->json([
            'ingresos' => $ingresos,
            'ingresos_contables' => $ingresosContables,
            'ingresos_nocontables' => $ingresosNoContables,
            'gastos' => $gastos,
            'facturacion' => $facturacion,
        ]);
    }

    /**
     * Top categorías de gasto → top N categoria_id con mayor total
     * Distribución de ingresos por cliente → % de ingresos por cliente respecto al total
     */
    public function indicadores(Request $request)
    {
        $userId = $request->input('user_id');
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth());

        // Top 5 categorías de gasto
        $topCategorias = Gasto::select('tipo_id', DB::raw('SUM(total) as total'))
            ->whereNull('flag_eliminado')
            ->whereBetween('created_at', [$startDate, $endDate])
            // ->whereHas('tipo', function ($query) {
            //     $query->whereNull('flag_eliminado');
            // })
            ->where('user_id', $userId)
            ->groupBy('tipo_id')
            ->with(['tipo:id,clave'])
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'categoria' => $item->tipo->clave ?? 'Sin categoría',
                    'total' => round($item->total, 2),
                ];
            });

        // Distribución de ingresos por cliente
        $distribucion = CfdiComprobante::select(
                'cfdi_receptor.Rfc',
                'cfdi_receptor.Nombre',
                DB::raw('SUM(cfdi_comprobante.Total) as total')
            )
            ->join('cfdi_receptor', 'cfdi_receptor.comprobante_id', '=', 'cfdi_comprobante.id')
            ->where('cfdi_comprobante.status', 1)
            ->where('cfdi_comprobante.user_id', $userId)
            ->whereBetween('cfdi_comprobante.created_at', [$startDate, $endDate])
            ->groupBy('cfdi_receptor.Rfc', 'cfdi_receptor.Nombre')
            ->get();

        $totalGeneral = $distribucion->sum('total');

        $distribucionClientes = $distribucion->map(function ($item) use ($totalGeneral) {
            return [
                'cliente' => $item->Nombre ?? 'Desconocido',
                'total' => round($item->total, 2),
                'porcentaje' => $totalGeneral > 0 ? round(($item->total / $totalGeneral) * 100, 2) : 0,
            ];
        });

        return response()->json([
            'top_gastos' => $topCategorias,
            'distribucion_ingresos' => $distribucionClientes,
        ]);
    }

    /**
     * Comparación entre periodos → comparar mes actual con anterior
     */
    // public function comparativa(Request $request)
    // {
    //     $userId = $request->input('user_id');

    //     $mesActualInicio = Carbon::now()->startOfMonth();
    //     $mesActualFin = Carbon::now()->endOfMonth();

    //     $mesAnteriorInicio = Carbon::now()->subMonth()->startOfMonth();
    //     $mesAnteriorFin = Carbon::now()->subMonth()->endOfMonth();

    //     $ingresosActual = Ingreso::whereNull('flag_eliminado')
    //         ->whereBetween('created_at', [$mesActualInicio, $mesActualFin])
    //         ->where('user_id', $userId)
    //         ->sum('total');

    //     $ingresosAnterior = Ingreso::whereNull('flag_eliminado')
    //         ->whereBetween('created_at', [$mesAnteriorInicio, $mesAnteriorFin])
    //         ->where('user_id', $userId)
    //         ->sum('total');

    //     $variacionIngresos = $ingresosAnterior > 0
    //         ? round((($ingresosActual - $ingresosAnterior) / $ingresosAnterior) * 100, 2)
    //         : null;

    //     $gastosActual = Gasto::whereNull('flag_eliminado')
    //         ->whereBetween('created_at', [$mesActualInicio, $mesActualFin])
    //         ->where('user_id', $userId)
    //         ->sum('total');

    //     $gastosAnterior = Gasto::whereNull('flag_eliminado')
    //         ->whereBetween('created_at', [$mesAnteriorInicio, $mesAnteriorFin])
    //         ->where('user_id', $userId)
    //         ->sum('total');

    //     $variacionGastos = $gastosAnterior > 0
    //         ? round((($gastosActual - $gastosAnterior) / $gastosAnterior) * 100, 2)
    //         : null;

    //     $emisor = CfdiEmpresa::
    //         where('user_id', $userId)
    //         ->first();

    //     if (!$emisor)
    //     {

    //         $facturasActual = 0;
    //         $facturasAnterior = 0;

    //     }else{

    //         $facturasActual = CfdiComprobante::where('status', 1)
    //             ->where('emisor_id', $emisor->id)
    //             ->whereBetween('created_at', [$mesActualInicio, $mesActualFin])
    //             ->sum('Total');

    //         $facturasAnterior = CfdiComprobante::where('status', 1)
    //             ->where('emisor_id', $emisor->id)
    //             ->whereBetween('created_at', [$mesAnteriorInicio, $mesAnteriorFin])
    //             ->sum('Total');

    //     }

    //     $variacionFacturas = $facturasAnterior > 0
    //         ? round((($facturasActual - $facturasAnterior) / $facturasAnterior) * 100, 2)
    //         : null;

    //     return response()->json([
    //         'mes_actual_ingresos' => $ingresosActual,
    //         'mes_anterior_ingresos' => $ingresosAnterior,
    //         'variacion_porcentual_ingresos' => $variacionIngresos,
    //         'mes_actual_gastos' => $gastosActual,
    //         'mes_anterior_gastos' => $gastosAnterior,
    //         'variacion_porcentual_gastos' => $variacionGastos,
    //         'mes_actual_facturas' => $facturasActual,
    //         'mes_anterior_facturas' => $facturasAnterior,
    //         'variacion_porcentual_facturas' => $variacionFacturas,
    //     ]);
    // }


    public function comparativa(Request $request)
    {
        $userId = $request->input('user_id');
        $mesA = $request->input('mes_a'); // formato YYYY-MM
        $mesB = $request->input('mes_b'); // formato YYYY-MM

        // Si no se envían, usar mes actual y anterior
        $mesAFecha = $mesA ? Carbon::createFromFormat('Y-m', $mesA) : Carbon::now()->startOfMonth();
        $mesBFecha = $mesB ? Carbon::createFromFormat('Y-m', $mesB) : Carbon::now()->subMonth()->startOfMonth();

        $aInicio = $mesAFecha->copy()->startOfMonth();
        $aFin = $mesAFecha->copy()->endOfMonth();
        $bInicio = $mesBFecha->copy()->startOfMonth();
        $bFin = $mesBFecha->copy()->endOfMonth();

        // Ingresos
        $ingresosA = Ingreso::whereNull('flag_eliminado')
            ->whereBetween('created_at', [$aInicio, $aFin])
            ->where('user_id', $userId)
            ->sum('total');

        $ingresosB = Ingreso::whereNull('flag_eliminado')
            ->whereBetween('created_at', [$bInicio, $bFin])
            ->where('user_id', $userId)
            ->sum('total');

        $variacionIngresos = $ingresosB > 0
            ? round((($ingresosA - $ingresosB) / $ingresosB) * 100, 2)
            : null;

        // Gastos
        $gastosA = Gasto::whereNull('flag_eliminado')
            ->whereBetween('created_at', [$aInicio, $aFin])
            ->where('user_id', $userId)
            ->sum('total');

        $gastosB = Gasto::whereNull('flag_eliminado')
            ->whereBetween('created_at', [$bInicio, $bFin])
            ->where('user_id', $userId)
            ->sum('total');

        $variacionGastos = $gastosB > 0
            ? round((($gastosA - $gastosB) / $gastosB) * 100, 2)
            : null;

        // Facturación
        $facturasA = CfdiComprobante::where('status', 1)
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$aInicio, $aFin])
            ->sum('Total');

        $facturasB = CfdiComprobante::where('status', 1)
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$bInicio, $bFin])
            ->sum('Total');

        $variacionFacturas = $facturasB > 0
            ? round((($facturasA - $facturasB) / $facturasB) * 100, 2)
            : null;

        return response()->json([
            'mes_a' => $mesAFecha->format('F Y'),
            'mes_b' => $mesBFecha->format('F Y'),
            'ingresos_a' => $ingresosA,
            'ingresos_b' => $ingresosB,
            'variacion_porcentual_ingresos' => $variacionIngresos,
            'gastos_a' => $gastosA,
            'gastos_b' => $gastosB,
            'variacion_porcentual_gastos' => $variacionGastos,
            'facturas_a' => $facturasA,
            'facturas_b' => $facturasB,
            'variacion_porcentual_facturas' => $variacionFacturas,
        ]);
    }


    public function statusFacturas(Request $request)
    {
        $user_id = $request->input('user_id');

        $anio = $request->input('anio');
        $mes = $request->input('mes');
        //$dia = $request->input('dia');

        if($mes >= 1 && $mes <= 9){
            $mes = '0'.$mes;
        }

        // if($dia >= 1 && $dia <= 9){
        //     $dia = '0'.$dia;
        // }

        //$fecha = $anio.'-'.$mes.'-'.$dia;
        $fecha = $anio.'-'.$mes.'-';

        //facturas pagadas
        $pagadas = CfdiComprobante::
            where('user_id',$user_id)
            ->where('status', 1)
            ->where('Fecha', 'like', '%'.$fecha.'%')
            ->where('status_pay', 1)
            ->count();

        //facturas por_pagar
        $por_pagar = CfdiComprobante::
            where('user_id',$user_id)
            ->where('status', 1)
            ->where('Fecha', 'like', '%'.$fecha.'%')
            ->where('status_pay', 0)
            ->count();

        //facturas canceladas
        $canceladas = CfdiComprobante::
            where('user_id',$user_id)
            ->where('status', 2)
            ->where('Fecha', 'like', '%'.$fecha.'%')
            ->count();

        return response()->json([
            'pagadas'=>$pagadas,
            'por_pagar'=>$por_pagar,
            'canceladas'=>$canceladas
        ], 200);
        
    }

    
}
