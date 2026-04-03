# Database Process Business

Dokumen ini merangkum fungsi setiap tabel dan kolom utama agar alur bisnis KIP-K UNAIR lebih mudah divalidasi.

## Tabel `users`

Tabel ini menyimpan akun login Laravel.

Kolom utama:
- `id`: primary key user.
- `name`: nama pengguna untuk tampilan sistem.
- `email`: identitas login unik.
- `email_verified_at`: jejak verifikasi email jika nanti diaktifkan.
- `password`: hash password Laravel.
- `role`: membedakan `admin` dan `mahasiswa`.
- `remember_token`: token remember-me untuk session web.
- `created_at`, `updated_at`: audit waktu pembuatan dan perubahan akun.

Catatan bisnis:
- Tabel ini sudah tepat untuk auth Laravel.
- Jika nanti admin tidak dibuat dari registrasi umum, admin sebaiknya dibuat lewat seeder atau panel internal.

## Tabel `parameter_schema_versions`

Tabel ini menyimpan versi aturan parameter yang aktif di sistem.

Kolom utama:
- `id`: primary key versi schema.
- `version`: nomor versi schema.
- `source_file_name`: nama file sumber import schema.
- `parameter_definitions`: definisi JSON untuk aturan parameter.
- `is_active`: penanda schema aktif.
- `imported_by`: user admin yang mengimpor schema.
- `created_at`, `updated_at`: audit waktu.

Catatan bisnis:
- Tabel ini berguna jika aturan parameter bisa berubah per batch atau per tahun.
- Jika V1 Anda tetap hanya 13 fitur tanpa perubahan struktur, tabel ini tetap berguna tetapi tidak perlu terlalu kompleks.

## Tabel `student_applications`

Tabel ini adalah tabel bisnis utama. Fungsinya menyimpan data pengajuan mahasiswa, baik dari form online maupun import offline.

### Identitas pengajuan
- `id`: primary key pengajuan.
- `schema_version`: versi aturan saat pengajuan dicatat.
- `student_user_id`: relasi ke akun mahasiswa. `null` untuk data offline yang belum terhubung ke akun.
- `submission_source`: sumber pengajuan, misalnya `online_student` atau `offline_admin_import`.

### Identitas mahasiswa dan sumber offline
- `applicant_name`: nama mahasiswa pada saat submit/import.
- `applicant_email`: email mahasiswa jika ada.
- `study_program`: prodi mahasiswa.
- `faculty`: fakultas mahasiswa.
- `source_reference_number`: nomor referensi dari file/rekap eksternal.
- `source_document_link`: link dokumen sumber dari import offline.
- `source_sheet_name`: nama sheet Excel asal data.
- `source_row_number`: nomor baris dari sheet asal.
- `source_label_text`: label mentah dari file sumber, misalnya `layak`, `indikasi`, atau catatan lain.
- `imported_at`: waktu import offline.

### Data bantuan sosial
- `kip`
- `pkh`
- `kks`
- `dtks`
- `sktm`

Catatan:
- Lima kolom ini tetap cocok disimpan langsung di tabel bisnis karena nilainya memang jawaban mentah `0/1`.

### Data ordinal yang saat ini masih transisi
- `penghasilan_gabungan`
- `penghasilan_ayah`
- `penghasilan_ibu`
- `jumlah_tanggungan`
- `anak_ke`
- `status_orangtua`
- `status_rumah`
- `daya_listrik`

Catatan:
- Delapan kolom ini sekarang dibuat `nullable`.
- Secara desain bisnis terbaru, kolom-kolom ini seharusnya tidak menjadi sumber utama data mentah.
- Kolom-kolom ini lebih cocok dipakai sebagai kolom transisi atau snapshot hasil encoding sebelum nanti dipindah penuh ke layer model.

### Data mentah yang menjadi sumber encoding
- `penghasilan_ayah_rupiah`: nominal penghasilan ayah.
- `penghasilan_ibu_rupiah`: nominal penghasilan ibu.
- `penghasilan_gabungan_rupiah`: nominal gabungan ayah + ibu.
- `jumlah_tanggungan_raw`: jumlah tanggungan asli.
- `anak_ke_raw`: urutan anak asli.
- `status_orangtua_text`: teks mentah status orang tua.
- `status_rumah_text`: teks mentah status rumah.
- `daya_listrik_text`: teks mentah daya listrik.

Catatan bisnis:
- Ini adalah arah yang benar jika sistem Anda memang dua lapis: data mentah dulu, encoding kemudian.

### Dokumen mahasiswa
- `submitted_pdf_path`: path PDF yang diunggah ke storage Laravel.
- `submitted_pdf_original_name`: nama file asli.
- `submitted_pdf_uploaded_at`: waktu upload.

Catatan bisnis:
- Untuk data offline, kolom ini boleh `null` karena sumber dokumen berasal dari `source_document_link`.

### Workflow admin
- `status`: status proses, misalnya `Submitted`, `Verified`, `Rejected`.
- `admin_decision`: keputusan final admin.
- `admin_decided_by`: admin yang memutuskan.
- `admin_decision_note`: alasan atau catatan keputusan.
- `admin_decided_at`: waktu keputusan final.
- `created_at`, `updated_at`: audit waktu umum.

Catatan bisnis:
- `status` dan `admin_decision` masih masuk akal dipisah jika nanti Anda ingin ada status tambahan seperti `Under Review`.
- Jika alur Anda tetap sederhana dan hanya tiga status, dua kolom ini bisa dipertimbangkan untuk disederhanakan di iterasi berikutnya.

## Tabel `application_status_logs`

Tabel ini menyimpan riwayat perubahan status pengajuan.

Kolom utama:
- `id`: primary key log.
- `application_id`: relasi ke pengajuan.
- `actor_user_id`: siapa yang melakukan aksi.
- `from_status`: status sebelum perubahan.
- `to_status`: status sesudah perubahan.
- `action`: aksi sistem, misalnya `submitted`, `verified`, `rejected`, `imported_offline`.
- `note`: catatan singkat.
- `metadata`: detail tambahan dalam JSON.
- `created_at`, `updated_at`: audit waktu log.

Catatan bisnis:
- Tabel ini penting dan sudah tepat.
- Audit log sebaiknya tidak dihapus walau data pengajuan diubah.

## Tabel `application_model_snapshots`

Tabel ini menyimpan snapshot hasil encoding dan hasil inferensi model untuk satu pengajuan.

Kolom utama:
- `id`: primary key snapshot.
- `application_id`: satu snapshot untuk satu pengajuan.
- `schema_version`: versi schema saat snapshot dibuat.
- `model_version_id`: versi model yang dipakai saat prediksi.

### Fitur encoded untuk model
- `kip`
- `pkh`
- `kks`
- `dtks`
- `sktm`
- `penghasilan_gabungan`
- `penghasilan_ayah`
- `penghasilan_ibu`
- `jumlah_tanggungan`
- `anak_ke`
- `status_orangtua`
- `status_rumah`
- `daya_listrik`

### Hasil model
- `model_ready`: apakah model aktif benar-benar siap.
- `catboost_label`: label dari CatBoost.
- `catboost_confidence`: confidence CatBoost.
- `naive_bayes_label`: label dari Naive Bayes.
- `naive_bayes_confidence`: confidence Naive Bayes.
- `disagreement_flag`: apakah dua model berbeda hasil.
- `final_recommendation`: rekomendasi final sistem, saat ini mengikuti CatBoost.
- `review_priority`: prioritas review admin.
- `rule_score`: skor dari rule-based layer.
- `rule_recommendation`: hasil rule-based layer.
- `snapshotted_at`: waktu snapshot dibuat.
- `created_at`, `updated_at`: audit waktu.

Catatan bisnis:
- Tabel ini sudah tepat untuk memisahkan data prediksi dari data bisnis.
- Ini memang tempat yang benar untuk menyimpan hasil encoding, bukan `student_applications`.

## Tabel `spk_training_data`

Tabel ini menyimpan dataset final yang dipakai untuk retrain model.

Kolom utama:
- `id`: primary key dataset row.
- `source_application_id`: relasi logis ke pengajuan sumber.
- `schema_version`: versi schema encoding yang dipakai.

### Fitur encoded
- `kip`
- `pkh`
- `kks`
- `dtks`
- `sktm`
- `penghasilan_gabungan`
- `penghasilan_ayah`
- `penghasilan_ibu`
- `jumlah_tanggungan`
- `anak_ke`
- `status_orangtua`
- `status_rumah`
- `daya_listrik`

### Label training
- `label`: label teks, `Layak` atau `Indikasi`.
- `label_class`: class numerik, `0` atau `1`.
- `is_active`: apakah row aktif dipakai retrain.
- `admin_corrected`: apakah admin pernah koreksi manual row training.
- `correction_note`: alasan koreksi.
- `created_at`, `updated_at`: audit waktu.

Catatan bisnis:
- Ini adalah tabel yang tepat untuk encoding dan training.
- Jika nanti Anda ingin full traceability, Anda bisa menambah `encoded_from_snapshot_id` atau `encoded_at`.

## Tabel `model_versions`

Tabel ini menyimpan histori retrain model.

Kolom utama:
- `id`: primary key versi model.
- `version_name`: nama versi model unik.
- `schema_version`: schema yang dipakai saat retrain.
- `status`: status retrain, misalnya `ready` atau `failed`.
- `triggered_by_user_id`: admin yang memicu retrain.
- `triggered_by_email`: email admin saat retrain dilakukan.
- `training_table`: tabel sumber training, default `spk_training_data`.
- `primary_model`: model utama, saat ini `catboost`.
- `secondary_model`: model pembanding, saat ini `categorical_nb`.
- `dataset_rows_total`: total data kandidat sebelum filter.
- `rows_used`: total data yang dipakai.
- `train_rows`: jumlah data train.
- `validation_rows`: jumlah data validation.
- `validation_strategy`: strategi validasi.
- `class_distribution`: distribusi label.
- `catboost_artifact_path`: path file model CatBoost.
- `naive_bayes_artifact_path`: path file model Naive Bayes.
- `catboost_train_accuracy`
- `catboost_validation_accuracy`
- `naive_bayes_train_accuracy`
- `naive_bayes_validation_accuracy`
- `note`: catatan umum retrain.
- `error_message`: error jika retrain gagal.
- `trained_at`: waktu model selesai dilatih.
- `created_at`, `updated_at`: audit waktu.

Catatan bisnis:
- Tabel ini sangat berguna untuk governance model.
- Ini memudahkan audit jika ada pertanyaan “prediksi ini dibuat oleh model versi yang mana”.

## Evaluasi proses bisnis saat ini

Proses saat ini sudah mendekati arsitektur yang benar:
- `student_applications` untuk data operasional dan mentah.
- `application_model_snapshots` untuk hasil encoding dan inferensi.
- `spk_training_data` untuk dataset retrain final.
- `model_versions` untuk histori model.

Hal yang masih transisi:
- Delapan kolom encoded ordinal masih ada di `student_applications`.
- Itu aman untuk kompatibilitas kode saat ini, tetapi secara desain jangka menengah kolom tersebut sebaiknya tidak lagi menjadi sumber kebenaran utama.

Rekomendasi urutan perbaikan berikutnya:
1. Ubah form submit mahasiswa agar mengirim data mentah, bukan kode ordinal.
2. Buat service encoder khusus dari `student_applications` mentah ke `application_model_snapshots` dan `spk_training_data`.
3. Setelah alur raw sudah penuh dipakai, pertimbangkan menghapus kolom encoded ordinal dari `student_applications`.
