<?php

namespace App\Modules\Identity\Infrastructure\Security;

use App\Modules\Identity\Application\Contracts\EdsSignatureVerifier;
use App\Modules\Identity\Application\DTO\VerifyEdsData;
use App\Modules\Identity\Application\DTO\VerifiedEdsPayload;
use App\Modules\Identity\Models\EdsChallenge;
use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CmsEdsSignatureVerifier implements EdsSignatureVerifier
{
    public function verify(EdsChallenge $challenge, VerifyEdsData $data): VerifiedEdsPayload
    {
        $signatureMeta = $this->describeSignature($data->signature);
        $cmsBinary = $this->decodeCms($data->signature);
        $cmsPem = $this->makeCmsPem($data->signature);
        $tempDir = storage_path('app/tmp/eds');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $cmsPath = tempnam($tempDir, 'cms_');
        $signerPath = tempnam($tempDir, 'signer_');
        $contentPath = tempnam($tempDir, 'content_');

        if ($cmsPath === false || $signerPath === false || $contentPath === false) {
            throw new UnprocessableEntityHttpException('Unable to prepare EDS verification workspace.');
        }

        try {
            file_put_contents($cmsPath, $cmsBinary);

            $verification = $this->verifyCms($cmsPath, $signerPath, $contentPath);

            if (! $verification['verified']) {
                file_put_contents($cmsPath, $cmsPem);
                $fallbackVerification = $this->verifyCmsPemFallback($cmsPath, $signerPath, $contentPath);

                if ($fallbackVerification['verified']) {
                    $verification = $fallbackVerification;
                }
            }

            if (! $verification['verified']) {
                $details = trim($verification['details'].' '.$signatureMeta);
                $details = $details !== '' ? ' '.$details : '';
                throw new UnprocessableEntityHttpException('Invalid EDS signature.'.$details);
            }

            $signedContent = file_get_contents($contentPath);

            if (! is_string($signedContent) || $signedContent !== $challenge->challenge) {
                throw new UnprocessableEntityHttpException('EDS challenge mismatch.');
            }

            $certificatePem = file_get_contents($signerPath);

            if (! is_string($certificatePem) || trim($certificatePem) === '') {
                throw new UnprocessableEntityHttpException('Signer certificate was not found in CMS.');
            }

            $certificate = openssl_x509_read($certificatePem);

            if ($certificate === false) {
                throw new UnprocessableEntityHttpException('Unable to read signer certificate.');
            }

            $parsed = openssl_x509_parse($certificate, false);

            if (! is_array($parsed)) {
                throw new UnprocessableEntityHttpException('Unable to parse signer certificate.');
            }

            $subject = is_array($parsed['subject'] ?? null) ? $parsed['subject'] : [];
            $issuer = is_array($parsed['issuer'] ?? null) ? $parsed['issuer'] : [];

            $lastName = $this->extractLastName($subject);
            $firstName = $this->extractFirstName($subject);
            $middleName = $this->extractMiddleName($subject);

            if ($lastName === null || $firstName === null) {
                throw new UnprocessableEntityHttpException('Unable to extract IIN/FIO from signer certificate.');
            }

            return new VerifiedEdsPayload(
                lastName: $lastName,
                firstName: $firstName,
                middleName: $middleName,
                certificateThumbprint: openssl_x509_fingerprint($certificate, 'sha256') ?: hash('sha256', $certificatePem),
                certificateSerial: $parsed['serialNumberHex'] ?? $parsed['serialNumber'] ?? null,
                subjectDn: $parsed['name'] ?? $this->stringifyDn($subject),
                issuerDn: $this->stringifyDn($issuer),
                validFrom: isset($parsed['validFrom_time_t']) ? $this->toIso8601((int) $parsed['validFrom_time_t']) : null,
                validTo: isset($parsed['validTo_time_t']) ? $this->toIso8601((int) $parsed['validTo_time_t']) : null,
            );
        } finally {
            @unlink($cmsPath);
            @unlink($signerPath);
            @unlink($contentPath);
        }
    }

    private function makeCmsPem(string $signature): string
    {
        $normalized = preg_replace('/\s+/', '', $signature) ?? '';

        if ($normalized === '') {
            throw new UnprocessableEntityHttpException('Empty EDS signature.');
        }

        return "-----BEGIN CMS-----\n"
            .chunk_split($normalized, 64, "\n")
            ."-----END CMS-----\n";
    }

    private function decodeCms(string $signature): string
    {
        $normalized = preg_replace('/\s+/', '', $signature) ?? '';

        if ($normalized === '') {
            throw new UnprocessableEntityHttpException('Empty EDS signature.');
        }

        $decoded = base64_decode($normalized, true);

        if ($decoded === false || $decoded === '') {
            throw new UnprocessableEntityHttpException('Unable to decode CMS signature.');
        }

        return $decoded;
    }

    private function describeSignature(string $signature): string
    {
        $normalized = preg_replace('/\s+/', '', $signature) ?? '';
        $prefix = substr($normalized, 0, 32);
        $decoded = base64_decode($normalized, true);

        return sprintf(
            'signature_meta[len=%d,prefix=%s,base64=%s]',
            strlen($normalized),
            $prefix,
            $decoded === false ? 'no' : 'yes',
        );
    }

    private function verifyCms(string $cmsPath, string $signerPath, string $contentPath): array
    {
        $cmsErrors = '';

        if (function_exists('openssl_cms_verify')) {
            $this->drainOpenSslErrors();

            $cmsVerified = openssl_cms_verify(
                $cmsPath,
                OPENSSL_CMS_NOVERIFY,
                $signerPath,
                [],
                null,
                $contentPath,
                null,
                OPENSSL_ENCODING_DER,
            );

            if ($cmsVerified === true) {
                return [
                    'verified' => true,
                    'details' => '',
                ];
            }

            $cmsErrors = $this->collectOpenSslErrors('cms_verify');
        }

        $this->drainOpenSslErrors();

        $pkcs7Verified = openssl_pkcs7_verify(
            $cmsPath,
            PKCS7_NOVERIFY,
            $signerPath,
            [],
            null,
            $contentPath,
        );

        if ($pkcs7Verified === true) {
            return [
                'verified' => true,
                'details' => $cmsErrors,
            ];
        }

        $pkcs7Errors = $this->collectOpenSslErrors('pkcs7_verify');

        return [
            'verified' => false,
            'details' => trim($cmsErrors.' '.$pkcs7Errors),
        ];
    }

    private function verifyCmsPemFallback(string $cmsPath, string $signerPath, string $contentPath): array
    {
        $cmsErrors = '';

        if (function_exists('openssl_cms_verify')) {
            $this->drainOpenSslErrors();

            $cmsVerified = openssl_cms_verify(
                $cmsPath,
                OPENSSL_CMS_NOVERIFY,
                $signerPath,
                [],
                null,
                $contentPath,
                null,
                OPENSSL_ENCODING_PEM,
            );

            if ($cmsVerified === true) {
                return [
                    'verified' => true,
                    'details' => '',
                ];
            }

            $cmsErrors = $this->collectOpenSslErrors('cms_verify_pem');
        }

        $this->drainOpenSslErrors();

        $pkcs7Verified = openssl_pkcs7_verify(
            $cmsPath,
            PKCS7_NOVERIFY,
            $signerPath,
            [],
            null,
            $contentPath,
        );

        if ($pkcs7Verified === true) {
            return [
                'verified' => true,
                'details' => $cmsErrors,
            ];
        }

        $pkcs7Errors = $this->collectOpenSslErrors('pkcs7_verify_pem');

        return [
            'verified' => false,
            'details' => trim($cmsErrors.' '.$pkcs7Errors),
        ];
    }

    private function collectOpenSslErrors(string $prefix): string
    {
        $errors = [];

        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }

        if ($errors === []) {
            return $prefix.': no OpenSSL details';
        }

        return $prefix.': '.implode(' | ', $errors);
    }

    private function drainOpenSslErrors(): void
    {
        while (openssl_error_string() !== false) {
            // drain stale errors
        }
    }

    private function extractLastName(array $subject): ?string
    {
        $lastName = $this->firstNonEmpty(
            $subject['surname'] ?? null,
            $subject['SN'] ?? null,
            $subject['S'] ?? null,
        );

        if ($lastName !== null) {
            return $lastName;
        }

        $commonName = $this->firstNonEmpty($subject['CN'] ?? null, $subject['cn'] ?? null);
        $firstName = $this->extractFirstName($subject);

        if ($commonName !== null && $firstName !== null) {
            return $commonName;
        }

        if ($commonName !== null) {
            $parts = preg_split('/\s+/', $commonName, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            return $parts[0] ?? null;
        }

        return null;
    }

    private function extractFirstName(array $subject): ?string
    {
        $firstName = $this->firstNonEmpty(
            $subject['givenName'] ?? null,
            $subject['G'] ?? null,
            $subject['GN'] ?? null,
        );

        if ($firstName !== null) {
            return $firstName;
        }

        $commonName = $this->firstNonEmpty($subject['CN'] ?? null, $subject['cn'] ?? null);

        if ($commonName === null) {
            return null;
        }

        $parts = preg_split('/\s+/', $commonName, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $parts[1] ?? null;
    }

    private function extractMiddleName(array $subject): ?string
    {
        $middleName = $this->firstNonEmpty(
            $subject['initials'] ?? null,
            $subject['INITIALS'] ?? null,
        );

        if ($middleName !== null) {
            return $middleName;
        }

        $commonName = $this->firstNonEmpty($subject['CN'] ?? null, $subject['cn'] ?? null);

        if ($commonName === null) {
            return null;
        }

        $parts = preg_split('/\s+/', $commonName, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return isset($parts[2]) ? implode(' ', array_slice($parts, 2)) : null;
    }

    private function stringifyDn(array $dn): ?string
    {
        if ($dn === []) {
            return null;
        }

        $pairs = [];

        foreach ($dn as $key => $value) {
            if (! is_scalar($value) || $value === '') {
                continue;
            }

            $pairs[] = $key.'='.$value;
        }

        return $pairs === [] ? null : implode(', ', $pairs);
    }

    private function toIso8601(int $timestamp): string
    {
        return (new DateTimeImmutable('@'.$timestamp))->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format(DATE_ATOM);
    }

    private function firstNonEmpty(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
