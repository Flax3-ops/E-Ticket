<?php

use CodeIgniter\Test\CIUnitTestCase;

require_once dirname(__DIR__, 2) . '/algoritma/algorithms.php';

final class AlgorithmsTest extends CIUnitTestCase
{
    public function testSortingAndSearchingMatchExpectedValues(): void
    {
        $items = [4, 1, 3, 2];
        $this->assertSame([1, 2, 3, 4], insertionSort($items));
        $this->assertSame([1, 2, 3, 4], mergeSort($items));
        $this->assertSame(2, linearSearch($items, 3));
        $this->assertSame(2, binarySearch([1, 2, 3, 4], 3));
        $this->assertSame(-1, binarySearch([1, 2, 3, 4], 9));
    }
}
