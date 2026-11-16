<?php

namespace App\Controllers;

use App\Models\TicketListQuery;
use App\Services\ActivityLogger;
use App\Services\TicketService;
use DomainException;

class Tickets extends BaseController
{
    public function index()
    {
        $page = max(1, (int) $this->request->getGet('page'));
        $filters = array_map('strval', $this->request->getGet(['status', 'priority', 'category_id', 'q']));
        $requester = session('role') === 'pemohon' ? (int) session('user_id') : null;
        $result = (new TicketListQuery(db_connect()))->paginate($filters, $requester, $page, 20);
        return view('tickets/index', ['tickets' => $result['rows'], 'categories' => db_connect()->table('categories')->orderBy('name')->get()->getResultArray(), 'page' => $page, 'pages' => max(1, (int) ceil($result['total'] / 20))]);
    }

    public function new()
    {
        return view('tickets/form', ['ticket' => null, 'categories' => db_connect()->table('categories')->orderBy('name')->get()->getResultArray()]);
    }

    public function create()
    {
        $data = $this->validatedData();
        if ($data === null) {
            return redirect()->back()->withInput()->with('error', 'Data tiket tidak valid.');
        }
        try {
            $id = (new TicketService(db_connect()))->create((int) session('user_id'), $data);
            return redirect()->to('/tickets/' . $id)->with('success', 'Tiket berhasil dibuat.');
        } catch (\Throwable $e) {
            log_message('error', 'E-TKT-001 {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Gagal membuat tiket (E-TKT-001): ' . $e->getMessage());
        }
    }

    public function show(int $id)
    {
        $db = db_connect();
        $ticket = (new TicketService($db))->find($id);
        if (! $ticket || ! TicketService::canView($ticket, (int) session('user_id'), (string) session('role'))) {
            return $this->response->setStatusCode(404)->setBody('Tiket tidak ditemukan.');
        }
        $ticket['category_name'] = $db->table('categories')->where('id', $ticket['category_id'])->get()->getRow('name');
        $ticket['requester_name'] = $db->table('users')->where('id', $ticket['user_id'])->get()->getRow('name');
        $comments = $db->table('comments c')->select('c.*, u.name')->join('users u', 'u.id=c.user_id')->where('c.ticket_id', $id)->orderBy('c.id')->get()->getResultArray();
        $officers = session('role') === 'admin' ? $db->table('users')->select('id,name')->where('role', 'petugas')->where('is_active', 1)->orderBy('name')->get()->getResultArray() : [];
        return view('tickets/show', ['officers' => $officers, 'ticket' => $ticket, 'comments' => $comments, 'attachments' => $db->table('attachments')->where('ticket_id', $id)->get()->getResultArray(), 'logs' => $db->table('ticket_logs')->where('ticket_id', $id)->orderBy('id')->get()->getResultArray()]);
    }

    public function edit(int $id)
    {
        $ticket = (new TicketService(db_connect()))->find($id);
        if (! $ticket || $ticket['deleted_at'] || ! (session('role') === 'admin' || (session('role') === 'pemohon' && (int) $ticket['user_id'] === (int) session('user_id') && $ticket['status'] === 'Baru'))) {
            return $this->response->setStatusCode(403)->setBody('Akses ditolak.');
        }
        return view('tickets/form', ['ticket' => $ticket, 'categories' => db_connect()->table('categories')->orderBy('name')->get()->getResultArray()]);
    }

    public function update(int $id)
    {
        $ticket = (new TicketService(db_connect()))->find($id);
        if (! $ticket || $ticket['deleted_at'] || ! (session('role') === 'admin' || (session('role') === 'pemohon' && (int) $ticket['user_id'] === (int) session('user_id') && $ticket['status'] === 'Baru'))) {
            return $this->response->setStatusCode(403)->setBody('Akses ditolak.');
        }
        $data = $this->validatedData();
        if ($data === null) {
            return redirect()->back()->withInput()->with('error', 'Data tidak valid.');
        }
        unset($data['priority']);
        $data['updated_at'] = gmdate('Y-m-d H:i:s');
        db_connect()->table('tickets')->where('id', $id)->update($data);
        (new ActivityLogger(db_connect()))->write((int) session('user_id'), 'ticket_updated', 'tickets', $id);
        return redirect()->to('/tickets/' . $id)->with('success', 'Tiket diperbarui.');
    }

    public function take(int $id)
    {
        try {
            (new TicketService(db_connect()))->take($id, (int) session('user_id'));
            return redirect()->to('/tickets/' . $id)->with('success', 'Tiket berhasil diambil.');
        } catch (DomainException $e) {
            return redirect()->to('/tickets/' . $id)->with('error', $e->getMessage());
        }
    }

    public function status(int $id)
    {
        try {
            (new TicketService(db_connect()))->changeStatus($id, (int) session('user_id'), (string) session('role'), (string) $this->request->getPost('status'), trim((string) $this->request->getPost('note')));
            return redirect()->to('/tickets/' . $id)->with('success', 'Status diperbarui.');
        } catch (DomainException $e) {
            return redirect()->to('/tickets/' . $id)->with('error', $e->getMessage());
        }
    }

    public function assign(int $id)
    {
        try {
            (new TicketService(db_connect()))->assign($id, (int) session('user_id'), (int) $this->request->getPost('officer_id'), trim((string) $this->request->getPost('reason')));
            return redirect()->to('/tickets/' . $id)->with('success', 'Penugasan dipindahkan.');
        } catch (DomainException $e) {
            return redirect()->to('/tickets/' . $id)->with('error', $e->getMessage());
        }
    }

    public function priority(int $id)
    {
        try {
            (new TicketService(db_connect()))->changePriority($id, (int) session('user_id'), (string) $this->request->getPost('priority'), (string) $this->request->getPost('reason'));
            return redirect()->to('/tickets/' . $id)->with('success', 'Prioritas diperbarui.');
        } catch (DomainException $e) {
            return redirect()->to('/tickets/' . $id)->with('error', $e->getMessage());
        }
    }

    public function delete(int $id)
    {
        $db = db_connect();
        $db->table('tickets')->where('id', $id)->where('deleted_at', null)->update(['deleted_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
        if ($db->affectedRows() === 1) {
            (new ActivityLogger($db))->write((int) session('user_id'), 'ticket_deleted', 'tickets', $id);
        }
        return redirect()->to('/tickets')->with('success', 'Tiket dihapus secara lunak.');
    }

    private function validatedData(): ?array
    {
        $data = [
            'title' => trim((string) $this->request->getPost('title')),
            'description' => trim((string) $this->request->getPost('description')),
            'category_id' => (int) $this->request->getPost('category_id'),
            'priority' => (string) $this->request->getPost('priority'),
        ];
        if (mb_strlen($data['title']) < 5 || mb_strlen($data['title']) > 200 || mb_strlen($data['description']) < 10 || ! in_array($data['priority'], ['Rendah', 'Sedang', 'Tinggi', 'Kritis'], true) || ! db_connect()->table('categories')->where('id', $data['category_id'])->countAllResults()) {
            return null;
        }
        return $data;
    }
}
