<?php

declare(strict_types=1);

namespace App\Support\DataExchange;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\Common\Creator\WriterFactory;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

final class MasterDataDocument
{
    /** @param list<string> $headers @param iterable<array<int, mixed>> $rows @param list<array<int, mixed>> $instructions */
    public function xlsx(string $filename, array $headers, iterable $rows, array $instructions = []): BinaryFileResponse
    {
        $base = tempnam(sys_get_temp_dir(), 'rajeh-xlsx-');
        $path = $base.'.xlsx';
        @unlink($base);
        $writer = WriterFactory::createFromFile($path);
        $writer->openToFile($path);
        if ($instructions !== []) {
            $writer->getCurrentSheet()->setName('Instructions');
            foreach ($instructions as $instruction) {
                $writer->addRow(Row::fromValues(array_map([$this, 'safeCell'], $instruction)));
            }
            $writer->addNewSheetAndMakeItCurrent()->setName('Data');
        }
        $writer->addRow(Row::fromValues($headers));
        foreach ($rows as $values) {
            $writer->addRow(Row::fromValues(array_map([$this, 'safeCell'], $values)));
        }
        $writer->close();

        return response()->download($path, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }

    /**
     * Build a streaming, schema-identified import workbook. Data Entry always
     * keeps the importer's exact machine headers on row one; reference sheets
     * are current snapshots and never masquerade as importable data.
     *
     * @param list<string> $headers
     * @param iterable<array<int, mixed>> $rows
     * @param list<array{ar:string,en:string}> $instructions
     * @param array<string, iterable<array<int, mixed>>> $references
     * @param list<int> $requiredColumns zero-based
     * @param list<int> $referenceColumns zero-based
     * @param array<int, string> $formats zero-based column => Excel number format
     * @param array<int, array{sheet:string,last_row:int}> $validations zero-based column => reference-sheet range
     */
    public function importTemplate(
        string $filename,
        string $schema,
        array $headers,
        iterable $rows,
        array $instructions,
        array $references = [],
        array $requiredColumns = [],
        array $referenceColumns = [],
        array $formats = [],
        ?string $companyIdentity = null,
        array $validations = [],
    ): BinaryFileResponse {
        $base = tempnam(sys_get_temp_dir(), 'rajeh-template-');
        $path = $base.'.xlsx';
        @unlink($base);

        $writer = WriterFactory::createFromFile($path);
        $writer->openToFile($path);
        $titleStyle = (new Style)->setFontBold()->setFontSize(14)->setFontColor(Color::WHITE)->setBackgroundColor('0F766E');
        $headerStyle = (new Style)->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor('0F766E')->setShouldWrapText();
        $requiredStyle = (new Style)->setBackgroundColor('FEF3C7');
        $referenceStyle = (new Style)->setBackgroundColor('E5E7EB')->setFontColor('374151');

        $instructionsSheet = $writer->getCurrentSheet()->setName('Instructions');
        $instructionsSheet->setColumnWidthForRange(28, 1, 1);
        $instructionsSheet->setColumnWidthForRange(90, 2, 2);
        $writer->addRow(Row::fromValues([str_starts_with(app()->getLocale(), 'ar') ? 'تعليمات الاستيراد' : 'Import instructions', $schema], $titleStyle));
        $writer->addRow(Row::fromValues([str_starts_with(app()->getLocale(), 'ar') ? 'هوية المخطط' : 'Schema identity', $schema]));
        $writer->addRow(Row::fromValues([str_starts_with(app()->getLocale(), 'ar') ? 'تاريخ التوليد' : 'Generated at', now()->utc()->format('Y-m-d\TH:i:s\Z')]));
        $writer->addRow(Row::fromValues([str_starts_with(app()->getLocale(), 'ar') ? 'نطاق الشركة' : 'Company scope', $companyIdentity ?? '—']));
        $writer->addRow(Row::fromValues([str_starts_with(app()->getLocale(), 'ar') ? 'قاعدة الرؤوس' : 'Header contract', str_starts_with(app()->getLocale(), 'ar') ? 'لا تغيّر أسماء أعمدة ورقة Data Entry أو ترتيبها.' : 'Do not rename or reorder Data Entry headers.']));
        foreach ($instructions as $instruction) {
            $primary = str_starts_with(app()->getLocale(), 'ar') ? $instruction['ar'] : $instruction['en'];
            $secondary = str_starts_with(app()->getLocale(), 'ar') ? $instruction['en'] : $instruction['ar'];
            $writer->addRow(Row::fromValues([$primary, $secondary]));
        }

        $dataSheet = $writer->addNewSheetAndMakeItCurrent()->setName('Data Entry');
        $view = (new SheetView)->setFreezeRow(2);
        if (str_starts_with(app()->getLocale(), 'ar')) {
            $view->setRightToLeft(true);
        }
        $dataSheet->setSheetView($view);
        foreach ($headers as $index => $header) {
            $dataSheet->setColumnWidth(in_array($index, $requiredColumns, true) ? 22 : 18, $index + 1);
        }
        $writer->addRow(Row::fromValues($headers, $headerStyle));
        $rowCount = 1;
        foreach ($rows as $values) {
            $styles = [];
            foreach ($headers as $index => $_header) {
                $style = in_array($index, $referenceColumns, true) ? clone $referenceStyle : (in_array($index, $requiredColumns, true) ? clone $requiredStyle : new Style);
                if (isset($formats[$index])) {
                    $style->setFormat($formats[$index]);
                }
                $styles[$index] = $style;
            }
            $writer->addRow(Row::fromValuesWithStyles(array_map([$this, 'safeCell'], array_pad(array_slice($values, 0, count($headers)), count($headers), '')), null, $styles));
            $rowCount++;
        }
        if ($rowCount === 1) {
            $writer->addRow(Row::fromValuesWithStyles(array_fill(0, count($headers), ''), null, collect($headers)->mapWithKeys(fn ($_header, int $index): array => [$index => in_array($index, $requiredColumns, true) ? clone $requiredStyle : (in_array($index, $referenceColumns, true) ? clone $referenceStyle : new Style)])->all()));
            $rowCount++;
        }
        $dataSheet->setAutoFilter(new AutoFilter(0, 1, max(0, count($headers) - 1), $rowCount));
        $dataSheet->setPrintTitleRows('1:1');

        foreach ($references as $name => $referenceRows) {
            $sheet = $writer->addNewSheetAndMakeItCurrent()->setName(mb_substr((string) $name, 0, 31));
            $sheet->setSheetView((new SheetView)->setFreezeRow(2));
            $count = 0;
            foreach ($referenceRows as $values) {
                $writer->addRow(Row::fromValues(array_map([$this, 'safeCell'], $values), $count === 0 ? $headerStyle : null));
                $count++;
            }
            if ($count > 0) {
                $sheet->setColumnWidthForRange(22, 1, max(1, count((array) $values)));
                $sheet->setAutoFilter(new AutoFilter(0, 1, max(0, count((array) $values) - 1), $count));
                $sheet->setPrintTitleRows('1:1');
            }
        }
        $writer->close();
        $this->enhanceImportWorkbook($path, $schema, $headers, $rowCount, $validations);

        return response()->download($path, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }

    /**
     * Add the Excel table and reference-backed list validations that OpenSpout
     * deliberately does not model. The payload remains streaming-generated;
     * this only edits the small worksheet/workbook metadata entries in-place.
     *
     * @param list<string> $headers
     * @param array<int, array{sheet:string,last_row:int}> $validations
     */
    private function enhanceImportWorkbook(string $path, string $schema, array $headers, int $rowCount, array $validations): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The ZIP extension is required to finalize import workbook tables and validations.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) throw new RuntimeException('The generated import workbook could not be finalized.');
        try {
            $sheetPath = 'xl/worksheets/sheet2.xml';
            $sheetXml = $zip->getFromName($sheetPath);
            if (! is_string($sheetXml)) throw new RuntimeException('The generated workbook is missing Data Entry.');
            $lastColumn = $this->excelColumn(count($headers));
            $tableName = 'Import_'.substr(hash('sha256', $schema), 0, 16);
            $validationXml = '';
            $definedNames = [];
            foreach ($validations as $column => $validation) {
                if (! isset($headers[$column]) || ($validation['last_row'] ?? 0) < 2) continue;
                $name = '_ImportList_'.($column + 1);
                $columnLetter = $this->excelColumn($column + 1);
                $sheetName = str_replace("'", "''", $validation['sheet']);
                $definedNames[] = '<definedName name="'.$name.'">&apos;'.$this->xml($sheetName).'&apos;!$A$2:$A$'.(int) $validation['last_row'].'</definedName>';
                $validationXml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Invalid reference" error="Choose a current authorized value from the reference sheet." sqref="'.$columnLetter.'2:'.$columnLetter.'1048576"><formula1>'.$name.'</formula1></dataValidation>';
            }
            $sheetXml = preg_replace('/<autoFilter\b[^>]*\/>/', '', $sheetXml, 1) ?? $sheetXml;
            if ($validationXml !== '') {
                $validationBlock = '<dataValidations count="'.count($definedNames).'">'.$validationXml.'</dataValidations>';
                $sheetXml = preg_replace('/(?=<printOptions\b|<pageMargins\b|<pageSetup\b|<headerFooter\b|<rowBreaks\b|<drawing\b|<legacyDrawing\b|<tableParts\b|<extLst\b|<\/worksheet>)/', $validationBlock, $sheetXml, 1) ?? $sheetXml;
            }
            $tableParts = '<tableParts count="1"><tablePart r:id="rIdHotfix15Table"/></tableParts>';
            $sheetXml = str_contains($sheetXml, '<extLst')
                ? preg_replace('/(?=<extLst\b)/', $tableParts, $sheetXml, 1)
                : str_replace('</worksheet>', $tableParts.'</worksheet>', $sheetXml);
            if (! is_string($sheetXml) || ! str_contains($sheetXml, $tableParts)) throw new RuntimeException('The generated Data Entry worksheet metadata could not be finalized.');
            $zip->addFromString($sheetPath, $sheetXml);

            $columns = '';
            foreach (array_values($headers) as $index => $header) $columns .= '<tableColumn id="'.($index + 1).'" name="'.$this->xml($header).'"/>';
            $ref = 'A1:'.$lastColumn.max(2, $rowCount);
            $tableXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" id="1" name="'.$tableName.'" displayName="'.$tableName.'" ref="'.$ref.'" totalsRowShown="0"><autoFilter ref="'.$ref.'"/><tableColumns count="'.count($headers).'">'.$columns.'</tableColumns><tableStyleInfo name="TableStyleMedium2" showFirstColumn="0" showLastColumn="0" showRowStripes="1" showColumnStripes="0"/></table>';
            $zip->addFromString('xl/tables/table1.xml', $tableXml);

            $relations = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdHotfix15Table" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/table" Target="../tables/table1.xml"/></Relationships>';
            $zip->addFromString('xl/worksheets/_rels/sheet2.xml.rels', $relations);

            $contentTypes = $zip->getFromName('[Content_Types].xml');
            if (! is_string($contentTypes)) throw new RuntimeException('The generated workbook has no content-types manifest.');
            $contentTypes = str_replace('</Types>', '<Override PartName="/xl/tables/table1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.table+xml"/></Types>', $contentTypes);
            $zip->addFromString('[Content_Types].xml', $contentTypes);

            if ($definedNames !== []) {
                $workbookXml = $zip->getFromName('xl/workbook.xml');
                if (! is_string($workbookXml)) throw new RuntimeException('The generated workbook has no workbook metadata.');
                $workbookXml = str_replace('</sheets>', '</sheets><definedNames>'.implode('', $definedNames).'</definedNames>', $workbookXml);
                $zip->addFromString('xl/workbook.xml', $workbookXml);
            }
        } finally {
            $zip->close();
        }
    }

    private function excelColumn(int $number): string
    {
        $letters = '';
        while ($number > 0) { $number--; $letters = chr(65 + ($number % 26)).$letters; $number = intdiv($number, 26); }
        return $letters;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** @param list<string> $headers @param iterable<array<int, mixed>> $rows */
    public function pdf(string $filename, string $title, array $headers, iterable $rows): Response
    {
        $rows = collect($rows)->map(static fn (array $row): array => array_map(static fn (mixed $value): string => (string) $value, $row));
        if (str_starts_with(app()->getLocale(), 'ar')) {
            $values = array_merge([$title], $headers, $rows->flatten()->all());
            $visual = app(ArabicPdfText::class)->visualOrder($values);
            $title = array_shift($visual);
            $headers = array_splice($visual, 0, count($headers));
            $columnCount = count($headers);
            $headers = array_reverse($headers);
            $rows = collect(array_chunk($visual, $columnCount))->map(static fn (array $row): array => array_reverse($row));
        }

        $fontCache = storage_path('framework/cache/dompdf');
        (new Filesystem)->ensureDirectoryExists($fontCache);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->setChroot(base_path());
        $options->setFontDir($fontCache);
        $options->setFontCache($fontCache);
        $options->setTempDir($fontCache);
        $options->setDefaultFont(str_starts_with(app()->getLocale(), 'ar') ? 'Cairo PDF Arabic' : 'Cairo PDF Latin');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pages.exports.master-data-pdf', ['title' => $title, 'headers' => $headers, 'rows' => $rows])->render(), 'UTF-8');
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'"']);
    }

    private function safeCell(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@]/', ltrim($value))) {
            return "'".$value;
        }

        return $value;
    }
}
