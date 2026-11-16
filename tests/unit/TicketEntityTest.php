<?php

use App\Entities\Ticket;
use CodeIgniter\Test\CIUnitTestCase;

final class TicketEntityTest extends CIUnitTestCase
{
    public function testTitleSetterNormalizesWhitespace(): void
    {
        $ticket = new Ticket(['title' => '  Printer   lantai  dua  ']);
        $this->assertSame('Printer lantai dua', $ticket->title);
    }

    public function testOverdueOnlyWhenOpenAndPastDue(): void
    {
        $now = strtotime('2026-11-16 12:00:00 UTC');
        $open = new Ticket(['status' => 'Diproses', 'sla_due_at' => '2026-11-16 11:00:00']);
        $this->assertTrue($open->isOverdue($now));
        $this->assertSame(-60, $open->slaMinutesRemaining($now));
        $this->assertFalse((new Ticket(['status' => 'Selesai', 'sla_due_at' => '2026-11-16 11:00:00']))->isOverdue($now), 'selesai tidak dihitung terlambat');
        $this->assertFalse((new Ticket(['status' => 'Baru', 'sla_due_at' => '2026-11-16 13:00:00']))->isOverdue($now));
        $this->assertSame('Terbuka (Baru)', (new Ticket(['status' => 'Baru']))->getStatusLabel());
    }
}
