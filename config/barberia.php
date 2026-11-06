<?php

return [
    // Horario normal del local (lo que se ve "limpio" en la agenda)
    'open_hour'  => 10,   // 10:00 AM
    'close_hour' => 19,   // 7:00 PM

    // Rango máximo en que se puede agendar (incluye fuera del horario normal)
    'day_start'  => 6,    // más temprano: 6:00 AM
    'day_end'    => 23,   // las citas deben terminar a más tardar a las 11:00 PM (máximo 24)
    // true = no se puede cobrar por SINPE sin adjuntar el comprobante
    'require_sinpe_proof' => false,

        // Respaldos
    'backup_dir'           => env('BACKUP_DIR'),
    'backup_password'      => env('BACKUP_PASSWORD'),
    'mysqldump'            => env('MYSQLDUMP_PATH'),
    'dump_no_tablespaces'  => env('DUMP_NO_TABLESPACES', true),
];