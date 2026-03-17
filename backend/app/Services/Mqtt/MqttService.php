<?php

namespace App\Services\Mqtt;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class MqttService
{
    protected function connect(string $clientSuffix): MqttClient
    {
        $mqtt = new MqttClient(
            config('mqtt.host'),
            config('mqtt.port'),
            config('mqtt.client_id') . '_' . $clientSuffix,
            MqttClient::MQTT_3_1_1
        );

        $settings = (new ConnectionSettings)
            ->setKeepAliveInterval(60);

        if (!empty(config('mqtt.username'))) {
            $settings = $settings->setUsername((string) config('mqtt.username'));
        }

        if (!empty(config('mqtt.password'))) {
            $settings = $settings->setPassword((string) config('mqtt.password'));
        }

        $mqtt->connect($settings, true);

        return $mqtt;
    }

    public function publish(string $topic, array $payload): void
    {
        $mqtt = $this->connect('publisher');

        $mqtt->publish(
            $topic,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            1
        );

        $mqtt->disconnect();
    }

    public function sendEditPerson(array $person, string $terminalId): void
    {
        $requestId = (string) Str::uuid();

        $mqtt = new MqttClient(
            config('mqtt.host'),
            config('mqtt.port'),
            config('mqtt.client_id') . '_publisher',
            MqttClient::MQTT_3_1_1
        );

        $settings = (new ConnectionSettings)->setKeepAliveInterval(60);

        if (!empty(config('mqtt.username'))) {
            $settings = $settings->setUsername((string) config('mqtt.username'));
        }

        if (!empty(config('mqtt.password'))) {
            $settings = $settings->setPassword((string) config('mqtt.password'));
        }

        $mqtt->connect($settings, true);

        $payload = [
            'requestId' => $requestId,
            'operator' => 'EditPerson',
            'info' => [
                'customId' => $person['custom_id'],
                'name' => $person['name'],
                'gender' => $person['gender'],
                'picURI' => $person['photo_url'],
                'address' => $person['bin'],
                'notes' => $person['unique_code'],
            ],
        ];

        Log::info('TERMINAL REQUEST SENT', [
            'request_id' => $requestId,
            'terminal_id' => $terminalId,
            'topic' => "mqtt/face/{$terminalId}",
            'payload' => $payload,
        ]);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        if ($json !== false) {
            $json = str_replace('\\/', '/', $json);
        }

        $mqtt->publish("mqtt/face/{$terminalId}", $json ?: '{}', 1);
        $mqtt->disconnect();
    }

    public function sendEditPersonFromVendorSpec(array $info, string $terminalId): string
    {
        $mqtt = $this->connect('edit_person');

        $messageId = sprintf(
            'ID:devicehost-%d:%d:%s',
            random_int(1000000000, 9999999999),
            random_int(1000000, 9999999),
            date('H:i')
        );

        $payload = [
            'messageId' => $messageId,
            'operator' => 'EditPerson',
            'info' => $info,
        ];

        Log::info('FACEID MQTT REQUEST', [
            'terminal_id' => $terminalId,
            'topic' => "mqtt/face/{$terminalId}",
            'message_id' => $messageId,
            'custom_id' => $info['customId'] ?? null,
            'operator' => 'EditPerson',
        ]);

        $mqtt->publish(
            "mqtt/face/{$terminalId}",
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            1
        );

        $mqtt->disconnect();

        return $messageId;
    }

    public function configureHttpVerifySubscription(string $terminalId, string $serverIp): string
    {
        $mqtt = $this->connect('http_subscribe');

        $payload = [
            'messageId' => $this->makeMessageId(),
            'operator' => 'UpHTTPconfig',
            'info' => [
                'ServerAddr' => $serverIp,
                'ServerPort' => '80',
                'Verify' => '4',
                'VerifyURL' => '/api/Subscribe/Verify',
                'Snap' => '0',
                'QRCode' => '0',
                'IDCard' => '0',
                'BeatInterval' => '600',
                'BeatURL' => '/api/Subscribe/Heartbeat',
                'TimedPush' => '0',
                'Auth' => '0',
                'UserName' => 'admin',
                'PassWord' => 'admin',
                'ResumefromBreakpoint' => '1',
            ],
        ];

        $mqtt->publish(
            "mqtt/face/{$terminalId}",
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            1
        );

        $mqtt->disconnect();

        return $payload['messageId'];
    }

    public function disablePhotos(string $terminalId): string
    {
        $mqtt = $this->connect('report');

        $payload = [
            'messageId' => $this->makeMessageId(),
            'operator' => 'SetMqttReport',
            'info' => [
                'UploadPic' => 0,
                'UploadSnap' => 0,
            ],
        ];

        $mqtt->publish(
            "mqtt/face/{$terminalId}",
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            1
        );

        $mqtt->disconnect();

        return $payload['messageId'];
    }

    public function configureSysTime(string $terminalId): string
    {
        $mqtt = $this->connect('systime');

        $time = Carbon::now('+05:00')->format('Y-n-j\\TH:i:s');

        $payload = [
            'messageId' => $this->makeMessageId(),
            'operator' => 'SetSysTime',
            'info' => [
                'SysTime' => $time,
            ],
        ];

        $mqtt->publish(
            "mqtt/face/{$terminalId}",
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            1
        );

        $mqtt->disconnect();

        return $payload['messageId'];
    }

    protected function makeMessageId(): string
    {
        return sprintf(
            'ID:devicehost-%d:%d:%s',
            random_int(1000000000, 9999999999),
            random_int(1000000, 9999999),
            now()->format('H:i')
        );
    }
}
