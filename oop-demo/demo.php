<?php

declare(strict_types=1);

namespace OopDemo;

interface Pengirim
{
    public function kirim(string $pesan): string;
}

abstract class PengirimDasar implements Pengirim
{
    protected string $prefix = 'Info';

    public function __construct(private string $nama)
    {
    }

    public function getNama(): string
    {
        return $this->nama;
    }

    public function setNama(string $nama): void
    {
        $this->nama = $nama;
    }
}

class PengirimDatabase extends PengirimDasar
{
    public function kirim(string $pesan): string
    {
        return $this->prefix . ' untuk ' . $this->getNama() . ': ' . $pesan;
    }
}

class PengirimDemo extends PengirimDasar
{
    public function kirim(string $pesan): string
    {
        return '[DEMO] ' . $this->getNama() . ': ' . $pesan;
    }
}

/**
 * PHP tidak memiliki overloading metode berdasarkan tanda tangan.
 * __call() mendemonstrasikan dispatch menurut jumlah argumen untuk materi Unit 6.
 */
class Formatter
{
    public function __call(string $name, array $arguments): string
    {
        if ($name !== 'format' || count($arguments) < 1 || count($arguments) > 2) {
            throw new \BadMethodCallException($name);
        }
        return count($arguments) === 1 ? (string) $arguments[0] : $arguments[0] . ': ' . $arguments[1];
    }
}

/** Polymorphism: dua class dipakai melalui kontrak yang sama. */
function tampilkan(Pengirim $pengirim): void
{
    echo $pengirim->kirim('Tiket diterima') . PHP_EOL;
}

tampilkan(new PengirimDatabase('Ani'));
tampilkan(new PengirimDemo('Budi'));
$formatter = new Formatter();
echo $formatter->format('Tiket') . PHP_EOL;
echo $formatter->format('Status', 'Selesai') . PHP_EOL;
