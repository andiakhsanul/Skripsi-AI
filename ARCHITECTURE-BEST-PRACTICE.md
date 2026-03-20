# Best Practice Arsitektur SPK SaaS V1

Dokumen ini merangkum pola implementasi yang Anda minta:
- Laravel sebagai pusat proses bisnis mahasiswa/admin, autentikasi, dan validasi parameter.
- Flask ML API sebagai service inferensi CatBoost + Naive Bayes dan retrain model.
- PostgreSQL sebagai sumber data aplikasi, schema parameter, dan data training.
- Single-tenant untuk UNAIR (tanpa multi-tenant dan billing di v1).

## Alur Utama

1. Admin mengimpor batch parameter dari Excel/CSV ke `parameter_schema_versions` dan mengaktifkan schema versi terbaru.
2. Mahasiswa login lalu submit pengajuan pada Laravel (`student_applications`) dengan `schema_version` aktif.
3. Laravel memanggil Flask `POST /api/predict` untuk mendapatkan output dual model:
- CatBoost label + confidence
- Naive Bayes label + confidence
- disagreement flag, final recommendation, review priority
4. Laravel menghitung `rule_score` dan `rule_recommendation` dari schema parameter aktif (rule-based).
5. Mahasiswa dapat unggah dokumen pendukung satu PDF per pengajuan.
6. Admin memverifikasi atau menolak pengajuan. Keputusan final disimpan pada record yang sama.
7. Keputusan admin disinkronkan ke `spk_training_data` untuk data retrain.
8. Admin menekan tombol retrain (`POST /api/admin/models/retrain`), Laravel meneruskan ke Flask `POST /api/retrain` dengan `schema_version`.
9. Flask melatih ulang model dari dataset aktif sesuai schema versi yang diminta.

## Endpoint Penting

### Laravel
- Auth:
- `POST /api/auth/register-student`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- Mahasiswa:
- `POST /api/student/applications`
- `GET /api/student/applications`
- `GET /api/student/applications/{id}`
- `POST /api/student/applications/{id}/document`
- Admin:
- `POST /api/admin/parameters/import`
- `GET /api/admin/parameters/schema-versions`
- `GET /api/admin/applications`
- `GET /api/admin/applications/{id}`
- `POST /api/admin/applications/{id}/verify`
- `POST /api/admin/applications/{id}/reject`
- `POST /api/admin/models/retrain`
- Legacy compatibility:
- `POST /api/spk/run-prediction`
- `POST /api/spk/retrain-model`

### Flask
- `GET /api/health`
- `POST /api/predict`
- `POST /api/retrain`

## Kontrak Output Prediksi

- `catboost_label`, `catboost_confidence`
- `naive_bayes_label`, `naive_bayes_confidence`
- `disagreement_flag`
- `final_recommendation` (mengikuti output CatBoost)
- `review_priority` (`high` jika disagreement atau confidence CatBoost di bawah threshold)

## Kolom Penting Pengajuan

- `rule_score` dan `rule_recommendation` untuk aturan berbasis parameter.
- `document_submission_link` untuk endpoint upload dokumen per pengajuan.
- `supporting_document_url` untuk URL PDF dokumen pendukung yang sudah diunggah.

## Konsep Keamanan Internal Service

- Gunakan header `X-Internal-Token` saat Laravel mengakses endpoint internal Flask.
- Simpan token di environment variable `FLASK_INTERNAL_TOKEN`.
- Jangan commit token produksi ke repository.
- Akses API Laravel menggunakan Bearer token internal (`api_tokens`) dan role (`admin` / `mahasiswa`).

## Praktik Operasional

1. Gunakan satu file compose untuk sederhana, tetapi tetap pisahkan environment:
- Root compose env di `.env`
- Flask env di `flask-ml-api/.env`
- Laravel env di `laravel-web/.env`

2. Aktifkan healthcheck:
- PostgreSQL dicek dengan `pg_isready`
- Flask dicek dengan endpoint `/api/health`

3. Simpan model hasil training di volume atau folder persisten:
- `flask-ml-api/models`

4. Gunakan data training yang telah dibersihkan admin:
- Hindari retrain dari data mentah yang belum tervalidasi.
- Retrain default menggunakan data sesuai `schema_version` aktif.

## Langkah Menjalankan

1. Pastikan semua file env sudah terisi.
2. Jalankan container:
   - `docker compose up -d --build`
3. Login admin dan impor schema parameter:
   - `POST /api/admin/parameters/import`
4. Login mahasiswa dan submit pengajuan:
   - `POST /api/student/applications`
5. Verifikasi admin lalu retrain model:
   - `POST /api/admin/applications/{id}/verify`
   - `POST /api/admin/models/retrain`
