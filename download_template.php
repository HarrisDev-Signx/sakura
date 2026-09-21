<?php
require_once 'config.php';
requireAdmin();

// Set header agar browser mendownloadnya sebagai file CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=template_soal_ujian.csv');

// Buka output stream
$output = fopen('php://output', 'w');

// Tambahkan BOM UTF-8 agar karakter khusus / bahasa Jepang aman dibaca Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Baris Header Kolom Template
fputcsv($output, ['No', 'Pertanyaan', 'Pilihan A', 'Pilihan B', 'Pilihan C', 'Pilihan D', 'Pilihan E', 'Pilihan F', 'Jawaban Benar']);

// Baris Contoh Soal (agar user paham cara mengisinya)
fputcsv($output, ['1', 'Apa arti dari Ohayou?', 'Selamat pagi', 'Selamat siang', 'Selamat malam', 'Selamat tinggal', '', '', 'a']);
fputcsv($output, ['2', 'Manakah yang merupakan huruf Hiragana?', 'Katakana', 'Hiragana', 'Kanji', 'Romaji', '', '', 'b']);

fclose($output);
exit;