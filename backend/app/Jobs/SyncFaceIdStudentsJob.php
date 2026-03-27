<?php

namespace App\Jobs;

use App\Models\Student;
use App\Models\Terminal;
use App\Modules\Organizations\Models\School;
use App\Services\Mqtt\MqttService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncFaceIdStudentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $schoolId,
        public ?string $forcedTerminalId = null,
        public int $delayMs = 500,
    ) {
    }

    public function handle(MqttService $mqttService): void
    {
        $delayMicros = max(0, $this->delayMs) * 1000;
        $school = School::query()->find($this->schoolId);

        if (! $school) {
            Log::channel('faceid')->warning('FACEID SYNC SKIPPED: SCHOOL NOT FOUND', [
                'school_id' => $this->schoolId,
            ]);
            return;
        }

        $terminalId = trim((string) ($this->forcedTerminalId ?: Terminal::query()
            ->where('school_id', $school->id)
            ->value('device_id')));

        if ($terminalId === '') {
            Log::channel('faceid')->warning('FACEID SYNC SKIPPED: TERMINAL EMPTY', [
                'school_id' => $school->id,
            ]);
            return;
        }

        Log::channel('faceid')->info('FACEID SYNC START', [
            'school_id' => $school->id,
            'terminal_id' => $terminalId,
            'delay_ms' => $this->delayMs,
        ]);

        $serverIp = config('mqtt.server_ip')
            ?? parse_url((string) config('app.url'), PHP_URL_HOST)
            ?? config('mqtt.host');

        if (! $serverIp) {
            Log::channel('faceid')->warning('FACEID SYNC SKIPPED: SERVER IP MISSING', [
                'school_id' => $school->id,
                'terminal_id' => $terminalId,
            ]);
            return;
        }

        $mqttService->configureHttpVerifySubscription($terminalId, (string) $serverIp);
        $mqttService->configureSysTime($terminalId);

        $sentCount = 0;

        Student::query()
            ->where('school_id', $school->id)
            ->whereNotNull('photo')
            ->where(function (Builder $query): void {
                $query->whereNull('photo_synced_at')
                    ->orWhereColumn('photo_updated_at', '>', 'photo_synced_at');
            })
            ->orderBy('id')
            ->chunk(200, function ($students) use ($mqttService, $terminalId, $school, $delayMicros, &$sentCount): void {
                foreach ($students as $student) {
                    if (! $student->photo || ! Storage::disk('public')->exists($student->photo)) {
                        Log::channel('faceid')->warning('FACEID SYNC PHOTO MISSING', [
                            'school_id' => $school->id,
                            'terminal_id' => $terminalId,
                            'student_id' => $student->id,
                            'photo' => $student->photo,
                        ]);
                        continue;
                    }

                    $photoPath = Storage::disk('public')->path($student->photo);
                    $picBase64 = base64_encode((string) file_get_contents($photoPath));

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
                        'address' => $school->bin ?? '',
                        'idCard' => '',
                        'tempCardType' => 0,
                        'EffectNumber' => 3,
                        'cardValidBegin' => '2040-10-10 10:00:00',
                        'cardValidEnd' => '2040-10-10 16:00:00',
                        'telnum1' => '3636',
                        'PersonalPassword' => '321654',
                        'isCheckSimilarity' => 1,
                        'Native' => 'KZ',
                        'cardType2' => 0,
                        'cardNum2' => '',
                        'notes' => (string) $student->id,
                        'personType' => 0,
                        'cardType' => 0,
                        'dwidentity' => 0,
                        'pic' => $picBase64,
                    ];

                    $requestTopic = "mqtt/face/{$terminalId}";
                    $ackTopic = "{$requestTopic}/Ack";
                    $messageId = $mqttService->sendEditPersonFromVendorSpec($info, $terminalId);

                    Log::channel('faceid')->info('FACEID SYNC STUDENT SENT', [
                        'school_id' => $school->id,
                        'terminal_id' => $terminalId,
                        'topic' => $requestTopic,
                        'ack_topic' => $ackTopic,
                        'student_id' => $student->id,
                        'iin' => $student->iin,
                        'student_number' => $student->student_number,
                        'custom_id' => $info['customId'],
                        'message_id' => $messageId,
                        'photo' => $student->photo,
                    ]);

                    $sentCount++;

                    if ($delayMicros > 0) {
                        usleep($delayMicros);
                    }
                }
            });

        Log::channel('faceid')->info('FACEID SYNC FINISHED', [
            'school_id' => $school->id,
            'terminal_id' => $terminalId,
            'sent_count' => $sentCount,
        ]);
    }
}
