<?php

use CodeIgniter\Test\CIUnitTestCase;

require_once dirname(__DIR__, 2) . '/algoritma/tiket.php';

final class AlgorithmsTiketTest extends CIUnitTestCase
{
    public function testBubbleSortHandlesEdgeCases(): void
    {
        $this->assertSame([], bubbleSort([]));
        $this->assertSame([5], bubbleSort([5]));
        $this->assertSame([1, 2, 3, 4, 5], bubbleSort([5, 4, 3, 2, 1]));
        $this->assertSame([1, 2, 2, 3], bubbleSort([3, 2, 1, 2]));
        $this->assertSame([1, 2, 3], bubbleSort([1, 2, 3]), 'sudah terurut: berhenti dini');
    }

    public function testTicketsSortedByPriorityThenNumber(): void
    {
        $tickets = [
            ['ticket_no' => 'TKT-3', 'priority' => 'Rendah'],
            ['ticket_no' => 'TKT-2', 'priority' => 'Kritis'],
            ['ticket_no' => 'TKT-5', 'priority' => 'Tinggi'],
            ['ticket_no' => 'TKT-1', 'priority' => 'Kritis'],
            ['ticket_no' => 'TKT-4', 'priority' => 'Sedang'],
        ];
        $sorted = urutkanTiketPerPrioritas($tickets);
        $this->assertSame(['TKT-1', 'TKT-2', 'TKT-5', 'TKT-4', 'TKT-3'], array_column($sorted, 'ticket_no'));
        $this->assertSame('TKT-3', $tickets[0]['ticket_no'], 'masukan tidak berubah');
    }

    public function testTicketNumberSearch(): void
    {
        $list = [['ticket_no' => 'TKT-A'], ['ticket_no' => 'TKT-C'], ['ticket_no' => 'TKT-E'], ['ticket_no' => 'TKT-G']];
        $this->assertSame(2, cariNomorBiner($list, 'TKT-E'));
        $this->assertSame(0, cariNomorBiner($list, 'TKT-A'));
        $this->assertSame(3, cariNomorBiner($list, 'TKT-G'));
        $this->assertSame(-1, cariNomorBiner($list, 'TKT-B'));
        $this->assertSame(-1, cariNomorBiner([], 'TKT-A'));
        $this->assertSame(1, cariNomorLinear($list, 'TKT-C'));
        $this->assertSame(-1, cariNomorLinear($list, 'TKT-Z'));
    }
}
