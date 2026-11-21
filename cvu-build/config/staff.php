<?php

return [
    'admin' => [
        'name' => env(
            'ADMIN_NAME',
            'Administrator'
        ),

        'username' => env(
            'ADMIN_USERNAME',
            'admin'
        ),

        'email' => env(
            'ADMIN_EMAIL',
            'admin@iserve.local'
        ),

        'password' => env(
            'ADMIN_PASSWORD',
            'admin123'
        ),

        'role' => 'admin',
    ],

    'sfu_head' => [
        'name' => env(
            'SFU_HEAD_NAME',
            'SFU Head'
        ),

        'username' => env(
            'SFU_HEAD_USERNAME',
            'sfuhead'
        ),

        'email' => env(
            'SFU_HEAD_EMAIL',
            'sfuhead@iserve.local'
        ),

        'password' => env(
            'SFU_HEAD_PASSWORD',
            'sfuhead123'
        ),

        'role' => 'sfu_head',
    ],

    'cvu_head' => [
        'name' => env(
            'CVU_HEAD_NAME',
            'CVU Head'
        ),

        'username' => env(
            'CVU_HEAD_USERNAME',
            'cvuhead'
        ),

        'email' => env(
            'CVU_HEAD_EMAIL',
            'cvuhead@iserve.local'
        ),

        'password' => env(
            'CVU_HEAD_PASSWORD',
            'cvuhead123'
        ),

        'role' => 'cvu_head',
    ],
];