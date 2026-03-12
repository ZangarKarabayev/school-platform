<?php

namespace Tests;

use App\Modules\Identity\Application\Contracts\EdsSignatureVerifier;
use App\Modules\Identity\Infrastructure\Security\FakeEdsSignatureVerifier;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(EdsSignatureVerifier::class, FakeEdsSignatureVerifier::class);
    }
}
