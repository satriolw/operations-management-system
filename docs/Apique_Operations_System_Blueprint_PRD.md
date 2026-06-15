# Operations Management System (OMS) — Apique Group: Product Blueprint (PRD)

**Aplikasi Operasional Terpadu untuk Apique Group, Less Worry & Kain Wangi Laundry**

- Versi: 1.2 (menambah Admin/CRUD, Template drag & drop, Identity/Auth, edge-case hardening)
- Tanggal: 12 Juni 2026 (rev 16 Juni 2026)
- Pemilik dokumen: Lurd (satrio@lessworry.id)
- Status: Draft — menunggu review tim Operations & Engineering

---

## 1. Ringkasan Eksekutif

Divisi Operations Apique Group mengelola puluhan outlet laundry auto-pilot di bawah brand Less Worry (full-service) dan Kain Wangi (self-service). Dua masalah operasional menonjol: (1) laporan harian ke investor sering terlambat atau terlupa karena masih disusun manual, dan (2) aktivitas harian tim lapangan tidak termonitor secara terukur.

Prinsip inti: masalah "lupa lapor" bukan masalah pengingat, melainkan masalah laporan manual. Karena data transaksi sudah tersedia di NEVIRA (POS) via API terdokumentasi, mayoritas isi laporan dapat di-generate dan dikirim otomatis tanpa bergantung pada ingatan manusia. Aplikasi diposisikan sebagai **mesin laporan dan sinyal operasional**, bukan sekadar checklist.

**Keputusan arsitektur kunci.** Aplikasi boleh berdiri sendiri sebagai aplikasi (untuk kecepatan rilis dan karena Operations adalah domain berbeda dari CRM/Sales), tetapi WAJIB membaca dari sumber data yang sama dengan ERP (NEVIRA / warehouse yang sama) dan menyimpan output-nya dengan skema yang nantinya dapat di-query oleh dashboard LBE. *Aplikasi terpisah boleh; data terpisah tidak.*

**Keputusan WhatsApp.** Opsi A — WhatsApp Cloud API resmi (Groups API). Berdasarkan dokumentasi resmi Meta (per 21 Mei 2026), pengiriman pesan ke grup kini didukung resmi, sehingga jalur tak resmi dengan strategi "ganti nomor jika di-ban" ditolak karena berisiko dan tidak berkelanjutan.

**Eksekusi tiga modul bertahap:**

- **Modul 1 — Report & Signal Engine:** laporan harian otomatis, penyesuaian revenue, deteksi outlet diam, anomali void/refund. Bermodal data NEVIRA yang sudah ada — dapat jalan paling dulu.
- **Modul 2 — Generator Dokumen Keuangan:** 5 jenis dokumen (Payment Request, Reimburse, Cash Advance, Expense Report, Refund), satu data model, approval sederhana, ekspor PDF.
- **Modul 3 — Operational Discipline:** checklist berbasis foto + timestamp anti-palsu, dan leaderboard antar-outlet ternormalisasi.

---

## 2. Problem Statement

Head Store dan tim Operations bertanggung jawab melaporkan kinerja outlet harian kepada investor LW/KWL. Saat ini laporan disusun manual, sehingga sering terlambat atau terlupa, dan tidak ada mekanisme yang memastikan aktivitas harian tiap role benar-benar dikerjakan dan terukur.

Dampak bila tidak diselesaikan: kepercayaan investor menurun karena pelaporan tidak konsisten; manajemen kehilangan visibilitas kinerja outlet secara real-time; potensi kebocoran biaya (mis. void/refund tidak wajar) tidak terdeteksi; dan beban administratif berulang (pembuatan dokumen keuangan manual) memperlambat tim.

> **Tingkat keyakinan:** Pernyataan masalah berasal langsung dari pemilik proses [keyakinan tinggi]. Besaran dampak finansial (mis. % kebocoran via void) belum diukur dan perlu verifikasi dengan data internal sebelum dijadikan target.

---

## 3. Goals (Tujuan Terukur)

Tujuan dinyatakan sebagai outcome, bukan output. Angka target adalah hipotesis awal yang perlu dikalibrasi dengan baseline nyata.

1. Menghilangkan keterlambatan laporan harian investor: 100% outlet aktif menerima laporan harian terkirim otomatis pada jam yang ditetapkan, tanpa intervensi manual untuk angka inti.
2. Mempercepat deteksi masalah operasional: outlet "diam" (tanpa transaksi pada jam wajar) terdeteksi dan dieskalasi dalam < 60 menit.
3. Meningkatkan transparansi finansial ke investor: 100% koreksi revenue (void/refund atas nota hari lalu) terlaporkan otomatis dengan alasan, di hari persetujuannya.
4. Mengurangi beban administratif dokumen keuangan: waktu pembuatan satu dokumen turun signifikan vs proses manual saat ini (target dikalibrasi setelah baseline).
5. Membuat KPI tiap role terukur dari sumber yang sulit dipalsukan (data POS, foto+timestamp), bukan dari laporan mandiri yang mudah di-game.

---

## 4. Non-Goals (Di Luar Lingkup)

- **Bukan pengganti ERP/LBE.** Tidak membangun ulang CRM, POS, atau Helpdesk. Memberi makan data ke LBE, bukan menggantikannya.
- **Bukan menyimpan salinan kebenaran data transaksi sendiri.** Sumber kebenaran tetap NEVIRA; aplikasi hanya membaca dan menurunkan sinyal.
- **Bukan engine approval keuangan kompleks (v1).** Routing approval bersyarat yang rumit ditunda.
- **Tidak memakai gateway WhatsApp tak resmi.** Strategi rotasi nomor saat di-ban ditolak; risiko ban permanen dan erosi kepercayaan investor terlalu tinggi.
- **Tidak mencakup integrasi Nevira di luar pembacaan data laporan (fase ini).**

---

## 5. Target Users & Roles

Aplikasi melayani 30–100 pengguna harian.

| Role | Kebutuhan utama | Prioritas |
|---|---|---|
| **Head Store** | Mengirim laporan harian ke grup investor; menyetujui void/refund; menjalankan checklist outlet. | Fokus pertama (role pelapor) |
| **Area Manager** | Memonitor banyak outlet; menerima alert outlet diam & anomali; membandingkan kinerja antar-outlet. | Fase 2 |
| **Assistant Ops Manager** | Memonitor kepatuhan operasional lintas area; rekap berkala. | Fase 2 |
| **Laundry Expert** | Menangani isu teknis/kualitas; KPI berbasis penyelesaian isu. | Fase 3 |
| **Staff / Crew Outlet** | Mengeksekusi checklist harian (foto+timestamp). | Fase 3 |

> **Catatan scope:** Permintaan "semua role sekaligus" ditolak demi fokus. Mulai dari Head Store (role yang kegagalannya paling kelihatan: laporan investor), validasi, lalu replikasi pola KPI ke role lain.

---

## 6. Arsitektur & Prinsip Data

### 6.1 Prinsip "Data Bersih yang Bisa Di-query LBE"

Aplikasi berdiri sendiri pada lapisan aplikasi, namun terikat satu sumber data dengan ERP. Tiga aturan wajib:

1. **Baca dari sumber yang sama:** semua data transaksi berasal dari NEVIRA API (atau warehouse yang sama dengan LBE). Tidak ada salinan kebenaran kedua.
2. **Simpan output terstruktur:** skor KPI, status laporan terkirim, hasil deteksi anomali disimpan dalam skema relasional yang bersih dan berstempel waktu.
3. **Future-proof untuk LBE:** skema dirancang agar LBE dapat menarik output aplikasi di fase berikutnya tanpa migrasi besar.

### 6.2 Integrasi NEVIRA

Endpoint relevan (dikonfirmasi tersedia & terdokumentasi):

- **Laporan harian dashboard:** `GET /api/reports/dashboard?start_date=&end_date=&id_outlet=`
- **Transaksi Void:** `GET /api/transactions?status=VOID&id_outlet=&start_date=&end_date=&is_void_refund=true`
- **Transaksi Refund:** `GET /api/transactions?status=REFUND&id_outlet=&start_date=&end_date=&is_void_refund=true`
- **Filter status bayar (Terealisasi vs Piutang):** `GET /api/transactions?payment_status=UNPAID&id_outlet=&start_date=&end_date=`

**Pemetaan field (dikonfirmasi dari response API, 12 Juni 2026):**

| Field laporan | Field NEVIRA | Contoh nilai |
|---|---|---|
| Nomor nota | `transaction_number` | INV/120/.../1 |
| Jenis koreksi | `status` | VOID / REFUND |
| Nominal terdampak | `grand_total` | Rp81.225 |
| Tanggal nota | `created_at` | 2026-06-12 13:10 |
| Tanggal disetujui | `approve_refund_void_date` | 2026-06-12 13:56 |
| Alasan | `refund_notes` / `void_notes` | "salah input nota" |
| Pemohon | `refund_void_by` | id_user (kasir) |
| Penyetuju | `refund_void_approved_by` | id_user |
| Outlet | `id_outlet` / `outlet_name` | 120 / Fatmawati |
| Status bayar | `payment_status` | PAID / UNPAID |

> **Detail implementasi:** filter `start_date`/`end_date` bekerja pada `created_at`. Untuk menangkap "disetujui hari ini atas nota hari sebelumnya", query memakai jendela tanggal lebih lebar lalu memfilter `approve_refund_void_date` = hari ini di sisi aplikasi.

---

## 7. Keputusan WhatsApp (Opsi A — Cloud API Resmi)

Validasi terhadap dokumentasi resmi Meta (halaman Groups API dan Group messaging, keduanya diperbarui 21 Mei 2026) memastikan pengiriman pesan ke grup kini didukung resmi melalui Messages API (`recipient_type: "group"`).

| Aspek | Ketentuan resmi & implikasi |
|---|---|
| **Peserta per grup** | Maksimal 8. Cukup untuk pola "1 grup = 1 investor" + Head Store + ops. Grup investor existing yang besar harus dirampingkan. |
| **Pembuatan grup** | Grup harus dibuat via API, sifatnya invite-only (peserta join lewat invite link). Grup WA manual existing TIDAK bisa diambil alih — perlu migrasi sekali. |
| **Status akun** | Wajib Official Business Account (OBA). Tidak tersedia untuk nomor WhatsApp Business App biasa. Perlu proses verifikasi bisnis Meta lebih dulu. |
| **Template & biaya** | Pesan business-initiated pakai approved template; buat template khusus Groups. Per-message pricing; pesan utility relatif murah. |
| **Kapasitas & keamanan** | Hingga 10.000 grup per nomor; 1 bisnis Cloud API per grup. Tidak ada risiko ban seperti jalur tak resmi — menghapus kebutuhan rotasi nomor. |

> **Strategi transisi (fallback Opsi B):** Selama OBA & migrasi grup belum selesai, arsitektur menyediakan mode hybrid — aplikasi menarik data NEVIRA, menyusun laporan lengkap, dan mengirim draft siap-tempel ke Head Store untuk di-paste manual ke grup existing. Menghapus masalah "lupa" tanpa risiko ban, dimatikan begitu Opsi A aktif.

---

## 8. Modul 1 — Report & Signal Engine

Modul prioritas. Bermodal data NEVIRA yang sudah ada; dapat dirilis paling dulu, bahkan via mode hybrid (Opsi B) sambil menunggu OBA.

### 8.1 User Stories

- Sebagai Head Store, saya ingin laporan harian outlet terkirim otomatis ke grup investor pada jam yang ditetapkan, agar saya tidak perlu menyusun dan mengingatnya manual.
- Sebagai investor, saya ingin menerima ringkasan kinerja harian yang rapi dan profesional beserta tangkapan layar dashboard, agar saya paham kondisi outlet tanpa membuka aplikasi lain.
- Sebagai investor, saya ingin diberi tahu secara transparan bila ada void/refund atas nota hari lalu yang baru disetujui hari ini, beserta alasannya, agar saya percaya pada keakuratan angka.
- Sebagai Area Manager, saya ingin menerima alert bila sebuah outlet tidak ada transaksi pada jam wajar, agar saya bisa menindak cepat.
- Sebagai Ops, saya ingin sistem menandai outlet/kasir dengan rasio void/refund tidak wajar, agar potensi kebocoran kas bisa ditinjau.

### 8.2 Requirements

#### Must-Have (P0)

| Kode | Requirement | Acceptance criteria (ringkas) |
|---|---|---|
| **P0-1** | Tarik data dashboard harian per outlet dari NEVIRA dan render menjadi pesan laporan terstruktur + gambar dashboard (mobile view). | Given data outlet tersedia, When jam kirim tiba, Then pesan + gambar terbentuk dengan angka sesuai sumber. |
| **P0-2** | Kirim laporan ke tujuan WhatsApp pada jadwal (mode hybrid: ke Head Store; mode Opsi A: ke grup investor via Groups API). | Pesan terkirim/terjadwal; status terkirim tercatat; kegagalan ter-log. |
| **P0-3** | Sembunyikan metrik bernilai nol (M², Pasang, Lembar) dari laporan; hanya tampilkan layanan yang bertransaksi. | Laporan tidak menampilkan baris metrik 0. |
| **P0-4** | Blok Penyesuaian Revenue: deteksi VOID dan REFUND yang disetujui hari ini (`approve_refund_void_date`) atas nota tanggal sebelumnya, dengan lookback ~7 hari; tampilkan nominal, alasan, dan revenue ter-restate per tanggal nota. | Bila ada koreksi, blok muncul dengan alasan & total; bila tidak, blok tidak muncul. Void & refund keduanya tercakup. |
| **P0-5** | Deteksi outlet diam: bandingkan transaksi pada titik cek terhadap baseline per-outlet; alert bila jauh di bawah ambang. | Outlet tanpa transaksi pada jam wajar memicu alert < 60 menit; hari libur tidak memicu alarm. |
| **P0-6** | Simpan status laporan & hasil sinyal dalam skema yang dapat di-query LBE. | Setiap kiriman & alert tersimpan terstruktur dengan timestamp. |
| **P0-7** | Pisahkan revenue jadi Terealisasi (paid) vs Piutang (unpaid) memakai filter `payment_status`; tampilkan keduanya di laporan harian. | Laporan menampilkan dua baris; jumlahnya = total_sales. |

#### Nice-to-Have (P1)

- Catatan naratif dinamis pada laporan (kalimat positif bila penjualan di atas rata-rata bulan; netral-jujur bila di bawah).
- **Flag self-approval (prioritaskan):** tandai otomatis setiap void/refund dengan `refund_void_by` = `refund_void_approved_by`. Terbukti terjadi pada data nyata (mis. user 181 menyetujui refund-nya sendiri) — sinyal pelanggaran proses yang lebih tajam daripada ambang nominal.
- Deteksi anomali void/refund per kasir dengan ambang statistik (>5% atau >2 SD dari baseline outlet) + alert instan untuk void nominal besar.
- **KPI akurasi input:** hitung refund/void rate berkategori "salah input" per kasir (75% akar masalah di sampel). Attribution wajib ke `id_cashier` (pembuat nota), BUKAN `refund_void_by` (yang mengoreksi) — di sampel 6/20 yang me-request bukan kasir aslinya.
- **Flag batch-approval:** tandai N persetujuan oleh approver sama dalam jendela waktu sangat pendek (mis. >2 dalam 60 detik). Terbukti: user 180 acc 4 void dalam 1 menit setelah menggantung ~2 hari.
- **Flag orphaned production:** void/refund pada order dengan `progress_percentage` > 0 TANPA nota pengganti. Primer: field terstruktur "nota pengganti" pada request void (perlu NEVIRA). Cadangan: heuristik (customer sama + item mirip + dibuat berdekatan). Label alert "perlu ditinjau", bukan "kebocoran terkonfirmasi".
- **Aging piutang:** daftar order UNPAID yang melewati X hari, belum dibayar dan belum di-void.
- Kalender operasional per outlet (jam buka, hari libur) untuk meredam false alarm.

#### Future Considerations (P2)

- Rekap mingguan/bulanan otomatis (tren WoW/MoM) ke investor — memakai generator PDF Modul 2.
- Personalisasi format laporan per investor.

### 8.3 Contoh Template Laporan Harian

```
LAPORAN HARIAN OPERASIONAL
[Nama Outlet] · Kamis, 11 Juni 2026

Selamat malam Bapak/Ibu [Nama Investor]
Berikut ringkasan kinerja outlet hari ini.

PENJUALAN
  Total penjualan      : Rp10.138.108
    - Terealisasi (paid) : Rp9.897.108
    - Piutang (unpaid)   : Rp241.000
  Jumlah transaksi     : 93 transaksi
  Rata-rata/transaksi  : Rp109.012
  Rata-rata/pelanggan  : Rp152.329

VOLUME LAYANAN
  Cuci kiloan          : 67 Kg
  Satuan               : 121 Pcs

CATATAN
  Penjualan hari ini tercatat baik dan operasional
  berjalan lancar.

Terima kasih atas kepercayaan Bapak/Ibu.
Salam hangat, Tim [Nama Brand]
[gambar dashboard mobile dilampirkan]
```

Angka nol (M², Pasang, Lembar) disembunyikan. Bagian CATATAN dapat di-generate dinamis.

### 8.4 Contoh Blok Penyesuaian Revenue (opsional)

```
PENYESUAIAN REVENUE HARI SEBELUMNYA

Hari ini disetujui koreksi atas transaksi terdahulu:

  Nota #INV-00123 — 10 Juni
    Refund Rp250.000
    Alasan: hasil cuci dikomplain, refund penuh.

  Nota #INV-00119 — 10 Juni
    Void Rp180.000
    Alasan: salah input layanan, dikoreksi.

  Total penyesuaian : -Rp430.000
  Revenue 10 Juni   : Rp9.200.000 -> Rp8.770.000

Penyesuaian ini kami sampaikan demi keterbukaan data.
```

**Aturan akuntansi (dikonfirmasi 12 Juni 2026):** `total_sales` NEVIRA mencakup transaksi PAID dan UNPAID (piutang B2B yang tetap diproduksi). Revenue diakui pada tanggal nota. Konsekuensi: baik VOID (atas order unpaid) maupun REFUND (atas order paid) sama-sama me-restate revenue tanggal nota. Karena itu blok Penyesuaian Revenue mencakup keduanya, dan revenue harian bersifat optimistis (mengandung piutang) — itulah alasan baris Terealisasi vs Piutang.

**Jendela lookback:** approval void bisa tertunda lama (di sampel sampai ~50 jam, dan disetujui batch). Karena itu pencarian koreksi memakai jendela ~7 hari ke belakang dan memfilter `approve_refund_void_date` = hari ini, bukan hanya "kemarin".

### 8.5 Temuan Audit Awal (bukti pendukung)

Audit cepat atas sampel 20 record (10 void + 10 refund) dari populasi 96 (29 void + 67 refund). Directional, perlu konfirmasi di populasi penuh sebelum dijadikan target stakeholder.

- **Struktur VOID vs REFUND:** 100% VOID = UNPAID, 100% REFUND = PAID di sampel. Memvalidasi pemisahan jalur koreksi.
- **Self-approval 2/20 (10%):** keduanya REFUND oleh user 181 — terkonsentrasi di tempat uang bergerak (deposit).
- **Batch rubber-stamp:** user 180 menyetujui 4 void dalam 1 menit (9 Juni 11:58), dengan lag request→approve sampai ~50 jam.
- **Akar masalah:** 75% input error, 15% permintaan/ubah customer, 10% belum bayar (abandoned).
- **Cross-day restatement:** 4/20 (semua VOID) bernota 5–7 Juni tapi disetujui 9 Juni, total Rp582.560 — karena unpaid dihitung, ini me-restate revenue hari lampau.
- **Kasus 6003 (Kemang, Rp722.925):** order produksi 100% selesai, unpaid, di-void. Pola koreksi (ganti nota tanpa bayar ulang) — risiko hanya bila nota pengganti tidak dibuat.

### 8.6 Lapisan Admin, Template & Identity (v1.1)

Penambahan scope dari review desain. Detail teknis di `Modul1_System_Design.md` §3.9–§3.12.

- **Pengiriman WhatsApp dinamis:** akun/nomor & target (grup/investor) dikelola lewat Admin, `deliver_mode` per target dapat diubah sewaktu-waktu. Perpindahan ke Opsi A tetap lewat gerbang kesiapan.
- **Template laporan dinamis (master → override) dengan builder drag & drop:** template master tingkat grup diwarisi per outlet/investor, boleh di-custom. Blok mengikat token field NEVIRA. Catatan kunci: pesan grup WA resmi wajib approved Meta template — model konten dipisah dari transport (hybrid = teks bebas; Opsi A = isi approved template).
- **Halaman edit outlet:** jam laporan, **jam cek outlet-diam dinamis**, ambang, jam operasional/libur — semua dapat dikonfigurasi.
- **Audit trail tinjauan (evidence):** tindak lanjut sinyal & Penyesuaian Revenue mencatat siapa/kapan/outcome/catatan (wajib), lampiran opsional — agar "sudah ditinjau" terbukti.
- **User management sendiri + opsi SSO:** OMS punya auth & role sendiri (siapa boleh Setujui & Kirim, review, admin), dengan hook untuk connect/SSO ke LBE/ERP nanti. **Tidak digabung ke LBE** (beda concern).
- **CRUD semua master data:** outlet, akun/target WhatsApp, template, user/role, dan referensi (peta `id_role → level`, kalender libur).

### 8.7 Edge Cases & Hardening (v1.2)

Hasil challenge edge-case (detail di `Modul1_System_Design.md` §3.13). Asumsi terkonfirmasi: refund selalu penuh & transaksi tak bisa diedit langsung → Penyesuaian Revenue lengkap. Investor 1:1 outlet & **terima laporan via WhatsApp saja** (tidak login app). Penambahan: pemulihan (DR) nomor WhatsApp yang hilang; **konfirmasi "sudah dikirim"** wajib di mode hybrid; hari outlet buka-nol tetap dikirim dengan catatan; baseline outlet-diam anti-bias; **severity tiering** sinyal untuk hindari alert fatigue; reviewer ≠ subjek pada tinjauan; otorisasi per-outlet untuk staf internal; cost guard WhatsApp; versioning template master. **Ketahanan auth NEVIRA:** login token berlaku 24 jam → token manager dengan refresh proaktif + re-login single-flight saat 401, agar job tak gagal saat token kedaluwarsa (OPS-108). Kredensial NEVIRA (termasuk service credential re-auth) di secret store, bukan CRUD. KWL self-service = profil terpisah, di luar Modul 1.

---

## 9. Modul 2 — Generator Dokumen Keuangan

Bottleneck: pembuatan dokumen berulang untuk diserahkan ke tim Finance. Solusi membunuh repetisi dengan template, bukan membangun ERP-approval raksasa.

### 9.1 User Stories

- Sebagai pengaju, saya ingin mengisi satu formulir dan mendapat dokumen PDF rapi (PR / Reimburse / Cash Advance / Expense Report / Refund), agar tidak menyusun ulang dari nol.
- Sebagai approver, saya ingin menyetujui dokumen secara berurutan dengan satu klik dan melihat statusnya.
- Sebagai Finance, saya ingin menerima dokumen terstandar beserta jejak persetujuan.

### 9.2 Requirements

**Must-Have (P0):**

- Satu data model bersama untuk lima jenis dokumen (field umum: pengaju, outlet, tanggal, nominal, cost center) + field spesifik per jenis.
- Lima template PDF; isi formulir sekali → auto-fill → ekspor PDF dengan blok tanda tangan/approval.
- Approval berurutan sederhana (Diajukan → Disetujui L1 → L2 → Final) dengan pencatatan status & timestamp.

**Nice-to-Have (P1):**

- Approve via tautan/klik (e-sign sederhana) dan notifikasi WhatsApp ke approver berikutnya.
- Riwayat dokumen yang dapat dicari per outlet/periode.

**Future Considerations (P2):**

- Routing approval bersyarat (mis. nominal di atas X butuh approver tambahan).
- Integrasi langsung ke sistem Finance/akuntansi.

> **Catatan urutan:** Modul 2 dibangun setelah Modul 1 terbukti hidup.

---

## 10. Modul 3 — Operational Discipline

### 10.1 Checklist Foto + Timestamp

- Sebagai Crew, saya menjalankan task harian (buka outlet, kebersihan, cek mesin) dengan mengunggah foto; sistem mencatat timestamp & lokasi.
- Sebagai Head Store, saya melihat skor kepatuhan checklist per outlet sebagai bagian KPI.

> **Anti-palsu (P0 untuk modul ini):** foto wajib dari kamera in-app (bukan galeri); watermark timestamp ditempel server-side. Tanpa ini checklist hanya teater.

### 10.2 Leaderboard Antar-Outlet

- Ranking memakai metrik ternormalisasi (growth %, revenue per kapasitas/mesin) — bukan revenue absolut.

> **Jebakan:** gaming akhir periode dan rasa tidak adil bila normalisasi salah. Tahan modul ini sampai data KPI dasar bersih.

---

## 11. Success Metrics

**Leading Indicators (cepat berubah):**

- Cakupan laporan otomatis: % outlet aktif yang laporannya terkirim tepat jadwal (target: 100% dalam 30 hari pasca-rilis Modul 1).
- Ketepatan waktu alert outlet diam: median waktu dari kondisi diam ke alert (target: < 60 menit).
- Adopsi generator dokumen: % dokumen dibuat via aplikasi vs manual (target dikalibrasi).
- Tingkat kegagalan kirim WhatsApp (target: < 2%).

**Lagging Indicators (berkembang seiring waktu):**

- Konsistensi pelaporan investor selama satu kuartal.
- Penurunan beban administratif (waktu rata-rata pembuatan dokumen).
- Deteksi dini kebocoran: jumlah anomali void/refund yang ditindaklanjuti.
- Kepuasan investor terhadap pelaporan (survei kualitatif).

> **Metode ukur:** seluruh target adalah hipotesis; tetapkan baseline nyata dari data 30 hari pertama sebelum mengunci angka. [keyakinan sedang]

---

## 12. Open Questions

| Pemilik | Pertanyaan | Status |
|---|---|---|
| Engineering | Field `approve_refund_void_date` & `refund_notes`/`void_notes` — **RESOLVED** (12 Juni 2026, terkonfirmasi ada). Sisa: pastikan filter tanggal endpoint (created_at vs approve date) → query jendela lebar + filter sisi aplikasi. | Tertutup / non-blocking |
| Ops/Audit | Apakah wewenang ganda (request + approve refund oleh orang sama, mis. user 181) sah secara kebijakan? Bila tidak, celah kontrol yang harus ditutup. | Perlu keputusan kebijakan |
| Engineering/Vendor | Bisakah NEVIRA menambah field terstruktur "nota pengganti" pada form request void/refund? Menentukan keandalan deteksi orphaned production. | Non-blocking (memengaruhi keandalan) |
| Engineering | Split Terealisasi vs Piutang — **RESOLVED**: filter `payment_status=UNPAID` tersedia. | Tertutup |
| Stakeholder | Berapa lama proses memperoleh Official Business Account (OBA) untuk entitas LW/KWL? | Blocking untuk Opsi A penuh |

---

## 13. Timeline & Phasing

1. **Fase 0 — Fondasi:** skema data bersih (LBE-ready), koneksi NEVIRA, kerangka penjadwalan. Mulai proses OBA paralel.
2. **Fase 1 — Modul 1 (mode hybrid):** laporan harian + penyesuaian revenue dikirim ke Head Store untuk paste manual; deteksi outlet diam. Memberi nilai tanpa menunggu OBA.
3. **Fase 2 — Modul 1 (Opsi A penuh):** migrasi grup investor ke Groups API; kirim otomatis langsung ke grup; aktifkan anomali void/refund; perluas KPI ke Area Manager.
4. **Fase 3 — Modul 2:** generator dokumen keuangan + approval sederhana.
5. **Fase 4 — Modul 3:** checklist foto+timestamp & leaderboard; perluas KPI ke Laundry Expert & Crew.

> **Dependensi kritis:** Opsi A penuh (Fase 2) bergantung pada selesainya OBA dan migrasi grup. Mode hybrid (Fase 1) sengaja dirancang agar tidak terblokir oleh dependensi tersebut.

---

## 14. Sumber & Referensi

- Meta for Developers — Groups API (maks 8 peserta, invite-only, wajib OBA, 10.000 grup/nomor). <https://developers.facebook.com/documentation/business-messaging/whatsapp/groups/>
- Meta for Developers — Group messaging (recipient_type "group"; teks/media/template; diperbarui 21 Mei 2026). <https://developers.facebook.com/documentation/business-messaging/whatsapp/groups/groups-messaging/>
- Konteks internal: Apique Group knowledge base (BU LW/KWL, peran LBE sebagai dashboard pusat ERP).

> **Disclaimer verifikasi:** ketentuan harga, kuota, dan ketersediaan WhatsApp Business Platform dapat berubah; verifikasi ulang ke halaman resmi Meta saat eksekusi. Ketersediaan & skema field NEVIRA mengikuti dokumentasi internal terbaru.
