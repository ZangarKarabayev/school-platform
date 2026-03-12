<?php

namespace App\Modules\Identity\Infrastructure\Security;

use App\Modules\Identity\Application\Contracts\EdsSignatureVerifier;
use App\Modules\Identity\Application\DTO\VerifyEdsData;
use App\Modules\Identity\Application\DTO\VerifiedEdsPayload;
use App\Modules\Identity\Models\EdsChallenge;
use COM;
use com_exception;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class KalkanComEdsSignatureVerifier implements EdsSignatureVerifier
{
    private const PROG_ID = 'KalkanCryptCOMLib.KalkanCryptCOM';

    private const KC_SIGN_CMS = 0x2;
    private const KC_IN_BASE64 = 0x10;
    private const KC_IN2_BASE64 = 0x20;

    private const KC_CERTPROP_SUBJECT_COMMONNAME = 0x80a;
    private const KC_CERTPROP_SUBJECT_GIVENNAME = 0x80b;
    private const KC_CERTPROP_SUBJECT_SURNAME = 0x80c;
    private const KC_CERTPROP_SUBJECT_SERIALNUMBER = 0x80d;
    private const KC_CERTPROP_NOTBEFORE = 0x813;
    private const KC_CERTPROP_NOTAFTER = 0x814;
    private const KC_CERTPROP_CERT_SN = 0x819;
    private const KC_CERTPROP_ISSUER_DN = 0x81a;
    private const KC_CERTPROP_SUBJECT_DN = 0x81b;

    public function verify(EdsChallenge $challenge, VerifyEdsData $data): VerifiedEdsPayload
    {
        $com = $this->createComClient();

        try {
            $this->initialize($com);

            $flags = self::KC_SIGN_CMS | self::KC_IN2_BASE64;
            $outData = ' ';
            $outVerifyInfo = ' ';
            $outCert = ' ';

            $com->VerifyData('', $flags, 0, ' ', $data->signature, $outData, $outVerifyInfo, $outCert);
            $this->assertNoComError($com, 'VerifyData');

            $signedContent = $this->normalizeComString($outData);

            if ($signedContent !== $challenge->challenge) {
                throw new UnprocessableEntityHttpException('EDS challenge mismatch.');
            }

            $certificate = $this->normalizeComString($outCert);

            if ($certificate === '') {
                $flags = self::KC_SIGN_CMS | self::KC_IN_BASE64;
                $signId = 1;
                $com->GetCertFromCMS($data->signature, $flags, $signId, $outCert);
                $this->assertNoComError($com, 'GetCertFromCMS');
                $certificate = $this->normalizeComString($outCert);
            }

            if ($certificate === '') {
                throw new UnprocessableEntityHttpException('Signer certificate was not found in CMS.');
            }

            $lastName = $this->normalizeNullable($this->getCertInfo($com, $certificate, self::KC_CERTPROP_SUBJECT_SURNAME));
            $firstName = $this->normalizeNullable($this->getCertInfo($com, $certificate, self::KC_CERTPROP_SUBJECT_GIVENNAME));
            $middleName = null;

            if ($lastName === null || $firstName === null) {
                [$lastName, $firstName, $middleName] = $this->splitCommonName(
                    $this->normalizeNullable($this->getCertInfo($com, $certificate, self::KC_CERTPROP_SUBJECT_COMMONNAME))
                );
            }

            if ($lastName === null || $firstName === null) {
                throw new UnprocessableEntityHttpException('Unable to extract IIN/FIO from signer certificate.');
            }

            return new VerifiedEdsPayload(
                lastName: $lastName,
                firstName: $firstName,
                middleName: $middleName,
                certificateThumbprint: hash('sha256', $certificate),
                certificateSerial: $this->normalizeNullable($this->getCertInfo($com, $certificate, self::KC_CERTPROP_CERT_SN)),
                subjectDn: $this->normalizeNullable($this->getCertInfo($com, $certificate, self::KC_CERTPROP_SUBJECT_DN)),
                issuerDn: $this->normalizeNullable($this->getCertInfo($com, $certificate, self::KC_CERTPROP_ISSUER_DN)),
                validFrom: $this->normalizeNullable($this->getCertInfo($com, $certificate, self::KC_CERTPROP_NOTBEFORE)),
                validTo: $this->normalizeNullable($this->getCertInfo($com, $certificate, self::KC_CERTPROP_NOTAFTER)),
            );
        } finally {
            if (method_exists($com, 'Finalize')) {
                try {
                    $com->Finalize();
                } catch (\Throwable) {
                    // ignore cleanup errors
                }
            }
        }
    }

    private function createComClient(): COM
    {
        try {
            return new COM(self::PROG_ID);
        } catch (com_exception $exception) {
            throw new UnprocessableEntityHttpException('Kalkan COM is not available: '.$exception->getMessage());
        }
    }

    private function initialize(COM $com): void
    {
        $com->Init();
        $this->assertNoComError($com, 'Init');
    }

    private function getCertInfo(COM $com, string $certificate, int $propId): string
    {
        $outData = '';
        $com->X509CertificateGetInfo($certificate, $propId, $outData);
        $this->assertNoComError($com, 'X509CertificateGetInfo');

        return $this->normalizeComString($outData);
    }

    private function assertNoComError(COM $com, string $operation): void
    {
        $errorString = '';
        $errorCode = 0;

        $com->GetLastErrorString($errorString, $errorCode);

        if ((int) $errorCode > 0) {
            throw new UnprocessableEntityHttpException(sprintf(
                '%s failed: 0x%08X %s',
                $operation,
                (int) $errorCode,
                $this->normalizeComString($errorString),
            ));
        }
    }

    private function splitCommonName(?string $commonName): array
    {
        if ($commonName === null) {
            return [null, null, null];
        }

        $parts = preg_split('/\s+/u', trim($commonName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return [
            $parts[0] ?? null,
            $parts[1] ?? null,
            isset($parts[2]) ? implode(' ', array_slice($parts, 2)) : null,
        ];
    }

    private function normalizeComString(mixed $value): string
    {
        return trim((string) $value);
    }

    private function normalizeNullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
