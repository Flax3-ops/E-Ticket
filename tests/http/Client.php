<?php

/** Klien HTTP sederhana (cookie jar + CSRF) untuk uji end-to-end. */
final class Client
{
    public string $jar;

    public function __construct(public string $base, string $name, string $tmp)
    {
        $this->jar = "$tmp/$name.jar";
        @unlink($this->jar);
    }

    /** @return array{0:int,1:string,2:string} status, body, location */
    public function req(string $method, string $path, array $post = [], array $files = [], bool $json = false): array
    {
        $ch = curl_init($this->base . $path);
        $headers = [];
        if ($method === 'POST') {
            $post[$this->tokenName()] = $this->token($path);
            foreach ($files as $field => [$file, $mime, $name]) {
                $post[$field] = new CURLFile($file, $mime, $name);
            }
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $files ? $post : http_build_query($post));
        }
        if ($json) {
            $headers[] = 'Accept: application/json';
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 60,
        ]);
        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr($raw, 0, $size);
        $loc = preg_match('/^Location:\s*(\S+)/mi', $head, $m) ? $m[1] : '';
        $loc = preg_replace('#^https?://[^/]+#', '', $loc);
        return [$status, substr($raw, $size), $loc];
    }

    public function follow(string $method, string $path, array $post = [], array $files = []): array
    {
        [$s, $b, $l] = $this->req($method, $path, $post, $files);
        for ($i = 0; $i < 5 && in_array($s, [301, 302, 303], true) && $l !== ''; $i++) {
            [$s, $b, $l] = $this->req('GET', $l);
        }
        return [$s, $b, $l];
    }

    private function tokenName(): string
    {
        return 'csrf_test_name';
    }

    private function token(string $path): string
    {
        // Halaman yang memuat form (login bila belum masuk, selain itu dashboard).
        foreach (['/login', '/'] as $page) {
            $ch = curl_init($this->base . $page);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_FOLLOWLOCATION => false]);
            $html = (string) curl_exec($ch);
            curl_close($ch);
            // Redirect memakai baseURL aplikasi (port lain): ulangi manual bila perlu.
            if (preg_match('/name="csrf_test_name"\s+value="([^"]+)"/', $html, $m) || preg_match('/value="([^"]+)"\s+name="csrf_test_name"/', $html, $m)) {
                return $m[1];
            }
        }
        return '';
    }
}
