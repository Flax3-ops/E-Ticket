<?php

namespace App\Controllers;

use App\Libraries\Notifier\DatabaseNotifier;
use App\Services\TicketService;

class Comments extends BaseController
{
    public function create(int $ticketId)
    {
        $db = db_connect();
        $ticket = (new TicketService($db))->find($ticketId);
        if (! $ticket || ! TicketService::canParticipate($ticket, (int) session('user_id'), (string) session('role'))) {
            return friendly_error(403, 'E-ACL-403', 'Akses ditolak');
        }
        $body = trim((string) $this->request->getPost('body'));
        if (mb_strlen($body) < 2 || mb_strlen($body) > 5000) {
            return redirect()->back()->with('error', 'Komentar harus 2–5000 karakter.');
        }
        $now = gmdate('Y-m-d H:i:s');
        $db->transBegin();
        try {
            $db->table('comments')->insert(['ticket_id' => $ticketId, 'user_id' => session('user_id'), 'body' => $body, 'created_at' => $now, 'updated_at' => $now]);
            $recipient = (int) session('user_id') === (int) $ticket['user_id'] ? (int) $ticket['assigned_to'] : (int) $ticket['user_id'];
            if ($recipient) {
                (new DatabaseNotifier($db))->notify($recipient, 'comment', 'Komentar baru pada ' . $ticket['ticket_no'], '/tickets/' . $ticketId);
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
        return redirect()->to('/tickets/' . $ticketId)->with('success', 'Komentar ditambahkan.');
    }
}
