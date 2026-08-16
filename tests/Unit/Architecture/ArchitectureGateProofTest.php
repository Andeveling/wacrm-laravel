<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

test('the Domain support architecture gate rejects a framework dependency', function () {
    $path = app_path('Domain/Accounts/Support/TemporaryForbiddenDependency.php');

    file_put_contents($path, <<<'PHP'
<?php

namespace App\Domain\Accounts\Support;

use Illuminate\Http\Request;

final class TemporaryForbiddenDependency
{
    public function request(): Request
    {
        return request();
    }
}
PHP);

    try {
        $result = Process::run([
            PHP_BINARY,
            'artisan',
            'test',
            '--exclude-testsuite=Browser',
            '--compact',
            '--filter=domain support is framework and transport independent',
        ]);

        expect($result->successful())->toBeFalse();
        expect($result->output().$result->errorOutput())->toContain('not to use');
    } finally {
        unlink($path);
    }
});
