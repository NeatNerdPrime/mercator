<?php

namespace App\Services\Graph;

use App\Models\Application;
use App\Models\ApplicationBlock;
use App\Models\ApplicationModule;
use App\Models\ApplicationService;
use App\Models\Cartographer;
use App\Models\Database;
use Illuminate\Support\Collection;

class ApplicationGraphBuilder
{
    /**
     * @param  Collection<int, ApplicationBlock>  $applicationBlocks
     * @param  Collection<int, Application>  $applications
     * @param  Collection<int, ApplicationService>  $applicationServices
     * @param  Collection<int, ApplicationModule>  $applicationModules
     * @param  Collection<int, Database>  $databases
     * @param  array{withHref?: bool, iconResolver?: callable(?int, string): string}  $options
     */
    public function buildDot(
        Collection $applicationBlocks,
        Collection $applications,
        Collection $applicationServices,
        Collection $applicationModules,
        Collection $databases,
        array $options = []
    ): string {
        $withHref = $options['withHref'] ?? true;
        // Resolves a per-record Document icon (or the type's static fallback) to whatever the
        // consumer can actually embed: a route URL for the interactive/WASM renderer (default),
        // or a filesystem path for the server-side Word rasterization.
        $iconResolver = $options['iconResolver'] ?? fn (?int $iconId, string $fallback) => $iconId === null
            ? $fallback
            : route('admin.documents.show', $iconId);

        $lines = ['digraph  {'];

        foreach ($applicationBlocks as $ab) {
            $lines[] = DotNode::withImage('AB'.$ab->id, $iconResolver(null, '/images/applicationblock.png'), [e($ab->name)], $this->href($ab, $withHref));
        }

        foreach ($applications as $application) {
            $image = $iconResolver($application->icon_id, '/images/application.png');
            $lines[] = DotNode::withImage('A'.$application->id, $image, [e($application->name)], $this->href($application, $withHref));

            foreach ($application->services as $service) {
                if ($applicationServices->contains('id', $service->id)) {
                    $lines[] = 'A'.$application->id.' -> AS'.$service->id;
                }
            }

            foreach ($application->databases as $database) {
                if ($databases->contains('id', $database->id)) {
                    $lines[] = 'A'.$application->id.' -> DB'.$database->id;
                }
            }

            if ($application->application_block_id !== null && $applicationBlocks->contains('id', $application->application_block_id)) {
                $lines[] = 'AB'.$application->application_block_id.' -> A'.$application->id;
            }
        }

        foreach ($applicationServices as $service) {
            $lines[] = DotNode::withImage('AS'.$service->id, $iconResolver(null, '/images/applicationservice.png'), [e($service->name)], $this->href($service, $withHref));

            foreach ($service->modules as $module) {
                if ($applicationModules->contains('id', $module->id)) {
                    $lines[] = 'AS'.$service->id.' -> M'.$module->id;
                }
            }
        }

        foreach ($applicationModules as $module) {
            $lines[] = DotNode::withImage('M'.$module->id, $iconResolver(null, '/images/applicationmodule.png'), [e($module->name)], $this->href($module, $withHref));
        }

        foreach ($databases as $database) {
            $image = $iconResolver($database->icon_id, '/images/database.png');
            $lines[] = DotNode::withImage('DB'.$database->id, $image, [e($database->name)], $this->href($database, $withHref));
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @param  Collection<int, Database>  $databases
     * @return array<int, array{path: string, width: string, height: string}>
     */
    public function imageManifest(Collection $applications, Collection $databases): array
    {
        $manifest = [
            ['path' => '/images/applicationblock.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/application.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/applicationservice.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/applicationmodule.png', 'width' => '64px', 'height' => '64px'],
            ['path' => '/images/database.png', 'width' => '64px', 'height' => '64px'],
        ];

        if (Cartographer::canAccess(Application::class)) {
            foreach ($applications as $application) {
                if ($application->icon_id !== null) {
                    $manifest[] = ['path' => route('admin.documents.show', $application->icon_id), 'width' => '64px', 'height' => '64px'];
                }
            }
        }

        if (Cartographer::canAccess(Database::class)) {
            foreach ($databases as $database) {
                if ($database->icon_id !== null) {
                    $manifest[] = ['path' => route('admin.documents.show', $database->icon_id), 'width' => '64px', 'height' => '64px'];
                }
            }
        }

        return $manifest;
    }

    private function href(ApplicationBlock|Application|ApplicationService|ApplicationModule|Database $model, bool $withHref): string
    {
        return $withHref ? ' href="#'.$model->getUID().'"' : '';
    }
}
