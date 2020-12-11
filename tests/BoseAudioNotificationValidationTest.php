<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class BoseAudioNotificationValidationTest extends TestCaseSymconValidation
{
    public function testValidateBoseAudioNotification(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateBoseAudioNotificationModule(): void
    {
        $this->validateModule(__DIR__ . '/../AudioNotification');
    }
}