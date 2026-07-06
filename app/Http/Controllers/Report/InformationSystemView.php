<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Cartographer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use App\Models\Activity;
use App\Models\Actor;
use App\Models\Information;
use App\Models\MacroProcessus;
use App\Models\Operation;
use App\Models\Process;
use App\Models\Task;
use App\Services\Graph\InformationSystemGraphBuilder;
use Symfony\Component\HttpFoundation\Response;

class InformationSystemView extends Controller
{
    /**
     * Prepare data for and render the information system report view.
     *
     * Builds collections of macroprocesses, processes, activities, operations, tasks, actors,
     * and informations filtered by the optional `macroprocess` and `process` request inputs,
     * stores selected identifiers in session, and returns the report view.
     *
     * @param  Request  $request  HTTP request; may include `macroprocess` and `process` inputs used to filter results and persisted to session.
     * @return View The rendered 'admin/reports/information_system' view with these variables:
     *              - `all_macroprocess`: all MacroProcessus sorted by name
     *              - `macroProcessuses`: selected MacroProcessus collection
     *              - `processes`: filtered Process collection
     *              - `all_process`: all processes belonging to selected macroprocesses or null
     *              - `activities`: filtered Activity collection
     *              - `operations`: filtered Operation collection
     *              - `tasks`: filtered Task collection
     *              - `actors`: filtered Actor collection
     *              - `informations`: filtered Information collection
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException If the current user is denied the 'reports_access' permission (responds with 403).
     */
    public function generate(Request $request): View
    {
        $allowed = Gate::allows('explore_access') || Cartographer::canAccessAny([MacroProcessus::class, Process::class, Activity::class, Operation::class, Task::class, Actor::class, Information::class]);
        abort_if(!$allowed, Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->macroprocess == null) {
            $request->session()->put('macroprocess', null);
            $macroprocess = null;
            $request->session()->put('process', null);
            $process = null;
        } else {
            if ($request->macroprocess != null) {
                $macroprocess = intval($request->macroprocess);
                $request->session()->put('macroprocess', $macroprocess);
            } else {
                $macroprocess = $request->session()->get('macroprocess');
            }

            if ($request->process == null) {
                $request->session()->put('process', null);
                $process = null;
            } elseif ($request->process != null) {
                $process = intval($request->process);
                $request->session()->put('process', $process);
            } else {
                $process = $request->session()->get('process');
            }
        }

        $all_macroprocess = Cartographer::scopedQuery(MacroProcessus::query())->orderBy('name')->get();

        if ($macroprocess !== null) {
            $macroProcessuses = MacroProcessus::where('macro_processuses.id', $macroprocess)->get();

            // TODO : improve me
            $processes = Cartographer::scopedQuery(Process::query())->get()->sortBy('name')
                ->filter(function ($item) use ($macroProcessuses, $process) {
                    if ($process !== null) {
                        return $item->id === $process;
                    }
                    foreach ($macroProcessuses as $macroprocess) {
                        foreach ($macroprocess->processes as $process) {
                            if ($item->id === $process->id) {
                                return true;
                            }
                        }
                    }

                    return false;
                });

            // TODO : improve me
            $all_process = Cartographer::scopedQuery(Process::query())->get()->sortBy('name')
                ->filter(function ($item) use ($macroProcessuses, $process) {
                    foreach ($macroProcessuses as $macroprocess) {
                        foreach ($macroprocess->processes as $process) {
                            if ($item->id === $process->id) {
                                return true;
                            }
                        }
                    }

                    return false;
                });

            // TODO : improve me
            $activities = Cartographer::scopedQuery(Activity::query())->get()->sortBy('name')
                ->filter(function ($item) use ($processes) {
                    foreach ($item->processes as $p) {
                        foreach ($processes as $process) {
                            if ($p->id === $process->id) {
                                return true;
                            }
                        }
                    }

                    return false;
                });

            // TODO : improve me
            $operations = Cartographer::scopedQuery(Operation::query())->get()->sortBy('name')
                ->filter(function ($item) use ($activities) {
                    foreach ($item->activities as $o) {
                        foreach ($activities as $activity) {
                            if ($o->id === $activity->id) {
                                return true;
                            }
                        }
                    }

                    return false;
                });

            // TODO : improve me
            $tasks = Cartographer::scopedQuery(Task::query())->get()->sortBy('name')
                ->filter(function ($item) use ($operations) {
                    foreach ($operations as $operation) {
                        foreach ($operation->tasks as $task) {
                            if ($item->id === $task->id) {
                                return true;
                            }
                        }
                    }

                    return false;
                });

            // TODO : improve me
            $actors = Cartographer::scopedQuery(Actor::query()->orderBy('name'))->get()
                ->filter(function ($item) use ($operations) {
                    foreach ($operations as $operation) {
                        foreach ($operation->actors as $actor) {
                            if ($item->id === $actor->id) {
                                return true;
                            }
                        }
                    }

                    return false;
                });

            // Collecter les IDs des informations liés aux processus
            $directIds = collect($processes)
                ->flatMap(fn($process) => $process->information->pluck('id'))
                ->unique();

            // Descendre récursivement dans les enfants
            $allIds = $directIds->toArray();
            $toProcess = $directIds->toArray();

            while (!empty($toProcess)) {
                $childIds = Information::query()->whereIn('id', $toProcess)
                    ->with('children:id')
                    ->get()
                    ->flatMap(fn($info) => $info->children->pluck('id'))
                    ->diff($allIds)   // évite les cycles et les doublons
                    ->unique()
                    ->values();

                $toProcess = $childIds->toArray();
                $allIds = array_merge($allIds, $toProcess);
            }

            $informations = Information::query()->whereIn('id', $allIds)
                ->orderBy('name')
                ->get();

        } else {
            $macroProcessuses = Cartographer::scopedQuery(MacroProcessus::query())->orderBy('name')->get();
            $processes = Cartographer::scopedQuery(Process::query())->orderBy('name')->get();
            $activities = Cartographer::scopedQuery(Activity::query())->orderBy('name')->get();
            $operations = Cartographer::scopedQuery(Operation::query())->orderBy('name')->get();
            $tasks = Cartographer::scopedQuery(Task::query())->orderBy('name')->get();
            $actors = Cartographer::scopedQuery(Actor::query())->orderBy('name')->get();
            $informations = Cartographer::scopedQuery(Information::query()->orderBy('name')->with('children'))->get();
            $all_process = null;
        }

        $graphBuilder = new InformationSystemGraphBuilder;

        return view('admin/reports/information_system')
            ->with('all_macroprocess', $all_macroprocess)
            ->with('macroProcessuses', $macroProcessuses)
            ->with('processes', $processes)
            ->with('all_process', $all_process)
            ->with('activities', $activities)
            ->with('operations', $operations)
            ->with('tasks', $tasks)
            ->with('actors', $actors)
            ->with('informations', $informations)
            ->with('dotSrc', $graphBuilder->buildDot($macroProcessuses, $processes, $activities, $operations, $tasks, $actors, $informations))
            ->with('imageManifest', $graphBuilder->imageManifest());
    }
}
