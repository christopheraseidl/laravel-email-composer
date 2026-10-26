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
        'restore_default' => 'Restaurar CSS predeterminado',
    ],
    'notifications' => [
        'saved' => 'Guardado',
        'default_restored' => 'CSS predeterminado restaurado',
    ],
    'restore_default' => [
        'heading' => '¿Restaurar el CSS predeterminado?',
        'description' => 'Esto reemplaza el CSS del tema por el predeterminado del paquete. El logotipo se conserva.',
    ],
    'helpers' => [
        'logo_url' => 'Una URL absoluta. Los clientes de correo no pueden cargar rutas relativas. Bórrela para subir un archivo en su lugar.',
        'logo_path' => 'Quite el archivo subido para introducir una URL en su lugar.',
    ],
];
