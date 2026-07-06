<?php

namespace App\Services\Graph;

use App\Models\Activity;
use App\Models\Actor;
use App\Models\Cartographer;
use App\Models\Information;
use App\Models\MacroProcessus;
use App\Models\Operation;
use App\Models\Process;
use App\Models\Task;
use Illuminate\Support\Collection;

class InformationSystemGraphBuilder
{
    /**
     * @param  Collection<int, MacroProcessus>  $macroProcessuses
     * @param  Collection<int, Process>  $processes
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, Operation>  $operations
     * @param  Collection<int, Task>  $tasks
     * @param  Collection<int, Actor>  $actors
     * @param  Collection<int, Information>  $informations
     * @param  array{withHref?: bool, iconPathResolver?: callable(string): string}  $options
     */
    public function buildDot(
        Collection $macroProcessuses,
        Collection $processes,
        Collection $activities,
        Collection $operations,
        Collection $tasks,
        Collection $actors,
        Collection $informations,
        array $options = []
    ): string {
        $withHref = $options['withHref'] ?? true;
        // Node images are always fixed-per-type (no per-record Document icon here). The interactive
        // screen wants a web-relative path (fetched over HTTP by the WASM renderer); server-side
        // rasterization (Word report) needs a real filesystem path, hence this resolver hook.
        $iconPath = $options['iconPathResolver'] ?? fn (string $path) => $path;

        $lines = ['digraph  {'];

        foreach ($macroProcessuses as $macroProcess) {
            $lines[] = $this->node('MP', $macroProcess->id, $macroProcess->name, $iconPath('/images/macroprocess.png'), $macroProcess->getUID(), $withHref);
        }

        foreach ($processes as $process) {
            $lines[] = $this->node('P', $process->id, $process->name, $iconPath('/images/process.png'), $process->getUID(), $withHref);

            foreach ($process->activities as $activity) {
                if ($activities->contains('id', $activity->id)) {
                    $lines[] = 'P'.$process->id.' -> A'.$activity->id;
                }
            }

            if (Cartographer::canAccess(Information::class)) {
                foreach ($process->information as $information) {
                    if ($informations->contains('id', $information->id)) {
                        $lines[] = 'P'.$process->id.' -> I'.$information->id;
                    }
                }
            }

            if ($process->macroprocess_id !== null && $macroProcessuses->contains('id', $process->macroprocess_id)) {
                $lines[] = 'MP'.$process->macroprocess_id.' -> P'.$process->id;
            }

            foreach ($process->operations as $operation) {
                if ($operations->contains('id', $operation->id)) {
                    $lines[] = 'P'.$process->id.' -> O'.$operation->id;
                }
            }
        }

        foreach ($activities as $activity) {
            $lines[] = $this->node('A', $activity->id, $activity->name, $iconPath('/images/activity.png'), $activity->getUID(), $withHref);

            foreach ($activity->operations as $operation) {
                if ($operations->contains('id', $operation->id)) {
                    $lines[] = 'A'.$activity->id.' -> O'.$operation->id;
                }
            }
        }

        foreach ($operations as $operation) {
            $lines[] = $this->node('O', $operation->id, $operation->name, $iconPath('/images/operation.png'), $operation->getUID(), $withHref);

            foreach ($operation->tasks as $task) {
                if ($tasks->contains('id', $task->id)) {
                    $lines[] = 'O'.$operation->id.' -> T'.$task->id;
                }
            }

            foreach ($operation->actors as $actor) {
                if ($actors->contains('id', $actor->id)) {
                    $lines[] = 'O'.$operation->id.' -> ACT'.$actor->id;
                }
            }
        }

        foreach ($tasks as $task) {
            $lines[] = $this->node('T', $task->id, $task->name, $iconPath('/images/task.png'), $task->getUID(), $withHref);
        }

        foreach ($actors as $actor) {
            $lines[] = $this->node('ACT', $actor->id, $actor->name, $iconPath('/images/actor.png'), $actor->getUID(), $withHref);
        }

        foreach ($informations as $information) {
            $lines[] = $this->node('I', $information->id, $information->name, $iconPath('/images/information.png'), $information->getUID(), $withHref);

            foreach ($information->children as $child) {
                if ($informations->contains('id', $child->id)) {
                    $lines[] = 'I'.$information->id.' -> I'.$child->id;
                }
            }
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * @return array<int, array{path: string, width: string, height: string}>
     */
    public function imageManifest(): array
    {
        return [
            ['path' => '/images/macroprocess.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/process.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/activity.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/operation.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/task.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/actor.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/information.png', 'width' => '64px', 'height' => '64px'],
        ];
    }

    private function node(string $prefix, int $id, ?string $name, string $image, string $uid, bool $withHref): string
    {
        $href = $withHref ? ' href="#'.$uid.'"' : '';

        return $prefix.$id.' [label="'.e($name ?? '').'" shape=none labelloc="b"  width=1 height=1.1 image="'.$image.'"'.$href.']';
    }
}
