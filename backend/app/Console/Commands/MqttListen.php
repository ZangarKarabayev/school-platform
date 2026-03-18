<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\Terminal;
use App\Services\Mqtt\MqttService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class MqttListen extends Command
{
    protected $signature = 'mqtt:listen';

    protected $description = 'Listen MQTT ACK messages from face terminals';

    public function handle(): int
    {
        $this->info('MQTT listener started...');

        $mqtt = new MqttClient(
            config('mqtt.host'),
            (int) config('mqtt.port'),
            config('mqtt.client_id') . '_listener',
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

        $mqtt->subscribe('mqtt/face/+/Ack', function (string $topic, string $message): void {
            $raw = trim($message);
            $data = json_decode($raw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $normalized = preg_replace('/([a-zA-Z0-9_]+)\s*:/', '"$1":', $raw);
                $normalized = preg_replace('/:\s*([a-zA-Z0-9_-]+)/', ':"$1"', (string) $normalized);
                $data = json_decode((string) $normalized, true);
            }

            if (!is_array($data)) {
                Log::channel('faceid')->warning('TERMINAL RESPONSE INVALID JSON', [
                    'topic' => $topic,
                    'raw' => $raw,
                ]);
                return;
            }

            preg_match('#^mqtt/face/([^/]+)/Ack$#', $topic, $matches);
            $terminalId = $matches[1] ?? null;

            if (!$terminalId) {
                return;
            }

            $operator = (string) ($data['operator'] ?? '');
            $messageId = $data['messageId'] ?? ($data['info']['messageId'] ?? null);
            $code = (string) ($data['code'] ?? '');
            $result = (string) ($data['info']['result'] ?? '');
            $detail = (string) ($data['info']['detail'] ?? '');
            $customId = $data['info']['customId'] ?? null;

            Log::channel('faceid')->info('TERMINAL RESPONSE RECEIVED', [
                'terminal_id' => $terminalId,
                'topic' => $topic,
                'operator' => $operator,
                'message_id' => $messageId,
                'custom_id' => $customId,
                'code' => $code,
                'result' => $result,
                'detail' => $detail,
                'payload' => $data,
                'raw' => $raw,
            ]);

            if ($operator === 'EditPerson-Ack' && $code === '200' && $result === 'ok' && $customId) {
                Cache::forget("faceid:retry:{$terminalId}:{$customId}");

                $student = $this->resolveStudentForTerminalCustomId((string) $terminalId, (string) $customId);

                if ($student) {
                    $student->forceFill([
                        'photo_synced_at' => now(),
                    ])->save();
                }

                Log::channel('faceid')->info('TERMINAL ACK APPLIED', [
                    'terminal_id' => $terminalId,
                    'custom_id' => $customId,
                    'student_id' => $student?->id,
                    'message_id' => $messageId,
                ]);

                return;
            }

            if ($operator === 'EditPerson-Ack' && $code === '464') {
                Log::channel('faceid')->warning('TERMINAL ACK PHOTO FEATURE ERROR', [
                    'terminal_id' => $terminalId,
                    'topic' => $topic,
                    'custom_id' => $customId,
                    'message_id' => $messageId,
                    'code' => $code,
                    'result' => $result,
                    'detail' => $detail,
                    'payload' => $data,
                ]);

                return;
            }

            if ($operator === 'EditPerson-Ack' && $code === '477' && $customId) {
                $retryLock = "faceid:retry:{$terminalId}:{$customId}";

                if (!Cache::add($retryLock, 1, now()->addMinutes(15))) {
                    Log::channel('faceid')->warning('FACEID RETRY SKIPPED BY LOCK', [
                        'terminal_id' => $terminalId,
                        'custom_id' => $customId,
                    ]);

                    return;
                }

                $terminal = Terminal::query()
                    ->where('device_id', (int) $terminalId)
                    ->first();

                $customIdValue = trim((string) $customId);
                $student = $this->resolveStudentForTerminalCustomId((string) $terminalId, $customIdValue);

                if (!$student || !$student->photo || !Storage::disk('public')->exists($student->photo)) {
                    Log::channel('faceid')->warning('FACEID RETRY WITH SIMILARITY OFF SKIPPED', [
                        'terminal_id' => $terminalId,
                        'custom_id' => $customId,
                    ]);

                    return;
                }

                $picBase64 = base64_encode((string) file_get_contents(Storage::disk('public')->path($student->photo)));
                $birthdayValue = $student->birth_date
                    ? Carbon::parse($student->birth_date)->format('Y-m-d')
                    : '1940-01-01';

                $info = [
                    'personId' => '',
                    'customId' => (string) $student->id,
                    'name' => trim(implode(' ', array_filter([$student->last_name, $student->first_name]))),
                    'nation' => 1,
                    'gender' => ((string) $student->gender === 'female') ? 1 : 0,
                    'birthday' => $birthdayValue,
                    'address' => $student->school?->bin ?? '',
                    'idCard' => '',
                    'tempCardType' => 0,
                    'EffectNumber' => 3,
                    'cardValidBegin' => '2040-10-10 10:00:00',
                    'cardValidEnd' => '2040-10-10 16:00:00',
                    'telnum1' => '3636',
                    'PersonalPassword' => '321654',
                    'isCheckSimilarity' => 0,
                    'Native' => 'KZ',
                    'cardType2' => 0,
                    'cardNum2' => '',
                    'notes' => (string) $student->id,
                    'personType' => 0,
                    'cardType' => 0,
                    'dwidentity' => 0,
                    'pic' => $picBase64,
                ];

                $resendMessageId = app(MqttService::class)->sendEditPersonFromVendorSpec($info, (string) $terminalId);

                Log::channel('faceid')->warning('FACEID RETRY EDIT SENT WITH SIMILARITY OFF', [
                    'terminal_id' => $terminalId,
                    'custom_id' => $customId,
                    'student_id' => $student->id,
                    'resend_message_id' => $resendMessageId,
                ]);
            }
        }, 1);

        while (true) {
            $mqtt->loop(true);
        }
    }

    private function resolveStudentForTerminalCustomId(string $terminalId, string $customId): ?Student
    {
        $terminal = Terminal::query()
            ->where('device_id', (int) $terminalId)
            ->first();

        $customIdValue = trim($customId);

        return Student::query()
            ->with('school')
            ->when($terminal?->school_id !== null, function (Builder $query) use ($terminal): void {
                $query->where('school_id', $terminal->school_id);
            })
            ->where(function (Builder $query) use ($customIdValue): void {
                $query->where('iin', $customIdValue)
                    ->orWhere('student_number', $customIdValue);

                if (ctype_digit($customIdValue)) {
                    $query->orWhere('id', (int) $customIdValue);
                }
            })
            ->first();
    }
}
