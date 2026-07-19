<?php

use Pest\Browser\Playwright\Playwright;

pest()->extend(Tests\TestCase::class)->in('Feature');

pest()->extend(Tests\BrowserTestCase::class)
    ->in('Browser')
    ->beforeEach(function () {
        Playwright::setTimeout(15_000);
    });
