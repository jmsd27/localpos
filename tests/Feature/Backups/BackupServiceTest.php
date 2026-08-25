<?php

use App\Services\BackupService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

function backupTestPath(): string
{
    return sys_get_temp_dir().'/localpos_backup_test_'.uniqid();
}

test('list devuelve los .sql existentes ordenados del mas reciente al mas antiguo', function () {
    $path = backupTestPath();
    Config::set('localpos.backup_path', $path);
    File::ensureDirectoryExists($path);

    File::put($path.'/localpos_2026-01-01_010000.sql', 'a');
    touch($path.'/localpos_2026-01-01_010000.sql', now()->subDay()->timestamp);
    File::put($path.'/localpos_2026-01-02_010000.sql', 'bb');
    File::put($path.'/ignorame.txt', 'no cuenta');

    $backups = app(BackupService::class)->list();

    expect($backups)->toHaveCount(2);
    expect($backups[0]['filename'])->toBe('localpos_2026-01-02_010000.sql');

    File::deleteDirectory($path);
});

test('delete elimina el archivo de respaldo indicado', function () {
    $path = backupTestPath();
    Config::set('localpos.backup_path', $path);
    File::ensureDirectoryExists($path);
    File::put($path.'/localpos_2026-01-01_010000.sql', 'a');

    app(BackupService::class)->delete('localpos_2026-01-01_010000.sql');

    expect(File::exists($path.'/localpos_2026-01-01_010000.sql'))->toBeFalse();

    File::deleteDirectory($path);
});

test('resolvePath rechaza intentos de path traversal', function () {
    $path = backupTestPath();
    Config::set('localpos.backup_path', $path);

    app(BackupService::class)->resolvePath('../../etc/passwd.sql');
})->throws(RuntimeException::class);

test('resolvePath rechaza archivos que no terminan en .sql', function () {
    app(BackupService::class)->resolvePath('script.sh');
})->throws(RuntimeException::class);
