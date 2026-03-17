<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\Terminal;
use App\Modules\Organizations\Models\School;
use App\Services\Mqtt\MqttService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FaceIdSyncStudents extends Command
{
    protected $signature = 'faceid:sync-students
        {--school-id= : Sync only one school by id}
        {--terminal-id= : Force terminal id for selected organization(s)}
        {--delay-ms=500 : Delay between student sends in milliseconds}';

    protected $description = 'Send all students with photos to FaceID terminal for schools linked to terminals.';

    public function handle(MqttService $mqttService): int
    {
        $delayMs = max(0, (int) $this->option('delay-ms'));
        $delayMicros = $delayMs * 1000;
        $schoolId = $this->option('school-id');
        $forcedTerminalId = $this->option('terminal-id');

        $schoolsQuery = School::query();

        if ($schoolId) {
            $schoolsQuery->whereKey($schoolId);
        }

        $schools = $schoolsQuery->get();

        if ($schools->isEmpty()) {
            $this->info('No organizations found for sync.');
            return self::SUCCESS;
        }

        foreach ($schools as $school) {
            $terminalId = $forcedTerminalId ?: Terminal::query()
                ->where('school_id', $school->id)
                ->value('device_id');

            $terminalId = trim((string) $terminalId);

            if ($terminalId === '') {
                $this->warn("Skip organization {$school->id}: terminal id is empty.");
                continue;
            }

            Log::channel('faceid')->info('FACEID SYNC START', [
                'school_id' => $school->id,
                'terminal_id' => $terminalId,
            ]);

            $this->info("Sync organization {$school->id} to terminal {$terminalId}...");

            if ($delayMs > 0) {
                $this->line("Using send delay: {$delayMs} ms");
            }

            $serverIp = config('mqtt.server_ip')
                ?? parse_url((string) config('app.url'), PHP_URL_HOST)
                ?? config('mqtt.host');

            if (!$serverIp) {
                $this->warn("Missing server IP for HTTP subscription on terminal {$terminalId}.");
                continue;
            }

            $mqttService->configureHttpVerifySubscription($terminalId, (string) $serverIp);
            $mqttService->configureSysTime($terminalId);

            $sentCount = 0;

            Student::query()
                ->where('school_id', $school->id)
                ->whereNotNull('photo')
                ->orderBy('id')
                ->chunk(200, function ($students) use ($mqttService, $terminalId, $school, $delayMicros, &$sentCount): void {
                    foreach ($students as $student) {
                        if (!$student->photo || !Storage::disk('public')->exists($student->photo)) {
                            $this->warn("Photo missing for student {$student->id}.");
                            continue;
                        }

                        $photoPath = Storage::disk('public')->path($student->photo);
                        $picBase64 = base64_encode((string) file_get_contents($photoPath));

                        $birthdayValue = $student->birth_date
                            ? Carbon::parse($student->birth_date)->format('Y-m-d')
                            : '1940-01-01';

                        $info = [
                            'personId' => '',
                            'customId' => $student->iin ?? $student->student_number ?? (string) $student->id,
                            'name' => trim(implode(' ', array_filter([$student->last_name, $student->first_name, $student->middle_name]))),
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
                            'notes' => $student->student_number ?? '',
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

        return self::SUCCESS;
    }
}



