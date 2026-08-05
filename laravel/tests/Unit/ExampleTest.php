<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('that true is true', function () {
    expect(true)->toBeTrue();
});
