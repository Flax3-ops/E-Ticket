<?php

namespace App\Controllers;

use App\Services\TicketService;
use DomainException;

class Tickets extends BaseController
{
    public function index()
    {
        $db = db_connect();
        $role = (string) session('role');
        $builder = $db->table('tickets t')->select('t.*, c.name AS category_name, u.name AS requester_name, p.name AS officer_name')->join('categories c', 'c.id=t.category_id')->join('users u', 'u.id=t.user_id')->join('users p', 'p.id=t.assigned_to', 'left')->where('t.deleted_at', null);
        if ($role === 'pemohon') {
            $builder->where('t.user_id', (int) session('user_id'));
        }
        foreach (['status', 'priority', 'category_id'] as $field) {
            $value = (string) $this->request->getGet($field);
            if ($value !== '') {
                $builder->where('t.' . $field, $value);
            }
        }
        $search = trim((string) $this->request->getGet('q'));
        if ($search !== '') {
            if (str_starts_with(strtoupper($search), 'TKT-')) {
                $builder->like('t.ticket_no', $search, 'after');
            } elseif (mb_strlen($search) >= 3) {
                $builder->where('MATCH(t.title) AGAINST (' . $db->escape($search) . ' IN NATURAL LANGUAGE MODE)', null, false);
            } else {
                $builder->like('t.title', $search);
            }
        }
        $page = max(1, (int) $this->request->getGet('page'));
        $count = (clone $builder)->countAllResults();
        $tickets = $builder->orderBy('t.id', 'DESC')->limit(20, ($page - 1) * 20)->get()->getResultArray();
        return view('tickets/index', ['tickets' => $tickets, 'categories' => $db->table('categories')->orderBy('name')->get()->getResultArray(), 'page' => $page, 'pages' => max(1, (int) ceil($count / 20))]);
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
        return view('tickets/show', ['ticket' => $ticket, 'comments' => $comments, 'attachments' => $db->table('attachments')->where('ticket_id', $id)->get()->getResultArray(), 'logs' => $db->table('ticket_logs')->where('ticket_id', $id)->orderBy('id')->get()->getResultArray()]);
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
        db_connect()->table('tickets')->where('id', $id)->where('deleted_at', null)->update(['deleted_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
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
