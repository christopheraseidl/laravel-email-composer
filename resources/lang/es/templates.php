<?php

return [
    'label' => 'plantilla de correo',
    'plural_label' => 'plantillas de correo',
    'fields' => [
        'name' => 'Nombre',
        'key' => 'Clave',
        'body' => 'Cuerpo',
        'placeholders' => 'Marcadores',
        'css' => 'CSS',
    ],
    'columns' => [
        'name' => 'Nombre',
        'key' => 'Clave',
        'updated_at' => 'Fecha de actualización',
    ],
    'helpers' => [
        'body' => 'Use marcadores [[ placeholder_name ]] donde deba insertarse el contenido del borrador. Declare cada marcador que use en el campo Marcadores, más abajo.',
        'placeholders' => 'La lista de marcadores [[ placeholder_name ]] usados en el cuerpo. El editor de borradores mostrará un campo por marcador y por idioma.',
        'placeholders_example' => 'Añadir un nombre de marcador (p. ej., greeting)',
    ],
];
