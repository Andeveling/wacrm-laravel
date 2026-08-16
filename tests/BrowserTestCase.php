<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Pest\Browser\Browsable;

abstract class BrowserTestCase extends TestCase
{
    use Browsable;
    use RefreshDatabase;
}
