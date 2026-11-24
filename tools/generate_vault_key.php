<?php
/**
 * One-time AvenPay vault-key generator. Validated by the PHP CI workflow.
 *
 * Run from cPanel Terminal/SSH:
 *   php /home/ACCOUNT/public_html/tools/generate_vault_key.php
 *
 * This script is deliberately CLI-only. It never prints the generated secret.
 * After a successful run, delete this script from the server/repository.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

$envFile = getenv('AVENPAY_ENV_FILE');
if (!$envFile) {
    // Matches database_config/env.php: one directory above public_html.
    $envFile = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'avenpay.env';
}

$directory = dirname($envFile);
if (!is_dir($directory) || !is_writable($directory)) {
    fwrite(STDERR, "ERROR: The private directory for avenpay.env is not writable.\n");
    exit(1);
}
if (is_link($envFile)) {
    fwrite(STDERR, "ERROR: Refusing to write through a symbolic link.\n");
    exit(1);
}

$lockPath = $directory . DIRECTORY_SEPARATOR . '.avenpay-vault-setup.lock';
$lock = @fopen($lockPath, 'c');
if (!$lock || !flock($lock, LOCK_EX)) {
    fwrite(STDERR, "ERROR: Could not obtain the setup lock.\n");
    exit(1);
}

try {
    $contents = is_file($envFile) ? file_get_contents($envFile) : '';
    if ($contents === false) {
        throw new RuntimeException('Could not read the private environment file.');
    }

    foreach (preg_split('/\r?\n/', $contents) as $line) {
        if (preg_match('/^\s*VAULT_KEY\s*=/', $line)) {
            throw new RuntimeException('VAULT_KEY is already configured. No changes were made.');
        }
    }

    if (getenv('VAULT_KEY') !== false && getenv('VAULT_KEY') !== '') {
        throw new RuntimeException('VAULT_KEY is already supplied by the server environment. No file was changed.');
    }

    $secret = bin2hex(random_bytes(32));
    $newContents = rtrim($contents, "\r\n");
    if ($newContents !== '') {
        $newContents .= PHP_EOL;
    }
    $newContents .= 'VAULT_KEY=' . $secret . PHP_EOL;

    $temporary = tempnam($directory, '.avenpay-env-');
    if ($temporary === false) {
        throw new RuntimeException('Could not create a temporary private file.');
    }

    try {
        if (file_put_contents($temporary, $newContents, LOCK_EX) === false) {
            throw new RuntimeException('Could not write the private environment file.');
        }
        if (!chmod($temporary, 0600)) {
            throw new RuntimeException('Could not restrict permissions on the private environment file.');
        }
        if (!rename($temporary, $envFile)) {
            throw new RuntimeException('Could not safely install the private environment file.');
        }
        @chmod($envFile, 0600);
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
    }

    echo "SUCCESS: A new AvenPay VAULT_KEY was generated and saved outside public_html.\n";
    echo "The secret was not displayed. Keep the avenpay.env file private and backed up securely.\n";
    echo "Delete generate_vault_key.php after running it. Do not run this generator again.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
