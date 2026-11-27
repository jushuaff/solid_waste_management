<?php

declare(strict_types=1);

// Run from the repository root: php scripts/check-syntax.php
// This validates PHP and actual Laravel-compiled Blade without rendering pages
// or querying/modifying application data.
$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$compiler = $app->make('blade.compiler');

function sourceFiles(string $directory, string $suffix): iterable
{
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
            yield $file->getPathname();
        }
    }
}

function lintPhp(string $path): ?string
{
    $process = proc_open(
        [PHP_BINARY, '-l', $path],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start PHP syntax checker.');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    return proc_close($process) === 0 ? null : trim((string) $output);
}

$checked = 0;
$failed = 0;
foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'tests', 'scripts'] as $directory) {
    foreach (sourceFiles($root.'/'.$directory, '.php') as $path) {
        if (str_starts_with($path, $root.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR)) {
            continue;
        }
        ++$checked;
        $error = lintPhp($path);
        if ($error !== null) {
            ++$failed;
            fwrite(STDERR, "FAIL ".$path.PHP_EOL.$error.PHP_EOL);
        }
    }
}

foreach (sourceFiles($root.'/resources/views', '.blade.php') as $path) {
    ++$checked;
    $temporary = null;
    try {
        $compiler->setPath($path);
        $source = file_get_contents($path);
        if ($source === false) {
            throw new RuntimeException('Unable to read template.');
        }
        $compiled = $compiler->compileString($source);
        $temporary = tempnam(sys_get_temp_dir(), 'swm-blade-');
        if ($temporary === false || file_put_contents($temporary, $compiled) === false) {
            throw new RuntimeException('Unable to write compiled template.');
        }
        $error = lintPhp($temporary);
        if ($error !== null) {
            throw new RuntimeException($error);
        }
    } catch (Throwable $error) {
        ++$failed;
        fwrite(STDERR, "FAIL ".$path.PHP_EOL.$error->getMessage().PHP_EOL);
    } finally {
        if (is_string($temporary) && is_file($temporary)) {
            unlink($temporary);
        }
    }
}

echo "Syntax check: ".$checked." PHP/Blade files checked; ".$failed." failed.".PHP_EOL;
exit($failed === 0 ? 0 : 1);
