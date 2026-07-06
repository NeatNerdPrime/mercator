<?php

namespace App\Services\Graph;

use App\Models\Application;
use App\Models\DataProcessing;
use App\Models\MacroProcessus;
use App\Models\Process;
use Illuminate\Support\Collection;

class GdprGraphBuilder
{
    /**
     * @param  Collection<int, MacroProcessus>  $macroProcessuses
     * @param  Collection<int, Process>  $processes
     * @param  Collection<int, DataProcessing>  $dataProcessings
     * @param  Collection<int, Application>  $applications
     * @param  array{withHref?: bool, iconPathResolver?: callable(string): string}  $options
     */
    public function buildDot(Collection $macroProcessuses, Collection $processes, Collection $dataProcessings, Collection $applications, array $options = []): string
    {
        $withHref = $options['withHref'] ?? true;
        $iconPath = $options['iconPathResolver'] ?? fn (string $webPath) => $webPath;

        $lines = ['digraph  {'];

        foreach ($macroProcessuses as $macroProcess) {
            $lines[] = DotNode::withImage('MP'.$macroProcess->id, $iconPath('/images/macroprocess.png'), [e($macroProcess->name)]);
        }

        foreach ($processes as $process) {
            $lines[] = DotNode::withImage('P'.$process->id, $iconPath('/images/process.png'), [e($process->name)]);

            if ($process->macroprocess_id !== null && $macroProcessuses->contains('id', $process->macroprocess_id)) {
                $lines[] = 'MP'.$process->macroprocess_id.' -> P'.$process->id;
            }

            foreach ($process->dataProcesses as $dp) {
                if ($dataProcessings->contains('id', $dp->id)) {
                    $lines[] = 'P'.$process->id.' -> DP'.$dp->id;
                }
            }
        }

        foreach ($dataProcessings as $dp) {
            $href = $withHref ? ' href="#'.$dp->getUID().'"' : '';
            $lines[] = DotNode::withImage('DP'.$dp->id, $iconPath('/images/dataprocessing.png'), [e($dp->name)], $href);

            foreach ($dp->applications as $app) {
                if ($applications->contains('id', $app->id)) {
                    $lines[] = 'DP'.$dp->id.' -> APP'.$app->id;
                }
            }
        }

        foreach ($applications as $app) {
            $lines[] = DotNode::withImage('APP'.$app->id, $iconPath('/images/application.png'), [e($app->name)]);
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
            ['path' => '/images/dataprocessing.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/application.png', 'width' => '64px', 'height' => '64px'],
        ];
    }
}
