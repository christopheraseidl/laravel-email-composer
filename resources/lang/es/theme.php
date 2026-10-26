<?php

return [
    'title' => 'Gestionar tema de correo',
    'navigation_label' => 'Gestionar tema de correo',
    'fields' => [
        'logo_url' => 'URL del logotipo',
        'logo_path' => 'Archivo del logotipo',
        'logo_alt' => 'Texto alternativo del logotipo',
        'css' => 'CSS',
    ],
    'actions' => [
        'save' => 'Guardar',
    ],
    'notifications' => [
        'saved' => 'Guardado',
    ],
    'helpers' => [
        'logo_url' => 'Una URL absoluta. Los clientes de correo no pueden cargar rutas relativas. Bórrela para subir un archivo en su lugar.',
        'logo_path' => 'Quite el archivo subido para introducir una URL en su lugar.',
    ],
];
