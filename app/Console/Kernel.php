<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    //Configurar el cron en el servidor
    //* * * * * cd /ruta/a/tu/proyecto && php artisan schedule:run >> /dev/null 2>&1

    // /usr/local/bin/ea-php81 /home/internowcom/apicontafacil.internow.com.mx/artisan schedule:run >> /dev/null 2>&1

    //probar que el comando funciona
    //php artisan alertas:calendario-fiscal

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\EnviarAlertasCalendarioFiscal::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();

        // Ejecutar el comando todos los días a las 8:00 AM
        $schedule->command('alertas:calendario-fiscal')->dailyAt('08:00');
        // $schedule->command('alertas:calendario-fiscal')->everyMinute(); //cada minuto (solo para pruebas)
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
