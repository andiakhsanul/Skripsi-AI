<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SPK KIP-K</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0a0d14;--surface:#111827;--s2:#1f2937;--s3:#374151;
  --bd:#1f2937;--bd2:#374151;
  --txt:#f9fafb;--txt2:#9ca3af;--txt3:#6b7280;
  --blue:#3b82f6;--blue2:#1d4ed8;--bglow:rgba(59,130,246,.15);
  --green:#10b981;--gbg:rgba(16,185,129,.12);
  --yellow:#f59e0b;--ybg:rgba(245,158,11,.12);
  --red:#ef4444;--rbg:rgba(239,68,68,.12);
  --purple:#8b5cf6;--r:12px;--rsm:8px;
}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh}
.hidden{display:none!important}
.page{display:none;min-height:100vh}
.page.active{display:block}
.auth-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;background:radial-gradient(ellipse at 60% 20%,rgba(59,130,246,.08) 0%,transparent 60%)}
.auth-card{background:rgba(17,24,39,.9);backdrop-filter:blur(20px);border:1px solid var(--bd2);border-radius:20px;padding:40px;width:100%;max-width:420px;box-shadow:0 25px 60px rgba(0,0,0,.5)}
.logo{text-align:center;margin-bottom:28px}
.logo-icon{width:56px;height:56px;background:linear-gradient(135deg,var(--blue),var(--purple));border-radius:14px;display:inline-flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:12px}
.logo h1{font-size:1.4rem;font-weight:700}
.logo p{color:var(--txt2);font-size:.82rem;margin-top:4px}
.form-group{margin-bottom:18px}
label{display:block;font-size:.82rem;font-weight:500;color:var(--txt2);margin-bottom:6px}
input,select,textarea{width:100%;padding:10px 14px;background:var(--s2);border:1px solid var(--bd2);border-radius:var(--rsm);color:var(--txt);font-family:inherit;font-size:.875rem;outline:none;transition:border-color .2s,box-shadow .2s}
input:focus,select:focus,textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px var(--bglow)}
.pfx{display:flex}.pfx span{padding:10px 12px;background:var(--s3);border:1px solid var(--bd2);border-right:none;border-radius:var(--rsm) 0 0 var(--rsm);font-size:.8rem;color:var(--txt2);white-space:nowrap}
.pfx input{border-radius:0 var(--rsm) var(--rsm) 0}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 20px;border:none;border-radius:var(--rsm);font-family:inherit;font-size:.875rem;font-weight:500;cursor:pointer;transition:all .2s;text-decoration:none}
.btn-primary{background:linear-gradient(135deg,var(--blue),#6366f1);color:#fff;box-shadow:0 4px 15px var(--bglow)}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(59,130,246,.4)}
.btn-success{background:var(--green);color:#fff}
.btn-success:hover{opacity:.9}
.btn-danger{background:var(--red);color:#fff}
.btn-danger:hover{opacity:.85}
.btn-ghost{background:transparent;color:var(--txt2);border:1px solid var(--bd2)}
.btn-ghost:hover{color:var(--txt);background:var(--s2)}
.btn-sm{padding:6px 14px;font-size:.8rem}
.btn-block{width:100%}
.btn:disabled{opacity:.45;cursor:not-allowed;transform:none!important}
.navbar{display:flex;align-items:center;justify-content:space-between;padding:14px 24px;background:rgba(10,13,20,.9);backdrop-filter:blur(20px);border-bottom:1px solid var(--bd);position:sticky;top:0;z-index:100}
.nav-brand{font-weight:700;color:var(--blue);font-size:.95rem}
.nav-brand span{color:var(--txt);font-weight:400}
.nav-right{display:flex;align-items:center;gap:12px}
.nav-name{font-size:.8rem;color:var(--txt2)}
.main-content{padding:28px 24px;max-width:1200px;margin:0 auto}
.card{background:var(--surface);border:1px solid var(--bd);border-radius:var(--r);padding:24px}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:28px}
.stat{background:var(--surface);border:1px solid var(--bd);border-radius:var(--r);padding:20px;transition:border-color .2s,transform .2s}
.stat:hover{border-color:var(--blue);transform:translateY(-2px)}
.stat-n{font-size:2.2rem;font-weight:700;line-height:1}
.stat-l{font-size:.78rem;color:var(--txt2);margin-top:6px;font-weight:500}
.c-blue .stat-n{color:var(--blue)} .c-green .stat-n{color:var(--green)}
.c-yellow .stat-n{color:var(--yellow)} .c-red .stat-n{color:var(--red)}
.c-purple .stat-n{color:var(--purple)}
.sec-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.sec-title{font-size:1.1rem;font-weight:700}
.filter-row{display:flex;gap:10px;flex-wrap:wrap}
.filter-row select{padding:8px 12px;font-size:.82rem;min-width:130px}
.tbl-wrap{overflow-x:auto;border-radius:var(--r);border:1px solid var(--bd)}
table{width:100%;border-collapse:collapse}
th{background:var(--s2);color:var(--txt2);font-weight:600;padding:11px 16px;text-align:left;font-size:.8rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}
td{padding:12px 16px;border-top:1px solid var(--bd);font-size:.875rem;vertical-align:middle}
tr:hover td{background:rgba(31,41,55,.5)}
.badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.72rem;font-weight:600;letter-spacing:.02em}
.b-sub{background:var(--ybg);color:var(--yellow)}
.b-ver{background:var(--gbg);color:var(--green)}
.b-rej{background:var(--rbg);color:var(--red)}
.b-high{background:var(--rbg);color:var(--red)}
.b-normal{background:rgba(107,114,128,.12);color:var(--txt3)}
.alert{padding:12px 16px;border-radius:var(--rsm);margin-bottom:16px;font-size:.85rem;border-left:3px solid}
.a-err{background:var(--rbg);border-color:var(--red);color:#fca5a5}
.a-ok{background:var(--gbg);border-color:var(--green);color:#6ee7b7}
.a-info{background:var(--bglow);border-color:var(--blue);color:#93c5fd}
.toggle-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:4px}
.tgl{display:flex;align-items:center;gap:10px;cursor:pointer;padding:10px 14px;border:1px solid var(--bd2);border-radius:var(--rsm);transition:all .2s}
.tgl:hover{border-color:var(--blue)}
.tgl input{display:none}
.tgl-box{width:18px;height:18px;border:2px solid var(--bd2);border-radius:4px;display:flex;align-items:center;justify-content:center;transition:all .2s;flex-shrink:0}
.tgl input:checked ~ .tgl-box{background:var(--blue);border-color:var(--blue)}
.tgl input:checked ~ .tgl-box::after{content:'✓';font-size:11px;color:#fff;font-weight:700}
.tgl-name{font-size:.875rem}
.drop-zone{border:2px dashed var(--bd2);border-radius:var(--r);padding:32px;text-align:center;cursor:pointer;transition:all .2s;position:relative}
.drop-zone:hover,.drop-zone.over{border-color:var(--blue);background:var(--bglow)}
.dz-icon{font-size:2rem;margin-bottom:8px}
.dz-txt{font-size:.875rem;color:var(--txt2)}
.dz-file{font-size:.85rem;color:var(--green);margin-top:8px;font-weight:500}
.conf-row{display:flex;align-items:center;gap:12px;margin-bottom:10px}
.conf-lbl{font-size:.78rem;color:var(--txt2);width:110px;flex-shrink:0}
.conf-bar{flex:1;background:var(--s2);border-radius:999px;height:7px;overflow:hidden}
.conf-fill{height:100%;border-radius:999px;transition:width .7s ease}
.conf-val{font-size:.78rem;font-weight:600;width:44px;text-align:right}
.detail-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:20px}
.di{background:var(--s2);border-radius:var(--rsm);padding:14px}
.di label{font-size:.72rem;color:var(--txt3);margin-bottom:4px;display:block}
.di-val{font-size:.9rem;font-weight:500}
.timeline{padding-left:16px;border-left:2px solid var(--bd2);margin-top:16px}
.tl-item{position:relative;padding:0 0 18px 20px}
.tl-item::before{content:'';position:absolute;left:-7px;top:5px;width:12px;height:12px;border-radius:50%;background:var(--blue);border:2px solid var(--bg)}
.tl-date{font-size:.72rem;color:var(--txt3)}
.tl-text{font-size:.875rem}
.spinner{display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.divider{border:none;border-top:1px solid var(--bd);margin:20px 0}
.link{color:var(--blue);cursor:pointer;background:none;border:none;font-family:inherit;font-size:inherit;padding:0;text-decoration:none}
.link:hover{text-decoration:underline}
.auth-foot{text-align:center;margin-top:20px;font-size:.85rem;color:var(--txt2)}
.back-btn{display:inline-flex;align-items:center;gap:6px;color:var(--txt2);cursor:pointer;background:none;border:none;font-family:inherit;font-size:.875rem;margin-bottom:20px;padding:0}
.back-btn:hover{color:var(--txt)}
.encoding-legend{background:var(--s2);border-radius:var(--rsm);padding:14px;font-size:.78rem;color:var(--txt3);margin-top:6px}
.encoding-legend span{color:var(--txt2);font-weight:500}
.page-wrap{padding:28px 24px;max-width:1100px;margin:0 auto}
</style>
</head>
<body>

<!-- LOGIN PAGE -->
<div id="page-login" class="page active">
<div class="auth-wrap">
<div class="auth-card">
<div class="logo">
  <div class="logo-icon">🎓</div>
  <h1>SPK KIP-K</h1>
  <p>Sistem Pendukung Keputusan Kartu Indonesia Pintar — Kuliah</p>
</div>
<div id="login-alert"></div>
<div class="form-group">
  <label>Email</label>
  <input id="login-email" type="email" placeholder="email@example.com" autocomplete="email">
</div>
<div class="form-group">
  <label>Password</label>
  <input id="login-password" type="password" placeholder="••••••••">
</div>
<button class="btn btn-primary btn-block" id="login-btn" onclick="doLogin()">
  <span id="login-btn-txt">Masuk</span>
</button>
<div class="auth-foot">Belum punya akun? <a onclick="goPage('page-register')">Daftar sebagai Mahasiswa</a></div>
</div>
</div>
</div>

<!-- REGISTER PAGE -->
<div id="page-register" class="page">
<div class="auth-wrap">
<div class="auth-card">
<div class="logo">
  <div class="logo-icon">✍️</div>
  <h1>Daftar Mahasiswa</h1>
  <p>Buat akun untuk mengajukan permohonan KIP-K</p>
</div>
<div id="reg-alert"></div>
<div class="form-group"><label>Nama Lengkap</label><input id="reg-name" type="text" placeholder="Nama sesuai KTP"></div>
<div class="form-group"><label>Email</label><input id="reg-email" type="email" placeholder="email@mahasiswa.ac.id"></div>
<div class="form-group"><label>Password</label><input id="reg-password" type="password" placeholder="Minimal 8 karakter"></div>
<button class="btn btn-primary btn-block" onclick="doRegister()"><span id="reg-btn-txt">Daftar</span></button>
<div class="auth-foot">Sudah punya akun? <a onclick="goPage('page-login')">Masuk</a></div>
</div>
</div>
</div>

<!-- MAHASISWA DASHBOARD -->
<div id="page-mhs" class="page">
<nav class="navbar">
  <div class="nav-brand">SPK KIP-K <span>— Mahasiswa</span></div>
  <div class="nav-right">
    <span class="nav-name" id="mhs-name">-</span>
    <button class="btn btn-ghost btn-sm" onclick="doLogout()">Keluar</button>
  </div>
</nav>
<div class="page-wrap">
  <div class="sec-header">
    <div class="sec-title">Pengajuan Saya</div>
    <button class="btn btn-primary btn-sm" onclick="goPage('page-mhs-form')">+ Ajukan KIP-K</button>
  </div>
  <div id="mhs-alert"></div>
  <div id="mhs-applications">
    <div class="card" style="text-align:center;color:var(--txt2);padding:48px">
      <div style="font-size:2.5rem;margin-bottom:12px">📋</div>
      <p>Belum ada pengajuan. Klik tombol <strong>Ajukan KIP-K</strong> untuk memulai.</p>
    </div>
  </div>
</div>
</div>

<!-- MAHASISWA FORM -->
<div id="page-mhs-form" class="page">
<nav class="navbar">
  <div class="nav-brand">SPK KIP-K <span>— Form Pengajuan</span></div>
  <div class="nav-right">
    <button class="btn btn-ghost btn-sm" onclick="goPage('page-mhs')">← Kembali</button>
  </div>
</nav>
<div class="page-wrap">
  <div class="card">
    <h2 style="margin-bottom:8px;font-size:1.1rem">Form Pengajuan KIP-K</h2>
    <p style="font-size:.84rem;color:var(--txt2);margin-bottom:24px">Isi data dengan jujur dan sesuai kondisi aktual. Pastikan Anda juga mengupload formulir PDF dari Ditmawa.</p>
    <div id="form-alert"></div>

    <h3 style="font-size:.9rem;font-weight:600;color:var(--txt2);margin-bottom:12px;text-transform:uppercase;letter-spacing:.05em">1. Kepemilikan Dokumen Bantuan Sosial</h3>
    <p style="font-size:.8rem;color:var(--txt3);margin-bottom:14px">Centang jika Anda/keluarga memiliki/terdaftar dalam program berikut:</p>
    <div class="toggle-grid" style="margin-bottom:24px">
      <label class="tgl"><input type="checkbox" id="f-kip"><span class="tgl-box"></span><span class="tgl-name">KIP (Kartu Indonesia Pintar)</span></label>
      <label class="tgl"><input type="checkbox" id="f-pkh"><span class="tgl-box"></span><span class="tgl-name">PKH (Program Keluarga Harapan)</span></label>
      <label class="tgl"><input type="checkbox" id="f-kks"><span class="tgl-box"></span><span class="tgl-name">KKS (Kartu Keluarga Sejahtera)</span></label>
      <label class="tgl"><input type="checkbox" id="f-dtks"><span class="tgl-box"></span><span class="tgl-name">DTKS (Data Terpadu Kesejahteraan Sosial)</span></label>
      <label class="tgl"><input type="checkbox" id="f-sktm"><span class="tgl-box"></span><span class="tgl-name">SKTM (Surat Keterangan Tidak Mampu)</span></label>
    </div>

    <h3 style="font-size:.9rem;font-weight:600;color:var(--txt2);margin-bottom:12px;text-transform:uppercase;letter-spacing:.05em">2. Penghasilan Keluarga</h3>
    <div class="detail-grid" style="margin-bottom:24px">
      <div class="form-group" style="margin:0">
        <label>Penghasilan Gabungan (Rp/bulan)</label>
        <div class="pfx"><span>Rp</span><input type="number" id="f-pg" min="0" placeholder="850000"></div>
        <div class="encoding-legend">≥4jt → Normal &nbsp;|&nbsp; 1-4jt → Sedang &nbsp;|&nbsp; <span>&lt;1jt → Prioritas</span></div>
      </div>
      <div class="form-group" style="margin:0">
        <label>Penghasilan Ayah (Rp/bulan)</label>
        <div class="pfx"><span>Rp</span><input type="number" id="f-pa" min="0" placeholder="500000"></div>
      </div>
      <div class="form-group" style="margin:0">
        <label>Penghasilan Ibu (Rp/bulan)</label>
        <div class="pfx"><span>Rp</span><input type="number" id="f-pi" min="0" placeholder="350000"></div>
      </div>
    </div>

    <h3 style="font-size:.9rem;font-weight:600;color:var(--txt2);margin-bottom:12px;text-transform:uppercase;letter-spacing:.05em">3. Kondisi Keluarga</h3>
    <div class="detail-grid" style="margin-bottom:24px">
      <div class="form-group" style="margin:0">
        <label>Jumlah Tanggungan (orang)</label>
        <input type="number" id="f-jt" min="0" max="20" placeholder="Contoh: 5">
        <div class="encoding-legend">≥6 org → <span>Prioritas</span> &nbsp;|&nbsp; 4-5 → Sedang &nbsp;|&nbsp; 0-3 → Normal</div>
      </div>
      <div class="form-group" style="margin:0">
        <label>Anda anak ke-</label>
        <input type="number" id="f-ak" min="1" max="20" placeholder="Contoh: 3">
        <div class="encoding-legend">≥5 → <span>Prioritas</span> &nbsp;|&nbsp; 3-4 → Sedang &nbsp;|&nbsp; 1-2 → Normal</div>
      </div>
      <div class="form-group" style="margin:0">
        <label>Status Orang Tua</label>
        <select id="f-so">
          <option value="">-- Pilih --</option>
          <option value="Lengkap">Lengkap (Ayah & Ibu ada)</option>
          <option value="Yatim">Yatim (Ayah meninggal)</option>
          <option value="Piatu">Piatu (Ibu meninggal)</option>
          <option value="Yatim Piatu">Yatim Piatu (keduanya meninggal)</option>
        </select>
      </div>
    </div>

    <h3 style="font-size:.9rem;font-weight:600;color:var(--txt2);margin-bottom:12px;text-transform:uppercase;letter-spacing:.05em">4. Standar Hidup</h3>
    <div class="detail-grid" style="margin-bottom:24px">
      <div class="form-group" style="margin:0">
        <label>Status Rumah</label>
        <select id="f-sr">
          <option value="">-- Pilih --</option>
          <option value="Milik Sendiri">Milik Sendiri</option>
          <option value="Sewa">Sewa / Kontrak</option>
          <option value="Menumpang">Menumpang</option>
          <option value="Tidak Punya">Tidak Memiliki Rumah</option>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label>Daya Listrik</label>
        <select id="f-dl">
          <option value="">-- Pilih --</option>
          <option value="PLN >900VA">PLN di atas 900 VA</option>
          <option value="PLN 450-900VA">PLN 450 VA atau 900 VA</option>
          <option value="Non-PLN">Tidak Ada Listrik / Non-PLN</option>
        </select>
      </div>
    </div>

    <h3 style="font-size:.9rem;font-weight:600;color:var(--txt2);margin-bottom:12px;text-transform:uppercase;letter-spacing:.05em">5. Formulir PDF Ditmawa <span style="color:var(--red)">*Wajib</span></h3>
    <p style="font-size:.8rem;color:var(--txt3);margin-bottom:12px">Upload formulir PDF yang telah diberikan Ditmawa secara offline. Maks 10MB.</p>
    <div class="drop-zone" id="drop-zone" onclick="document.getElementById('f-pdf').click()" ondragover="event.preventDefault();this.classList.add('over')" ondragleave="this.classList.remove('over')" ondrop="handleDrop(event)">
      <div class="dz-icon">📄</div>
      <div class="dz-txt">Klik atau seret file PDF ke sini</div>
      <div class="dz-file" id="dz-filename">Belum ada file dipilih</div>
    </div>
    <input type="file" id="f-pdf" accept=".pdf" style="display:none" onchange="onFileSelected(this)">

    <div style="margin-top:28px;display:flex;gap:12px;flex-wrap:wrap">
      <button class="btn btn-primary" id="submit-btn" onclick="submitApplication()" style="min-width:160px">
        <span id="submit-txt">Kirim Pengajuan</span>
      </button>
      <button class="btn btn-ghost" onclick="goPage('page-mhs')">Batal</button>
    </div>
  </div>
</div>
</div>

<!-- MAHASISWA DETAIL -->
<div id="page-mhs-detail" class="page">
<nav class="navbar">
  <div class="nav-brand">SPK KIP-K <span>— Detail Pengajuan</span></div>
  <div class="nav-right">
    <button class="btn btn-ghost btn-sm" onclick="goPage('page-mhs')">← Kembali</button>
  </div>
</nav>
<div class="page-wrap" id="mhs-detail-content"></div>
</div>

<!-- ADMIN DASHBOARD -->
<div id="page-admin" class="page">
<nav class="navbar">
  <div class="nav-brand">SPK KIP-K <span>— Admin</span></div>
  <div class="nav-right">
    <span class="nav-name" id="admin-name">-</span>
    <button class="btn btn-ghost btn-sm" onclick="goPage('page-admin-retrain')" style="margin-right:4px">🤖 Retrain</button>
    <button class="btn btn-ghost btn-sm" onclick="doLogout()">Keluar</button>
  </div>
</nav>
<div class="page-wrap">
  <div class="sec-header"><div class="sec-title">Dashboard Admin</div></div>
  <div id="admin-stats" class="stats-grid">
    <div class="stat c-blue"><div class="stat-n" id="s-total">-</div><div class="stat-l">Total Pengajuan</div></div>
    <div class="stat c-yellow"><div class="stat-n" id="s-sub">-</div><div class="stat-l">Menunggu Verifikasi</div></div>
    <div class="stat c-green"><div class="stat-n" id="s-ver">-</div><div class="stat-l">Terverifikasi</div></div>
    <div class="stat c-red"><div class="stat-n" id="s-rej">-</div><div class="stat-l">Ditolak</div></div>
    <div class="stat c-red"><div class="stat-n" id="s-high">-</div><div class="stat-l">Prioritas Review Tinggi</div></div>
    <div class="stat c-purple"><div class="stat-n" id="s-train">-</div><div class="stat-l">Data Training Aktif</div></div>
  </div>
  <div class="card">
    <div class="sec-header">
      <div class="sec-title">Daftar Pengajuan</div>
      <div class="filter-row">
        <select id="f-status" onchange="loadAdminApps()"><option value="">Semua Status</option><option value="Submitted">Menunggu</option><option value="Verified">Terverifikasi</option><option value="Rejected">Ditolak</option></select>
        <select id="f-priority" onchange="loadAdminApps()"><option value="">Semua Prioritas</option><option value="high">Prioritas Tinggi</option><option value="normal">Normal</option></select>
      </div>
    </div>
    <div id="admin-app-list"><div style="text-align:center;padding:32px;color:var(--txt2)"><span class="spinner"></span> Memuat...</div></div>
  </div>
</div>
</div>

<!-- ADMIN DETAIL -->
<div id="page-admin-detail" class="page">
<nav class="navbar">
  <div class="nav-brand">SPK KIP-K <span>— Detail Pengajuan</span></div>
  <div class="nav-right"><button class="btn btn-ghost btn-sm" onclick="goPage('page-admin')">← Kembali</button></div>
</nav>
<div class="page-wrap" id="admin-detail-content"></div>
</div>

<!-- ADMIN CORRECTION -->
<div id="page-admin-correction" class="page">
<nav class="navbar">
  <div class="nav-brand">SPK KIP-K <span>— Koreksi Data Encoded</span></div>
  <div class="nav-right"><button class="btn btn-ghost btn-sm" id="corr-back-btn" onclick="goPage('page-admin-detail')">← Kembali</button></div>
</nav>
<div class="page-wrap">
  <div class="card">
    <h2 style="margin-bottom:6px">Koreksi Data Training</h2>
    <p style="font-size:.8rem;color:var(--txt2);margin-bottom:20px">Sesuaikan nilai encoded berdasarkan file PDF Ditmawa yang sudah Anda review. Nilai yang diubah akan digunakan saat retrain model berikutnya.</p>
    <div id="corr-alert"></div>
    <div id="corr-legend" class="alert a-info" style="margin-bottom:20px">
      <strong>Panduan Encoding:</strong><br>
      Biner (KIP/PKH/KKS/DTKS/SKTM): 0=Tidak, 1=Ya<br>
      Penghasilan: 1=&lt;1jt, 2=1-4jt, 3=≥4jt<br>
      Tanggungan: 1=≥6 org, 2=4-5 org, 3=0-3 org &nbsp;|&nbsp; Anak Ke: 1=≥ke-5, 2=ke-3/4, 3=ke-1/2<br>
      Status Ortu: 1=Yatim Piatu, 2=Yatim/Piatu, 3=Lengkap<br>
      Rumah: 1=Tidak Punya, 2=Sewa/Numpang, 3=Milik Sendiri &nbsp;|&nbsp; Listrik: 1=Non-PLN, 2=450-900VA, 3=&gt;900VA<br>
      Label: Layak=lolos, Indikasi=tidak lolos
    </div>
    <div id="corr-form"></div>
  </div>
</div>
</div>

<!-- ADMIN RETRAIN -->
<div id="page-admin-retrain" class="page">
<nav class="navbar">
  <div class="nav-brand">SPK KIP-K <span>— Retrain Model</span></div>
  <div class="nav-right"><button class="btn btn-ghost btn-sm" onclick="goPage('page-admin')">← Dashboard</button></div>
</nav>
<div class="page-wrap">
  <div class="card" style="max-width:600px">
    <h2 style="margin-bottom:6px">Retrain Model AI</h2>
    <p style="font-size:.82rem;color:var(--txt2);margin-bottom:20px">Melatih ulang model CatBoost (primer) dan Naive Bayes (sekunder) menggunakan seluruh data training yang aktif.</p>
    <div class="alert a-info">
      <strong>CatBoost</strong> adalah model utama — rekomendasi akhir selalu mengikuti CatBoost.<br>
      Naive Bayes digunakan sebagai pembanding untuk mendeteksi ketidaksepakatan antar model.
    </div>
    <div id="retrain-alert" style="margin-top:16px"></div>
    <div id="retrain-result" style="margin-top:16px"></div>
    <div style="margin-top:24px">
      <button class="btn btn-primary" id="retrain-btn" onclick="doRetrain()" style="min-width:180px">
        <span id="retrain-btn-txt">🚀 Mulai Retrain</span>
      </button>
    </div>
  </div>
</div>
</div>
<script>
const API = '/api';
let authToken = localStorage.getItem('spk_token') || '';
let authUser  = JSON.parse(localStorage.getItem('spk_user') || 'null');
let currentAppId = null;

function goPage(id) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  window.scrollTo(0, 0);
  if (id === 'page-mhs') loadMhsApps();
  if (id === 'page-admin') { loadAdminStats(); loadAdminApps(); }
}

function setAlert(id, msg, type='err') {
  const el = document.getElementById(id);
  if (!el) return;
  el.innerHTML = msg ? `<div class="alert a-${type==='ok'?'ok':type==='info'?'info':'err'}">${msg}</div>` : '';
}

function setLoading(btnId, txtId, loading, defaultTxt) {
  const btn = document.getElementById(btnId), txt = document.getElementById(txtId);
  if (!btn || !txt) return;
  btn.disabled = loading;
  txt.innerHTML = loading ? '<span class="spinner"></span>' : defaultTxt;
}

async function req(method, path, data, isForm) {
  const opts = { method, headers: { 'Accept': 'application/json' } };
  if (authToken) opts.headers['Authorization'] = 'Bearer ' + authToken;
  if (data) {
    if (isForm) { opts.body = data; }
    else { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(data); }
  }
  const r = await fetch(API + path, opts);
  const j = await r.json().catch(() => ({}));
  return { ok: r.ok, status: r.status, data: j };
}

async function doLogin() {
  const email = document.getElementById('login-email').value.trim();
  const pass  = document.getElementById('login-password').value;
  if (!email || !pass) { setAlert('login-alert','Email dan password wajib diisi'); return; }
  setLoading('login-btn','login-btn-txt',true,'Masuk');
  const {ok, data} = await req('POST','/auth/login',{email,password:pass});
  setLoading('login-btn','login-btn-txt',false,'Masuk');
  if (!ok) { setAlert('login-alert', data.message||'Login gagal'); return; }
  authToken = data.token; authUser = data.user;
  localStorage.setItem('spk_token', authToken);
  localStorage.setItem('spk_user', JSON.stringify(authUser));
  setAlert('login-alert','');
  routeByRole();
}

async function doRegister() {
  const name  = document.getElementById('reg-name').value.trim();
  const email = document.getElementById('reg-email').value.trim();
  const pass  = document.getElementById('reg-password').value;
  if (!name||!email||!pass) { setAlert('reg-alert','Semua field wajib diisi'); return; }
  if (pass.length < 8)      { setAlert('reg-alert','Password minimal 8 karakter'); return; }
  setLoading('reg-btn','reg-btn-txt',true,'Daftar');
  const {ok,data} = await req('POST','/auth/register-student',{name,email,password:pass});
  setLoading('reg-btn','reg-btn-txt',false,'Daftar');
  if (!ok) { setAlert('reg-alert', data.message||'Registrasi gagal'); return; }
  authToken = data.token; authUser = data.user;
  localStorage.setItem('spk_token', authToken);
  localStorage.setItem('spk_user', JSON.stringify(authUser));
  routeByRole();
}

async function doLogout() {
  await req('POST','/auth/logout');
  authToken = ''; authUser = null;
  localStorage.removeItem('spk_token'); localStorage.removeItem('spk_user');
  goPage('page-login');
}

function routeByRole() {
  if (!authUser) { goPage('page-login'); return; }
  if (authUser.role === 'admin') {
    document.getElementById('admin-name').textContent = authUser.name;
    goPage('page-admin');
  } else {
    document.getElementById('mhs-name').textContent = authUser.name;
    goPage('page-mhs');
  }
}

// ── Mahasiswa ──
async function loadMhsApps() {
  const {ok,data} = await req('GET','/student/applications');
  const el = document.getElementById('mhs-applications');
  if (!ok || !data.data || data.data.length === 0) {
    el.innerHTML = `<div class="card" style="text-align:center;color:var(--txt2);padding:48px"><div style="font-size:2.5rem;margin-bottom:12px">📋</div><p>Belum ada pengajuan.</p></div>`;
    return;
  }
  let rows = data.data.map(a => {
    const badgeClass = {Submitted:'b-sub',Verified:'b-ver',Rejected:'b-rej'}[a.status]||'b-sub';
    return `<tr onclick="loadMhsDetail(${a.id})" style="cursor:pointer">
      <td>#${a.id}</td>
      <td><span class="badge ${badgeClass}">${a.status}</span></td>
      <td>${a.ditmawa_pdf_uploaded_at ? '✅ Terupload' : '❌ Belum'}</td>
      <td>${new Date(a.created_at).toLocaleDateString('id-ID')}</td>
    </tr>`;
  }).join('');
  el.innerHTML = `<div class="tbl-wrap"><table><thead><tr><th>ID</th><th>Status</th><th>PDF Ditmawa</th><th>Tanggal</th></tr></thead><tbody>${rows}</tbody></table></div>`;
}

async function loadMhsDetail(id) {
  currentAppId = id;
  goPage('page-mhs-detail');
  const el = document.getElementById('mhs-detail-content');
  el.innerHTML = '<div style="text-align:center;padding:40px"><span class="spinner"></span></div>';
  const {ok,data} = await req('GET',`/student/applications/${id}`);
  if (!ok) { el.innerHTML = '<div class="alert a-err">Gagal memuat detail.</div>'; return; }
  const d = data.data;
  const badgeClass = {Submitted:'b-sub',Verified:'b-ver',Rejected:'b-rej'}[d.status]||'b-sub';
  let logs = (d.logs||[]).map(l => `<div class="tl-item"><div class="tl-date">${new Date(l.created_at).toLocaleString('id-ID')}</div><div class="tl-text">${l.action} → <strong>${l.to_status||'-'}</strong></div></div>`).join('');
  let note = d.admin_decision_note ? `<div class="alert a-info" style="margin-top:16px"><strong>Catatan Admin:</strong> ${d.admin_decision_note}</div>` : '';
  el.innerHTML = `
<div class="card">
  <h2 style="margin-bottom:20px">Detail Pengajuan #${d.id}</h2>
  <div class="detail-grid">
    <div class="di"><label>Status Pengajuan</label><div class="di-val"><span class="badge ${badgeClass}">${d.status}</span></div></div>
    <div class="di"><label>PDF Ditmawa</label><div class="di-val">${d.ditmawa_pdf_uploaded ? '✅ Sudah diupload' : '❌ Belum'}</div></div>
    <div class="di"><label>Tanggal Pengajuan</label><div class="di-val">${new Date(d.created_at).toLocaleDateString('id-ID')}</div></div>
    ${d.admin_decided_at ? `<div class="di"><label>Tanggal Keputusan</label><div class="di-val">${new Date(d.admin_decided_at).toLocaleDateString('id-ID')}</div></div>` : ''}
  </div>
  ${note}
  <hr class="divider">
  <h3 style="font-size:.9rem;font-weight:600;color:var(--txt2);margin-bottom:12px">Riwayat Status</h3>
  <div class="timeline">${logs || '<p style="color:var(--txt3)">Belum ada riwayat.</p>'}</div>
</div>`;
}

function onFileSelected(input) {
  const file = input.files[0];
  document.getElementById('dz-filename').textContent = file ? `📄 ${file.name}` : 'Belum ada file dipilih';
}
function handleDrop(e) {
  e.preventDefault();
  document.getElementById('drop-zone').classList.remove('over');
  const dt = e.dataTransfer;
  if (dt.files.length) {
    document.getElementById('f-pdf').files = dt.files;
    onFileSelected(document.getElementById('f-pdf'));
  }
}

async function submitApplication() {
  const pdf = document.getElementById('f-pdf').files[0];
  if (!pdf) { setAlert('form-alert','PDF Ditmawa wajib diupload'); return; }
  const pg = parseInt(document.getElementById('f-pg').value)||0;
  const pa = parseInt(document.getElementById('f-pa').value)||0;
  const pi = parseInt(document.getElementById('f-pi').value)||0;
  const jt = parseInt(document.getElementById('f-jt').value)||0;
  const ak = parseInt(document.getElementById('f-ak').value)||0;
  const so = document.getElementById('f-so').value;
  const sr = document.getElementById('f-sr').value;
  const dl = document.getElementById('f-dl').value;
  if (!so||!sr||!dl) { setAlert('form-alert','Semua pilihan status wajib diisi'); return; }
  if (ak < 1) { setAlert('form-alert','Anda anak ke- minimal 1'); return; }
  setLoading('submit-btn','submit-txt',true,'Kirim Pengajuan');
  const fd = new FormData();
  fd.append('kip', document.getElementById('f-kip').checked ? '1':'0');
  fd.append('pkh', document.getElementById('f-pkh').checked ? '1':'0');
  fd.append('kks', document.getElementById('f-kks').checked ? '1':'0');
  fd.append('dtks', document.getElementById('f-dtks').checked ? '1':'0');
  fd.append('sktm', document.getElementById('f-sktm').checked ? '1':'0');
  fd.append('penghasilan_gabungan_raw', pg);
  fd.append('penghasilan_ayah_raw', pa);
  fd.append('penghasilan_ibu_raw', pi);
  fd.append('jumlah_tanggungan_raw', jt);
  fd.append('anak_ke_raw', ak);
  fd.append('status_orangtua_raw', so);
  fd.append('status_rumah_raw', sr);
  fd.append('daya_listrik_raw', dl);
  fd.append('ditmawa_pdf', pdf);
  const {ok,data} = await req('POST','/student/applications', fd, true);
  setLoading('submit-btn','submit-txt',false,'Kirim Pengajuan');
  if (!ok) { setAlert('form-alert', data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Gagal mengirim pengajuan')); return; }
  setAlert('form-alert','Pengajuan berhasil dikirim! Menunggu verifikasi admin.','ok');
  setTimeout(() => goPage('page-mhs'), 1800);
}

// ── Admin ──
async function loadAdminStats() {
  const {ok,data} = await req('GET','/admin/stats');
  if (!ok) return;
  const d = data.data;
  document.getElementById('s-total').textContent = d.applications.total;
  document.getElementById('s-sub').textContent   = d.applications.submitted;
  document.getElementById('s-ver').textContent   = d.applications.verified;
  document.getElementById('s-rej').textContent   = d.applications.rejected;
  document.getElementById('s-high').textContent  = d.applications.high_priority_pending;
  document.getElementById('s-train').textContent = d.training_data.total_active;
}

async function loadAdminApps() {
  const status   = document.getElementById('f-status')?.value || '';
  const priority = document.getElementById('f-priority')?.value || '';
  let qs = [];
  if (status)   qs.push('status='+status);
  if (priority) qs.push('review_priority='+priority);
  const el = document.getElementById('admin-app-list');
  el.innerHTML = '<div style="text-align:center;padding:24px;color:var(--txt2)"><span class="spinner"></span></div>';
  const {ok,data} = await req('GET','/admin/applications'+(qs.length?'?'+qs.join('&'):''));
  if (!ok) { el.innerHTML = '<div class="alert a-err">Gagal memuat data.</div>'; return; }
  if (!data.data || !data.data.length) { el.innerHTML = '<p style="text-align:center;padding:24px;color:var(--txt2)">Tidak ada pengajuan ditemukan.</p>'; return; }
  let rows = data.data.map(a => {
    const bc = {Submitted:'b-sub',Verified:'b-ver',Rejected:'b-rej'}[a.status]||'b-sub';
    const pc = a.review_priority==='high'?'b-high':'b-normal';
    return `<tr onclick="loadAdminDetail(${a.id})" style="cursor:pointer">
      <td>#${a.id}</td><td>${a.student?.name||'-'}</td>
      <td><span class="badge ${bc}">${a.status}</span></td>
      <td><span class="badge ${pc}">${a.review_priority==='high'?'⚠ Tinggi':'Normal'}</span></td>
      <td>${a.final_recommendation||'-'}</td>
      <td>${a.ditmawa_pdf_path?'✅':'❌'}</td>
      <td>${new Date(a.created_at).toLocaleDateString('id-ID')}</td>
    </tr>`;
  }).join('');
  el.innerHTML = `<div class="tbl-wrap"><table><thead><tr><th>ID</th><th>Mahasiswa</th><th>Status</th><th>Prioritas</th><th>Rekomendasi AI</th><th>PDF</th><th>Tanggal</th></tr></thead><tbody>${rows}</tbody></table></div>`;
}

async function loadAdminDetail(id) {
  currentAppId = id;
  goPage('page-admin-detail');
  const el = document.getElementById('admin-detail-content');
  el.innerHTML = '<div style="text-align:center;padding:40px"><span class="spinner"></span></div>';
  const {ok,data} = await req('GET',`/admin/applications/${id}`);
  if (!ok) { el.innerHTML = '<div class="alert a-err">Gagal memuat detail.</div>'; return; }
  const d = data.data;
  const bc = {Submitted:'b-sub',Verified:'b-ver',Rejected:'b-rej'}[d.status]||'b-sub';
  const pdfLink = d.ditmawa_pdf_url ? `<a href="${d.ditmawa_pdf_url}" target="_blank" class="btn btn-ghost btn-sm">📄 Lihat PDF Ditmawa</a>` : '<span style="color:var(--txt3)">Belum ada PDF</span>';
  const cbConf  = Math.round((d.catboost_confidence||0)*100);
  const nbConf  = Math.round((d.naive_bayes_confidence||0)*100);
  const cbColor = d.catboost_label==='Layak' ? '#10b981' : '#ef4444';
  const nbColor = d.naive_bayes_label==='Layak' ? '#10b981' : '#ef4444';
  const disagree = d.disagreement_flag ? '<div class="alert a-err" style="margin-bottom:16px">⚠ Kedua model tidak sepakat — perlu review manual lebih teliti.</div>' : '';
  const decidedBtns = d.status==='Submitted' ? `
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:20px">
  <input id="admin-note-${id}" type="text" placeholder="Catatan (opsional)..." style="flex:1;min-width:200px">
  <button class="btn btn-success" onclick="adminVerify(${id})">✅ Verifikasi</button>
  <button class="btn btn-danger" onclick="adminReject(${id})">❌ Tolak</button>
  <button class="btn btn-ghost btn-sm" onclick="loadCorrectionPage(${id})">🔧 Koreksi Encoding</button>
</div>` : `<div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
  <button class="btn btn-ghost btn-sm" onclick="loadCorrectionPage(${id})">🔧 Koreksi Data Training</button>
</div>`;
  el.innerHTML = `
<div id="admin-act-alert-${id}"></div>
<div class="card" style="margin-bottom:20px">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <div><h2 style="font-size:1.1rem">Pengajuan #${d.id}</h2><span style="font-size:.8rem;color:var(--txt2)">${d.student?.name} — ${d.student?.email}</span></div>
    <div style="display:flex;gap:10px;align-items:center">${pdfLink}<span class="badge ${bc}">${d.status}</span></div>
  </div>
  <div class="detail-grid">
    <div class="di"><label>Penghasilan Gabungan (raw)</label><div class="di-val">Rp ${(d.penghasilan_gabungan_raw||0).toLocaleString('id-ID')}</div></div>
    <div class="di"><label>Penghasilan Ayah (raw)</label><div class="di-val">Rp ${(d.penghasilan_ayah_raw||0).toLocaleString('id-ID')}</div></div>
    <div class="di"><label>Penghasilan Ibu (raw)</label><div class="di-val">Rp ${(d.penghasilan_ibu_raw||0).toLocaleString('id-ID')}</div></div>
    <div class="di"><label>Jml Tanggungan</label><div class="di-val">${d.jumlah_tanggungan_raw||'-'} orang → kode ${d.jumlah_tanggungan||'-'}</div></div>
    <div class="di"><label>Anak Ke-</label><div class="di-val">${d.anak_ke_raw||'-'} → kode ${d.anak_ke||'-'}</div></div>
    <div class="di"><label>Status Orang Tua</label><div class="di-val">${d.status_orangtua_raw||'-'} → kode ${d.status_orangtua||'-'}</div></div>
    <div class="di"><label>Status Rumah</label><div class="di-val">${d.status_rumah_raw||'-'} → kode ${d.status_rumah||'-'}</div></div>
    <div class="di"><label>Daya Listrik</label><div class="di-val">${d.daya_listrik_raw||'-'} → kode ${d.daya_listrik||'-'}</div></div>
    <div class="di"><label>KIP/PKH/KKS/DTKS/SKTM</label><div class="di-val">${[d.kip,d.pkh,d.kks,d.dtks,d.sktm].map((v,i)=>(['KIP','PKH','KKS','DTKS','SKTM'][i])+':'+v).join(' | ')}</div></div>
  </div>
  ${decidedBtns}
</div>
<div class="card">
  <h3 style="font-size:.95rem;font-weight:700;margin-bottom:4px">Hasil Analisis AI</h3>
  <p style="font-size:.78rem;color:var(--txt2);margin-bottom:16px">CatBoost adalah model primer — rekomendasi akhir mengikuti CatBoost.</p>
  ${disagree}
  <div class="conf-row"><div class="conf-lbl">CatBoost (Primer)</div><div class="conf-bar"><div class="conf-fill" style="width:${cbConf}%;background:${cbColor}"></div></div><div class="conf-val" style="color:${cbColor}">${cbConf}%</div></div>
  <div style="margin-bottom:16px;font-size:.82rem;color:var(--txt2)">Label: <strong style="color:${cbColor}">${d.catboost_label||'-'}</strong></div>
  <div class="conf-row"><div class="conf-lbl">Naive Bayes</div><div class="conf-bar"><div class="conf-fill" style="width:${nbConf}%;background:${nbColor}"></div></div><div class="conf-val" style="color:${nbColor}">${nbConf}%</div></div>
  <div style="margin-bottom:20px;font-size:.82rem;color:var(--txt2)">Label: <strong style="color:${nbColor}">${d.naive_bayes_label||'-'}</strong></div>
  <div style="background:var(--s2);border-radius:var(--rsm);padding:16px;display:flex;gap:24px;flex-wrap:wrap">
    <div><div style="font-size:.75rem;color:var(--txt3)">Rekomendasi Akhir (CatBoost)</div><div style="font-size:1.1rem;font-weight:700;color:${d.final_recommendation==='Layak'?'var(--green)':'var(--red)'}">${d.final_recommendation||'-'}</div></div>
    <div><div style="font-size:.75rem;color:var(--txt3)">Rule Score</div><div style="font-size:1.1rem;font-weight:700;color:var(--blue)">${d.rule_score!=null?d.rule_score.toFixed(3):'-'}</div></div>
    <div><div style="font-size:.75rem;color:var(--txt3)">Rule Rekomendasi</div><div style="font-size:1rem;font-weight:600">${d.rule_recommendation||'-'}</div></div>
  </div>
</div>`;
}

async function adminVerify(id) {
  const note = document.getElementById('admin-note-'+id)?.value||'';
  const {ok,data} = await req('POST',`/admin/applications/${id}/verify`,{note});
  setAlert('admin-act-alert-'+id, ok ? 'Pengajuan berhasil diverifikasi.' : data.message||'Gagal', ok?'ok':'err');
  if (ok) { loadAdminApps(); loadAdminDetail(id); }
}

async function adminReject(id) {
  const note = document.getElementById('admin-note-'+id)?.value||'';
  const {ok,data} = await req('POST',`/admin/applications/${id}/reject`,{note});
  setAlert('admin-act-alert-'+id, ok ? 'Pengajuan berhasil ditolak.' : data.message||'Gagal', ok?'ok':'err');
  if (ok) { loadAdminApps(); loadAdminDetail(id); }
}

async function loadCorrectionPage(id) {
  currentAppId = id;
  goPage('page-admin-correction');
  const el = document.getElementById('corr-form');
  el.innerHTML = '<div style="text-align:center;padding:24px"><span class="spinner"></span></div>';
  const {ok,data} = await req('GET',`/admin/applications/${id}/training-data`);
  if (!ok) { el.innerHTML = `<div class="alert a-err">${data.message||'Data training belum tersedia. Verifikasi/tolak pengajuan terlebih dahulu.'}</div>`; return; }
  const td = data.data.training_data;
  const fields = [
    {key:'kip',label:'KIP',opts:[{v:0,l:'0 — Tidak'},{v:1,l:'1 — Ya'}]},
    {key:'pkh',label:'PKH',opts:[{v:0,l:'0 — Tidak'},{v:1,l:'1 — Ya'}]},
    {key:'kks',label:'KKS',opts:[{v:0,l:'0 — Tidak'},{v:1,l:'1 — Ya'}]},
    {key:'dtks',label:'DTKS',opts:[{v:0,l:'0 — Tidak'},{v:1,l:'1 — Ya'}]},
    {key:'sktm',label:'SKTM',opts:[{v:0,l:'0 — Tidak'},{v:1,l:'1 — Ya'}]},
    {key:'penghasilan_gabungan',label:'Penghasilan Gabungan',opts:[{v:1,l:'1 — <1jt'},{v:2,l:'2 — 1-4jt'},{v:3,l:'3 — ≥4jt'}]},
    {key:'penghasilan_ayah',label:'Penghasilan Ayah',opts:[{v:1,l:'1 — <1jt'},{v:2,l:'2 — 1-4jt'},{v:3,l:'3 — ≥4jt'}]},
    {key:'penghasilan_ibu',label:'Penghasilan Ibu',opts:[{v:1,l:'1 — <1jt'},{v:2,l:'2 — 1-4jt'},{v:3,l:'3 — ≥4jt'}]},
    {key:'jumlah_tanggungan',label:'Jumlah Tanggungan',opts:[{v:1,l:'1 — ≥6'},{v:2,l:'2 — 4-5'},{v:3,l:'3 — 0-3'}]},
    {key:'anak_ke',label:'Anak Ke-',opts:[{v:1,l:'1 — ≥5'},{v:2,l:'2 — 3-4'},{v:3,l:'3 — 1-2'}]},
    {key:'status_orangtua',label:'Status Orang Tua',opts:[{v:1,l:'1 — Yatim Piatu'},{v:2,l:'2 — Yatim/Piatu'},{v:3,l:'3 — Lengkap'}]},
    {key:'status_rumah',label:'Status Rumah',opts:[{v:1,l:'1 — Tidak Punya'},{v:2,l:'2 — Sewa/Numpang'},{v:3,l:'3 — Milik Sendiri'}]},
    {key:'daya_listrik',label:'Daya Listrik',opts:[{v:1,l:'1 — Non-PLN'},{v:2,l:'2 — 450-900VA'},{v:3,l:'3 — >900VA'}]},
    {key:'label',label:'Label Keputusan',opts:[{v:'Layak',l:'Layak — Lolos KIP-K'},{v:'Indikasi',l:'Indikasi — Tidak Lolos'}]},
  ];
  let html = '<div class="detail-grid">';
  fields.forEach(f => {
    const cur = td[f.key];
    const opts = f.opts.map(o => `<option value="${o.v}"${String(cur)===String(o.v)?'selected':''}>${o.l}</option>`).join('');
    html += `<div class="di"><label>${f.label} (saat ini: ${cur})</label><select id="c-${f.key}">${opts}</select></div>`;
  });
  html += `</div><div class="form-group" style="margin-top:16px"><label>Catatan Koreksi</label><textarea id="c-note" rows="2" placeholder="Alasan perubahan nilai...">${td.correction_note||''}</textarea></div>`;
  html += `<button class="btn btn-primary" onclick="saveCorrection(${id})" style="margin-top:12px">💾 Simpan Koreksi</button>`;
  el.innerHTML = html;
}

async function saveCorrection(id) {
  const keys = ['kip','pkh','kks','dtks','sktm','penghasilan_gabungan','penghasilan_ayah','penghasilan_ibu','jumlah_tanggungan','anak_ke','status_orangtua','status_rumah','daya_listrik','label'];
  const payload = {};
  keys.forEach(k => {
    const el = document.getElementById('c-'+k);
    if (el) payload[k] = isNaN(el.value) ? el.value : parseInt(el.value);
  });
  payload.correction_note = document.getElementById('c-note')?.value||'';
  const {ok,data} = await req('PUT',`/admin/applications/${id}/training-data`, payload);
  setAlert('corr-alert', ok ? data.message||'Koreksi disimpan.' : data.message||'Gagal', ok?'ok':'err');
}

async function doRetrain() {
  setLoading('retrain-btn','retrain-btn-txt',true,'Mulai Retrain');
  const {ok,data} = await req('POST','/admin/models/retrain');
  setLoading('retrain-btn','retrain-btn-txt',false,'🚀 Mulai Retrain');
  if (!ok) { setAlert('retrain-alert', data.message||data.detail||'Retrain gagal'); return; }
  setAlert('retrain-alert', 'Retrain berhasil!', 'ok');
  const ts = data.training_summary || {};
  document.getElementById('retrain-result').innerHTML = `
<div class="card" style="margin-top:0;background:var(--s2)">
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px">
    <div class="di"><label>Data Digunakan</label><div class="di-val">${ts.rows_used||'-'} baris</div></div>
    <div class="di"><label>Akurasi CatBoost</label><div class="di-val" style="color:var(--blue)">${ts.catboost_accuracy!=null?(ts.catboost_accuracy*100).toFixed(1)+'%':'-'}</div></div>
    <div class="di"><label>Akurasi Naive Bayes</label><div class="di-val" style="color:var(--purple)">${ts.naive_bayes_accuracy!=null?(ts.naive_bayes_accuracy*100).toFixed(1)+'%':'-'}</div></div>
    <div class="di"><label>Model Primer</label><div class="di-val">CatBoost ⭐</div></div>
  </div>
</div>`;
}

// ── Init ──
window.addEventListener('load', () => {
  authToken = localStorage.getItem('spk_token') || '';
  authUser  = JSON.parse(localStorage.getItem('spk_user') || 'null');
  if (authToken && authUser) routeByRole();
  else goPage('page-login');
});
</script>
</body>
</html>
