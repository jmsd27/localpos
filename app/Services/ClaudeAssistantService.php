<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Puente de solo lectura hacia la API de Claude (Anthropic) para el módulo
 * "Diagnóstico y asistencia". Dado un error del log, arma un prompt con el
 * stack trace y extractos del código citado —con los secretos redactados— y
 * pide un diagnóstico + parche sugerido en texto.
 *
 * Lo que NO hace: ejecutar comandos, escribir archivos, aplicar cambios ni
 * leer el .env. El arreglo lo aplica una persona por el flujo normal
 * (local → git → redeploy).
 *
 * Se usa el cliente Http de Laravel (no el SDK de Anthropic) para no sumar una
 * dependencia de Composer en un hosting compartido: es el mismo patrón que
 * SyncPushService.
 */
class ClaudeAssistantService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const MAX_FILES = 4;

    private const RATE_KEY_MAX = 8;

    private const SYSTEM_PROMPT = <<<'TXT'
        Eres un ingeniero senior de Laravel 12 + Livewire 4. Recibes un error de
        producción de "puntoYA" (un punto de venta para restaurantes) junto con
        extractos del código citado en el stack trace. Responde en español, de
        forma concisa, con:

        1. La causa raíz más probable.
        2. Los archivos y líneas a tocar.
        3. El arreglo mínimo como diff unificado en un bloque ```diff.

        No inventes rutas ni código que no se te haya mostrado. Si falta contexto
        para estar seguro, dilo e indica qué archivo haría falta ver. Nunca
        reproduzcas credenciales ni secretos.
        TXT;

    public function enabled(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * Prompt de texto plano con el error y los extractos de código. Sirve tanto
     * para la llamada a la API como para el botón "Copiar contexto para Claude
     * Code".
     *
     * @param  array{timestamp?:string,level?:string,summary?:string,body?:string}  $entry
     */
    public function contextBundle(array $entry): string
    {
        $body = (string) ($entry['body'] ?? $entry['summary'] ?? '');

        $lines = [
            '# Error de producción — puntoYA',
            '',
            'Fecha: '.($entry['timestamp'] ?? 'desconocida'),
            'Nivel: '.($entry['level'] ?? 'ERROR'),
            '',
            '## Entrada del log',
            '',
            '```',
            $this->redact($body),
            '```',
        ];

        $excerpts = $this->codeExcerpts($body);

        if ($excerpts !== []) {
            $lines[] = '';
            $lines[] = '## Código citado en el stack trace';

            foreach ($excerpts as $file => $snippet) {
                $lines[] = '';
                $lines[] = "### {$file}";
                $lines[] = '';
                $lines[] = '```php';
                $lines[] = $snippet;
                $lines[] = '```';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{timestamp?:string,level?:string,summary?:string,body?:string}  $entry
     * @return array{ok:bool, markdown?:string, error?:string}
     */
    public function explain(array $entry, ?int $userId = null): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'error' => 'Falta configurar ANTHROPIC_API_KEY.'];
        }

        $rateKey = 'claude-assist:'.($userId ?? 'anon');

        if (RateLimiter::tooManyAttempts($rateKey, self::RATE_KEY_MAX)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return ['ok' => false, 'error' => "Límite de consultas alcanzado. Probá de nuevo en {$seconds} s."];
        }

        RateLimiter::hit($rateKey, 60);

        try {
            $response = Http::withHeaders([
                'x-api-key' => (string) config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(120)->post(self::ENDPOINT, [
                'model' => (string) config('services.anthropic.model', 'claude-opus-5'),
                'max_tokens' => 6000,
                'system' => self::SYSTEM_PROMPT,
                'thinking' => ['type' => 'adaptive'],
                'output_config' => ['effort' => 'medium'],
                'messages' => [
                    ['role' => 'user', 'content' => $this->contextBundle($entry)],
                ],
            ]);
        } catch (ConnectionException $e) {
            return ['ok' => false, 'error' => 'No se pudo contactar la API de Claude: '.$this->oneLine($e->getMessage())];
        }

        if ($response->failed()) {
            return [
                'ok' => false,
                'error' => "La API de Claude respondió {$response->status()}: ".$this->oneLine($response->body()),
            ];
        }

        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        if (trim($text) === '') {
            return ['ok' => false, 'error' => 'Claude no devolvió texto (stop_reason: '.$response->json('stop_reason', '?').').'];
        }

        return ['ok' => true, 'markdown' => $text];
    }

    /**
     * Extractos (±20 líneas) de los archivos .php del proyecto citados en el
     * stack trace. Nunca sale de base_path() y nunca lee archivos .env.
     *
     * @return array<string, string>
     */
    private function codeExcerpts(string $body): array
    {
        preg_match_all(
            '#([A-Za-z]:\\\\[^\s:"\'()]+\.php|/[^\s:"\'()]+\.php|(?:[\w.\-]+/)+[\w.\-]+\.php)(?::(\d+))?#',
            $body,
            $matches,
            PREG_SET_ORDER,
        );

        $base = str_replace('\\', '/', base_path());
        $out = [];

        foreach ($matches as $match) {
            if (count($out) >= self::MAX_FILES) {
                break;
            }

            $raw = str_replace('\\', '/', $match[1]);
            $candidate = preg_match('#^([A-Za-z]:/|/)#', $raw) ? $raw : $base.'/'.$raw;
            $real = realpath($candidate);

            if ($real === false) {
                continue;
            }

            $real = str_replace('\\', '/', $real);

            if (! Str::startsWith($real, $base.'/')) {
                continue;
            }

            if (Str::contains(strtolower($real), '.env') || Str::contains($real, '/vendor/')) {
                continue;
            }

            $rel = Str::after($real, $base.'/');

            if (isset($out[$rel]) || ! is_readable($real)) {
                continue;
            }

            $fileLines = @file($real, FILE_IGNORE_NEW_LINES);

            if ($fileLines === false) {
                continue;
            }

            $target = isset($match[2]) ? max(1, (int) $match[2]) : 1;
            $from = isset($match[2]) ? max(0, $target - 21) : 0;
            $to = isset($match[2]) ? min(count($fileLines), $target + 20) : min(count($fileLines), 60);

            $snippet = [];

            for ($n = $from; $n < $to; $n++) {
                $snippet[] = ($n + 1).': '.$this->redact($fileLines[$n]);
            }

            $out[$rel] = implode("\n", $snippet);
        }

        return $out;
    }

    private function redact(string $text): string
    {
        $text = preg_replace(
            '/((?:API_KEY|APP_KEY|SECRET|TOKEN|PASSWORD|PASSWD|BEARER|AUTHORIZATION)[\w-]*\s*[=:>\'"\s]+)(\S{4,})/i',
            '$1***',
            $text,
        ) ?? $text;

        $text = preg_replace('/\bbase64:[A-Za-z0-9+\/=]{8,}/', 'base64:***', $text) ?? $text;

        return preg_replace('/\b[A-Fa-f0-9]{32,}\b/', '***', $text) ?? $text;
    }

    private function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_substr($text, 0, 300)) ?? '');
    }
}
