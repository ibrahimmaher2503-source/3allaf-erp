<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Support\DataExchange\ImportWorkbook;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

final class ImportCustomerGroups
{
    public const HEADERS = ['code', 'parent_code', 'name_ar', 'name_en', 'sort_order', 'status'];

    /** @return array{added:int,rejected:list<array<int,mixed>>} */
    public function execute(User $actor, Store $store, string $path): array
    {
        abort_unless($actor->can('customers.edit') && $actor->canAccessStore((int) $store->id), 403);
        $reader = ReaderFactory::createFromFile($path);
        $reader->open($path);
        $raw = [];
        $number = 0;
        try {
            ImportWorkbook::assertMetadata($reader, 'toyjoy.customer-groups.v2', (string) $store->company()->value('code'));
            $sheet = ImportWorkbook::dataSheet($reader);
            foreach ($sheet->getRowIterator() as $row) {
                    $number++;
                    $cells = $row->getCells();
                    $values = array_map(fn ($c) => (string) ($c->getValue() ?? ''), $cells);
                    if ($number === 1) {
                        ImportWorkbook::assertHeaders($values, self::HEADERS, __('The import headers must exactly match the downloaded template.'));

continue;
                    }if ($number > 5001) {
                        throw new InvalidArgumentException(__('The import is limited to 5,000 data rows.'));
                    }if (! array_filter($values, fn ($v) => trim($v) !== '')) {
                        continue;
                    }foreach ($cells as $cell) {
                        if ($cell instanceof FormulaCell || preg_match('/^[=+\-@]/', ltrim((string) $cell->getValue()))) {
                            throw new InvalidArgumentException(__('Formula cells are not allowed in import files.'));
                        }
                    }$raw[] = ['row' => $number, 'data' => array_combine(self::HEADERS, array_pad($values, count(self::HEADERS), ''))];
            }
        } finally {
            $reader->close();
        }
        $known = CustomerGroup::query()->forCompany((int) $store->company_id)->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper((string) $code) => (int) $id])->all();
        $fileCodes = [];
        $rejected = [];
        $valid = [];
        foreach ($raw as $item) {
            $d = $item['data'];
            $code = strtoupper(trim($d['code']));
            $parent = strtoupper(trim($d['parent_code']));
            $errors = [];
            if ($code === '' || trim($d['name_ar']) === '' || trim($d['name_en']) === '') {
                $errors[] = __('Code and bilingual names are required.');
            }if (isset($known[$code]) || isset($fileCodes[$code])) {
                $errors[] = __('Duplicate stable code.');
            }if (! in_array($d['status'], ['active', 'inactive'], true)) {
                $errors[] = __('Invalid status.');
            }if ($parent === $code && $code !== '') {
                $errors[] = __('A group cannot be its own parent.');
            }$fileCodes[$code] = true;
            $errors ? $rejected[] = [$item['row'], ...array_values($d), implode(' | ', $errors)] : $valid[] = ['row' => $item['row'], 'data' => array_merge($d, ['code' => $code, 'parent_code' => $parent])];
        }
        $validCodes = array_column(array_column($valid, 'data'), 'code');
        foreach ($valid as $i => $item) {
            $parent = $item['data']['parent_code'];
            if ($parent !== '' && ! isset($known[$parent]) && ! in_array($parent, $validCodes, true)) {
                $rejected[] = [$item['row'], ...array_values($item['data']), __('Missing parent code.')];
                unset($valid[$i]);
            }
        }
        $added = 0;
        $pending = array_values($valid);
        while ($pending !== []) {
            $progress = false;
            foreach ($pending as $i => $item) {
                $d = $item['data'];
                $parent = $d['parent_code'] === '' ? null : ($known[$d['parent_code']] ?? null);
                if ($d['parent_code'] !== '' && $parent === null) {
                    continue;
                }$group = CustomerGroup::query()->create(['company_id' => $store->company_id, 'parent_id' => $parent, 'code' => $d['code'], 'name_ar' => trim($d['name_ar']), 'name_en' => trim($d['name_en']), 'sort_order' => max(0, (int) $d['sort_order']), 'status' => $d['status'], 'created_by' => $actor->id, 'updated_by' => $actor->id, 'lock_version' => 1]);
                $known[$d['code']] = $group->id;
                $added++;
                unset($pending[$i]);
                $progress = true;
            }if (! $progress) {
                foreach ($pending as $item) {
                    $rejected[] = [$item['row'], ...array_values($item['data']), __('Circular hierarchy or unresolved parent.')];
                }break;
            }$pending = array_values($pending);
        }
        app(RecordAuditEvent::class)->execute('customer_master_data', 'customer_groups_imported', metadata: ['company_id' => $store->company_id, 'added' => $added, 'rejected' => count($rejected)]);

        return ['added' => $added, 'rejected' => $rejected];
    }
}
