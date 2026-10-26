<?php

return [
    'label' => 'destinatario',
    'plural_label' => 'destinatarios',
    'fields' => [
        'name' => 'Nombre',
        'email' => 'Dirección de correo',
        'locale' => 'Idioma',
        'lists' => 'Listas',
        'metadata' => 'Metadatos',
        'unsubscribed_at' => 'Fecha de baja',
    ],
    'columns' => [
        'name' => 'Nombre',
        'email' => 'Dirección de correo',
        'locale' => 'Idioma',
        'unsubscribed_at' => 'Fecha de baja',
        'created_at' => 'Fecha de creación',
        'updated_at' => 'Fecha de actualización',
    ],
    'filters' => [
        'subscription' => [
            'label' => 'Suscripción',
            'all' => 'Todos',
            'unsubscribed' => 'Dados de baja',
            'subscribed' => 'Suscritos',
        ],
        'lists' => 'Listas',
    ],
];
