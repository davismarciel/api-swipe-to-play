#!/bin/bash

# Script para popular o banco de dados
echo "🌱 Executando seeds..."

# Executar todos os arquivos SQL em ordem
psql -h db -U postgres -d stp_db -f /var/www/html/database/seeds/01_categories.sql
psql -h db -U postgres -d stp_db -f /var/www/html/database/seeds/02_genres.sql
psql -h db -U postgres -d stp_db -f /var/www/html/database/seeds/03_developers.sql
psql -h db -U postgres -d stp_db -f /var/www/html/database/seeds/04_publishers.sql
psql -h db -U postgres -d stp_db -f /var/www/html/database/seeds/05_games.sql
psql -h db -U postgres -d stp_db -f /var/www/html/database/seeds/06_game_categories.sql
psql -h db -U postgres -d stp_db -f /var/www/html/database/seeds/07_game_genres.sql
psql -h db -U postgres -d stp_db -f /var/www/html/database/seeds/08_game_developers.sql
psql -h db -U postgres -d stp_db -f /var/www/html/database/seeds/09_game_publishers.sql
psql -h db -U postgres -d stp_db -f /var/www/html/database/seeds/10_game_languages.sql
psql -h db -U postgres -d stp_db -f /var/www/html/database/seeds/11_game_movies.sql

echo "✅ Seeds executados!"
