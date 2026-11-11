<?php
/**
 * Normalizer.php
 * Tahap NORMALISASI PERTANYAAN + EKSPANSI SINONIM (Rekomendasi #1):
 * - Mengubah teks ke huruf kecil (lowercase)
 * - Menghapus tanda baca & simbol
 * - Menghapus kata hubung / kata tanya umum (stopwords Bahasa Indonesia)
 * - Memperkaya token dengan sinonim/singkatan layanan publik Kabupaten Purworejo
 */

class Normalizer
{
    /**
     * Daftar kata umum (stopwords) yang tidak membawa bobot topik spesifik
     */
    private static array $stopwords = [
        'apa', 'apakah', 'siapa', 'dimana', 'kemana', 'darimana', 'kapan', 'bagaimana', 'gimana',
        'berapa', 'mengapa', 'kenapa', 'tolong', 'mohon', 'minta', 'info', 'informasi', 'tanya',
        'bertanya', 'mau', 'ingin', 'akan', 'bisa', 'dapat', 'boleh', 'harus', 'perlu',
        'yang', 'di', 'ke', 'dari', 'pada', 'dalam', 'untuk', 'guna', 'buat', 'dengan', 'dan',
        'atau', 'serta', 'jika', 'kalau', 'bila', 'karena', 'sebab', 'agar', 'supaya',
        'ini', 'itu', 'tersebut', 'tsb', 'adalah', 'ialah', 'merupakan', 'yaitu', 'yakni',
        'saya', 'aku', 'kami', 'kita', 'anda', 'kamu', 'dia', 'mereka', 'pak', 'bu', 'kak', 'min', 'admin',
        'ada', 'tidak', 'nggak', 'gak', 'bukan', 'belum', 'sudah', 'telah', 'sedang', 'lagi',
        'saja', 'aja', 'kok', 'sih', 'dong', 'deh', 'lah', 'kah', 'pun', 'nya', 'ya', 'yah',
        'tentang', 'mengenai', 'terkait', 'seputar', 'soal', 'hal', 'bagian', 'tempat'
    ];

    /**
     * Kamus Ekspansi Sinonim & Singkatan Layanan Kabupaten Purworejo
     * Agar pertanyaan masyarakat dengan bahasa sehari-hari tetap cocok dengan istilah resmi di KB
     */
    private static array $synonymMap = [
        'dishub'      => ['dinhub', 'perhubungan'],
        'dinhub'      => ['dishub', 'perhubungan'],
        'dukcapil'    => ['disdukcapil', 'kependudukan'],
        'capil'       => ['disdukcapil', 'kependudukan'],
        'ktp'         => ['ktp el', 'ektp', 'kependudukan'],
        'ektp'        => ['ktp', 'disdukcapil'],
        'kk'          => ['kartu keluarga', 'disdukcapil'],
        'akte'        => ['akta', 'kelahiran', 'kematian'],
        'nikah'       => ['kawin', 'perkawinan', 'kua'],
        'cerai'       => ['perceraian', 'pengadilan agama'],
        'p3k'         => ['pppk', 'asn', 'kepegawaian'],
        'pppk'        => ['p3k', 'asn'],
        'cpns'        => ['casn', 'bkpsdm'],
        'bansos'      => ['bantuan sosial', 'pkh', 'desil', 'dinsos'],
        'pkh'         => ['bansos', 'desil'],
        'bpjs'        => ['jkn', 'kis', 'kesehatan', 'ketenagakerjaan'],
        'kis'         => ['bpjs', 'kesehatan'],
        'pdam'        => ['tirta perwitasari', 'air minum'],
        'pam'         => ['pdam', 'tirta perwitasari'],
        'pemadam'     => ['damkar', 'kebakaran', 'apar'],
        'damkar'      => ['pemadam', 'kebakaran'],
        'pajak'       => ['bppkad', 'samsat', 'pbb', 'retribusi'],
        'pbb'         => ['pajak bumi bangunan', 'bppkad'],
        'stnk'        => ['samsat', 'pajak kendaraan'],
        'sim'         => ['polres', 'kepolisian'],
        'skck'        => ['polres', 'kepolisian'],
        'tilang'      => ['kejari', 'kejaksaan'],
        'nib'         => ['oss', 'perizinan', 'dpmptsp'],
        'oss'         => ['nib', 'perizinan', 'dpmptsp'],
        'izin'        => ['perizinan', 'dpmptsp'],
        'rsud'        => ['rumah sakit', 'tjitrowardojo', 'tjokronegoro'],
        'puskesmas'   => ['kesehatan', 'dinkes'],
        'pmi'         => ['donor darah', 'stok darah'],
        'tani'        => ['pertanian', 'dkpp', 'padi'],
        'pupuk'       => ['pertanian', 'dkpp'],
    ];

    /**
     * Normalisasi pertanyaan pengguna menjadi string bersih, token utama, dan ekspansi sinonim
     */
    public static function normalize(string $rawQuestion): array
    {
        // 1. Lowercase & rapikan spasi
        $lower = mb_strtolower(trim($rawQuestion), 'UTF-8');

        // 2. Ganti karakter non-alfanumerik dengan spasi
        $cleaned = preg_replace('/[^a-z0-9\s]/u', ' ', $lower);
        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));

        if ($cleaned === '') {
            return [
                'original'        => $rawQuestion,
                'cleaned'         => '',
                'normalized'      => '',
                'tokens'          => [],
                'expanded_tokens' => []
            ];
        }

        $rawTokens = explode(' ', $cleaned);
        $tokens    = [];

        foreach ($rawTokens as $word) {
            if ($word === '' || mb_strlen($word) < 2) {
                continue;
            }
            if (in_array($word, self::$stopwords, true)) {
                continue;
            }
            $tokens[] = $word;
        }

        if (empty($tokens)) {
            $tokens = array_filter($rawTokens, fn($w) => mb_strlen($w) >= 2);
        }

        $uniqueTokens = array_values(array_unique($tokens));

        // 3. Ekspansi sinonim untuk memperkaya kata kunci pencarian tanpa mengubah makna asli
        $expanded = $uniqueTokens;
        foreach ($uniqueTokens as $token) {
            if (isset(self::$synonymMap[$token])) {
                foreach (self::$synonymMap[$token] as $syn) {
                    foreach (explode(' ', $syn) as $synPart) {
                        if ($synPart !== '' && !in_array($synPart, $expanded, true)) {
                            $expanded[] = $synPart;
                        }
                    }
                }
            }
        }

        return [
            'original'        => $rawQuestion,
            'cleaned'         => $cleaned,
            'normalized'      => implode(' ', $uniqueTokens),
            'tokens'          => $expanded,
            'base_tokens'     => $uniqueTokens,
            'synonyms_added'  => array_values(array_diff($expanded, $uniqueTokens))
        ];
    }
}
