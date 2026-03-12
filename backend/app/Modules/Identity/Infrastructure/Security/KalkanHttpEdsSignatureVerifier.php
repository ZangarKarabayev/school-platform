<?php

namespace App\Modules\Identity\Infrastructure\Security;

use App\Modules\Identity\Application\Contracts\EdsSignatureVerifier;
use App\Modules\Identity\Application\DTO\VerifyEdsData;
use App\Modules\Identity\Application\DTO\VerifiedEdsPayload;
use App\Modules\Identity\Models\EdsChallenge;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class KalkanHttpEdsSignatureVerifier implements EdsSignatureVerifier
{
    public function verify(EdsChallenge $challenge, VerifyEdsData $data): VerifiedEdsPayload
    {
        $response = Http::acceptJson()
            ->timeout(15)
            ->post($this->endpoint(), [
                'challenge' => $challenge->challenge,
                'signature' => $data->signature,
            ]);

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new UnprocessableEntityHttpException(sprintf(
                'Kalkan verifier returned an invalid response. status=%d body=%s',
                $response->status(),
                trim($response->body()) !== '' ? $response->body() : '[empty]',
            ));
        }

        if (! $response->successful() || ! $this->payloadBool($payload, 'valid', 'Valid')) {
            throw new UnprocessableEntityHttpException(
                sprintf(
                    '%s status=%d raw=%s',
                    $this->payloadString($payload, 'message', 'Message') ?? 'Invalid EDS signature.',
                    $response->status(),
                    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[json_encode_failed]',
                ),
            );
        }

        $commonName = $this->cleanCertificateValue($this->payloadString($payload, 'commonName', 'CommonName'));
        $rawLastName = $this->cleanCertificateValue($this->payloadString($payload, 'lastName', 'LastName'));
        $rawGivenName = $this->cleanCertificateValue($this->payloadString($payload, 'firstName', 'FirstName'));
        ['lastName' => $lastName, 'firstName' => $firstName, 'middleName' => $middleName] = $this->normalizePersonName(
            $rawLastName,
            $rawGivenName,
            $commonName,
        );

        if ($lastName === null || $firstName === null) {
            throw new UnprocessableEntityHttpException('Kalkan verifier did not return enough signer data.');
        }

        return new VerifiedEdsPayload(
            lastName: $lastName,
            firstName: $firstName,
            middleName: $middleName,
            certificateThumbprint: hash('sha256', $data->signature),
            certificateSerial: $this->cleanCertificateValue($this->payloadString($payload, 'certificateSerial', 'CertificateSerial')),
            subjectDn: $this->payloadString($payload, 'subjectDn', 'SubjectDn'),
            issuerDn: $this->payloadString($payload, 'issuerDn', 'IssuerDn'),
            validFrom: $this->normalizeDate($this->cleanCertificateValue($this->payloadString($payload, 'validFrom', 'ValidFrom'))),
            validTo: $this->normalizeDate($this->cleanCertificateValue($this->payloadString($payload, 'validTo', 'ValidTo'))),
        );
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.eds_auth.verifier_url', 'http://127.0.0.1:5055'), '/').'/verify-cms';
    }

    private function payloadBool(array $payload, string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return filter_var($payload[$key], FILTER_VALIDATE_BOOL);
            }
        }

        return false;
    }

    private function payloadString(array $payload, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return $this->stringOrNull($payload[$key]);
            }
        }

        return null;
    }

    private function cleanCertificateValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/^[A-Za-z][A-Za-z0-9]*=/', '', $value);
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\s+ALMT$/i', ' Asia/Almaty', $value) ?? $value;
        $formats = [
            'd.m.Y H:i:s e',
            'd.m.Y H:i:s',
            DATE_ATOM,
            'Y-m-d H:i:s',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $normalized);

                if ($date !== false) {
                    return $date->toDateTimeString();
                }
            } catch (\Throwable) {
                // try next format
            }
        }

        return $value;
    }

    private function normalizePersonName(?string $lastName, ?string $givenName, ?string $commonName): array
    {
        $firstName = $givenName;
        $middleName = null;

        if ($commonName !== null) {
            $parts = preg_split('/\s+/u', $commonName, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if ($lastName === null && isset($parts[0])) {
                $lastName = $parts[0];
            }

            if (count($parts) >= 2) {
                $commonNameLastName = $parts[0];
                $commonNameFirstName = $parts[1];
                $commonNameMiddleName = count($parts) >= 3 ? implode(' ', array_slice($parts, 2)) : null;

                if ($lastName === null || $this->namesEqual($lastName, $commonNameLastName)) {
                    $lastName = $commonNameLastName;
                    $firstName = $commonNameFirstName;
                    $middleName = $commonNameMiddleName;
                }
            }
        }

        if ($middleName === null && $givenName !== null && ! $this->namesEqual($givenName, $firstName)) {
            $middleName = $givenName;
        }

        return [
            'lastName' => $lastName,
            'firstName' => $firstName,
            'middleName' => $middleName,
        ];
    }

    private function namesEqual(?string $left, ?string $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        return mb_strtolower(trim($left)) === mb_strtolower(trim($right));
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
