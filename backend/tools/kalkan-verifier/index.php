<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/verify-cms') {
    http_response_code(404);
    echo json_encode(['message' => 'Not found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (! function_exists('KalkanCrypt_Init')) {
    http_response_code(500);
    echo json_encode([
        'valid' => false,
        'message' => 'Kalkan extension is not loaded.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);

$challenge = is_array($payload) ? trim((string) ($payload['challenge'] ?? '')) : '';
$signature = is_array($payload) ? trim((string) ($payload['signature'] ?? '')) : '';

if ($challenge === '' || $signature === '') {
    http_response_code(422);
    echo json_encode([
        'valid' => false,
        'message' => 'challenge and signature are required',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

const KC_SIGN_CMS = 0x2;
const KC_IN_BASE64 = 0x10;
const KC_OUT_PEM = 0x200;

const KC_CERTPROP_SUBJECT_COMMONNAME = 0x80A;
const KC_CERTPROP_SUBJECT_GIVENNAME = 0x80B;
const KC_CERTPROP_SUBJECT_SURNAME = 0x80C;
const KC_CERTPROP_SUBJECT_SERIALNUMBER = 0x80D;
const KC_CERTPROP_NOTBEFORE = 0x813;
const KC_CERTPROP_NOTAFTER = 0x814;
const KC_CERTPROP_CERT_SN = 0x819;
const KC_CERTPROP_ISSUER_DN = 0x81A;
const KC_CERTPROP_SUBJECT_DN = 0x81B;

const KC_CERT_CA = 0x201;
const KC_CERT_INTERMEDIATE = 0x202;

KalkanCrypt_Init();

$trustedCertificates = [
    ['/root/kalkan-verifier/certs/root_rsa_2020.cer', KC_CERT_CA],
    ['/root/kalkan-verifier/certs/root_gost_2022.cer', KC_CERT_CA],
    ['/root/kalkan-verifier/certs/nca_rsa_2022.cer', KC_CERT_INTERMEDIATE],
    ['/root/kalkan-verifier/certs/nca_gost_2022.cer', KC_CERT_INTERMEDIATE],
];

foreach ($trustedCertificates as [$path, $type]) {
    if (! is_file($path)) {
        http_response_code(500);
        echo json_encode([
            'valid' => false,
            'message' => 'Trusted certificate file not found: '.$path,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $loadError = KalkanCrypt_X509LoadCertificateFromFile($type, $path);

    if ($loadError > 0) {
        http_response_code(500);
        echo json_encode([
            'valid' => false,
            'message' => 'Failed to load trusted certificate '.$path.': '.trim((string) KalkanCrypt_GetLastErrorString()),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$verifiedData = '';
$verifyInfo = '';
$certificateFromVerify = '';

$verifyError = KalkanCrypt_VerifyData(
    '',
    KC_IN_BASE64 + KC_SIGN_CMS,
    $challenge,
    0,
    $signature,
    $verifiedData,
    $verifyInfo,
    $certificateFromVerify
);

if ($verifyError > 0) {
    http_response_code(422);
    echo json_encode([
        'valid' => false,
        'message' => 'VerifyData failed: '.trim((string) KalkanCrypt_GetLastErrorString()),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (trim((string) $verifiedData) !== $challenge) {
    http_response_code(422);
    echo json_encode([
        'valid' => false,
        'message' => 'Signed data does not match challenge',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$certificate = '';
$errorCode = KalkanCrypt_getCertFromCMS(
    $signature,
    1,
    KC_IN_BASE64 + KC_SIGN_CMS + KC_OUT_PEM,
    $certificate
);

if ($errorCode > 0 || trim($certificate) === '') {
    $certificate = trim((string) $certificateFromVerify);
}

if ($certificate === '') {
    http_response_code(422);
    echo json_encode([
        'valid' => false,
        'message' => 'GetCertFromCMS failed: '.trim((string) KalkanCrypt_GetLastErrorString()),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$getInfo = static function (int $propId) use ($certificate): string {
    $value = '';
    $rv = KalkanCrypt_X509CertificateGetInfo($propId, $certificate, $value);

    if ($rv > 0) {
        return '';
    }

    return trim((string) $value);
};

$serialNumber = $getInfo(KC_CERTPROP_SUBJECT_SERIALNUMBER);
preg_match('/\d{12}/', $serialNumber, $iinMatch);

$response = [
    'valid' => true,
    'message' => null,
    'iin' => $iinMatch[0] ?? '',
    'lastName' => $getInfo(KC_CERTPROP_SUBJECT_SURNAME),
    'firstName' => $getInfo(KC_CERTPROP_SUBJECT_GIVENNAME),
    'commonName' => $getInfo(KC_CERTPROP_SUBJECT_COMMONNAME),
    'certificateSerial' => $getInfo(KC_CERTPROP_CERT_SN),
    'subjectDn' => $getInfo(KC_CERTPROP_SUBJECT_DN),
    'issuerDn' => $getInfo(KC_CERTPROP_ISSUER_DN),
    'validFrom' => $getInfo(KC_CERTPROP_NOTBEFORE),
    'validTo' => $getInfo(KC_CERTPROP_NOTAFTER),
];

if ($response['iin'] === '') {
    $response['valid'] = false;
    $response['message'] = 'IIN not found in certificate';
    http_response_code(422);
} else {
    http_response_code(200);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
