<?php
// Kebijakan Privasi - Scholarium Digital Library
$updatedAt = '28 Desember 2025';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Kebijakan Privasi | Scholarium</title>

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
            <h1 class="h4 fw-bold mb-1">Kebijakan Privasi</h1>
            <div class="small opacity-75">Terakhir diperbarui: <?php echo htmlspecialchars($updatedAt); ?></div>
        </div>
    </div>
</header>

<main class="container px-3 py-4" style="max-width: 900px;">
    <div class="card card-doc">
        <div class="card-body p-4 p-md-5">

            <p class="muted mb-4">
                Dokumen ini menjelaskan bagaimana Scholarium Digital Library ("Scholarium", "kami") mengumpulkan, menggunakan,
                dan melindungi informasi ketika Anda mengakses situs dan layanan kami.
            </p>

            <h2 class="h6 fw-bold">1) Informasi yang kami kumpulkan</h2>
            <ul class="muted">
                <li><strong>Data penggunaan</strong>: halaman yang diakses, waktu akses, perangkat/browser, dan aktivitas umum untuk analitik dan perbaikan layanan.</li>
                <li><strong>Data pencarian</strong>: kata kunci pencarian yang Anda masukkan untuk membantu fungsi pencarian dan peningkatan relevansi.</li>
                <li><strong>Cookie/penyimpanan lokal</strong>: dapat digunakan untuk kenyamanan dan pengalaman pengguna (mis. preferensi tampilan).</li>
            </ul>

            <h2 class="h6 fw-bold mt-4">2) Informasi yang tidak kami kumpulkan</h2>
            <p class="muted">
                Kami tidak meminta pendaftaran akun dan tidak mewajibkan Anda memberikan data sensitif seperti nomor identitas, data kartu pembayaran,
                atau informasi kesehatan.
            </p>

            <h2 class="h6 fw-bold mt-4">3) Cara kami menggunakan informasi</h2>
            <ul class="muted">
                <li>Menjalankan fitur inti (pencarian, navigasi folder, dan pemuatan konten).</li>
                <li>Memperbaiki performa, keamanan, dan stabilitas layanan.</li>
                <li>Memahami penggunaan untuk peningkatan pengalaman (mis. apa yang sering dicari).</li>
            </ul>

            <h2 class="h6 fw-bold mt-4">4) Sumber konten & tautan pihak ketiga</h2>
            <p class="muted">
                Sebagian konten dapat ditautkan/tersimpan pada layanan pihak ketiga (misalnya Google Drive). Ketika Anda membuka atau mengunduh file,
                browser Anda dapat berinteraksi dengan layanan pihak ketiga tersebut dan tunduk pada kebijakan privasi mereka.
            </p>

            <h2 class="h6 fw-bold mt-4">5) Keamanan</h2>
            <p class="muted">
                Kami berupaya menerapkan praktik keamanan yang wajar untuk melindungi layanan. Namun, tidak ada metode transmisi data melalui internet
                yang dapat dijamin 100% aman.
            </p>

            <h2 class="h6 fw-bold mt-4">6) Retensi data</h2>
            <p class="muted">
                Data penggunaan dan log teknis dapat disimpan selama diperlukan untuk operasional, analitik, pencegahan penyalahgunaan, dan peningkatan layanan.
                Kami berupaya menyimpan data seminimal mungkin dan selama periode yang wajar.
            </p>

            <h2 class="h6 fw-bold mt-4">7) Hak Anda</h2>
            <p class="muted">
                Anda dapat membatasi cookie melalui pengaturan browser. Jika Anda ingin meminta penghapusan atau klarifikasi terkait data tertentu,
                silakan hubungi kami.
            </p>

            <h2 class="h6 fw-bold mt-4">8) Perubahan kebijakan</h2>
            <p class="muted">
                Kami dapat memperbarui kebijakan ini dari waktu ke waktu. Tanggal "Terakhir diperbarui" akan disesuaikan bila ada perubahan.
            </p>

            <h2 class="h6 fw-bold mt-4">9) Kontak</h2>
            <p class="muted mb-0">
                Untuk pertanyaan terkait privasi, hubungi: <strong>admin@domain-anda.com</strong> (silakan ganti dengan email resmi Anda).
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
