<?php

namespace App\Models;

use App\Entities\Ticket;
use CodeIgniter\Model;

/**
 * Model tiket (Unit 6: pewarisan dari CodeIgniter\Model). Hanya untuk baca; seluruh perubahan
 * status melalui App\Services\TicketService agar transaksi, log, dan notifikasi konsisten.
 */
class TicketModel extends Model
{
    protected $table = 'tickets';
    protected $primaryKey = 'id';
    protected $returnType = Ticket::class;
    protected $useSoftDeletes = true;
    protected $deletedField = 'deleted_at';
    protected $useTimestamps = false;
    protected $allowedFields = [];
    protected $protectFields = true;

    /** find() bertipe array|object|null; metode ini memastikan hasilnya entity Ticket atau null. */
    public function findTicket(int $id): ?Ticket
    {
        $row = $this->find($id);
        return $row instanceof Ticket ? $row : null;
    }
}
