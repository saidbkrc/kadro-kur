<?php

namespace Tests;

use App\Models\Setting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Setting statik cache'i süreç boyunca paylaşıldığı için her testte sıfırla.
        Setting::flushCache();
    }
}
