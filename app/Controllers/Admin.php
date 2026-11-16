<?php

namespace App\Controllers;

use App\Services\ActivityLogger;

class Admin extends BaseController
{
    public function users()
    {
        return view('admin/users', ['items' => db_connect()->table('users')->select('id,name,email,role,is_active,created_at')->orderBy('id', 'DESC')->get()->getResultArray()]);
    }

    public function saveUser()
    {
        $db = db_connect();
        $id = (int) $this->request->getPost('id');
        $role = (string) $this->request->getPost('role');
        $email = trim((string) $this->request->getPost('email'));
        $name = trim((string) $this->request->getPost('name'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($name) < 2 || ! in_array($role, ['pemohon', 'petugas', 'admin'], true)) {
            return redirect()->back()->with('error', 'Data pengguna tidak valid.');
        }
        $data = ['name' => $name, 'email' => $email, 'role' => $role, 'is_active' => (int) (bool) $this->request->getPost('is_active'), 'updated_at' => gmdate('Y-m-d H:i:s')];
        $password = (string) $this->request->getPost('password');
        if ($id === 0 && mb_strlen($password) < 8) {
            return redirect()->back()->with('error', 'Password baru minimal 8 karakter.');
        }
        if ($password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        }
        if ($id) {
            $db->table('users')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = gmdate('Y-m-d H:i:s');
            $db->table('users')->insert($data);
            $id = (int) $db->insertID();
        }
        (new ActivityLogger($db))->write((int) session('user_id'), 'user_saved', 'users', $id);
        return redirect()->to('/admin/users')->with('success', 'Pengguna disimpan.');
    }

    public function categories()
    {
        return view('admin/categories', ['items' => db_connect()->table('categories')->orderBy('name')->get()->getResultArray()]);
    }

    public function deleteUser(int $id)
    {
        if ($id === (int) session('user_id')) {
            return redirect()->back()->with('error', 'Akun yang sedang digunakan tidak dapat dinonaktifkan.');
        }
        db_connect()->table('users')->where('id', $id)->update(['is_active' => 0, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        (new ActivityLogger(db_connect()))->write((int) session('user_id'), 'user_deactivated', 'users', $id);
        return redirect()->to('/admin/users')->with('success', 'Pengguna dinonaktifkan.');
    }

    public function saveCategory()
    {
        $db = db_connect();
        $id = (int) $this->request->getPost('id');
        $name = trim((string) $this->request->getPost('name'));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            return redirect()->back()->with('error', 'Nama kategori harus 2–100 karakter.');
        }
        $data = ['name' => $name, 'description' => trim((string) $this->request->getPost('description')), 'updated_at' => gmdate('Y-m-d H:i:s')];
        if ($id) {
            $db->table('categories')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = gmdate('Y-m-d H:i:s');
            $db->table('categories')->insert($data);
            $id = (int) $db->insertID();
        }
        (new ActivityLogger($db))->write((int) session('user_id'), 'category_saved', 'categories', $id);
        return redirect()->to('/admin/categories')->with('success', 'Kategori disimpan.');
    }

    public function settings()
    {
        return view('admin/settings', ['items' => db_connect()->table('settings')->orderBy('key')->get()->getResultArray()]);
    }

    public function deleteCategory(int $id)
    {
        $db = db_connect();
        if ($db->table('tickets')->where('category_id', $id)->countAllResults() > 0) {
            return redirect()->back()->with('error', 'Kategori masih dipakai oleh tiket.');
        }
        $db->table('categories')->where('id', $id)->delete();
        (new ActivityLogger($db))->write((int) session('user_id'), 'category_deleted', 'categories', $id);
        return redirect()->to('/admin/categories')->with('success', 'Kategori dihapus.');
    }

    public function saveSettings()
    {
        $db = db_connect();
        foreach ((array) $this->request->getPost('settings') as $key => $value) {
            if (! preg_match('/^(sla_(Kritis|Tinggi|Sedang|Rendah)|(cpu|ram|disk|db)_limit)$/', (string) $key) || ! is_numeric($value) || (float) $value <= 0 || (float) $value > 1000) {
                continue;
            }
            $db->table('settings')->where('key', $key)->update(['value' => (string) $value, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        }
        (new ActivityLogger($db))->write((int) session('user_id'), 'settings_saved', 'settings');
        return redirect()->to('/admin/settings')->with('success', 'Pengaturan disimpan.');
    }

    public function logs()
    {
        $db = db_connect();
        $builder = $db->table('activity_logs a')->select('a.*, u.name')->join('users u', 'u.id=a.user_id', 'left');
        if ($action = trim((string) $this->request->getGet('action'))) {
            $builder->like('a.action', $action);
        }
        return view('admin/logs', ['items' => $builder->orderBy('a.id', 'DESC')->limit(200)->get()->getResultArray()]);
    }
}
