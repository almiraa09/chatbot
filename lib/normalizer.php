<?php
/**
 * lib/normalizer.php
 * Pustaka utilitas untuk normalisasi pertanyaan pengguna Bahasa Indonesia
 */

require_once __DIR__ . '/../core/Normalizer.php';
require_once __DIR__ . '/../types/ChatTypes.php';

/**
 * Menormalisasi pertanyaan pengguna dan mengembalikan objek NormalizedQueryResult
 */
function normalizeUserQuestion(string $question): NormalizedQueryResult
{
    $res = Normalizer::normalize($question);
    return new NormalizedQueryResult(
        $res['original'],
        $res['normalized'],
        $res['tokens']
    );
}
