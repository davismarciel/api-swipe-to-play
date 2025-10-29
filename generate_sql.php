<?php

/**
 * Script para gerar SQL puro a partir do JSONL
 *
 * Uso: php generate_sql.php
 * Output: storage/app/seeds/games_seed.sql
 */

function slugify($text)
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

function escape($str)
{
    if ($str === null) return 'NULL';
    return "'" . addslashes($str) . "'";
}

function parseDate($dateData)
{
    if (!is_array($dateData) || !isset($dateData['date'])) {
        return 'NULL';
    }

    $date = $dateData['date'];
    if (empty($date)) return 'NULL';

    // Tentar converter formatos como "Aug 21, 2012" para "2012-08-21"
    $timestamp = strtotime($date);
    if ($timestamp === false) return 'NULL';

    return "'" . date('Y-m-d', $timestamp) . "'";
}

echo "🚀 Gerando SQL a partir do JSONL...\n\n";

$file = __DIR__ . '/storage/app/seeds/aggregator_multigame.jsonl';
if (!file_exists($file)) {
    die("❌ Arquivo não encontrado: $file\n");
}

$output = __DIR__ . '/storage/app/seeds/games_seed.sql';

// Coletar dados
$categories = [];
$genres = [];
$developers = [];
$publishers = [];
$games = [];

$handle = fopen($file, 'r');
$lineNum = 0;

while (($line = fgets($handle)) !== false) {
    $lineNum++;
    $data = json_decode($line, true);

    if (!$data) {
        echo "⚠️  Linha $lineNum: JSON inválido\n";
        continue;
    }

    // Coletar categorias
    if (isset($data['categories']) && is_array($data['categories'])) {
        foreach ($data['categories'] as $cat) {
            $categories[$cat] = $cat;
        }
    }

    // Coletar genres
    if (isset($data['genres']) && is_array($data['genres'])) {
        foreach ($data['genres'] as $genre) {
            $genres[$genre] = $genre;
        }
    }

    // Coletar developers
    if (isset($data['developers']) && is_array($data['developers'])) {
        foreach ($data['developers'] as $dev) {
            $developers[$dev] = $dev;
        }
    }

    // Coletar publishers
    if (isset($data['publishers']) && is_array($data['publishers'])) {
        foreach ($data['publishers'] as $pub) {
            $publishers[$pub] = $pub;
        }
    }

    $games[] = $data;
}

fclose($handle);

echo "📊 Estatísticas:\n";
echo "   - Games: " . count($games) . "\n";
echo "   - Categories: " . count($categories) . "\n";
echo "   - Genres: " . count($genres) . "\n";
echo "   - Developers: " . count($developers) . "\n";
echo "   - Publishers: " . count($publishers) . "\n\n";

// Ordenar
ksort($categories);
ksort($genres);
ksort($developers);
ksort($publishers);

// Mapear IDs
$catMap = [];
$i = 1;
foreach ($categories as $c) {
    $catMap[$c] = $i++;
}
$genMap = [];
$i = 1;
foreach ($genres as $g) {
    $genMap[$g] = $i++;
}
$devMap = [];
$i = 1;
foreach ($developers as $d) {
    $devMap[$d] = $i++;
}
$pubMap = [];
$i = 1;
foreach ($publishers as $p) {
    $pubMap[$p] = $i++;
}

// Gerar SQL
$sql = "-- Generated at " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- Games: " . count($games) . " | Categories: " . count($categories) . " | Genres: " . count($genres) . "\n\n";
$sql .= "BEGIN;\n\n";
$sql .= "-- Disable triggers temporarily\n";
$sql .= "SET session_replication_role = 'replica';\n\n";

// Categories
$sql .= "-- ============================================\n";
$sql .= "-- CATEGORIES (" . count($categories) . ")\n";
$sql .= "-- ============================================\n";
foreach ($catMap as $name => $id) {
    $slug = slugify($name);
    $sql .= "INSERT INTO categories (id, name, slug, created_at, updated_at) VALUES ($id, " . escape($name) . ", " . escape($slug) . ", NOW(), NOW());\n";
}
$sql .= "\n";

// Genres
$sql .= "-- ============================================\n";
$sql .= "-- GENRES (" . count($genres) . ")\n";
$sql .= "-- ============================================\n";
foreach ($genMap as $name => $id) {
    $slug = slugify($name);
    $sql .= "INSERT INTO genres (id, name, slug, created_at, updated_at) VALUES ($id, " . escape($name) . ", " . escape($slug) . ", NOW(), NOW());\n";
}
$sql .= "\n";

// Developers
$sql .= "-- ============================================\n";
$sql .= "-- DEVELOPERS (" . count($developers) . ")\n";
$sql .= "-- ============================================\n";
foreach ($devMap as $name => $id) {
    $slug = slugify($name);
    $sql .= "INSERT INTO developers (id, name, slug, created_at, updated_at) VALUES ($id, " . escape($name) . ", " . escape($slug) . ", NOW(), NOW());\n";
}
$sql .= "\n";

// Publishers
$sql .= "-- ============================================\n";
$sql .= "-- PUBLISHERS (" . count($publishers) . ")\n";
$sql .= "-- ============================================\n";
foreach ($pubMap as $name => $id) {
    $slug = slugify($name);
    $sql .= "INSERT INTO publishers (id, name, slug, created_at, updated_at) VALUES ($id, " . escape($name) . ", " . escape($slug) . ", NOW(), NOW());\n";
}
$sql .= "\n";

// Games
$sql .= "-- ============================================\n";
$sql .= "-- GAMES (" . count($games) . ")\n";
$sql .= "-- ============================================\n\n";

$gameId = 1;
foreach ($games as $g) {
    $gameName = $g['name'] ?? 'Unknown';
    $sql .= "-- Game #$gameId: $gameName\n";

    $steamId = $g['id'] ?? 0;
    $name = escape($g['name'] ?? '');
    $type = escape($g['type'] ?? 'game');
    $slug = escape(slugify($g['name'] ?? ''));
    $shortDesc = escape($g['short_description'] ?? '');
    $requiredAge = intval($g['required_age'] ?? 0);
    $isFree = !empty($g['is_free']) ? 'true' : 'false';
    $haveDlc = !empty($g['have_dlc']) ? 'true' : 'false';
    $icon = escape($g['icon'] ?? '');
    $languages = escape(json_encode($g['supported_languages'] ?? []));

    $releaseDate = parseDate($g['release_date'] ?? null);
    $comingSoon = (isset($g['release_date']['coming_soon']) && $g['release_date']['coming_soon']) ? 'true' : 'false';

    $recommendations = intval($g['recommendations']['total'] ?? 0);
    $achievementsCount = intval($g['achievements']['total'] ?? 0);

    $positiveReviews = intval($g['positive_reviews'] ?? 0);
    $negativeReviews = intval($g['negative_reviews'] ?? 0);
    $totalReviews = intval($g['total_reviews'] ?? ($positiveReviews + $negativeReviews));
    $positiveRatio = isset($g['positive_ratio']) && is_numeric($g['positive_ratio']) ? $g['positive_ratio'] : 'NULL';

    $contentDescriptors = escape(json_encode($g['content_descriptors'] ?? []));

    $sql .= "INSERT INTO games (id, steam_id, name, type, slug, short_description, required_age, is_free, have_dlc, icon, supported_languages, release_date, coming_soon, recommendations, achievements_count, positive_reviews, negative_reviews, total_reviews, positive_ratio, content_descriptors, is_active, created_at, updated_at) VALUES ";
    $sql .= "($gameId, $steamId, $name, $type, $slug, $shortDesc, $requiredAge, $isFree, $haveDlc, $icon, $languages, $releaseDate, $comingSoon, $recommendations, $achievementsCount, $positiveReviews, $negativeReviews, $totalReviews, $positiveRatio, $contentDescriptors, true, NOW(), NOW());\n";

    // Platforms
    $windows = (!empty($g['plataforms']['windows'])) ? 'true' : 'false';
    $mac = (!empty($g['plataforms']['mac'])) ? 'true' : 'false';
    $linux = (!empty($g['plataforms']['linux'])) ? 'true' : 'false';
    $sql .= "INSERT INTO game_platforms (game_id, windows, mac, linux, created_at, updated_at) VALUES ($gameId, $windows, $mac, $linux, NOW(), NOW());\n";

    // Requirements
    $pcReq = escape($g['pc_requeriments'] ?? $g['pc_requirements'] ?? '');
    $macReq = escape($g['mac_requeriments'] ?? $g['mac_requirements'] ?? '');
    $linuxReq = escape($g['linux_requeriments'] ?? $g['linux_requirements'] ?? '');
    $sql .= "INSERT INTO game_requirements (game_id, pc_requirements, mac_requirements, linux_requirements, created_at, updated_at) VALUES ($gameId, $pcReq, $macReq, $linuxReq, NOW(), NOW());\n";

    // Community Ratings
    $ratings = $g['ratings'] ?? [];
    $toxicity = $ratings['toxicity_rate'] ?? 0;
    $cheater = $ratings['cheater_rate'] ?? 0;
    $bug = $ratings['bug_rate'] ?? 0;
    $micro = $ratings['microtransaction_rate'] ?? 0;
    $badOpt = $ratings['bad_optimization_rate'] ?? 0;
    $notRec = $ratings['not_recommended_rate'] ?? 0;
    $sql .= "INSERT INTO game_community_ratings (game_id, toxicity_rate, cheater_rate, bug_rate, microtransaction_rate, bad_optimization_rate, not_recommended_rate, created_at, updated_at) VALUES ($gameId, $toxicity, $cheater, $bug, $micro, $badOpt, $notRec, NOW(), NOW());\n";

    // Media (movies)
    if (isset($g['movies']) && is_array($g['movies'])) {
        foreach ($g['movies'] as $movie) {
            $mediaId = isset($movie['id']) ? intval($movie['id']) : 'NULL';
            $movieName = escape($movie['name'] ?? '');
            $thumb = escape($movie['thumbnail'] ?? '');
            $webm = escape(json_encode($movie['webm'] ?? null));
            $mp4 = escape(json_encode($movie['mp4'] ?? null));
            $dashAv1 = escape($movie['dash_av1'] ?? '');
            $dashH264 = escape($movie['dash_h264'] ?? '');
            $hlsH264 = escape($movie['hls_h264'] ?? '');
            $highlight = (!empty($movie['highlight'])) ? 'true' : 'false';

            $sql .= "INSERT INTO game_media (game_id, media_id, name, thumbnail, webm, mp4, dash_av1, dash_h264, hls_h264, highlight, created_at, updated_at) VALUES ($gameId, $mediaId, $movieName, $thumb, $webm, $mp4, $dashAv1, $dashH264, $hlsH264, $highlight, NOW(), NOW());\n";
        }
    }

    // Pivot: game_category
    if (isset($g['categories']) && is_array($g['categories'])) {
        foreach ($g['categories'] as $cat) {
            if (isset($catMap[$cat])) {
                $catId = $catMap[$cat];
                $sql .= "INSERT INTO game_category (game_id, category_id, created_at, updated_at) VALUES ($gameId, $catId, NOW(), NOW());\n";
            }
        }
    }

    // Pivot: game_genre
    if (isset($g['genres']) && is_array($g['genres'])) {
        foreach ($g['genres'] as $genre) {
            if (isset($genMap[$genre])) {
                $genId = $genMap[$genre];
                $sql .= "INSERT INTO game_genre (game_id, genre_id, created_at, updated_at) VALUES ($gameId, $genId, NOW(), NOW());\n";
            }
        }
    }

    // Pivot: game_developer
    if (isset($g['developers']) && is_array($g['developers'])) {
        foreach ($g['developers'] as $dev) {
            if (isset($devMap[$dev])) {
                $devId = $devMap[$dev];
                $sql .= "INSERT INTO game_developer (game_id, developer_id, created_at, updated_at) VALUES ($gameId, $devId, NOW(), NOW());\n";
            }
        }
    }

    // Pivot: game_publisher
    if (isset($g['publishers']) && is_array($g['publishers'])) {
        foreach ($g['publishers'] as $pub) {
            if (isset($pubMap[$pub])) {
                $pubId = $pubMap[$pub];
                $sql .= "INSERT INTO game_publisher (game_id, publisher_id, created_at, updated_at) VALUES ($gameId, $pubId, NOW(), NOW());\n";
            }
        }
    }

    $sql .= "\n";
    $gameId++;
}

// Reset sequences
$sql .= "-- ============================================\n";
$sql .= "-- RESET SEQUENCES\n";
$sql .= "-- ============================================\n";
$sql .= "SELECT setval(pg_get_serial_sequence('categories', 'id'), (SELECT COALESCE(MAX(id), 0) FROM categories));\n";
$sql .= "SELECT setval(pg_get_serial_sequence('genres', 'id'), (SELECT COALESCE(MAX(id), 0) FROM genres));\n";
$sql .= "SELECT setval(pg_get_serial_sequence('developers', 'id'), (SELECT COALESCE(MAX(id), 0) FROM developers));\n";
$sql .= "SELECT setval(pg_get_serial_sequence('publishers', 'id'), (SELECT COALESCE(MAX(id), 0) FROM publishers));\n";
$sql .= "SELECT setval(pg_get_serial_sequence('games', 'id'), (SELECT COALESCE(MAX(id), 0) FROM games));\n";
$sql .= "SELECT setval(pg_get_serial_sequence('game_media', 'id'), (SELECT COALESCE(MAX(id), 0) FROM game_media));\n";
$sql .= "SELECT setval(pg_get_serial_sequence('game_platforms', 'id'), (SELECT COALESCE(MAX(id), 0) FROM game_platforms));\n";
$sql .= "SELECT setval(pg_get_serial_sequence('game_requirements', 'id'), (SELECT COALESCE(MAX(id), 0) FROM game_requirements));\n";
$sql .= "SELECT setval(pg_get_serial_sequence('game_community_ratings', 'id'), (SELECT COALESCE(MAX(id), 0) FROM game_community_ratings));\n\n";

// Re-enable triggers
$sql .= "-- Re-enable triggers\n";
$sql .= "SET session_replication_role = 'origin';\n\n";
$sql .= "COMMIT;\n";

// Salvar arquivo
file_put_contents($output, $sql);

echo "✅ SQL gerado com sucesso!\n";
echo "📁 Arquivo: $output\n";
echo "📦 Tamanho: " . number_format(filesize($output) / 1024, 2) . " KB\n\n";
echo "🚀 Para executar:\n";
echo "   docker exec -i stp_db psql -U postgres -d stp_db < storage/app/seeds/games_seed.sql\n\n";
