<?php

namespace CSeidl\EmailComposer\Support;

use Generator;
use SplFileObject;

/**
 * Reads a CSV with a header row. Column names are trimmed and lowercased,
 * and empty cells are left out.
 */
class CsvReader
{
    /**
     * @param  array<int, string>  $required  columns the header must have
     * @return Generator<int, array<string, string>> line number to cells by column
     *
     * @throws \DomainException when the header lacks a required column
     */
    public function rows(string $path, array $required = []): Generator
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(',', '"', '');

        $header = null;

        foreach ($file as $index => $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            if ($header === null) {
                $header = $this->header($row, $required);

                continue;
            }

            yield $index + 1 => $this->cells($row, $header);
        }

        if ($header === null && $required !== []) {
            throw new \DomainException(__('email-composer::import.notifications.missing_column', ['column' => $required[0]]));
        }
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<int, string>  $required
     * @return array<int, string>
     */
    protected function header(array $row, array $required): array
    {
        $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]) ?? $row[0];
        $header = array_map(fn (?string $column) => mb_strtolower(trim($this->utf8((string) $column))), $row);

        foreach ($required as $column) {
            if (! in_array($column, $header, true)) {
                throw new \DomainException(__('email-composer::import.notifications.missing_column', ['column' => $column]));
            }
        }

        return $header;
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<int, string>  $header
     * @return array<string, string>
     */
    protected function cells(array $row, array $header): array
    {
        $cells = [];

        foreach ($header as $position => $column) {
            $value = trim($this->utf8((string) ($row[$position] ?? '')));

            if ($column !== '' && $value !== '') {
                $cells[$column] = $value;
            }
        }

        return $cells;
    }

    /** Excel on Windows saves "CSV (Comma delimited)" as Windows-1252. */
    protected function utf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8')
            ? $value
            : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
