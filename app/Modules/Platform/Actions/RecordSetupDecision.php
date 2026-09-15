<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\SetupDecision;
use App\Modules\Platform\Support\InitialSetupStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RecordSetupDecision
{
    public function execute(string $stepKey, string $decision): SetupDecision
    {
        Gate::authorize('company_settings.edit');

        $step = collect(app(InitialSetupStatus::class)->snapshot()['steps'])->firstWhere('key', $stepKey);
        if ($step === null || ! $step['can_access']) {
            throw ValidationException::withMessages(['setup_step' => __('This setup step is not available to your account.')]);
        }

        $expected = $step['required'] ? 'deferred' : 'skipped';
        if ($decision !== $expected || $step['complete']) {
            throw ValidationException::withMessages(['decision' => __('This setup decision is not valid for the selected step.')]);
        }

        $company = Company::query()->where('status', 'active')->orderBy('id')->firstOrFail();

        return DB::transaction(function () use ($company, $stepKey, $decision): SetupDecision {
            $record = SetupDecision::query()->create([
                'company_id' => $company->id,
                'step_key' => $stepKey,
                'decision' => $decision,
                'decided_by' => auth()->id(),
                'decided_at' => now(),
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'setup_step_'.$decision,
                source: $record,
                before: null,
                after: $record->only(['company_id', 'step_key', 'decision', 'decided_by', 'decided_at']),
                metadata: ['setup_step' => $stepKey],
            );

            return $record;
        });
    }
}
