<?php
// Syarat & Ketentuan - Scholarium Digital Library
$updatedAt = '28 Desember 2025';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Syarat & Ketentuan | Scholarium</title>

    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/2232/2232688.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root { --bg-body: #f1f5f9; }
        body {
            background-color: var(--bg-body);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif;
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
        }
        .page-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #fff;
            border-bottom-left-radius: 2rem;
            border-bottom-right-radius: 2rem;
        }
        .card-doc {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }
        .muted { color: #475569; }
        a { color: inherit; }
    </style>
</head>
<body>

<header class="page-hero py-4">
    <div class="container px-4" style="max-width: 900px;">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <a href="index.php" class="text-decoration-none text-white d-flex align-items-center gap-2" aria-label="Kembali ke beranda">
                <i class="fa-solid fa-graduation-cap text-warning"></i>
                <span class="fw-bold">Scholarium</span>
            </a>
            <a href="index.php" class="btn btn-sm btn-light rounded-pill px-3" aria-label="Kembali ke katalog">
                <i class="fa-solid fa-house me-1"></i> Beranda
            </a>
        </div>
        <div class="mt-3">
            <h1 class="h4 fw-bold mb-1">Syarat &amp; Ketentuan</h1>
            <div class="small opacity-75">Terakhir diperbarui: <?php echo htmlspecialchars($updatedAt); ?></div>
        </div>
    </div>
</header>

<main class="container px-3 py-4" style="max-width: 900px;">
    <div class="card card-doc">
        <div class="card-body p-4 p-md-5">

            <p class="muted mb-4">
                Dengan mengakses dan menggunakan Scholarium Digital Library ("Scholarium", "kami"), Anda setuju untuk terikat pada Syarat &amp; Ketentuan ini.
                Jika Anda tidak setuju, mohon untuk tidak menggunakan layanan.
            </p>

            <h2 class="h6 fw-bold">1) Tujuan layanan</h2>
            <p class="muted">
                Scholarium menyediakan katalog/navigasi dokumen untuk memudahkan pencarian dan akses referensi. Konten dapat berasal dari sumber pihak ketiga.
            </p>

            <h2 class="h6 fw-bold mt-4">2) Hak cipta & kepemilikan konten</h2>
            <ul class="muted">
                <li>Hak cipta atas dokumen tetap milik pemegang hak masing-masing.</li>
                <li>Anda bertanggung jawab memastikan penggunaan dokumen sesuai izin/ketentuan pemegang hak dan peraturan yang berlaku.</li>
                <li>Jika Anda adalah pemegang hak dan keberatan atas suatu konten, silakan ajukan permintaan peninjauan/penghapusan (lihat bagian Kontak).</li>
            </ul>

            <h2 class="h6 fw-bold mt-4">3) Penggunaan yang dilarang</h2>
            <ul class="muted">
                <li>Menggunakan layanan untuk tujuan melanggar hukum, merugikan pihak lain, atau melanggar hak kekayaan intelektual.</li>
                <li>Melakukan scraping/akses otomatis berlebihan yang mengganggu stabilitas layanan.</li>
                <li>Mengunggah, menyisipkan, atau menyebarkan malware, phishing, atau konten berbahaya.</li>
                <li>Mencoba mengakses area/fitur yang tidak diizinkan atau mengakali pembatasan keamanan.</li>
            </ul>

            <h2 class="h6 fw-bold mt-4">4) Ketersediaan & perubahan layanan</h2>
            <p class="muted">
                Kami dapat mengubah, menambah, mengurangi, atau menghentikan sebagian/seluruh layanan kapan saja tanpa pemberitahuan sebelumnya,
                termasuk pembaruan tampilan, fitur pencarian, dan tautan dokumen.
            </p>

            <h2 class="h6 fw-bold mt-4">5) Tautan pihak ketiga</h2>
            <p class="muted">
                Layanan dapat memuat tautan ke pihak ketiga (mis. Google Drive). Kami tidak mengendalikan dan tidak bertanggung jawab atas isi,
                kebijakan, atau praktik pihak ketiga tersebut.
            </p>

            <h2 class="h6 fw-bold mt-4">6) Penyangkalan (disclaimer)</h2>
            <p class="muted">
                Layanan disediakan "sebagaimana adanya" dan "sebagaimana tersedia". Kami tidak memberikan jaminan bahwa layanan selalu bebas gangguan,
                selalu akurat, atau selalu tersedia. Anda menggunakan layanan atas risiko Anda sendiri.
            </p>

            <h2 class="h6 fw-bold mt-4">7) Batasan tanggung jawab</h2>
            <p class="muted">
                Sejauh diizinkan oleh hukum, kami tidak bertanggung jawab atas kerugian tidak langsung, insidental, khusus, konsekuensial,
                atau kehilangan data yang timbul dari penggunaan layanan atau konten pihak ketiga.
            </p>

            <h2 class="h6 fw-bold mt-4">8) Perubahan syarat</h2>
            <p class="muted">
                Kami dapat memperbarui syarat ini dari waktu ke waktu. Dengan terus menggunakan layanan setelah perubahan berlaku,
                Anda dianggap menyetujui syarat yang diperbarui.
            </p>

            <h2 class="h6 fw-bold mt-4">9) Kontak</h2>
            <p class="muted mb-0">
                Permintaan peninjauan/penghapusan konten atau pertanyaan terkait syarat: <strong>admin@quizb.my.id</strong>
                (silakan ganti dengan email resmi Anda).
            </p>

        </div>
    </div>
</main>

<footer class="py-3">
    <div class="container px-3" style="max-width: 900px;">
        <div class="small text-center text-muted">&copy; <?php echo date('Y'); ?> Scholarium Digital Library</div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="click-tracker.js"></script>
</body>
</html>
