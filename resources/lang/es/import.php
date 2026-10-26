<?php

return [
    'fields' => [
        'file' => 'Archivo CSV',
        'list' => 'Añadir a la lista',
    ],
    'actions' => [
        'import' => 'Importar',
    ],
    'notifications' => [
        'queued' => [
            'title' => 'Importación en cola',
            'body' => 'Recibirá una notificación cuando termine.',
        ],
        'imported' => 'Destinatarios importados',
        'partly_failed' => 'Importación fallida en parte',
        'failed' => 'Importación fallida',
        'totals' => 'Creados: :created, actualizados: :updated, omitidos: :skipped, rechazados: :rejected.',
        'parts_failed' => 'Fallaron :failed de :parts partes. Es posible que algunas de sus filas se hayan importado.',
        'rejected_line' => 'Línea :line: :reason',
        'missing_column' => 'El archivo no tiene una columna :column.',
        'missing_list' => 'La lista de destinatarios ya no existe.',
        'stopped' => 'La importación se detuvo por un error antes de importar ninguna fila.',
    ],
    'helpers' => [
        'file' => 'Una fila de encabezado con una columna email y, opcionalmente, columnas name, locale y de metadatos. Hasta :size KB.',
    ],
];
