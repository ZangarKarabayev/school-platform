<?php

namespace App\Modules\Identity\Infrastructure\Security;

use App\Modules\Identity\Application\Contracts\EdsSignatureVerifier;
use App\Modules\Identity\Application\DTO\VerifyEdsData;
use App\Modules\Identity\Application\DTO\VerifiedEdsPayload;
use App\Modules\Identity\Models\EdsChallenge;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class KalkanExtensionEdsSignatureVerifier implements EdsSignatureVerifier
{
    private const KC_CERTPROP_SUBJECT_COMMONNAME = 0x80a;
    private const KC_CERTPROP_SUBJECT_GIVENNAME = 0x80b;
    private const KC_CERTPROP_SUBJECT_SURNAME = 0x80c;
    private const KC_CERTPROP_SUBJECT_SERIALNUMBER = 0x80d;
    private const KC_CERTPROP_NOTBEFORE = 0x813;
    private const KC_CERTPROP_NOTAFTER = 0x814;
    private const KC_CERTPROP_CERT_SN = 0x819;
    private const KC_CERTPROP_ISSUER_DN = 0x81a;
    private const KC_CERTPROP_SUBJECT_DN = 0x81b;

    private const KC_SIGN_CMS = 0x2;
    private const KC_IN_BASE64 = 0x10;
    private const KC_OUT_PEM = 0x200;

    public function verify(EdsChallenge $challenge, VerifyEdsData $data): VerifiedEdsPayload
    {
        $this->assertExtensionIsAvailable();

        KalkanCrypt_Init();

        $certificate = '';
        $errorCode = KalkanCrypt_getCertFromCMS(
            $data->signature,
            1,
            self::KC_IN_BASE64 + self::KC_SIGN_CMS + self::KC_OUT_PEM,
            $certificate,
        );

        if ($errorCode > 0 || trim($certificate) === '') {
            throw new UnprocessableEntityHttpException(
                'Kalkan extension failed to parse CMS: '.trim((string) KalkanCrypt_GetLastErrorString())
            );
        }

        $commonName = $this->cleanCertificateValue($this->getCertInfo($certificate, self::KC_CERTPROP_SUBJECT_COMMONNAME));
        $rawLastName = $this->cleanCertificateValue($this->getCertInfo($certificate, self::KC_CERTPROP_SUBJECT_SURNAME));
        $rawGivenName = $this->cleanCertificateValue($this->getCertInfo($certificate, self::KC_CERTPROP_SUBJECT_GIVENNAME));
        ['lastName' => $lastName, 'firstName' => $firstName, 'middleName' => $middleName] = $this->normalizePersonName(
            $rawLastName,
            $rawGivenName,
            $commonName,
        );

        if ($lastName === null || $firstName === null) {
            throw new UnprocessableEntityHttpException('Unable to extract IIN/FIO from signer certificate.');
        }

        return new VerifiedEdsPayload(
            lastName: $lastName,
            firstName: $firstName,
            middleName: $middleName,
            certificateThumbprint: hash('sha256', $certificate),
            certificateSerial: $this->cleanCertificateValue($this->getCertInfo($certificate, self::KC_CERTPROP_CERT_SN)),
            subjectDn: $this->stringOrNull($this->getCertInfo($certificate, self::KC_CERTPROP_SUBJECT_DN)),
            issuerDn: $this->stringOrNull($this->getCertInfo($certificate, self::KC_CERTPROP_ISSUER_DN)),
            validFrom: $this->normalizeDate($this->cleanCertificateValue($this->getCertInfo($certificate, self::KC_CERTPROP_NOTBEFORE))),
            validTo: $this->normalizeDate($this->cleanCertificateValue($this->getCertInfo($certificate, self::KC_CERTPROP_NOTAFTER))),
        );
    }

    private function assertExtensionIsAvailable(): void
    {
        foreach ([
            'KalkanCrypt_Init',
            'KalkanCrypt_getCertFromCMS',
            'KalkanCrypt_X509CertificateGetInfo',
            'KalkanCrypt_GetLastErrorString',
        ] as $function) {
            if (! function_exists($function)) {
                throw new UnprocessableEntityHttpException(sprintf(
                    'Kalkan extension function %s is not available.',
                    $function,
                ));
            }
        }
    }

    private function getCertInfo(string $certificate, int $propId): string
    {
        $value = '';
        $errorCode = KalkanCrypt_X509CertificateGetInfo($propId, $certificate, $value);

        if ($errorCode > 0) {
            return '';
        }

        return trim((string) $value);
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

    private function stringOrNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
