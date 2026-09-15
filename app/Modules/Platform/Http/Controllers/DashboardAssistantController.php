<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Actions\SaveUiPreferences;
use App\Modules\Platform\Actions\SaveTutorialProgress;
use App\Modules\Platform\Data\PageGuideContext;
use App\Modules\Platform\Support\TutorialModuleRegistry;
use App\Modules\Platform\Support\TutorialRegistry;
use App\Modules\Platform\Support\UserFlowRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class DashboardAssistantController
{
    public function preferences(Request $request, SaveUiPreferences $action): JsonResponse
    {
        $preference = $action->execute($request->user(), $request->validate([
            'appearance' => ['sometimes', 'string'], 'accent_color' => ['sometimes', 'string'], 'sidebar_mode' => ['sometimes', 'string'],
            'navbar_mode' => ['sometimes', 'string'], 'content_width' => ['sometimes', 'string'], 'table_density' => ['sometimes', 'string'],
            'font_scale' => ['sometimes', 'string'], 'reduced_motion' => ['sometimes'],
        ]));

        return response()->json($preference->only(['appearance', 'accent_color', 'sidebar_mode', 'navbar_mode', 'content_width', 'table_density', 'font_scale', 'reduced_motion']));
    }

    public function screen(Request $request, string $screenId): Response
    {
        $guide = TutorialRegistry::find($screenId);
        abort_unless($guide, 404);
        abort_unless(collect($guide['permissions'])->contains(fn (string $permission): bool => Gate::allows($permission)), 403);

        $guide['permissions'] = array_values(array_filter(
            $guide['permissions'],
            fn (string $permission): bool => Gate::allows($permission),
        ));

        $allowedActions = array_values(array_filter(
            $guide['approved_actions'],
            fn (array $action): bool => Gate::allows($action['required_permission']),
        ));
        $context = new PageGuideContext($screenId, $guide['route_names'][0], (string) app()->getLocale(), $guide, $allowedActions, 'full-guide', TutorialModuleRegistry::forRoute($guide['route_names'][0]));
        $payload = $context->toArray();
        $payload['related_flows'] = array_values(array_filter(array_map(
            function (string $flowId): ?array {
                $flow = UserFlowRegistry::find($flowId);

                if (! $flow) {
                    return null;
                }

                return [
                    'flow_id' => $flow['flow_id'],
                    'title' => $flow['title'],
                    'actor' => $flow['actor'],
                ];
            },
            $guide['flows'],
        )));

        return response()
            ->view('platform.help.screen', ['context' => $payload])
            ->header('Cache-Control', 'private, no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    public function tutorialProgress(Request $request, SaveTutorialProgress $action): JsonResponse
    {
        $data = $request->validate([
            'screen_id' => ['required', 'string', 'max:80'],
            'status' => ['required', 'string', 'in:'.implode(',', SaveTutorialProgress::STATUSES)],
        ]);

        abort_unless(TutorialRegistry::find($data['screen_id']), 404);

        $preference = $action->execute($request->user(), $data['screen_id'], $data['status']);

        return response()->json(['tutorial_progress' => $preference->tutorial_progress ?? []]);
    }

    public function flow(Request $request, string $flowId): Response
    {
        $flow = UserFlowRegistry::find($flowId);
        abort_unless($flow, 404);
        abort_unless(collect($flow['source_screen_ids'])->contains(fn (string $screenId): bool => $this->canViewScreen($screenId)), 403);

        $flow['required_permissions'] = array_values(array_filter(
            $flow['required_permissions'],
            fn (string $permission): bool => Gate::allows($permission),
        ));

        $guideLinks = array_values(array_filter(array_map(
            function (string $screenId): ?array {
                $guide = TutorialRegistry::find($screenId);

                if ($guide === null || ! $this->canViewScreen($screenId)) {
                    return null;
                }

                $routeName = $guide['route_names'][0];

                return [
                    'screen_id' => $screenId,
                    'title' => $guide['title'],
                    'href' => route('platform.help.screen', ['screenId' => $screenId]),
                    'route_name' => $routeName,
                    'is_readiness' => str_ends_with($routeName, '.readiness'),
                ];
            },
            $flow['destination_screen_ids'],
        )));

        return response()
            ->view('platform.help.flow', ['flow' => $flow, 'guideLinks' => $guideLinks])
            ->header('Cache-Control', 'private, no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    private function canViewScreen(string $screenId): bool
    {
        $guide = TutorialRegistry::find($screenId);

        return $guide !== null && collect($guide['permissions'])->contains(fn (string $permission): bool => Gate::allows($permission));
    }
}
