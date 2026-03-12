<?php

return [
    'accepted' => ':attribute өрісін қабылдау қажет.',
    'confirmed' => ':attribute растаумен сәйкес келмейді.',
    'digits' => ':attribute өрісі :digits цифрдан тұруы керек.',
    'email' => ':attribute жарамды электрондық пошта болуы керек.',
    'integer' => ':attribute бүтін сан болуы керек.',
    'max' => [
        'string' => ':attribute өрісі :max таңбадан аспауы керек.',
    ],
    'min' => [
        'string' => ':attribute өрісі кемінде :min таңбадан тұруы керек.',
    ],
    'numeric' => ':attribute сан болуы керек.',
    'regex' => ':attribute өрісінің форматы қате.',
    'required' => ':attribute өрісін толтыру міндетті.',
    'string' => ':attribute жол болуы керек.',
    'unique' => ':attribute бұрыннан қолданылып тұр.',

    'custom' => [
        'first_name' => [
            'required' => 'Атыңызды енгізіңіз.',
        ],
        'last_name' => [
            'required' => 'Тегіңізді енгізіңіз.',
        ],
        'password' => [
            'required' => 'Құпиясөзді енгізіңіз.',
            'min' => 'Құпиясөз кемінде :min таңбадан тұруы керек.',
            'confirmed' => 'Құпиясөз бен растау сәйкес емес.',
        ],
        'phone' => [
            'required' => 'Телефон нөмірін енгізіңіз.',
            'regex' => 'Телефон нөмірін дұрыс форматта енгізіңіз.',
            'unique' => 'Бұл телефон нөмірі бұрыннан тіркелген.',
        ],
        'signature' => [
            'required' => 'Алдымен ЭЦҚ таңдап, challenge-ке қол қойыңыз.',
        ],
    ],

    'attributes' => [
        'challenge_id' => 'challenge идентификаторы',
        'code' => 'растау коды',
        'device_name' => 'құрылғы атауы',
        'first_name' => 'аты',
        'last_name' => 'тегі',
        'middle_name' => 'әкесінің аты',
        'password' => 'құпиясөз',
        'password_confirmation' => 'құпиясөзді растау',
        'phone' => 'телефон',
        'signature' => 'ЭЦҚ қолтаңбасы',
    ],
];
