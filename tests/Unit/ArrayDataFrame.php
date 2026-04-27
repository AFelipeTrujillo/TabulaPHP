<?php

declare(strict_types=1);

namespace Oxide\Tests\Unit;

use Oxide\Core\DataFrame as DataFrameInterface;

/**
 * ArrayDataFrame — Test Double for DataFrame Interface
 *
 * This lightweight implementation allows us to test the interface contract
 * without requiring the Rust native extension. It stores data as a simple
 * array of associative arrays.
 *
 * @internal — Only for testing purposes.
 */
class ArrayDataFrame implements DataFrameInterface, \Countable
{
    /** @var array<int, array<string, mixed>> */
    private array $rows;

    /** @var array<string> */
    private array $columns;

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string>|null $columns Optional list of column names.
     */
    public function __construct(array $rows, ?array $columns = null)
    {
        $this->rows = $rows;
        $this->columns = $columns ?? (empty($rows) ? [] : array_keys($rows[0]));
    }

    // -----------------------------------------------------------------------
    //  DataFrame Interface Implementation
    // -----------------------------------------------------------------------

    public static function fromCsv(string $path): DataFrameInterface
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("CSV file not found at: {$path}");
        }

        $rows = [];

        if (($handle = fopen($path, 'r')) !== false) {
            $headers = fgetcsv($handle);

            if ($headers !== false) {
                $headers = array_map('trim', $headers);

                while (($data = fgetcsv($handle)) !== false) {
                    $row = [];
                    foreach ($headers as $i => $header) {
                        $row[$header] = $data[$i] ?? null;
                    }
                    $rows[] = $row;
                }
            }

            fclose($handle);
        }

        return new self($rows);
    }

    public function count(): int
    {
        return count($this->rows);
    }

    public function mean(string $column): float
    {
        if (!in_array($column, $this->columns, true)) {
            throw new \InvalidArgumentException(
                "Column '{$column}' does not exist."
            );
        }

        $values = array_filter(
            array_column($this->rows, $column),
            fn ($v) => is_numeric($v)
        );

        $numericCount = count($values);

        if ($numericCount === 0) {
            // Column exists but has no numeric values
            $allValues = array_column($this->rows, $column);
            if (!empty($allValues)) {
                throw new \InvalidArgumentException(
                    "Column '{$column}' is non-numeric."
                );
            }

            return 0.0;
        }

        // Check if there were non-numeric values in the original column
        $totalValues = array_column($this->rows, $column);
        if (count($values) !== count($totalValues)) {
            throw new \InvalidArgumentException(
                "Column '{$column}' is non-numeric."
            );
        }

        return array_sum($values) / count($values);
    }

    public function select(array $columns): DataFrameInterface
    {
        $selected = [];

        foreach ($this->rows as $row) {
            $newRow = [];
            foreach ($columns as $col) {
                $newRow[$col] = $row[$col] ?? null;
            }
            $selected[] = $newRow;
        }

        return new self($selected, $columns);
    }

    public function filter(string $column, string $operator, mixed $value): DataFrameInterface
    {
        $filtered = array_filter(
            $this->rows,
            function (array $row) use ($column, $operator, $value): bool {
                $cell = $row[$column] ?? null;

                return match ($operator) {
                    '==' => $cell == $value,
                    '!=' => $cell != $value,
                    '>'  => $cell > $value,
                    '>=' => $cell >= $value,
                    '<'  => $cell < $value,
                    '<=' => $cell <= $value,
                    default => throw new \InvalidArgumentException(
                        "Unsupported operator: {$operator}"
                    ),
                };
            }
        );

        return new self(array_values($filtered), $this->columns);
    }

    public function toArray(): array
    {
        return $this->rows;
    }
}