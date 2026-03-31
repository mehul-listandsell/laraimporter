<?php

namespace LaraImporter\Services;

use Illuminate\Http\UploadedFile;

class FileParserService
{
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'csv', 'txt' => $this->parseCsv($file),
            'json' => $this->parseJson($file),
            'xls', 'xlsx', 'ods' => $this->parseSpreadsheet($file),
            default => throw new \InvalidArgumentException("Unsupported file format: {$extension}"),
        };
    }

    protected function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) throw new \RuntimeException('Could not open file for reading.');

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = $this->detectDelimiter($firstLine);

        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) { fclose($handle); throw new \RuntimeException('Could not read file headers.'); }

        $headers = array_map(fn($h) => trim(preg_replace('/[\x{FEFF}\x{200B}]/u', '', $h)), $headers);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count(array_filter($row, fn($v) => $v !== null && $v !== '')) === 0) continue;
            $row = array_pad($row, count($headers), '');
            $row = array_slice($row, 0, count($headers));
            $rows[] = array_combine($headers, $row);
        }
        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows, 'total_rows' => count($rows), 'format' => 'csv'];
    }

    protected function parseJson(UploadedFile $file): array
    {
        $data = json_decode(file_get_contents($file->getRealPath()), true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new \RuntimeException('Invalid JSON: ' . json_last_error_msg());
        if (!is_array($data) || empty($data)) throw new \RuntimeException('JSON must contain a non-empty array of objects.');
        if (!isset($data[0])) $data = [$data];

        $flatData = array_map(fn($row) => $this->flattenRow($row), $data);
        $headers = array_keys($flatData[0]);

        return ['headers' => $headers, 'rows' => $flatData, 'total_rows' => count($flatData), 'format' => 'json'];
    }

    protected function parseSpreadsheet(UploadedFile $file): array
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new \RuntimeException('phpoffice/phpspreadsheet is required for Excel/ODS support. Install it via: composer require phpoffice/phpspreadsheet');
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $worksheet->toArray(null, true, true, true);

        if (empty($data)) throw new \RuntimeException('Spreadsheet is empty.');

        $headerRow = array_shift($data);
        $headers = array_map(fn($val) => trim((string) ($val ?? '')), array_values($headerRow));
        while (!empty($headers) && $headers[count($headers) - 1] === '') array_pop($headers);
        if (empty($headers)) throw new \RuntimeException('No headers found in first row.');

        $rows = [];
        foreach ($data as $rowData) {
            $values = array_slice(array_values($rowData), 0, count($headers));
            $values = array_pad($values, count($headers), '');
            if (count(array_filter($values, fn($v) => $v !== null && $v !== '')) === 0) continue;
            $rows[] = array_combine($headers, array_map(fn($v) => (string) ($v ?? ''), $values));
        }

        $spreadsheet->disconnectWorksheets();

        return ['headers' => $headers, 'rows' => $rows, 'total_rows' => count($rows), 'format' => $file->getClientOriginalExtension()];
    }

    public function getPreview(array $parsedData, int $limit = 10): array
    {
        return [
            'headers' => $parsedData['headers'],
            'rows' => array_slice($parsedData['rows'], 0, $limit),
            'total_rows' => $parsedData['total_rows'],
            'showing' => min($limit, $parsedData['total_rows']),
        ];
    }

    protected function detectDelimiter(string $line): string
    {
        $delimiters = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];
        foreach ($delimiters as $d => &$count) $count = substr_count($line, $d);
        return array_search(max($delimiters), $delimiters);
    }

    protected function flattenRow(array $row, string $prefix = ''): array
    {
        $flat = [];
        foreach ($row as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;
            if (is_array($value) && !$this->isSequentialArray($value)) {
                $flat = array_merge($flat, $this->flattenRow($value, $newKey));
            } else {
                $flat[$newKey] = is_array($value) ? json_encode($value) : $value;
            }
        }
        return $flat;
    }

    protected function isSequentialArray(array $arr): bool
    {
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
