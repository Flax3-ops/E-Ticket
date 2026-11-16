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
            return friendly_error(403, 'E-ACL-403', 'Akses ditolak');
        }
        if ($db->table('attachments')->where('ticket_id', $ticketId)->countAllResults() >= 3) {
            return redirect()->back()->with('error', 'Maksimal 3 lampiran per tiket.');
        }
        $file = $this->request->getFile('attachment');
        $mime = $file && $file->isValid() ? $file->getMimeType() : '';
        $size = $file && $file->isValid() ? (int) $file->getSize() : 0;
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
        if (! $file || ! $file->isValid() || $size > 2 * 1024 * 1024 || ! isset($extensions[$mime])) {
            return redirect()->back()->with('error', 'Lampiran harus JPG, PNG, atau PDF dengan ukuran maksimal 2 MB.');
        }
        // MIME dan ukuran dibaca sebelum move(): berkas sementara hilang setelah dipindahkan.
        $stored = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $originalName = basename($file->getClientName());
        $file->move(WRITEPATH . 'uploads', $stored);
        $now = gmdate('Y-m-d H:i:s');
        $db->table('attachments')->insert(['ticket_id' => $ticketId, 'original_name' => $originalName, 'stored_name' => $stored, 'mime' => $mime, 'size' => $size, 'created_at' => $now, 'updated_at' => $now]);
        (new \App\Services\ActivityLogger($db))->write((int) session('user_id'), 'attachment_uploaded', 'tickets', $ticketId);
        return redirect()->to('/tickets/' . $ticketId)->with('success', 'Lampiran diunggah.');
    }

    public function download(int $id)
    {
        $db = db_connect();
        $attachment = $db->table('attachments')->where('id', $id)->get()->getRowArray();
        $ticket = $attachment ? (new TicketService($db))->find((int) $attachment['ticket_id']) : null;
        if (! $ticket || ! TicketService::canParticipate($ticket, (int) session('user_id'), (string) session('role'))) {
            return friendly_error(404, 'E-ATT-404', 'Lampiran tidak ditemukan');
        }
        $path = WRITEPATH . 'uploads/' . basename($attachment['stored_name']);
        if (! is_file($path)) {
            return friendly_error(404, 'E-ATT-410', 'Berkas lampiran tidak ada di penyimpanan');
        }
        return $this->response->download($path, null)->setFileName($attachment['original_name']);
    }
}
