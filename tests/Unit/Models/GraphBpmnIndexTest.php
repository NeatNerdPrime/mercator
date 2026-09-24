<?php

use App\Models\Activity;
use App\Models\Graph;
use App\Models\Process;

it('returns the BPMN graphs referencing an object', function () {
    $activity = Activity::factory()->create();
    $other = Activity::factory()->create();

    $graph = Graph::factory()->create([
        'class' => 2,
        'content' => '{"cells":[{"ref":"#'.$activity->getUID().'"}]}',
    ]);
    // Not a BPMN graph: must be ignored even if it references the activity
    Graph::factory()->create([
        'class' => 1,
        'content' => '{"ref":"#'.$activity->getUID().'"}',
    ]);

    expect($activity->graphs()->pluck('id')->all())->toBe([$graph->id])
        ->and($activity->graphs()->first()->name)->toBe($graph->name)
        ->and($other->graphs())->toBeEmpty();
});

it('does not match an object whose UID is a prefix of the referenced one', function () {
    $process = Process::factory()->create();

    Graph::factory()->create([
        'class' => 2,
        'content' => '{"ref":"#'.$process->getUID().'0"}',
    ]);

    expect($process->graphs())->toBeEmpty();
});

it('sees graphs saved or deleted after the index was built', function () {
    $activity = Activity::factory()->create();

    expect($activity->graphs())->toBeEmpty();

    $graph = Graph::factory()->create([
        'class' => 2,
        'content' => '{"ref":"#'.$activity->getUID().'"}',
    ]);

    expect(Graph::bpmnGraphsReferencing($activity->getUID())->pluck('id')->all())->toBe([$graph->id]);

    $graph->delete();

    expect(Graph::bpmnGraphsReferencing($activity->getUID()))->toBeEmpty();
});
