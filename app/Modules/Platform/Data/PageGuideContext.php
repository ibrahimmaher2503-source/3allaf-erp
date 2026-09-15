<?php

declare(strict_types=1);

namespace App\Modules\Platform\Data;

use App\Models\User;
use App\Modules\Platform\Support\TutorialModuleRegistry;
use App\Modules\Platform\Support\TutorialRegistry;
use Illuminate\Support\Facades\Gate;

final readonly class PageGuideContext
{
    public function __construct(
        public string $screenId,
        public string $routeName,
        public string $locale,
        public array $guide,
        public array $allowedActions,
        public string $state = 'default',
        public ?array $module = null,
    ) {}

    public static function fromRequest(?User $user): ?self
    {
        $routeName = request()->route()?->getName();
        $guide = TutorialRegistry::forRoute($routeName);

        if (! $user || ! $routeName || ! $guide) {
            return null;
        }

        $allowedActions = array_values(array_filter(
            $guide['approved_actions'],
            fn (array $action): bool => Gate::allows($action['required_permission']),
        ));

        $guide['approved_actions'] = $allowedActions;
        $guide['permissions'] = array_values(array_filter(
            $guide['permissions'],
            fn (string $permission): bool => Gate::allows($permission),
        ));

        return new self(
            screenId: $guide['screen_id'],
            routeName: $routeName,
            locale: (string) app()->getLocale(),
            guide: $guide,
            allowedActions: $allowedActions,
            module: TutorialModuleRegistry::forRoute($routeName),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'screen_id' => $this->screenId,
            'route_name' => $this->routeName,
            'locale' => $this->locale,
            'title' => $this->guide['title'],
            'purpose' => $this->guide['purpose'],
            'when_to_use' => $this->guide['when_to_use'],
            'route_names' => $this->guide['route_names'],
            'module' => $this->module,
            'permissions' => $this->guide['permissions'],
            'approved_actions' => $this->allowedActions,
            'stories' => $this->guide['stories'],
            'flows' => $this->guide['flows'],
            'acceptance_criteria' => $this->guide['acceptance_criteria'],
            'sections' => $this->guide['sections'],
            'tour_steps' => $this->guide['tour_steps'],
            'version' => $this->guide['version'],
            'updated_at' => $this->guide['updated_at'],
        ];
    }
}
