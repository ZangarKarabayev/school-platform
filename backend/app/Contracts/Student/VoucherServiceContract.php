<?php

namespace App\Contracts\Student;

interface VoucherServiceContract
{
    public function handleVoucherActivation(array $requestData): array;

    public function getVoucherHistory(array $requestData): array;
}
