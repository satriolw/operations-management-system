# Modul 1 — Architecture Review & Decision Record (ADR)

**Tujuan:** evaluasi arsitektur sebelum eksekusi (rencana: review & implementasi via Claude Code).
**Dasar:** `Modul1_System_Design.md`, `Modul1_Engineering_Tickets.md`, Blueprint PRD v1.0.
**Aplikasi:** Operations Management System (OMS) · Apique Group.
**Tanggal:** 12 Juni 2026 (rev v1.2) · **Status keseluruhan:** Disetujui untuk dieksekusi, dengan 9 risiko yang harus ditangani lebih dulu (lihat §3).

---

## 1. Ringkasan Penilaian

Arsitektur yang diusulkan — modular monolith Laravel, NEVIRA via REST API di balik interface, queue + scheduler, output-only persistence — **sehat dan proporsional** untuk skala 15–50 outlet. Tidak ada over-engineering, dan batas terhadap NEVIRA/LBE dijaga dengan benar.

Risiko terbesar **bukan** pada keputusan besar, melainkan pada **detail integrasi** yang mudah meledak diam-diam: ketidakkonsistenan timezone di response NEVIRA, rendering gambar di PHP, dan ketergantungan pada referensi yang belum ada (hierarki role, field nota pengganti). Semua bisa ditangani; harus eksplisit sebelum koding.

---

## 2. Architecture Decision Records

Format ringkas: Konteks → Keputusan → Konsekuensi (+ = manfaat, − = biaya).

### ADR-001 — Modular monolith Laravel (bukan microservices)
**Konteks:** tim menguasai Laravel; skala kecil; satu ekosistem dengan NEVIRA.
**Keputusan:** satu aplikasi Laravel, dipisah per modul domain (Ingestion/Reporting/Revenue/Signals/Delivery/Admin).
**Konsekuensi:** + cepat, murah, mudah di-review. + satu basis kode untuk Claude Code. − rilis tak bisa per-modul (tak dibutuhkan). **Status: Accepted.**

### ADR-002 — Baca NEVIRA via REST API (bukan direct DB)
**Konteks:** infra internal sama → godaan baca DB langsung.
**Keputusan:** API-first di balik interface `TransactionSource`; read-replica hanya bila terbukti perlu.
**Konsekuensi:** + batas/konsistensi terjaga, tahan perubahan skema NEVIRA. − agregasi sedikit lebih lambat (tak material). **Status: Accepted.**

### ADR-003 — Polling terjadwal, desain event-ready
**Konteks:** kapabilitas webhook NEVIRA belum diketahui.
**Keputusan:** poller terjadwal sekarang; `POST /webhooks/nevira` disiapkan (OPS-107) di balik interface yang sama.
**Konsekuensi:** + cukup untuk laporan harian & cek diam; + tanpa rombak bila webhook datang. − cek outlet-diam belum real-time (alert < 60 mnt cukup). **Status: Accepted (asumsi: polling).**

### ADR-004 — Queue Redis (fallback DB), Laravel Scheduler
**Keputusan:** async via queue; cron via Scheduler; idempotency key per (outlet, date) & (report_run, channel).
**Konsekuensi:** + reliabilitas + replay; − butuh worker berjalan (proses tambahan). **Status: Accepted.**

### ADR-005 — Abstraksi Delivery: hybrid → Opsi A
**Konteks:** Opsi A (Groups API) terblokir OBA.
**Keputusan:** interface `Deliverer` dengan dua implementasi; mode per outlet via konfigurasi.
**Konsekuensi:** + nilai keluar sebelum OBA; + tukar mode tanpa ubah pemanggil. − langkah manual sementara (jika hybrid via WA biasa). **Status: Accepted — lihat Risiko R5 soal kerancuan "grup internal".**

### ADR-006 — Persistensi output-only + minim-PII
**Keputusan:** simpan turunan saja (status laporan, sinyal, koreksi); tidak menyimpan PII customer dari void/refund.
**Konsekuensi:** + sumber tunggal terjaga, risiko privasi minim; − tiap run fetch ulang (murah di skala ini). **Status: Accepted (OPS-705).**

### ADR-007 — Self-approval sadar-kebijakan (baru)
**Konteks:** kebijakan: wewenang ganda diizinkan untuk level ≥ Kepala Toko.
**Keputusan:** flag self-approval hanya melanggar bila role penyetuju < Kepala Toko; selebihnya dicatat sebagai pengecualian sah.
**Konsekuensi:** + selaras kebijakan nyata, mengurangi false alarm; − butuh referensi hierarki role yang belum ada (Risiko R3). **Status: Accepted, pending data role.**

### ADR-008 — Template Engine: model konten terpisah dari transport (baru, v1.1)
**Konteks:** laporan harus dinamis (master→override per investor) dengan builder drag & drop; tapi pesan grup WA bisnis-initiated wajib approved Meta template.
**Keputusan:** pisahkan model konten (`layout_json` + token) dari transport; `hybrid` render teks bebas, `assisted`/`full_auto` mengisi approved Meta template fleksibel. Pipeline tidak diblokir builder (seed default).
**Konsekuensi:** + fleksibel & dapat diwarisi; + frontend (builder) paralel, tak memblokir backend. − kompleksitas; − konten full_auto harus muat template → perlu guard + fallback. **Status: Accepted (lihat Risiko R7).**

### ADR-009 — Identity OMS sendiri, siap SSO ke LBE/ERP (baru, v1.1)
**Konteks:** OMS butuh role app (Setujui & Kirim, review, admin) yang tak ada di NEVIRA; LBE = dashboard, bukan IdP.
**Keputusan:** auth + role/permission OMS sendiri di balik interface `IdentityProvider`; federasi/SSO ke LBE/ERP menyusul tanpa rombak. Pisahkan user-OMS dari aktor-NEVIRA.
**Konsekuensi:** + cepat & sesuai kebutuhan; + tidak mencampur concern. − identitas kedua sementara sampai SSO ERP siap (mitigasi: jangan duplikasi master karyawan, referensikan). **Status: Accepted.**

### ADR-010 — Token lifecycle NEVIRA: login 24 jam + re-auth (baru, v1.2)
**Konteks:** NEVIRA pakai login token berlaku 24 jam (dikonfirmasi 16 Juni 2026).
**Keputusan:** token manager di `NeviraApiSource` — token cache bersama (Redis), **refresh proaktif** menjelang 24 jam, **single-flight re-login** pada 401 lalu retry sekali, gagal → alert + fallback. 401/403 dipisah dari error transient (429/5xx). Service credential re-auth di secret store, bukan hanya token.
**Konsekuensi:** + job tahan expiry tanpa intervensi manual; + tidak ada storm re-login multi-worker. − harus menyimpan service credential (memperluas aturan secret di OPS-705). **Status: Accepted (OPS-108).**

---

## 3. Risiko & Titik Lemah (harus ditangani sebelum/di awal koding)

> Bagian ini sengaja kritis. Ini tempat arsitektur paling mungkin gagal diam-diam.

### R1 — Ketidakkonsistenan timezone di response NEVIRA · **Severity: Tinggi**
Di data nyata, timestamp tingkat-transaksi berformat **WIB lokal** (`created_at: "2026-06-12 13:10:12"`) sedangkan timestamp nested di `services` berformat **UTC** (`"2026-06-12T06:10:12.000000Z"`) — selisih 7 jam untuk transaksi yang sama. Logika "disetujui hari ini" (Penyesuaian Revenue, OPS-401) sangat sensitif terhadap batas hari. Salah menafsirkan zona = koreksi revenue salah hari.
**Mitigasi:** tetapkan satu sumber waktu kanonik (gunakan field tingkat-transaksi sebagai WIB; normalkan semua ke Asia/Jakarta di parser OPS-103); tulis unit test khusus batas tengah malam. **Tangani di OPS-103/401.**

### R2 — Rendering gambar dashboard di PHP · **Severity: Sedang-Tinggi**
PHP lemah untuk render kartu visual kaya (OPS-204). Opsi: (a) headless browser via `spatie/browsershot` (butuh Node + Chromium di server) — hasil terbaik, dependensi terberat; (b) library image PHP (GD/Imagick) — ringan, tapi tata letak manual & rapuh; (c) generate HTML lalu screenshot. 
**Mitigasi:** putuskan lebih dulu sebelum sprint laporan. Rekomendasi: Browsershot bila server boleh menambah Chromium; jika tidak, template HTML→image sederhana. **Tiket berisiko tertinggi — minta keputusan lead di awal.**

### R3 — Referensi hierarki role belum ada · **Severity: Sedang**
ADR-007 butuh peta `id_role` → level untuk tahu siapa "≥ Kepala Toko". Data sampel hanya menunjukkan `id_role` 37 & 3 tanpa makna level. Tanpa ini, OPS-601 tak bisa membedakan self-approval sah vs pelanggaran.
**Mitigasi:** dapatkan tabel role NEVIRA + tandai role Kepala Toko ke atas; sampai ada, OPS-601 mencatat semua self-approval sebagai "perlu ditinjau" (tidak memblokir).

### R4 — Kopling ke bentuk response NEVIRA tanpa kontrak versi · **Severity: Sedang**
Tidak ada skema/versi formal; perubahan diam-diam dapat merusak parser.
**Mitigasi:** `TransactionSource` sebagai anti-corruption layer + contract test terhadap fixture nyata (OPS-102). Alarm bila field hilang.

### R5 — Kerancuan "grup internal" = Opsi A, bukan hybrid · **Severity: Sedang (perencanaan)**
Rencana mengirim ke grup WA via API agar nomor resmi berkirim **adalah Opsi A** dan menuntut OBA + grup buatan-API (maks 8). Bila ini dijadikan target MVP, MVP bergantung OBA — menghapus manfaat "hybrid lebih dulu".
**Mitigasi:** pilih sadar — (a) terima jeda OBA, atau (b) MVP kirim draft ke Head Store via WA biasa, pindah ke grup-API setelah OBA. Keputusan bisnis, bukan teknis.

### R6 — Heuristik orphaned-production rawan akurasi · **Severity: Rendah-Sedang**
Field "nota pengganti" tak tersedia dalam waktu dekat → andalkan heuristik (OPS-604). Risiko false positive/negative.
**Mitigasi:** label "perlu ditinjau" (bukan tuduhan); kalibrasi jendela & ambang; siapkan jalur ke field terstruktur.

### R7 — Drag & drop tidak boleh menabrak batas approved template · **Severity: Sedang**
Builder drag & drop (OPS-902) bisa menghasilkan konten yang **tidak muat** ke approved Meta template untuk `full_auto`/`assisted`. Bila tak dijaga, kiriman ditolak Meta atau gagal diam-diam.
**Mitigasi:** guard di OPS-903 — validasi konten terhadap kapasitas template sebelum kirim; bila tak muat, **fallback hybrid + alert**. Rancang approved template dengan body 1 parameter besar. Builder & pipeline di-decouple (pipeline pakai seed/override, tak menunggu builder).

### R8 — Alert fatigue dari sinyal volume tinggi · **Severity: Sedang**
75% void/refund = "salah input". Bila tiap kejadian jadi alert real-time, ops mematikan notifikasi → semua sinyal (termasuk yang penting) terabaikan.
**Mitigasi (OPS-1002):** severity tiering — high (self-approval pelanggaran, void besar, orphaned) real-time; low (input error rutin) masuk digest. KPI input agregat, bukan per kejadian.

### R9 — Kontinuitas nomor/WABA WhatsApp · **Severity: Sedang**
Nomor OBA yang "memiliki" grup-API hilang/banned → grup yatim, investor harus di-invite ulang; nomor baru butuh OBA sendiri.
**Mitigasi (OPS-307):** state akun + runbook DR (re-provision → OBA → recreate grup → re-invite) + fallback hybrid selama pemulihan. Ganti nomor bukan sekadar edit field.

---

## 4. Kesiapan Eksekusi via Claude Code

Karena review & implementasi akan lewat Claude Code, ini yang membuat repo "Claude-Code-friendly":

1. **`CLAUDE.md` di root** berisi: ringkasan arsitektur (link ke 3 dokumen ini), konvensi modul, aturan emas ("jangan simpan kebenaran transaksi NEVIRA", "jangan persist PII customer", "semua waktu dinormalkan ke Asia/Jakarta").
2. **Interface lebih dulu.** Minta Claude Code membangun `TransactionSource` & `Deliverer` (kontrak) sebelum implementasi — sesuai OPS-102/301. Ini memandu seluruh domain.
3. **Fixture nyata sebagai test seed.** Simpan response void/refund/unpaid yang sudah kita punya sebagai fixture; jadikan contract test (OPS-102) dan test batas-tengah-malam (R1). Claude Code bekerja jauh lebih aman dengan test ada lebih dulu.
4. **Satu modul per sesi.** Kerjakan per epic (Ingestion → Reporting → Revenue → Signals → Delivery), bukan semua sekaligus; mudah di-review.
5. **Definition of Done dari file tiket** dipakai sebagai checklist PR.
6. **Putuskan R2 (rendering gambar) di luar Claude Code dulu** — itu keputusan infra (boleh tambah Chromium atau tidak), bukan keputusan koding.

---

## 5. Rekomendasi Akhir

Lanjutkan ke eksekusi. Urutan yang aman:

1. Selesaikan keputusan non-koding: R2 (cara render gambar), R5 (target pengiriman MVP), R3 (dapatkan tabel role).
2. Siapkan repo + `CLAUDE.md` + fixture test.
3. Sprint 1 (fondasi) via Claude Code: skema (OPS-101), interface NEVIRA (OPS-102), parser + normalisasi waktu (OPS-103, tangani R1), scheduler (OPS-104), kebijakan PII (OPS-705).
4. Baru lanjut ke laporan & sinyal.

Arsitektur tidak perlu diubah; yang perlu adalah **menutup 6 risiko di atas sebagai bagian dari tiket terkait**, bukan menundanya.
