<?php

namespace App\Http\Controllers\Api;

use App\Enums\PrintJobStatus;
use App\Http\Controllers\Controller;
use App\Models\PrintJob;
use App\Models\Terminal;
use App\Services\PrintService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints consumidos por el agente local de impresión: un proceso que
 * corre en la PC junto al terminal, con acceso físico a la impresora
 * térmica ESC/POS y/o al cajón de dinero. El agente sondea (poll) este
 * endpoint cada pocos segundos, imprime los trabajos pendientes y confirma
 * el resultado. Se autentica con el token fijo del terminal (X-Terminal-Token),
 * no con sesión de usuario, porque corre desatendido.
 */
class PrintQueueController extends Controller
{
    public function index(Request $request, SettingsService $settings): JsonResponse
    {
        /** @var Terminal $terminal */
        $terminal = $request->attributes->get('terminal');

        $jobs = PrintJob::query()
            ->where('terminal_id', $terminal->id)
            ->where('status', PrintJobStatus::Pendiente)
            ->orderBy('created_at')
            ->limit(20)
            ->get(['id', 'type', 'content', 'open_drawer', 'created_at']);

        $logo = $settings->get($terminal->business_id, 'ticket_logo_escpos');

        // El agente local toma de acá la configuración de la impresora
        // (IP/puerto o USB) que el admin cargó en Administración → Terminales,
        // así no hace falta repetirla en variables de entorno en cada PC.
        return response()->json([
            'terminal' => [
                'name' => $terminal->name,
                'connection_type' => $terminal->connection_type,
                'ip_address' => $terminal->ip_address,
                'printer_port' => $terminal->printer_port,
                'printer_name' => $terminal->printer_name,
                'usb_path' => $terminal->usb_path,
                'paper_width_chars' => $terminal->paper_width_chars,
            ],
            // Config del ticket de venta (Administración → Configuración →
            // Ticket de venta). El logo solo viaja cuando hay algo por
            // imprimir, para no engordar cada sondeo con el mapa de bits.
            'ticket' => [
                'feed_lines' => (int) $settings->get($terminal->business_id, 'ticket_feed', 3),
                'logo' => ($jobs->isNotEmpty() && $logo) ? $logo : null,
            ],
            'jobs' => $jobs,
        ]);
    }

    public function ack(Request $request, PrintJob $printJob, PrintService $printer): JsonResponse
    {
        $this->authorizeTerminal($request, $printJob);

        $printer->markPrinted($printJob);

        return response()->json(['status' => 'ok']);
    }

    public function fail(Request $request, PrintJob $printJob, PrintService $printer): JsonResponse
    {
        $this->authorizeTerminal($request, $printJob);

        $data = $request->validate(['error' => 'required|string|max:500']);

        $printer->markFailed($printJob, $data['error']);

        return response()->json(['status' => 'ok']);
    }

    private function authorizeTerminal(Request $request, PrintJob $printJob): void
    {
        /** @var Terminal $terminal */
        $terminal = $request->attributes->get('terminal');

        abort_unless($printJob->terminal_id === $terminal->id, 403);
    }
}
