<?php

declare(strict_types=1);

namespace Oxide\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the DataFrame interface contract.
 *
 * These tests validate the expected behavior of any DataFrame implementation.
 * We use an ArrayDataFrame as a lightweight test double that simulates
 * the interface contract without requiring the Rust native extension.
 */
class DataFrameTest extends TestCase
{
    // -----------------------------------------------------------------------
    //  fromCsv()
    // -----------------------------------------------------------------------

    public function test_from_csv_throws_exception_when_file_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        ArrayDataFrame::fromCsv('/nonexistent/file.csv');
    }

    // -----------------------------------------------------------------------
    //  count()
    // -----------------------------------------------------------------------

    public function test_count_returns_zero_for_empty_dataframe(): void
    {
        $df = new ArrayDataFrame([]);

        $this->assertSame(0, $df->count());
    }

    public function test_count_returns_number_of_rows(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
            ['name' => 'Charlie', 'age' => 35],
        ];
        $df = new ArrayDataFrame($data);

        $this->assertSame(3, $df->count());
    }

    // -----------------------------------------------------------------------
    //  mean()
    // -----------------------------------------------------------------------

    public function test_mean_computes_average_of_column(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 20],
            ['name' => 'Charlie', 'age' => 40],
        ];
        $df = new ArrayDataFrame($data);

        $this->assertEquals(30.0, $df->mean('age'));
    }

    public function test_mean_throws_exception_for_nonexistent_column(): void
    {
        $df = new ArrayDataFrame([
            ['name' => 'Alice', 'age' => 30],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $df->mean('nonexistent');
    }

    public function test_mean_throws_exception_for_non_numeric_column(): void
    {
        $df = new ArrayDataFrame([
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-numeric');

        $df->mean('name');
    }

    public function test_mean_returns_zero_when_no_rows(): void
    {
        $df = new ArrayDataFrame([], ['age']);

        $this->assertEquals(0.0, $df->mean('age'));
    }

    // -----------------------------------------------------------------------
    //  select()
    // -----------------------------------------------------------------------

    public function test_select_returns_only_requested_columns(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30, 'city' => 'NYC'],
            ['name' => 'Bob', 'age' => 25, 'city' => 'LA'],
        ];
        $df = new ArrayDataFrame($data);

        $subset = $df->select(['name', 'city']);

        $expected = [
            ['name' => 'Alice', 'city' => 'NYC'],
            ['name' => 'Bob', 'city' => 'LA'],
        ];
        $this->assertSame($expected, $subset->toArray());
    }

    // -----------------------------------------------------------------------
    //  filter()
    // -----------------------------------------------------------------------

    public function test_filter_with_equal_operator(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
            ['name' => 'Charlie', 'age' => 30],
        ];
        $df = new ArrayDataFrame($data);

        $filtered = $df->filter('age', '==', 30);

        $this->assertCount(2, $filtered);
        $this->assertSame('Alice', $filtered->toArray()[0]['name']);
        $this->assertSame('Charlie', $filtered->toArray()[1]['name']);
    }

    public function test_filter_with_greater_than_operator(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
            ['name' => 'Charlie', 'age' => 35],
        ];
        $df = new ArrayDataFrame($data);

        $filtered = $df->filter('age', '>', 25);

        $this->assertCount(2, $filtered);
    }

    public function test_filter_with_less_than_operator(): void
    {
        $df = new ArrayDataFrame([
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
        ]);

        $filtered = $df->filter('age', '<', 30);
        $this->assertCount(1, $filtered);
        $this->assertSame('Bob', $filtered->toArray()[0]['name']);
    }

    public function test_filter_returns_empty_when_no_match(): void
    {
        $df = new ArrayDataFrame([
            ['name' => 'Alice', 'age' => 30],
        ]);

        $filtered = $df->filter('age', '>', 100);
        $this->assertCount(0, $filtered);
    }

    // -----------------------------------------------------------------------
    //  toArray()
    // -----------------------------------------------------------------------

    public function test_toArray_returns_all_data(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
        ];
        $df = new ArrayDataFrame($data);

        $this->assertSame($data, $df->toArray());
    }

    public function test_toArray_returns_empty_array_for_empty_dataframe(): void
    {
        $df = new ArrayDataFrame([]);

        $this->assertSame([], $df->toArray());
    }

    // -----------------------------------------------------------------------
    //  Chaining (fluent API)
    // -----------------------------------------------------------------------

    public function test_fluent_api_chaining(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30, 'city' => 'NYC'],
            ['name' => 'Bob', 'age' => 25, 'city' => 'LA'],
            ['name' => 'Charlie', 'age' => 35, 'city' => 'NYC'],
            ['name' => 'Diana', 'age' => 28, 'city' => 'LA'],
        ];
        $df = new ArrayDataFrame($data);

        // Filter by city, then select only name and age
        $result = $df
            ->filter('city', '==', 'NYC')
            ->select(['name', 'age']);

        $this->assertCount(2, $result);
        $this->assertSame([
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Charlie', 'age' => 35],
        ], $result->toArray());
    }
}
