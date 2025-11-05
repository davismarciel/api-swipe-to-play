<?php

/**
 * Script para gerar SQL puro a partir do JSONL
 *
 * Uso: php generate_sql.php
 * Output: database/seeds/ (arquivos separados)
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
    return "'" . str_replace("'", "''", $str) . "'";
}

function parseDate($dateData)
{
    if (!is_array($dateData) || !isset($dateData['date'])) {
        return 'NULL';
    }

    $date = $dateData['date'];
    if (empty($date)) return 'NULL';

    $timestamp = strtotime($date);
    if ($timestamp === false) return 'NULL';

    return "'" . date('Y-m-d', $timestamp) . "'";
}

echo "🚀 Gerando SQL a partir do JSONL...\n\n";

$file = __DIR__ . '/storage/app/seeds/games_aggregator.jsonl';
if (!file_exists($file)) {
    die("❌ Arquivo não encontrado: $file\n");
}

$outputDir = __DIR__ . '/database/seeds/';

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

    if (isset($data['categories']) && is_array($data['categories'])) {
        foreach ($data['categories'] as $cat) {
            $categories[$cat] = $cat;
        }
    }

    if (isset($data['genres']) && is_array($data['genres'])) {
        foreach ($data['genres'] as $genre) {
            $genres[$genre] = $genre;
        }
    }

    if (isset($data['developers']) && is_array($data['developers'])) {
        foreach ($data['developers'] as $dev) {
            $developers[$dev] = $dev;
        }
    }

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

ksort($categories);
ksort($genres);
ksort($developers);
ksort($publishers);

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

echo "📝 Gerando arquivos SQL separados...\n\n";

$categoriesSql = "-- Seeds para Categories\n";
$categoriesSql .= "-- Gerado automaticamente\n\n";
$categoriesSql .= "INSERT INTO categories (name) VALUES\n";
$catValues = [];
foreach ($catMap as $name => $id) {
    $catValues[] = "  (" . escape($name) . ")";
}
$categoriesSql .= implode(",\n", $catValues) . ";\n\n";
$categoriesSql .= "-- Total: " . count($categories) . " categorias\n";
file_put_contents($outputDir . '01_categories.sql', $categoriesSql);
echo "✅ 01_categories.sql gerado\n";

$genresSql = "-- Seeds para Genres\n";
$genresSql .= "-- Gerado automaticamente\n\n";
$genresSql .= "INSERT INTO genres (name) VALUES\n";
$genreValues = [];
foreach ($genMap as $name => $id) {
    $genreValues[] = "  (" . escape($name) . ")";
}
$genresSql .= implode(",\n", $genreValues) . ";\n\n";
$genresSql .= "-- Total: " . count($genres) . " gêneros\n";
file_put_contents($outputDir . '02_genres.sql', $genresSql);
echo "✅ 02_genres.sql gerado\n";

$developersSql = "-- Seeds para Developers\n";
$developersSql .= "-- Gerado automaticamente\n\n";
$developersSql .= "INSERT INTO developers (name) VALUES\n";
$devValues = [];
foreach ($devMap as $name => $id) {
    $devValues[] = "  (" . escape($name) . ")";
}
$developersSql .= implode(",\n", $devValues) . ";\n\n";
$developersSql .= "-- Total: " . count($developers) . " desenvolvedores\n";
file_put_contents($outputDir . '03_developers.sql', $developersSql);
echo "✅ 03_developers.sql gerado\n";

$publishersSql = "-- Seeds para Publishers\n";
$publishersSql .= "-- Gerado automaticamente\n\n";
$publishersSql .= "INSERT INTO publishers (name) VALUES\n";
$pubValues = [];
foreach ($pubMap as $name => $id) {
    $pubValues[] = "  (" . escape($name) . ")";
}
$publishersSql .= implode(",\n", $pubValues) . ";\n\n";
$publishersSql .= "-- Total: " . count($publishers) . " publishers\n";
file_put_contents($outputDir . '04_publishers.sql', $publishersSql);
echo "✅ 04_publishers.sql gerado\n";

// ============================================================================
// 05_games.sql - Games table (normalized schema)
// ============================================================================
$gamesSql = "-- Seeds para Games\n";
$gamesSql .= "-- Gerado automaticamente\n\n";
$gamesSql .= "INSERT INTO games (\n";
$gamesSql .= "  steam_id, name, slug, type, short_description, required_age, is_free, have_dlc,\n";
$gamesSql .= "  icon, cover, supported_languages, release_date, coming_soon,\n";
$gamesSql .= "  recommendations, achievements_count, achievements_highlighted,\n";
$gamesSql .= "  positive_reviews, negative_reviews, total_reviews, positive_ratio,\n";
$gamesSql .= "  content_descriptors, is_active\n";
$gamesSql .= ") VALUES\n";

$gameValues = [];
$platformValues = [];
$requirementValues = [];
$ratingValues = [];

foreach ($games as $g) {
    $steamId = $g['id'] ?? 0;
    $name = escape($g['name'] ?? '');
    $slug = escape(slugify($g['name'] ?? ''));
    $type = escape($g['type'] ?? 'game');
    $shortDesc = escape($g['short_description'] ?? '');
    $requiredAge = intval($g['required_age'] ?? 0);
    $isFree = !empty($g['is_free']) ? 'TRUE' : 'FALSE';
    $haveDlc = !empty($g['have_dlc']) ? 'TRUE' : 'FALSE';
    $icon = escape($g['icon'] ?? '');
    $cover = escape($g['cover'] ?? '');
    
    // Supported languages as JSON
    $languages = isset($g['supported_languages']) && is_array($g['supported_languages']) 
        ? "'" . str_replace("'", "''", json_encode($g['supported_languages'])) . "'" 
        : 'NULL';
    
    // Release date
    $comingSoon = (isset($g['release_date']['coming_soon']) && $g['release_date']['coming_soon']) ? 'TRUE' : 'FALSE';
    $releaseDate = parseDate($g['release_date'] ?? []);
    
    // Stats
    $recommendations = intval($g['recommendations']['total'] ?? 0);
    $achievements = intval($g['achievements']['total'] ?? 0);
    $achievementsHighlighted = isset($g['achievements']['highlighted']) && is_array($g['achievements']['highlighted']) 
        ? "'" . str_replace("'", "''", json_encode($g['achievements']['highlighted'])) . "'" 
        : 'NULL';
    $totalReviews = intval($g['total_reviews'] ?? 0);
    $positiveReviews = intval($g['positive_reviews'] ?? 0);
    $negativeReviews = intval($g['negative_reviews'] ?? 0);
    $positiveRatio = isset($g['positive_ratio']) && is_numeric($g['positive_ratio']) ? $g['positive_ratio'] : 'NULL';
    
    // Content descriptors as JSON
    $contentDescriptors = "'" . str_replace("'", "''", json_encode($g['content_descriptors'] ?? [])) . "'";
    $isActive = 'TRUE';
    
    $gameValues[] = "  ($steamId, $name, $slug, $type, $shortDesc, $requiredAge, $isFree, $haveDlc, $icon, $cover, $languages, $releaseDate, $comingSoon, $recommendations, $achievements, $achievementsHighlighted, $positiveReviews, $negativeReviews, $totalReviews, $positiveRatio, $contentDescriptors, $isActive)";
    
    // Prepare platform data
    $windows = (!empty($g['plataforms']['windows'])) ? 'TRUE' : 'FALSE';
    $mac = (!empty($g['plataforms']['mac'])) ? 'TRUE' : 'FALSE';
    $linux = (!empty($g['plataforms']['linux'])) ? 'TRUE' : 'FALSE';
    $platformValues[] = "  ($steamId, $windows, $mac, $linux)";
    
    // Prepare requirements data
    $pcReq = escape($g['pc_requeriments'] ?? $g['pc_requirements'] ?? '');
    $macReq = escape($g['mac_requeriments'] ?? $g['mac_requirements'] ?? '');
    $linuxReq = escape($g['linux_requeriments'] ?? $g['linux_requirements'] ?? '');
    $requirementValues[] = "  ($steamId, $pcReq, $macReq, $linuxReq)";
    
    // Prepare ratings data
    $ratings = $g['ratings'] ?? [];
    $toxicityRate = $ratings['toxicity_rate'] ?? 0;
    $cheaterRate = $ratings['cheater_rate'] ?? 0;
    $bugRate = $ratings['bug_rate'] ?? 0;
    $microRate = $ratings['microtransaction_rate'] ?? 0;
    $badOptRate = $ratings['bad_optimization_rate'] ?? 0;
    $notRecRate = $ratings['not_recommended_rate'] ?? 0;
    $ratingValues[] = "  ($steamId, $toxicityRate, $cheaterRate, $bugRate, $microRate, $badOptRate, $notRecRate)";
}

$gamesSql .= implode(",\n", $gameValues) . ";\n\n";
$gamesSql .= "-- Total: " . count($games) . " jogos\n";
file_put_contents($outputDir . '05_games.sql', $gamesSql);
echo "✅ 05_games.sql gerado\n";

// ============================================================================
// 05b_game_platforms.sql - Game platforms (separate table)
// ============================================================================
$platformsSql = "-- Seeds para Game Platforms\n";
$platformsSql .= "-- Gerado automaticamente\n\n";
$platformsSql .= "INSERT INTO game_platforms (game_id, windows, mac, linux)\n";
$platformsSql .= "SELECT id, platforms.* FROM games\n";
$platformsSql .= "CROSS JOIN LATERAL (VALUES\n";
$platformsSql .= implode(",\n", array_map(function($val, $idx) use ($games) {
    $steamId = $games[$idx]['id'] ?? 0;
    return str_replace("($steamId,", "(", $val);
}, $platformValues, array_keys($platformValues))) . "\n";
$platformsSql .= ") AS platforms(windows, mac, linux)\n";
$platformsSql .= "WHERE games.steam_id = ARRAY[" . implode(', ', array_map(fn($g) => $g['id'] ?? 0, $games)) . "][array_position(ARRAY[" . implode(', ', array_map(fn($g) => $g['id'] ?? 0, $games)) . "], games.steam_id::int)];\n\n";

// Simpler approach - just match by position
$platformsSql = "-- Seeds para Game Platforms\n";
$platformsSql .= "-- Gerado automaticamente\n\n";
$platformsSql .= "-- Insert platforms using game_id lookup\n";
foreach ($games as $g) {
    $steamId = $g['id'] ?? 0;
    $windows = (!empty($g['plataforms']['windows'])) ? 'TRUE' : 'FALSE';
    $mac = (!empty($g['plataforms']['mac'])) ? 'TRUE' : 'FALSE';
    $linux = (!empty($g['plataforms']['linux'])) ? 'TRUE' : 'FALSE';
    $platformsSql .= "INSERT INTO game_platforms (game_id, windows, mac, linux) SELECT id, $windows, $mac, $linux FROM games WHERE steam_id = '$steamId' ON CONFLICT (game_id) DO NOTHING;\n";
}
$platformsSql .= "\n-- Total: " . count($games) . " plataformas\n";
file_put_contents($outputDir . '05b_game_platforms.sql', $platformsSql);
echo "✅ 05b_game_platforms.sql gerado\n";

// ============================================================================
// 05c_game_requirements.sql - Game requirements (separate table)
// ============================================================================
$requirementsSql = "-- Seeds para Game Requirements\n";
$requirementsSql .= "-- Gerado automaticamente\n\n";
foreach ($games as $g) {
    $steamId = $g['id'] ?? 0;
    $pcReq = escape($g['pc_requeriments'] ?? $g['pc_requirements'] ?? '');
    $macReq = escape($g['mac_requeriments'] ?? $g['mac_requirements'] ?? '');
    $linuxReq = escape($g['linux_requeriments'] ?? $g['linux_requirements'] ?? '');
    $requirementsSql .= "INSERT INTO game_requirements (game_id, pc_requirements, mac_requirements, linux_requirements) SELECT g.id, $pcReq, $macReq, $linuxReq FROM games g WHERE g.steam_id = '$steamId' AND NOT EXISTS (SELECT 1 FROM game_requirements gr WHERE gr.game_id = g.id);\n";
}
$requirementsSql .= "\n-- Total: " . count($games) . " requisitos\n";
file_put_contents($outputDir . '05c_game_requirements.sql', $requirementsSql);
echo "✅ 05c_game_requirements.sql gerado\n";

// ============================================================================
// 05d_game_community_ratings.sql - Game ratings (separate table)
// ============================================================================
$ratingsSql = "-- Seeds para Game Community Ratings\n";
$ratingsSql .= "-- Gerado automaticamente\n\n";
foreach ($games as $g) {
    $steamId = $g['id'] ?? 0;
    $ratings = $g['ratings'] ?? [];
    $toxicityRate = $ratings['toxicity_rate'] ?? 0;
    $cheaterRate = $ratings['cheater_rate'] ?? 0;
    $bugRate = $ratings['bug_rate'] ?? 0;
    $microRate = $ratings['microtransaction_rate'] ?? 0;
    $badOptRate = $ratings['bad_optimization_rate'] ?? 0;
    $notRecRate = $ratings['not_recommended_rate'] ?? 0;
    $ratingsSql .= "INSERT INTO game_community_ratings (game_id, toxicity_rate, cheater_rate, bug_rate, microtransaction_rate, bad_optimization_rate, not_recommended_rate) SELECT g.id, $toxicityRate, $cheaterRate, $bugRate, $microRate, $badOptRate, $notRecRate FROM games g WHERE g.steam_id = '$steamId' AND NOT EXISTS (SELECT 1 FROM game_community_ratings gcr WHERE gcr.game_id = g.id);\n";
}
$ratingsSql .= "\n-- Total: " . count($games) . " avaliações\n";
file_put_contents($outputDir . '05d_game_community_ratings.sql', $ratingsSql);
echo "✅ 05d_game_community_ratings.sql gerado\n";

// ============================================================================
// 06_game_category.sql - Game-Category pivot table
// ============================================================================
$gameCategoriesSql = "-- Seeds para Game Categories (relacionamento many-to-many)\n";
$gameCategoriesSql .= "-- Gerado automaticamente\n\n";

foreach ($games as $g) {
    $steamId = $g['id'] ?? 0;
    if (isset($g['categories']) && is_array($g['categories'])) {
        foreach ($g['categories'] as $cat) {
            if (isset($catMap[$cat])) {
                $catSlug = slugify($cat);
                $gameCategoriesSql .= "INSERT INTO game_category (game_id, category_id) SELECT g.id, c.id FROM games g, categories c WHERE g.steam_id = '$steamId' AND c.slug = '$catSlug' ON CONFLICT (game_id, category_id) DO NOTHING;\n";
            }
        }
    }
}

$gameCategoriesSql .= "\n-- Total relacionamentos inseridos\n";
file_put_contents($outputDir . '06_game_category.sql', $gameCategoriesSql);
echo "✅ 06_game_category.sql gerado\n";

// ============================================================================
// 07_game_genre.sql - Game-Genre pivot table
// ============================================================================
$gameGenresSql = "-- Seeds para Game Genres (relacionamento many-to-many)\n";
$gameGenresSql .= "-- Gerado automaticamente\n\n";

foreach ($games as $g) {
    $steamId = $g['id'] ?? 0;
    if (isset($g['genres']) && is_array($g['genres'])) {
        foreach ($g['genres'] as $genre) {
            if (isset($genMap[$genre])) {
                $genSlug = slugify($genre);
                $gameGenresSql .= "INSERT INTO game_genre (game_id, genre_id) SELECT g.id, ge.id FROM games g, genres ge WHERE g.steam_id = '$steamId' AND ge.slug = '$genSlug' ON CONFLICT (game_id, genre_id) DO NOTHING;\n";
            }
        }
    }
}

$gameGenresSql .= "\n-- Total relacionamentos inseridos\n";
file_put_contents($outputDir . '07_game_genre.sql', $gameGenresSql);
echo "✅ 07_game_genre.sql gerado\n";

// ============================================================================
// 08_game_developer.sql - Game-Developer pivot table
// ============================================================================
$gameDevelopersSql = "-- Seeds para Game Developers (relacionamento many-to-many)\n";
$gameDevelopersSql .= "-- Gerado automaticamente\n\n";

foreach ($games as $g) {
    $steamId = $g['id'] ?? 0;
    if (isset($g['developers']) && is_array($g['developers'])) {
        foreach ($g['developers'] as $dev) {
            if (isset($devMap[$dev])) {
                $devSlug = slugify($dev);
                $gameDevelopersSql .= "INSERT INTO game_developer (game_id, developer_id) SELECT g.id, d.id FROM games g, developers d WHERE g.steam_id = '$steamId' AND d.slug = '$devSlug' ON CONFLICT (game_id, developer_id) DO NOTHING;\n";
            }
        }
    }
}

$gameDevelopersSql .= "\n-- Total relacionamentos inseridos\n";
file_put_contents($outputDir . '08_game_developer.sql', $gameDevelopersSql);
echo "✅ 08_game_developer.sql gerado\n";

// ============================================================================
// 09_game_publisher.sql - Game-Publisher pivot table
// ============================================================================
$gamePublishersSql = "-- Seeds para Game Publishers (relacionamento many-to-many)\n";
$gamePublishersSql .= "-- Gerado automaticamente\n\n";

foreach ($games as $g) {
    $steamId = $g['id'] ?? 0;
    if (isset($g['publishers']) && is_array($g['publishers'])) {
        foreach ($g['publishers'] as $pub) {
            if (isset($pubMap[$pub])) {
                $pubSlug = slugify($pub);
                $gamePublishersSql .= "INSERT INTO game_publisher (game_id, publisher_id) SELECT g.id, p.id FROM games g, publishers p WHERE g.steam_id = '$steamId' AND p.slug = '$pubSlug' ON CONFLICT (game_id, publisher_id) DO NOTHING;\n";
            }
        }
    }
}

$gamePublishersSql .= "\n-- Total relacionamentos inseridos\n";
file_put_contents($outputDir . '09_game_publisher.sql', $gamePublishersSql);
echo "✅ 09_game_publisher.sql gerado\n";

// ============================================================================
// 10_game_media.sql - Game media/movies
// ============================================================================
$gameMediaSql = "-- Seeds para Game Media\n";
$gameMediaSql .= "-- Gerado automaticamente\n\n";

foreach ($games as $g) {
    $steamId = $g['id'] ?? 0;
    if (isset($g['movies']) && is_array($g['movies'])) {
        foreach ($g['movies'] as $movie) {
            $mediaId = escape($movie['id'] ?? '');
            $movieName = escape($movie['name'] ?? '');
            $thumbnail = escape($movie['thumbnail'] ?? '');
            $webm = isset($movie['webm']) ? "'" . str_replace("'", "''", json_encode($movie['webm'])) . "'" : 'NULL';
            $mp4 = isset($movie['mp4']) ? "'" . str_replace("'", "''", json_encode($movie['mp4'])) . "'" : 'NULL';
            $dashAv1 = escape($movie['dash_av1'] ?? '');
            $dashH264 = escape($movie['dash_h264'] ?? '');
            $hlsH264 = escape($movie['hls_h264'] ?? '');
            $highlight = (!empty($movie['highlight'])) ? 'TRUE' : 'FALSE';
            
            $gameMediaSql .= "INSERT INTO game_media (game_id, media_id, name, thumbnail, webm, mp4, dash_av1, dash_h264, hls_h264, highlight) SELECT g.id, $mediaId, $movieName, $thumbnail, $webm, $mp4, $dashAv1, $dashH264, $hlsH264, $highlight FROM games g WHERE g.steam_id = '$steamId' AND NOT EXISTS (SELECT 1 FROM game_media gm WHERE gm.game_id = g.id AND gm.media_id = $mediaId);\n";
        }
    }
}

$gameMediaSql .= "\n-- Total vídeos inseridos\n";
file_put_contents($outputDir . '10_game_media.sql', $gameMediaSql);
echo "✅ 10_game_media.sql gerado\n";

echo "\n🎉 Todos os arquivos SQL foram gerados com sucesso!\n";
echo "📁 Diretório: $outputDir\n";
echo "📊 Resumo:\n";
echo "   - 01_categories.sql: " . count($categories) . " categorias\n";
echo "   - 02_genres.sql: " . count($genres) . " gêneros\n";
echo "   - 03_developers.sql: " . count($developers) . " desenvolvedores\n";
echo "   - 04_publishers.sql: " . count($publishers) . " publishers\n";
echo "   - 05_games.sql: " . count($games) . " jogos\n";
echo "   - 05b_game_platforms.sql: " . count($games) . " plataformas\n";
echo "   - 05c_game_requirements.sql: " . count($games) . " requisitos\n";
echo "   - 05d_game_community_ratings.sql: " . count($games) . " avaliações\n";
echo "   - 06_game_category.sql: relacionamentos\n";
echo "   - 07_game_genre.sql: relacionamentos\n";
echo "   - 08_game_developer.sql: relacionamentos\n";
echo "   - 09_game_publisher.sql: relacionamentos\n";
echo "   - 10_game_media.sql: vídeos\n\n";
echo "🚀 Usar Laravel Seeder: php artisan db:seed\n\n";
