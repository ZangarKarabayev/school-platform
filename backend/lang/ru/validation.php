<?php

return [
    'accepted' => 'Поле :attribute должно быть принято.',
    'confirmed' => 'Поле :attribute не совпадает с подтверждением.',
    'digits' => 'Поле :attribute должно содержать :digits цифр.',
    'email' => 'Поле :attribute должно быть действительным электронным адресом.',
    'integer' => 'Поле :attribute должно быть целым числом.',
    'max' => [
        'string' => 'Поле :attribute не должно превышать :max символов.',
    ],
    'min' => [
        'string' => 'Поле :attribute должно содержать не менее :min символов.',
    ],
    'numeric' => 'Поле :attribute должно быть числом.',
    'regex' => 'Поле :attribute имеет неверный формат.',
    'required' => 'Поле :attribute обязательно для заполнения.',
    'string' => 'Поле :attribute должно быть строкой.',
    'unique' => 'Поле :attribute уже используется.',

    'custom' => [
        'first_name' => [
            'required' => 'Укажите имя.',
        ],
        'last_name' => [
            'required' => 'Укажите фамилию.',
        ],
        'password' => [
            'required' => 'Укажите пароль.',
            'min' => 'Пароль должен содержать не менее :min символов.',
            'confirmed' => 'Пароль и подтверждение не совпадают.',
        ],
        'phone' => [
            'required' => 'Укажите телефон.',
            'regex' => 'Укажите телефон в корректном формате.',
            'unique' => 'Этот телефон уже зарегистрирован.',
        ],
        'signature' => [
            'required' => 'Сначала выберите ЭЦП и подпишите challenge.',
        ],
    ],

    'attributes' => [
        'challenge_id' => 'идентификатор challenge',
        'code' => 'код подтверждения',
        'device_name' => 'имя устройства',
        'first_name' => 'имя',
        'last_name' => 'фамилия',
        'middle_name' => 'отчество',
        'password' => 'пароль',
        'password_confirmation' => 'подтверждение пароля',
        'phone' => 'телефон',
        'signature' => 'подпись ЭЦП',
    ],
];
