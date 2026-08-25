<?php

use App\Enums\PrintJobType;
use App\Models\Branch;
use App\Models\Business;
use App\Models\PrintJob;
use App\Models\Terminal;

function printQueueContext(): array
{
    $business = Business::factory()->create();
    $branch = Branch::factory()->for($business)->create();
    $terminal = Terminal::factory()->create(['business_id' => $business->id, 'branch_id' => $branch->id]);

    return [$business, $branch, $terminal];
}

test('sin token el endpoint responde 401', function () {
    $this->getJson('/api/print-jobs')->assertUnauthorized();
});

test('con un token invalido el endpoint responde 401', function () {
    $this->getJson('/api/print-jobs', ['X-Terminal-Token' => 'token-falso'])->assertUnauthorized();
});

test('el agente obtiene solo los trabajos pendientes de su propia terminal', function () {
    [$business, $branch, $terminal] = printQueueContext();
    $otherTerminal = Terminal::factory()->create(['business_id' => $business->id, 'branch_id' => $branch->id]);

    $mine = PrintJob::create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id,
        'type' => PrintJobType::TicketVenta, 'status' => 'pendiente', 'content' => 'hola',
    ]);
    PrintJob::create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'terminal_id' => $otherTerminal->id,
        'type' => PrintJobType::TicketVenta, 'status' => 'pendiente', 'content' => 'de otro',
    ]);
    PrintJob::create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id,
        'type' => PrintJobType::TicketVenta, 'status' => 'impreso', 'content' => 'ya impreso',
    ]);

    $response = $this->getJson('/api/print-jobs', ['X-Terminal-Token' => $terminal->api_token]);

    $response->assertOk();
    $ids = collect($response->json('jobs'))->pluck('id');
    expect($ids)->toContain($mine->id);
    expect($ids)->toHaveCount(1);
});

test('el agente confirma un trabajo impreso via ack', function () {
    [, , $terminal] = printQueueContext();

    $job = PrintJob::create([
        'business_id' => $terminal->business_id, 'branch_id' => $terminal->branch_id, 'terminal_id' => $terminal->id,
        'type' => PrintJobType::TicketVenta, 'status' => 'pendiente', 'content' => 'hola',
    ]);

    $this->postJson("/api/print-jobs/{$job->id}/ack", [], ['X-Terminal-Token' => $terminal->api_token])
        ->assertOk();

    expect($job->fresh()->status->value)->toBe('impreso');
});

test('el agente reporta un error de impresion via fail', function () {
    [, , $terminal] = printQueueContext();

    $job = PrintJob::create([
        'business_id' => $terminal->business_id, 'branch_id' => $terminal->branch_id, 'terminal_id' => $terminal->id,
        'type' => PrintJobType::TicketVenta, 'status' => 'pendiente', 'content' => 'hola',
    ]);

    $this->postJson("/api/print-jobs/{$job->id}/fail", ['error' => 'Sin papel'], ['X-Terminal-Token' => $terminal->api_token])
        ->assertOk();

    $job->refresh();
    expect($job->status->value)->toBe('error');
    expect($job->error_message)->toBe('Sin papel');
});

test('una terminal no puede confirmar el trabajo de otra terminal', function () {
    [$business, $branch, $terminal] = printQueueContext();
    $otherTerminal = Terminal::factory()->create(['business_id' => $business->id, 'branch_id' => $branch->id]);

    $job = PrintJob::create([
        'business_id' => $business->id, 'branch_id' => $branch->id, 'terminal_id' => $otherTerminal->id,
        'type' => PrintJobType::TicketVenta, 'status' => 'pendiente', 'content' => 'hola',
    ]);

    $this->postJson("/api/print-jobs/{$job->id}/ack", [], ['X-Terminal-Token' => $terminal->api_token])
        ->assertForbidden();
});
