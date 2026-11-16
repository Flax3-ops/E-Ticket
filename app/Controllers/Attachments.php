<?php

namespace App\Controllers;

use App\Services\TicketService;

class Attachments extends BaseController
{
    public function upload(int $ticketId)
    {
        $db = db_connect();
        $ticket = (new TicketService($db))->find($ticketId);
        if (! $ticket || ! TicketService::canParticipate($ticket, (int) session('user_id'), (string) session('role'))) {
            return $this->response->setStatusCode(403)->setBody('Akses ditolak.');
        }
        if ($db->table('attachments')->where('ticket_id', $ticketId)->countAllResults() >= 3) {
            return redirect()->back()->with('error', 'Maksimal 3 lampiran per tiket.');
        }
        $file = $this->request->getFile('attachment');
        if (! $file || ! $file->isValid() || $file->getSize() > 2 * 1024 * 1024 || ! in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'application/pdf'], true)) {
            return redirect()->back()->with('error', 'Lampiran harus JPG, PNG, atau PDF dengan ukuran maksimal 2 MB.');
        }
        $stored = bin2hex(random_bytes(16)) . '.' . ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$file->getMimeType()];
        $file->move(WRITEPATH . 'uploads', $stored);
        $now = gmdate('Y-m-d H:i:s');
        $db->table('attachments')->insert(['ticket_id' => $ticketId, 'original_name' => basename($file->getClientName()), 'stored_name' => $stored, 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'created_at' => $now, 'updated_at' => $now]);
        return redirect()->to('/tickets/' . $ticketId)->with('success', 'Lampiran diunggah.');
    }

    public function download(int $id)
    {
        $db = db_connect();
        $attachment = $db->table('attachments')->where('id', $id)->get()->getRowArray();
        $ticket = $attachment ? (new TicketService($db))->find((int) $attachment['ticket_id']) : null;
        if (! $ticket || ! TicketService::canParticipate($ticket, (int) session('user_id'), (string) session('role'))) {
            return $this->response->setStatusCode(404)->setBody('Lampiran tidak ditemukan.');
        }
        $path = WRITEPATH . 'uploads/' . basename($attachment['stored_name']);
        if (! is_file($path)) {
            return $this->response->setStatusCode(404)->setBody('Berkas tidak ditemukan.');
        }
        return $this->response->download($path, null)->setFileName($attachment['original_name']);
    }
}
