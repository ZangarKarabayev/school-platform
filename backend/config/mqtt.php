<?php

return [
    'host' => env('MQTT_HOST'),
    'port' => env('MQTT_PORT', 1883),
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),
    'client_id' => env('MQTT_CLIENT_ID', 'altyn-as-server'),
    'server_ip' => env('MQTT_SERVER_IP'),
];
