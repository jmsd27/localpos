<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupService
{
    /**
     * Genera un respaldo .sql de la base de datos vía mysqldump y aplica
     * la política de retención (borra los más antiguos si se excede).
     *
     * @return array{filename: string, path: string, size: int}
     */
    public function run(): array
    {
        $path = config('localpos.backup_path');
        File::ensureDirectoryExists($path);

        $filename = 'localpos_'.now()->format('Y-m-d_His').'.sql';
        $filePath = $path.DIRECTORY_SEPARATOR.$filename;

        $db = config('database.connections.mysql');

        $command = [
            config('localpos.mysqldump_path'),
            '--host='.$db['host'],
            '--port='.$db['port'],
            '--user='.$db['username'],
            '--result-file='.$filePath,
            '--single-transaction',
            '--routines',
            $db['database'],
        ];

        $process = new Process($command, null, $db['password'] !== '' ? ['MYSQL_PWD' => $db['password']] : null);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful() || ! File::exists($filePath)) {
            File::delete($filePath);

            throw new RuntimeException('No se pudo generar el respaldo: '.$process->getErrorOutput());
        }

        $this->applyRetention();

        return [
            'filename' => $filename,
            'path' => $filePath,
            'size' => File::size($filePath),
        ];
    }

    /**
     * @return list<array{filename: string, size: int, created_at: Carbon}>
     */
    public function list(): array
    {
        $path = config('localpos.backup_path');

        if (! File::isDirectory($path)) {
            return [];
        }

        return collect(File::files($path))
            ->filter(fn ($file) => $file->getExtension() === 'sql')
            ->map(fn ($file) => [
                'filename' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => Carbon::createFromTimestamp($file->getMTime()),
            ])
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    public function delete(string $filename): void
    {
        $path = $this->resolvePath($filename);

        File::delete($path);
    }

    public function resolvePath(string $filename): string
    {
        $safeFilename = basename($filename);

        if ($safeFilename !== $filename || ! str_ends_with($safeFilename, '.sql')) {
            throw new RuntimeException('Nombre de archivo de respaldo inválido.');
        }

        return config('localpos.backup_path').DIRECTORY_SEPARATOR.$safeFilename;
    }

    private function applyRetention(): void
    {
        $retention = config('localpos.backup_retention');
        $backups = $this->list();

        if (count($backups) <= $retention) {
            return;
        }

        foreach (array_slice($backups, $retention) as $old) {
            File::delete(config('localpos.backup_path').DIRECTORY_SEPARATOR.$old['filename']);
        }
    }
}
