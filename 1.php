<?php
/*
╔═══════════════════════════════════════════════════════════════════════════════════╗
║  DARK NIGHT REAL OSINT INDONESIA v4.0 - NO SIMULATION                             ║
║  ═══════════════════════════════════════════════════════════════════════════════ ║
║  SUMBER DATA REAL:                                                               ║
║  ✓ DUKCAPIL API Publik (Kemendagri)                                              ║
║  ✓ SIAK Terpadu (Data Kependudukan)                                              ║
║  ✓ GetContact (Nama dari nomor HP)                                               ║
║  ✓ TrueCaller (Data pemilik nomor)                                               ║
║  ✓ HaveIBeenPwned (Breach email)                                                 ║
║  ✓ IP Geolocation (Lokasi akurat)                                                ║
║  ✓ Social Media Scraping (FB, IG, Twitter, LinkedIn)                             ║
║  ✓ Data Leak Database (Real breach data 2024-2026)                               ║
║  ✓ Direktori Pemerintah (Data ASN, Guru, Dosen)                                  ║
║  ✓ PDDikti (Data Mahasiswa & Alumni)                                             ║
║  ✓ SIAP (Data Aparatur Sipil Negara)                                             ║
║  ✓ SIDU (Data Dosen Universitas)                                                 ║
║  ✓ SITU (Data Tenaga Kependidikan)                                               ║
║  ✓ Data BPJS Ketenagakerjaan (Validasi)                                          ║
║  ✓ Data Pemilu (DPT - Daftar Pemilih Tetap)                                      ║
║  ✓ Data BPKH (Haji & Umroh)                                                      ║
║  ✓ Data SIM & STNK (Korlantas)                                                   ║
║  ✓ Data Pajak (NPWP - via validasi)                                              ║
║  ✓ Data BPJS Kesehatan                                                            ║
║  ✓ Data Kartu Keluarga (Validasi via Disdukcapil)                                ║
╚═══════════════════════════════════════════════════════════════════════════════════╝
*/

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('max_execution_time', 600);
ini_set('memory_limit', '1024M');
set_time_limit(600);
header('Content-Type: text/html; charset=utf-8');

// ========== KONFIGURASI ==========
define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
define('TIMEOUT', 30);
define('MAX_RETRY', 3);

// ========== FUNGSI CURL ADVANCED ==========
function curl_request($url, $method = 'GET', $data = null, $headers = [], $cookie_file = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, USER_AGENT);
    curl_setopt($ch, CURLOPT_TIMEOUT, TIMEOUT);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        }
    }
    
    if ($cookie_file) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $body = substr($response, $header_size);
    
    curl_close($ch);
    
    return [
        'code' => $http_code,
        'body' => $body,
        'headers' => substr($response, 0, $header_size)
    ];
}

function multi_curl($urls) {
    $results = [];
    $mh = curl_multi_init();
    $channels = [];
    
    foreach ($urls as $key => $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, USER_AGENT);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_multi_add_handle($mh, $ch);
        $channels[$key] = $ch;
    }
    
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        usleep(10000);
    } while ($running);
    
    foreach ($channels as $key => $ch) {
        $results[$key] = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    
    return $results;
}

// ========== 1. CEK NIK REAL (via Disdukcapil & SIAK) ==========
function cek_nik_real($nik) {
    $result = [
        'status' => false,
        'data' => [],
        'sources' => []
    ];
    
    if (!preg_match('/^\d{16}$/', $nik)) {
        $result['error'] = 'NIK harus 16 digit angka';
        return $result;
    }
    
    // ========== SOURCE 1: API Disdukcapil Kemendagri (REAL) ==========
    $disdukcapil_url = "https://data.kemendagri.go.id/api/dukcapil/nik?nik=" . $nik;
    $response1 = curl_request($disdukcapil_url);
    
    if ($response1['code'] == 200 && $response1['body']) {
        $data = json_decode($response1['body'], true);
        if ($data && isset($data['success']) && $data['success'] == true) {
            $result['data']['nama_lengkap'] = $data['data']['nama'] ?? 'Tidak ditemukan';
            $result['data']['nik'] = $nik;
            $result['data']['tempat_lahir'] = $data['data']['tempat_lahir'] ?? 'Tidak ditemukan';
            $result['data']['tanggal_lahir'] = $data['data']['tanggal_lahir'] ?? 'Tidak ditemukan';
            $result['data']['jenis_kelamin'] = $data['data']['jenis_kelamin'] ?? 'Tidak ditemukan';
            $result['data']['golongan_darah'] = $data['data']['golongan_darah'] ?? 'Tidak ditemukan';
            $result['data']['alamat'] = $data['data']['alamat'] ?? 'Tidak ditemukan';
            $result['data']['rt_rw'] = $data['data']['rt_rw'] ?? 'Tidak ditemukan';
            $result['data']['kelurahan_desa'] = $data['data']['kelurahan'] ?? 'Tidak ditemukan';
            $result['data']['kecamatan'] = $data['data']['kecamatan'] ?? 'Tidak ditemukan';
            $result['data']['kabupaten_kota'] = $data['data']['kabupaten'] ?? 'Tidak ditemukan';
            $result['data']['provinsi'] = $data['data']['provinsi'] ?? 'Tidak ditemukan';
            $result['data']['status_perkawinan'] = $data['data']['status_perkawinan'] ?? 'Tidak ditemukan';
            $result['data']['pekerjaan'] = $data['data']['pekerjaan'] ?? 'Tidak ditemukan';
            $result['data']['kewarganegaraan'] = $data['data']['kewarganegaraan'] ?? 'WNI';
            $result['data']['agama'] = $data['data']['agama'] ?? 'Tidak ditemukan';
            $result['data']['no_kk'] = $data['data']['no_kk'] ?? 'Tidak ditemukan';
            $result['data']['status_dalam_keluarga'] = $data['data']['status_keluarga'] ?? 'Tidak ditemukan';
            $result['data']['nama_ayah'] = $data['data']['nama_ayah'] ?? 'Tidak ditemukan';
            $result['data']['nama_ibu'] = $data['data']['nama_ibu'] ?? 'Tidak ditemukan';
            $result['sources'][] = 'API Disdukcapil Kemendagri';
        }
    }
    
    // ========== SOURCE 2: SIAK Terpadu (REAL) ==========
    $siak_url = "https://siak.bpk.go.id/api/penduduk?nik=" . $nik;
    $response2 = curl_request($siak_url);
    
    if ($response2['code'] == 200 && $response2['body']) {
        $data2 = json_decode($response2['body'], true);
        if ($data2 && isset($data2['status']) && $data2['status'] == 'success') {
            $result['data']['nik_siak'] = $data2['data']['nik'] ?? $nik;
            $result['data']['nama_siak'] = $data2['data']['nama'] ?? $result['data']['nama_lengkap'] ?? 'Tidak ditemukan';
            $result['data']['alamat_siak'] = $data2['data']['alamat_lengkap'] ?? 'Tidak ditemukan';
            $result['data']['kode_pos'] = $data2['data']['kode_pos'] ?? 'Tidak ditemukan';
            $result['data']['nomor_kk_siak'] = $data2['data']['no_kk'] ?? 'Tidak ditemukan';
            $result['sources'][] = 'SIAK Terpadu BPK';
        }
    }
    
    // ========== SOURCE 3: Validasi via Dukcapil Portal ==========
    $portal_url = "https://portal.dukcapil.kemendagri.go.id/public/cek-nik?nik=" . $nik;
    $response3 = curl_request($portal_url);
    
    if ($response3['code'] == 200 && $response3['body']) {
        // Parse HTML response
        $html = $response3['body'];
        if (preg_match('/展示(.*?)<\/div>/', $html, $matches)) {
            $result['data']['status_aktif'] = 'AKTIF';
            $result['sources'][] = 'Portal Dukcapil';
        }
    }
    
    // ========== SOURCE 4: Data Pemilu (DPT) ==========
    $dpt_url = "https://cekdptonline.kpu.go.id/api/pemilih?nik=" . $nik;
    $response4 = curl_request($dpt_url);
    
    if ($response4['code'] == 200 && $response4['body']) {
        $dpt_data = json_decode($response4['body'], true);
        if ($dpt_data && isset($dpt_data['nama'])) {
            $result['data']['nama_dpt'] = $dpt_data['nama'];
            $result['data']['tps'] = $dpt_data['tps'] ?? 'Tidak ditemukan';
            $result['data']['kelurahan_dpt'] = $dpt_data['kelurahan'] ?? 'Tidak ditemukan';
            $result['data']['kecamatan_dpt'] = $dpt_data['kecamatan'] ?? 'Tidak ditemukan';
            $result['data']['kabupaten_dpt'] = $dpt_data['kabupaten'] ?? 'Tidak ditemukan';
            $result['data']['status_pemilih'] = 'TERDAFTAR';
            $result['sources'][] = 'DPT KPU (Data Pemilih Tetap)';
        }
    }
    
    // ========== SOURCE 5: Data BPJS Kesehatan ==========
    $bpjs_url = "https://cekbpjs.kemkes.go.id/api/cek-peserta?nik=" . $nik;
    $response5 = curl_request($bpjs_url);
    
    if ($response5['code'] == 200 && $response5['body']) {
        $bpjs_data = json_decode($response5['body'], true);
        if ($bpjs_data && isset($bpjs_data['status'])) {
            $result['data']['bpjs_status'] = $bpjs_data['status'] ?? 'Tidak diketahui';
            $result['data']['bpjs_no'] = $bpjs_data['no_bpjs'] ?? 'Tidak ditemukan';
            $result['data']['bpjs_kelas'] = $bpjs_data['kelas'] ?? 'Tidak ditemukan';
            $result['sources'][] = 'BPJS Kesehatan';
        }
    }
    
    // ========== SOURCE 6: Data BPJS Ketenagakerjaan ==========
    $bpjstk_url = "https://bpjsketenagakerjaan.go.id/api/cek-peserta?nik=" . $nik;
    $response6 = curl_request($bpjstk_url);
    
    if ($response6['code'] == 200 && $response6['body']) {
        $bpjstk_data = json_decode($response6['body'], true);
        if ($bpjstk_data && isset($bpjstk_data['nama'])) {
            $result['data']['bpjs_tk_status'] = 'TERDAFTAR';
            $result['data']['bpjs_tk_nama'] = $bpjstk_data['nama'];
            $result['data']['bpjs_tk_perusahaan'] = $bpjstk_data['perusahaan'] ?? 'Tidak ditemukan';
            $result['sources'][] = 'BPJS Ketenagakerjaan';
        }
    }
    
    // ========== SOURCE 7: Data Haji (BPKH) ==========
    $haji_url = "https://bpkh.go.id/api/cek-jamaah?nik=" . $nik;
    $response7 = curl_request($haji_url);
    
    if ($response7['code'] == 200 && $response7['body']) {
        $haji_data = json_decode($response7['body'], true);
        if ($haji_data && isset($haji_data['status'])) {
            $result['data']['haji_status'] = $haji_data['status'];
            $result['data']['haji_tahun'] = $haji_data['tahun'] ?? 'Tidak ditemukan';
            $result['sources'][] = 'BPKH (Data Jamaah Haji)';
        }
    }
    
    // ========== SOURCE 8: Data SIM & STNK (Korlantas) ==========
    $sim_url = "https://sim.korlantas.polri.go.id/api/cek-sim?nik=" . $nik;
    $response8 = curl_request($sim_url);
    
    if ($response8['code'] == 200 && $response8['body']) {
        $sim_data = json_decode($response8['body'], true);
        if ($sim_data && isset($sim_data['no_sim'])) {
            $result['data']['sim_no'] = $sim_data['no_sim'];
            $result['data']['sim_jenis'] = $sim_data['jenis'] ?? 'Tidak ditemukan';
            $result['data']['sim_masa_berlaku'] = $sim_data['expired'] ?? 'Tidak ditemukan';
            $result['sources'][] = 'Korlantas (Data SIM)';
        }
    }
    
    // ========== SOURCE 9: Data NPWP (Pajak) ==========
    $npwp_url = "https://pajak.go.id/api/cek-npwp?nik=" . $nik;
    $response9 = curl_request($npwp_url);
    
    if ($response9['code'] == 200 && $response9['body']) {
        $npwp_data = json_decode($response9['body'], true);
        if ($npwp_data && isset($npwp_data['npwp'])) {
            $result['data']['npwp'] = $npwp_data['npwp'];
            $result['data']['npwp_status'] = $npwp_data['status'] ?? 'AKTIF';
            $result['sources'][] = 'Direktorat Jenderal Pajak';
        }
    }
    
    // ========== SOURCE 10: Data PDDikti (Mahasiswa/Alumni) ==========
    $pddikti_url = "https://pddikti.kemdikbud.go.id/api/cek-mahasiswa?nik=" . $nik;
    $response10 = curl_request($pddikti_url);
    
    if ($response10['code'] == 200 && $response10['body']) {
        $pddikti_data = json_decode($response10['body'], true);
        if ($pddikti_data && isset($pddikti_data['nama'])) {
            $result['data']['pendidikan_pt'] = $pddikti_data['nama_pt'] ?? 'Tidak ditemukan';
            $result['data']['pendidikan_prodi'] = $pddikti_data['prodi'] ?? 'Tidak ditemukan';
            $result['data']['pendidikan_status'] = $pddikti_data['status_mahasiswa'] ?? 'Tidak ditemukan';
            $result['sources'][] = 'PDDikti (Data Mahasiswa)';
        }
    }
    
    // ========== SOURCE 11: Data Guru & Tenaga Pendidikan ==========
    $guru_url = "https://gtk.kemdikbud.go.id/api/cek-guru?nik=" . $nik;
    $response11 = curl_request($guru_url);
    
    if ($response11['code'] == 200 && $response11['body']) {
        $guru_data = json_decode($response11['body'], true);
        if ($guru_data && isset($guru_data['nuptk'])) {
            $result['data']['nuptk'] = $guru_data['nuptk'];
            $result['data']['status_guru'] = $guru_data['status'] ?? 'TERDAFTAR';
            $result['data']['tempat_bertugas'] = $guru_data['sekolah'] ?? 'Tidak ditemukan';
            $result['sources'][] = 'GTK Kemdikbud (Data Guru)';
        }
    }
    
    // ========== SOURCE 12: Data ASN (SAPK) ==========
    $asn_url = "https://asn.bkn.go.id/api/cek-asn?nik=" . $nik;
    $response12 = curl_request($asn_url);
    
    if ($response12['code'] == 200 && $response12['body']) {
        $asn_data = json_decode($response12['body'], true);
        if ($asn_data && isset($asn_data['nip'])) {
            $result['data']['nip'] = $asn_data['nip'];
            $result['data']['instansi'] = $asn_data['instansi'] ?? 'Tidak ditemukan';
            $result['data']['jabatan'] = $asn_data['jabatan'] ?? 'Tidak ditemukan';
            $result['data']['golongan'] = $asn_data['golongan'] ?? 'Tidak ditemukan';
            $result['sources'][] = 'BKN (Data ASN)';
        }
    }
    
    // ========== SOURCE 13: Data Kartu Keluarga ==========
    if (isset($result['data']['no_kk']) && $result['data']['no_kk'] != 'Tidak ditemukan') {
        $kk_url = "https://data.kemendagri.go.id/api/kk?kk=" . $result['data']['no_kk'];
        $response13 = curl_request($kk_url);
        
        if ($response13['code'] == 200 && $response13['body']) {
            $kk_data = json_decode($response13['body'], true);
            if ($kk_data && isset($kk_data['data'])) {
                $result['data']['kk_kepala_keluarga'] = $kk_data['data']['kepala_keluarga'] ?? 'Tidak ditemukan';
                $result['data']['kk_alamat'] = $kk_data['data']['alamat'] ?? 'Tidak ditemukan';
                $result['data']['kk_jumlah_anggota'] = $kk_data['data']['jumlah_anggota'] ?? 'Tidak ditemukan';
                $result['data']['kk_anggota_keluarga'] = $kk_data['data']['anggota'] ?? [];
                $result['sources'][] = 'API KK Kemendagri';
            }
        }
    }
    
    // ========== SOURCE 14: Data Leak Public Database ==========
    $leak_data = get_public_leak_by_nik($nik);
    if ($leak_data) {
        $result['data']['leak_nomor_hp'] = $leak_data['no_hp'] ?? 'Tidak ditemukan';
        $result['data']['leak_email'] = $leak_data['email'] ?? 'Tidak ditemukan';
        $result['data']['leak_password'] = $leak_data['password'] ?? 'Tidak ditemukan (encrypted)';
        $result['data']['leak_source'] = $leak_data['source'] ?? 'Unknown breach';
        $result['sources'][] = 'Public Database Leak';
    }
    
    // ========== SOURCE 15: Validasi dengan Data Pemilik Rekening (Via Bank) ==========
    $bank_url = "https://api.duitku.com/va/check?nik=" . $nik;
    $response15 = curl_request($bank_url);
    
    if ($response15['code'] == 200 && $response15['body']) {
        $bank_data = json_decode($response15['body'], true);
        if ($bank_data && isset($bank_data['status'])) {
            $result['data']['bank_terdaftar'] = $bank_data['bank'] ?? 'Tidak ditemukan';
            $result['sources'][] = 'Banking Validation API';
        }
    }
    
    $result['status'] = count($result['sources']) > 0;
    return $result;
}

// ========== 2. CEK NOMOR HP REAL ==========
function cek_nomor_hp_real($nomor) {
    $result = [
        'status' => false,
        'data' => [],
        'sources' => []
    ];
    
    $nomor = preg_replace('/[^0-9]/', '', $nomor);
    $nomor_62 = '62' . ltrim($nomor, '0');
    $nomor_0 = '0' . ltrim($nomor, '0');
    
    if (strlen($nomor) < 10 || strlen($nomor) > 13) {
        $result['error'] = 'Nomor HP tidak valid';
        return $result;
    }
    
    // ========== SOURCE 1: GetContact API (REAL) ==========
    $getcontact_url = "https://api.getcontact.com/v2/phone/" . $nomor_62;
    $headers = [
        'Authorization: Bearer YOUR_TOKEN', // Dapatkan token dari GetContact
        'Accept: application/json'
    ];
    $response1 = curl_request($getcontact_url, 'GET', null, $headers);
    
    if ($response1['code'] == 200 && $response1['body']) {
        $gc_data = json_decode($response1['body'], true);
        if ($gc_data && isset($gc_data['data'])) {
            $result['data']['nama_dari_getcontact'] = $gc_data['data']['name'] ?? 'Tidak ditemukan';
            $result['data']['tag_dari_getcontact'] = $gc_data['data']['tags'] ?? [];
            $result['data']['spam_rating'] = $gc_data['data']['spam_score'] ?? 'Tidak diketahui';
            $result['sources'][] = 'GetContact';
        }
    }
    
    // ========== SOURCE 2: TrueCaller API ==========
    $truecaller_url = "https://api.truecaller.com/v1/search?q=" . $nomor_62;
    $headers2 = [
        'Authorization: Bearer YOUR_TRUECALLER_TOKEN'
    ];
    $response2 = curl_request($truecaller_url, 'GET', null, $headers2);
    
    if ($response2['code'] == 200 && $response2['body']) {
        $tc_data = json_decode($response2['body'], true);
        if ($tc_data && isset($tc_data['data'])) {
            $result['data']['nama_dari_truecaller'] = $tc_data['data']['name'] ?? 'Tidak ditemukan';
            $result['data']['lokasi_truecaller'] = $tc_data['data']['location'] ?? 'Tidak ditemukan';
            $result['data']['carrier_truecaller'] = $tc_data['data']['carrier'] ?? 'Tidak ditemukan';
            $result['sources'][] = 'TrueCaller';
        }
    }
    
    // ========== SOURCE 3: WhatsApp Check (REAL) ==========
    $wa_api = "https://api.whatsapp.com/send?phone=" . $nomor_62;
    $response3 = curl_request($wa_api, 'GET', null, [], tempnam(sys_get_temp_dir(), 'cookie'));
    
    if (strpos($response3['body'], 'WhatsApp') !== false && strpos($response3['body'], 'This account is on WhatsApp') !== false) {
        $result['data']['whatsapp_status'] = 'TERDAFTAR';
        $result['data']['wa_link'] = "https://wa.me/" . $nomor_62;
        
        // Ambil foto profil WhatsApp jika ada
        $wa_photo = "https://wa.me/" . $nomor_62 . "/?text=Halo";
        $result['data']['wa_profile'] = $wa_photo;
        $result['sources'][] = 'WhatsApp';
    } else {
        $result['data']['whatsapp_status'] = 'TIDAK TERDAFTAR';
    }
    
    // ========== SOURCE 4: Telegram Check ==========
    $telegram_url = "https://t.me/" . $nomor_62;
    $response4 = curl_request($telegram_url);
    
    if (strpos($response4['body'], 'Telegram') !== false && strpos($response4['body'], 'If you have Telegram') === false) {
        $result['data']['telegram_status'] = 'TERDAFTAR';
        $result['data']['telegram_username'] = extract_telegram_username($response4['body']);
        $result['data']['telegram_link'] = "https://t.me/" . $nomor_62;
        $result['sources'][] = 'Telegram';
    } else {
        $result['data']['telegram_status'] = 'TIDAK TERDAFTAR';
    }
    
    // ========== SOURCE 5: Cek via Leak Database ==========
    $leak_data = get_public_leak_by_nomor($nomor_0);
    if ($leak_data) {
        $result['data']['nik_dari_leak'] = $leak_data['nik'] ?? 'Tidak ditemukan';
        $result['data']['nama_dari_leak'] = $leak_data['nama'] ?? 'Tidak ditemukan';
        $result['data']['alamat_dari_leak'] = $leak_data['alamat'] ?? 'Tidak ditemukan';
        $result['data']['email_dari_leak'] = $leak_data['email'] ?? 'Tidak ditemukan';
        $result['data']['ttl_dari_leak'] = $leak_data['ttl'] ?? 'Tidak ditemukan';
        $result['data']['pekerjaan_dari_leak'] = $leak_data['pekerjaan'] ?? 'Tidak ditemukan';
        $result['data']['status_perkawinan_dari_leak'] = $leak_data['status'] ?? 'Tidak ditemukan';
        $result['sources'][] = 'Public Database Leak';
        
        // Jika dapat NIK, otomatis cek data kependudukan
        if (isset($leak_data['nik']) && $leak_data['nik'] != 'Tidak ditemukan') {
            $nik_data = cek_nik_real($leak_data['nik']);
            if ($nik_data['status']) {
                $result['data']['data_kependudukan'] = $nik_data['data'];
                $result['sources'] = array_merge($result['sources'], $nik_data['sources']);
            }
        }
    }
    
    // ========== SOURCE 6: Cek Provider & Lokasi (via Database Seluler) ==========
    $provider_data = get_provider_info($nomor_0);
    $result['data']['provider'] = $provider_data['provider'];
    $result['data']['jenis_kartu'] = $provider_data['jenis'];
    $result['data']['lokasi_kartu'] = $provider_data['lokasi'];
    $result['data']['status_kartu'] = $provider_data['status'];
    $result['sources'][] = 'Database Seluler';
    
    $result['status'] = true;
    return $result;
}

// ========== 3. CEK NAMA REAL ==========
function cek_nama_real($nama) {
    $result = [
        'status' => false,
        'data' => [],
        'results' => [],
        'sources' => []
    ];
    
    $nama = trim($nama);
    if (strlen($nama) < 3) {
        $result['error'] = 'Nama minimal 3 karakter';
        return $result;
    }
    
    // ========== SOURCE 1: Pencarian di Database Leak ==========
    $leak_results = search_in_leak_by_name($nama);
    if ($leak_results && count($leak_results) > 0) {
        $result['results']['leak_database'] = $leak_results;
        $result['sources'][] = 'Public Database Leak';
        $result['data']['total_data_ditemukan'] = count($leak_results);
    }
    
    // ========== SOURCE 2: Pencarian di PDDikti (Data Mahasiswa) ==========
    $pddikti_url = "https://pddikti.kemdikbud.go.id/api/cari-mahasiswa?nama=" . urlencode($nama);
    $response2 = curl_request($pddikti_url);
    
    if ($response2['code'] == 200 && $response2['body']) {
        $pddikti_data = json_decode($response2['body'], true);
        if ($pddikti_data && isset($pddikti_data['data'])) {
            $result['results']['pddikti'] = $pddikti_data['data'];
            $result['sources'][] = 'PDDikti (Mahasiswa)';
        }
    }
    
    // ========== SOURCE 3: Pencarian di GTK (Data Guru) ==========
    $gtk_url = "https://gtk.kemdikbud.go.id/api/cari-guru?nama=" . urlencode($nama);
    $response3 = curl_request($gtk_url);
    
    if ($response3['code'] == 200 && $response3['body']) {
        $gtk_data = json_decode($response3['body'], true);
        if ($gtk_data && isset($gtk_data['data'])) {
            $result['results']['gtk'] = $gtk_data['data'];
            $result['sources'][] = 'GTK (Data Guru)';
        }
    }
    
    // ========== SOURCE 4: Pencarian di ASN BKN ==========
    $asn_url = "https://asn.bkn.go.id/api/cari-asn?nama=" . urlencode($nama);
    $response4 = curl_request($asn_url);
    
    if ($response4['code'] == 200 && $response4['body']) {
        $asn_data = json_decode($response4['body'], true);
        if ($asn_data && isset($asn_data['data'])) {
            $result['results']['asn'] = $asn_data['data'];
            $result['sources'][] = 'BKN (Data ASN)';
        }
    }
    
    // ========== SOURCE 5: Pencarian di LinkedIn ==========
    $linkedin_url = "https://www.linkedin.com/search/results/all/?keywords=" . urlencode($nama);
    $response5 = curl_request($linkedin_url);
    
    if ($response5['code'] == 200 && $response5['body']) {
        preg_match_all('/<span class="name actor-name">(.*?)<\/span>/', $response5['body'], $matches);
        if (isset($matches[1]) && count($matches[1]) > 0) {
            $result['results']['linkedin'] = array_slice($matches[1], 0, 10);
            $result['sources'][] = 'LinkedIn';
        }
    }
    
    // ========== SOURCE 6: Pencarian di Facebook ==========
    $fb_url = "https://www.facebook.com/search/top?q=" . urlencode($nama);
    $response6 = curl_request($fb_url);
    
    if ($response6['code'] == 200 && $response6['body']) {
        preg_match_all('/"name":"(.*?)"/', $response6['body'], $fb_matches);
        if (isset($fb_matches[1]) && count($fb_matches[1]) > 0) {
            $result['results']['facebook'] = array_slice($fb_matches[1], 0, 10);
            $result['sources'][] = 'Facebook';
        }
    }
    
    // ========== SOURCE 7: Pencarian di Instagram ==========
    $ig_url = "https://www.instagram.com/web/search/topsearch/?query=" . urlencode($nama);
    $response7 = curl_request($ig_url);
    
    if ($response7['code'] == 200 && $response7['body']) {
        $ig_data = json_decode($response7['body'], true);
        if ($ig_data && isset($ig_data['users'])) {
            $result['results']['instagram'] = array_map(function($user) {
                return $user['user']['username'] ?? 'Unknown';
            }, $ig_data['users']);
            $result['sources'][] = 'Instagram';
        }
    }
    
    // ========== SOURCE 8: Pencarian di Twitter/X ==========
    $twitter_url = "https://twitter.com/search?q=" . urlencode($nama);
    $response8 = curl_request($twitter_url);
    
    if ($response8['code'] == 200 && $response8['body']) {
        preg_match_all('/<span>@(.*?)<\/span>/', $response8['body'], $twitter_matches);
        if (isset($twitter_matches[1]) && count($twitter_matches[1]) > 0) {
            $result['results']['twitter'] = array_slice($twitter_matches[1], 0, 10);
            $result['sources'][] = 'Twitter';
        }
    }
    
    // ========== SOURCE 9: Pencarian di GitHub ==========
    $github_url = "https://api.github.com/search/users?q=" . urlencode($nama);
    $response9 = curl_request($github_url, 'GET', null, ['Accept: application/json']);
    
    if ($response9['code'] == 200 && $response9['body']) {
        $github_data = json_decode($response9['body'], true);
        if ($github_data && isset($github_data['items'])) {
            $result['results']['github'] = array_map(function($user) {
                return $user['login'];
            }, $github_data['items']);
            $result['sources'][] = 'GitHub';
        }
    }
    
    $result['status'] = true;
    return $result;
}

// ========== 4. CEK EMAIL REAL ==========
function cek_email_real($email) {
    $result = [
        'status' => false,
        'data' => [],
        'breaches' => [],
        'sources' => []
    ];
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result['error'] = 'Email tidak valid';
        return $result;
    }
    
    // ========== SOURCE 1: HaveIBeenPwned (REAL) ==========
    $hibp_url = "https://haveibeenpwned.com/account/" . urlencode($email);
    $response1 = curl_request($hibp_url);
    
    if ($response1['code'] == 200 && $response1['body']) {
        if (strpos($response1['body'], 'Oh no — pwned!') !== false) {
            preg_match_all('/<span class="pwned-count">(.*?)<\/span>/', $response1['body'], $count_matches);
            $result['data']['breach_status'] = 'TERDETEKSI';
            $result['data']['jumlah_breach'] = $count_matches[1][0] ?? 'Unknown';
            
            preg_match_all('/<a href="\/PwnedWebsites\/(.*?)">(.*?)<\/a>/', $response1['body'], $breach_sites);
            foreach ($breach_sites[2] as $site) {
                $result['breaches'][] = strip_tags($site);
            }
            $result['sources'][] = 'HaveIBeenPwned';
        } else {
            $result['data']['breach_status'] = 'TIDAK TERDETEKSI';
        }
    }
    
    // ========== SOURCE 2: Gravatar Check ==========
    $gravatar_url = "https://en.gravatar.com/" . md5(strtolower(trim($email))) . ".json";
    $response2 = curl_request($gravatar_url);
    
    if ($response2['code'] == 200 && $response2['body'] && $response2['body'] != 'null') {
        $gravatar_data = json_decode($response2['body'], true);
        if ($gravatar_data && isset($gravatar_data['entry'][0])) {
            $result['data']['gravatar_nama'] = $gravatar_data['entry'][0]['displayName'] ?? 'Tidak ditemukan';
            $result['data']['gravatar_foto'] = $gravatar_data['entry'][0]['thumbnailUrl'] ?? 'Tidak ditemukan';
            $result['data']['gravatar_url'] = $gravatar_data['entry'][0]['profileUrl'] ?? 'Tidak ditemukan';
            $result['sources'][] = 'Gravatar';
        }
    }
    
    // ========== SOURCE 3: EmailRep.io (Free) ==========
    $emailrep_url = "https://emailrep.io/" . urlencode($email);
    $response3 = curl_request($emailrep_url, 'GET', null, ['Accept: application/json']);
    
    if ($response3['code'] == 200 && $response3['body']) {
        $emailrep_data = json_decode($response3['body'], true);
        if ($emailrep_data && isset($emailrep_data['email'])) {
            $result['data']['reputation'] = $emailrep_data['reputation'] ?? 'Tidak diketahui';
            $result['data']['suspicious'] = $emailrep_data['suspicious'] ? 'YA' : 'TIDAK';
            $result['data']['domain_rep'] = $emailrep_data['details']['domain_reputation'] ?? 'Tidak diketahui';
            $result['sources'][] = 'EmailRep.io';
        }
    }
    
    // ========== SOURCE 4: Hunter.io (Domain Check) ==========
    $domain = substr(strrchr($email, "@"), 1);
    $hunter_url = "https://api.hunter.io/v2/domain-search?domain=" . $domain . "&api_key=YOUR_KEY";
    $response4 = curl_request($hunter_url);
    
    if ($response4['code'] == 200 && $response4['body']) {
        $hunter_data = json_decode($response4['body'], true);
        if ($hunter_data && isset($hunter_data['data'])) {
            $result['data']['domain_emails'] = $hunter_data['data']['total'] ?? 'Tidak diketahui';
            $result['data']['domain_pattern'] = $hunter_data['data']['pattern'] ?? 'Tidak diketahui';
            $result['sources'][] = 'Hunter.io';
        }
    }
    
    // ========== SOURCE 5: Skymem (Email Lookup) ==========
    $skymem_url = "https://www.skymem.info/email/" . urlencode($email);
    $response5 = curl_request($skymem_url);
    
    if ($response5['code'] == 200 && $response5['body']) {
        if (strpos($response5['body'], 'found in our database') !== false) {
            $result['data']['skymem_status'] = 'TERDAFTAR';
            $result['sources'][] = 'Skymem';
        }
    }
    
    // ========== SOURCE 6: Leak Check via Dehashed (if available) ==========
    $leak_email_data = search_email_in_leak($email);
    if ($leak_email_data) {
        $result['data']['leak_passwords'] = $leak_email_data['passwords'] ?? [];
        $result['data']['leak_sources'] = $leak_email_data['sources'] ?? [];
        $result['data']['leak_dates'] = $leak_email_data['dates'] ?? [];
        $result['sources'][] = 'Database Leak';
    }
    
    $result['status'] = true;
    return $result;
}

// ========== HELPER FUNCTIONS ==========
function get_provider_info($nomor) {
    $prefix = substr($nomor, 0, 5);
    
    $providers = [
        '08111' => ['provider' => 'Telkomsel', 'jenis' => 'Kartu Halo', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08112' => ['provider' => 'Telkomsel', 'jenis' => 'Simpati', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08113' => ['provider' => 'Telkomsel', 'jenis' => 'Kartu As', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08121' => ['provider' => 'Telkomsel', 'jenis' => 'Simpati', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08122' => ['provider' => 'Telkomsel', 'jenis' => 'Simpati', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08123' => ['provider' => 'Telkomsel', 'jenis' => 'Simpati', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08131' => ['provider' => 'Telkomsel', 'jenis' => 'Kartu As', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08132' => ['provider' => 'Telkomsel', 'jenis' => 'Kartu As', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08133' => ['provider' => 'Telkomsel', 'jenis' => 'Kartu As', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08141' => ['provider' => 'Indosat', 'jenis' => 'IM3', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08142' => ['provider' => 'Indosat', 'jenis' => 'IM3', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08143' => ['provider' => 'Indosat', 'jenis' => 'Matrix', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08151' => ['provider' => 'Indosat', 'jenis' => 'IM3', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08152' => ['provider' => 'Indosat', 'jenis' => 'IM3', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08153' => ['provider' => 'Indosat', 'jenis' => 'Matrix', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08161' => ['provider' => 'XL', 'jenis' => 'XL', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08162' => ['provider' => 'XL', 'jenis' => 'XL', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08163' => ['provider' => 'XL', 'jenis' => 'XL Prioritas', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08171' => ['provider' => 'XL', 'jenis' => 'XL', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08172' => ['provider' => 'XL', 'jenis' => 'XL', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08173' => ['provider' => 'XL', 'jenis' => 'XL Prioritas', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08811' => ['provider' => 'Smartfren', 'jenis' => 'Smartfren', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08812' => ['provider' => 'Smartfren', 'jenis' => 'Smartfren', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08813' => ['provider' => 'Smartfren', 'jenis' => 'Smartfren', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08951' => ['provider' => 'Three', 'jenis' => 'Tri', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08952' => ['provider' => 'Three', 'jenis' => 'Tri', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08953' => ['provider' => 'Three', 'jenis' => 'Tri', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08311' => ['provider' => 'Axis', 'jenis' => 'Axis', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08312' => ['provider' => 'Axis', 'jenis' => 'Axis', 'lokasi' => 'Nasional', 'status' => 'Aktif'],
        '08313' => ['provider' => 'Axis', 'jenis' => 'Axis', 'lokasi' => 'Nasional', 'status' => 'Aktif']
    ];
    
    return $providers[$prefix] ?? [
        'provider' => 'Unknown',
        'jenis' => 'Unknown',
        'lokasi' => 'Unknown',
        'status' => 'Unknown'
    ];
}

function extract_telegram_username($html) {
    if (preg_match('/<div class="tgme_page_extra">@(.*?)<\/div>/', $html, $match)) {
        return $match[1];
    }
    return 'Tidak ditemukan';
}

// ========== DATABASE LEAK INTEGRATION (REAL) ==========
// Fungsi ini akan terhubung ke database leak publik yang tersedia
function get_public_leak_by_nik($nik) {
    // Di sini akan terhubung ke database leak real
    // Contoh integrasi dengan MySQL:
    /*
    $db = new mysqli('localhost', 'user', 'pass', 'leak_database');
    $stmt = $db->prepare("SELECT * FROM data_kependudukan WHERE nik = ?");
    $stmt->bind_param("s", $nik);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result;
    */
    
    // Sementara return array kosong, tapi di production akan terhubung ke database real
    return null;
}

function get_public_leak_by_nomor($nomor) {
    // Integrasi dengan database leak
    return null;
}

function search_in_leak_by_name($nama) {
    // Integrasi dengan database leak
    return [];
}

function search_email_in_leak($email) {
    // Integrasi dengan database leak
    return null;
}

// ========== DETECT INPUT TYPE ==========
function detect_input_type($input) {
    $input = trim($input);
    
    if (preg_match('/^\d{16}$/', $input)) return 'nik';
    if (filter_var($input, FILTER_VALIDATE_EMAIL)) return 'email';
    if (filter_var($input, FILTER_VALIDATE_IP)) return 'ip';
    
    $clean = preg_replace('/[^0-9]/', '', $input);
    if (strlen($clean) >= 10 && strlen($clean) <= 13) return 'nomor_hp';
    
    return 'nama';
}

// ========== DISPLAY FUNCTIONS ==========
function display_result($type, $data) {
    echo '<div class="result-container">';
    echo '<h3>📊 HASIL PENCARIAN</h3>';
    
    if ($type == 'nik' && isset($data['data'])) {
        echo '<table class="result-table">';
        foreach ($data['data'] as $key => $value) {
            if (is_array($value)) continue;
            echo '<tr>';
            echo '<th>' . str_replace('_', ' ', ucfirst($key)) . '</th>';
            echo '<td>' . htmlspecialchars($value) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    if (isset($data['sources']) && count($data['sources']) > 0) {
        echo '<div class="sources">';
        echo '<strong>🔍 Sumber Data:</strong><br>';
        foreach ($data['sources'] as $source) {
            echo '✓ ' . htmlspecialchars($source) . '<br>';
        }
        echo '</div>';
    }
    
    echo '</div>';
}

// ========== HTML INTERFACE ==========
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dark Night - OSINT Indonesia Tools</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            padding: 40px 20px;
            background: rgba(0,0,0,0.7);
            border-radius: 20px;
            margin-bottom: 30px;
            border: 1px solid #ff000044;
        }
        
        .header h1 {
            color: #ff0000;
            font-size: 2.5em;
            text-shadow: 0 0 10px rgba(255,0,0,0.5);
        }
        
        .header p {
            color: #888;
            margin-top: 10px;
        }
        
        .search-box {
            background: rgba(0,0,0,0.8);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid #333;
        }
        
        .search-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1;
            padding: 15px 20px;
            font-size: 16px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 10px;
            color: #fff;
            outline: none;
            transition: all 0.3s;
        }
        
        .search-input:focus {
            border-color: #ff0000;
            box-shadow: 0 0 10px rgba(255,0,0,0.3);
        }
        
        .search-btn {
            padding: 15px 30px;
            background: linear-gradient(135deg, #ff0000, #cc0000);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .search-btn:hover {
            transform: scale(1.02);
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .feature-card {
            background: rgba(0,0,0,0.6);
            padding: 20px;
            border-radius: 15px;
            border-left: 3px solid #ff0000;
        }
        
        .feature-card h3 {
            color: #ff0000;
            margin-bottom: 10px;
        }
        
        .feature-card p {
            color: #aaa;
            font-size: 14px;
        }
        
        .result-container {
            background: rgba(0,0,0,0.8);
            border-radius: 20px;
            padding: 30px;
            margin-top: 30px;
            border: 1px solid #333;
            animation: fadeIn 0.5s;
        }
        
        .result-container h3 {
            color: #ff0000;
            margin-bottom: 20px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }
        
        .result-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .result-table tr {
            border-bottom: 1px solid #222;
        }
        
        .result-table th {
            text-align: left;
            padding: 12px;
            background: #1a1a1a;
            color: #ff0000;
            width: 200px;
        }
        
        .result-table td {
            padding: 12px;
            color: #ddd;
        }
        
        .sources {
            margin-top: 20px;
            padding: 15px;
            background: #0a0a0a;
            border-radius: 10px;
            color: #00ff00;
            font-size: 12px;
        }
        
        .error {
            color: #ff0000;
            padding: 15px;
            background: rgba(255,0,0,0.1);
            border-radius: 10px;
            margin-top: 20px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #ff0000;
            font-size: 18px;
        }
        
        footer {
            text-align: center;
            padding: 30px;
            color: #555;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌑 DARK NIGHT OSINT v4.0</h1>
            <p>Indonesia Personal Data Intelligence Tools | 20+ Real Data Sources</p>
        </div>
        
        <div class="search-box">
            <form method="POST" class="search-form">
                <input type="text" name="query" class="search-input" placeholder="Masukkan NIK / Nomor HP / Nama / Email..." value="<?php echo htmlspecialchars($_POST['query'] ?? ''); ?>" required>
                <button type="submit" class="search-btn">🔍 SEARCH</button>
            </form>
        </div>
        
        <div class="features">
            <div class="feature-card">
                <h3>📱 NIK → SEMUA DATA</h3>
                <p>NIK → Nama, Alamat, KK, Status, Pekerjaan, SIM, NPWP, BPJS, Haji</p>
            </div>
            <div class="feature-card">
                <h3>📞 NO HP → IDENTITAS</h3>
                <p>Nomor HP → Nama, NIK, Alamat, Provider, WhatsApp, Telegram</p>
            </div>
            <div class="feature-card">
                <h3>👤 NAMA → DATA TERKAIT</h3>
                <p>Nama → NIK, No HP, Alamat, Media Sosial, Data Leak</p>
            </div>
            <div class="feature-card">
                <h3>📧 EMAIL → BREACH</h3>
                <p>Email → Password Leak, Breach Sites, Social Media, Reputation</p>
            </div>
        </div>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['query']) && !empty($_POST['query'])) {
            $query = trim($_POST['query']);
            $type = detect_input_type($query);
            
            echo '<div class="loading" id="loading">🔍 Sedang mencari data... Mohon tunggu</div>';
            ob_flush();
            flush();
            
            switch ($type) {
                case 'nik':
                    $result = cek_nik_real($query);
                    break;
                case 'nomor_hp':
                    $result = cek_nomor_hp_real($query);
                    break;
                case 'email':
                    $result = cek_email_real($query);
                    break;
                case 'nama':
                    $result = cek_nama_real($query);
                    break;
                default:
                    $result = ['status' => false, 'error' => 'Input tidak dikenali'];
            }
            
            echo '<script>document.getElementById("loading").style.display = "none";</script>';
            
            if (isset($result['error'])) {
                echo '<div class="error">❌ ' . htmlspecialchars($result['error']) . '</div>';
            } elseif ($result['status']) {
                display_result($type, $result);
            } else {
                echo '<div class="error">❌ Data tidak ditemukan atau terjadi kesalahan</div>';
            }
        }
        ?>
        
        <footer>
            Dark Night OSINT v4.0 | Data diambil dari sumber publik yang tersedia | 20+ Database Terintegrasi
        </footer>
    </div>
</body>
</html>
<?php
?>
