<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Lee las últimas entradas de nivel error del log de Laravel para el panel de
 * diagnóstico (Administración → Asistencia). Es de solo lectura: nunca escribe
 * ni rota el archivo.
 *
 * Solo se leen los últimos ~256 KB del log —suficiente para las incidencias
 * recientes— y se descartan las líneas por debajo de ERROR.
 */
class LaravelLogReader
{
    private const READ_BYTES = 262_144;

    private const LEVELS = ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR'];

    public function __construct(
        private readonly string $path,
    ) {}

    public static function default(): self
    {
        return new self(storage_path('logs/laravel.log'));
    }

    public function fileExists(): bool
    {
        return is_file($this->path);
    }

    public function fileBytes(): int
    {
        return $this->fileExists() ? (int) filesize($this->path) : 0;
    }

    /**
     * @return list<array{key:string,timestamp:string,level:string,summary:string,body:string}>
     */
    public function recentErrors(int $limit = 15): array
    {
        if (! $this->fileExists()) {
            return [];
        }

        $chunk = $this->tail(self::READ_BYTES);

        // Cada entrada empieza con "[YYYY-MM-DD HH:MM:SS] entorno.NIVEL:".
        $pattern = '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\]\s+\S+\.([A-Z]+):/m';

        if (! preg_match_all($pattern, $chunk, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return [];
        }

        $entries = [];

        foreach ($matches as $i => $match) {
            $level = $match[2][0];

            if (! in_array($level, self::LEVELS, true)) {
                continue;
            }

            $start = $match[0][1];
            $end = $matches[$i + 1][0][1] ?? strlen($chunk);
            $body = rtrim(substr($chunk, $start, $end - $start));

            // Primera línea sin el prefijo "[fecha] entorno.NIVEL:".
            $firstLine = strtok($body, "\n") ?: $body;
            $summary = trim(Str::after($firstLine, $match[2][0].':'));

            $entries[] = [
                'key' => md5($match[1][0].'|'.Str::limit($summary, 200, '')),
                'timestamp' => $match[1][0],
                'level' => $level,
                'summary' => Str::limit($summary, 300),
                'body' => Str::limit($body, 6000, "\n…(recortado)"),
            ];
        }

        return array_slice(array_reverse($entries), 0, $limit);
    }

    private function tail(int $bytes): string
    {
        $size = $this->fileBytes();
        $handle = fopen($this->path, 'rb');

        if ($handle === false) {
            return '';
        }

        if ($size > $bytes) {
            fseek($handle, -$bytes, SEEK_END);
            fgets($handle); // descarta la primera línea (probablemente parcial)
        }

        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $contents;
    }
}
