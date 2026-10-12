<?php

/**
 * Report dependency provenance without loading credentials, installing packages,
 * or changing the lock. Local path installs require an explicit opt-in.
 */

$root = dirname(__DIR__);
$arguments = array_slice($argv, 1);
if (array_diff($arguments, ['--allow-local-paths']) !== []) {
    fwrite(STDERR, "Usage: php scripts/check-dependencies.php [--allow-local-paths]\n");
    exit(2);
}
$allowLocal = in_array('--allow-local-paths', $arguments, true);
foreach (['composer.lock', 'vendor/autoload.php', 'vendor/composer/installed.json'] as $path) {
    if (! is_file($root . '/' . $path)) {
        fwrite(STDERR, "Missing {$path}. In a fresh checkout without a lock, resolve with composer update; with an existing lock, use composer install.\n");
        exit(1);
    }
}

try {
    $lock = json_decode(file_get_contents($root . '/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
    $installed = json_decode(file_get_contents($root . '/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
    $packages = $installed['packages'] ?? $installed;
    $locked = [];
    foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
        $locked[$package['name']] = $package;
    }
    $vendor = str_replace('\\', '/', realpath($root . '/vendor')) . '/';
    $problems = [];
    $local = [];
    foreach ($packages as $package) {
        $name = $package['name'];
        $path = isset($package['install-path']) ? realpath($root . '/vendor/composer/' . $package['install-path']) : false;
        $outsideVendor = $path !== false && ! str_starts_with(strtolower(str_replace('\\', '/', $path) . '/'), strtolower($vendor));
        if (($package['dist']['type'] ?? '') === 'path' || ($locked[$name]['dist']['type'] ?? '') === 'path' || $outsideVendor) {
            $local[] = $name . ' ' . $package['version'];
        }
        if (! isset($locked[$name]) || $locked[$name]['version'] !== $package['version']) {
            $problems[] = $name . ': installed version does not match composer.lock';
        } elseif (($locked[$name]['source']['reference'] ?? $locked[$name]['dist']['reference'] ?? null) !== ($package['source']['reference'] ?? $package['dist']['reference'] ?? null)) {
            $problems[] = $name . ': installed reference does not match composer.lock';
        }
        unset($locked[$name]);
    }
    foreach ($locked as $name => $package) {
        $problems[] = $name . ': locked dependency is missing (development dependencies are required for validation)';
    }
    printf("PHP %s on %s; %d installed packages checked against the local lock.\n", PHP_VERSION, PHP_OS_FAMILY, count($packages));
    if ($local !== []) {
        printf("Local path dependencies:\n  %s\n", implode("\n  ", $local));
        if (! $allowLocal) {
            $problems[] = 'Use --allow-local-paths only for intentional sibling development; validate published dependencies separately before release.';
        }
    }
    if ($problems !== []) {
        fwrite(STDERR, implode("\n", $problems) . "\n");
        exit(1);
    }
    echo "Dependency provenance verified. Run composer validate to check the manifest/lock hash and composer check-platform-reqs for extensions.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Dependency metadata could not be checked: ' . $error->getMessage() . "\n");
    exit(1);
}
