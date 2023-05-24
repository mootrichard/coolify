<?php

namespace App\Http\Livewire\Project\Application;

use App\Models\Application;
use Livewire\Component;
use Illuminate\Support\Str;
use Visus\Cuid2\Cuid2;

class Rollback extends Component
{
    public Application $application;
    public $images = [];
    public string|null $current;
    public array $parameters;

    public function mount()
    {
        $this->parameters = get_parameters();
    }
    public function rollbackImage($tag)
    {
        $deployment_uuid = new Cuid2(7);

        queue_application_deployment(
            application: $this->application,
            metadata: [
                'deployment_uuid' => $deployment_uuid,
                'application_uuid' => $this->application->uuid,
                'force_rebuild' => false,
                'commit' => $tag,
            ]
        );

        return redirect()->route('project.application.deployments', [
            'project_uuid' => $this->parameters['project_uuid'],
            'application_uuid' => $this->parameters['application_uuid'],
            'environment_name' => $this->parameters['environment_name'],
        ]);
    }
    public function loadImages()
    {
        try {
            $image = $this->application->uuid;
            $output = instant_remote_process([
                "docker inspect --format='{{.Config.Image}}' {$this->application->uuid}",
            ], $this->application->destination->server, throwError: false);
            $current_tag = Str::of($output)->trim()->explode(":");
            $this->current = data_get($current_tag, 1);

            $output = instant_remote_process([
                "docker images --format '{{.Repository}}#{{.Tag}}#{{.CreatedAt}}'",
            ], $this->application->destination->server);
            $this->images = Str::of($output)->trim()->explode("\n")->filter(function ($item) use ($image) {
                return Str::of($item)->contains($image);
            })->map(function ($item) {
                $item = Str::of($item)->explode('#');
                if ($item[1] === $this->current) {
                    // $is_current = true;
                }
                return [
                    'tag' => $item[1],
                    'created_at' => $item[2],
                    'is_current' => $is_current ?? null,
                ];
            })->toArray();
        } catch (\Throwable $e) {
            return general_error_handler($e, $this);
        }
    }
}
