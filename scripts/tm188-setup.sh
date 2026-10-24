#!/usr/bin/env bash
# Persiapan sekali jalan TM188 (server-dev) agar kode dan database dari laptop bisa diduplikasi ke sini.
# Dijalankan Jo dengan: sudo bash ~/tm188-setup.sh
# Tidak menghapus apa pun. Kata sandi MySQL diketik Jo sendiri dan tidak dikirim ke Claude.
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then echo "Jalankan dengan: sudo bash $0"; exit 1; fi

DEPLOY_USER=svradm
APPS="/var/www/tmerp /var/www/devlib"
HOME_DIR=$(getent passwd "$DEPLOY_USER" | cut -d: -f6)

echo "[1/4] Kepemilikan kode: $DEPLOY_USER (pemilik) dan www-data (grup, dipakai php-fpm)"
usermod -aG www-data "$DEPLOY_USER"
for d in $APPS; do
  [ -d "$d" ] || { echo "  lewati (tidak ada): $d"; continue; }
  chown -R "$DEPLOY_USER":www-data "$d"
  find "$d" -type d -exec chmod 2775 {} +
  find "$d" -type f -exec chmod g+rw {} +
  git config --system --add safe.directory "$d" 2>/dev/null || true
  for f in "$d"/.env "$d"/.env.*; do
    [ -f "$f" ] || continue
    case "$f" in *.example) continue;; esac
    chmod 640 "$f"
  done
  echo "  selesai: $d"
done

echo "[2/4] Akun MySQL khusus deploy (claude_deploy): hanya untuk database tm2* dan ddt*"
read -r -s -p "Buat kata sandi untuk claude_deploy (tidak ditampilkan; hindari tanda kutip ' \" dan \\): " DBPW; echo
read -r -s -p "Ulangi kata sandi: " DBPW2; echo
[ "$DBPW" = "$DBPW2" ] || { echo "Kata sandi tidak sama. Batal."; exit 1; }
[ "${#DBPW}" -ge 12 ] || { echo "Minimal 12 karakter. Batal."; exit 1; }
case "$DBPW" in *"'"*|*'"'*|*'\'*) echo "Kata sandi mengandung ' \" atau \\. Batal."; exit 1;; esac
mysql <<SQL
CREATE USER IF NOT EXISTS 'claude_deploy'@'localhost' IDENTIFIED BY '$DBPW';
ALTER USER 'claude_deploy'@'localhost' IDENTIFIED BY '$DBPW';
GRANT ALL PRIVILEGES ON \`tm2%\`.* TO 'claude_deploy'@'localhost';
GRANT ALL PRIVILEGES ON \`ddt%\`.* TO 'claude_deploy'@'localhost';
FLUSH PRIVILEGES;
SQL
umask 077
cat > "$HOME_DIR/.my.cnf" <<CNF
[client]
user=claude_deploy
password="$DBPW"
CNF
chown "$DEPLOY_USER":"$DEPLOY_USER" "$HOME_DIR/.my.cnf"
chmod 600 "$HOME_DIR/.my.cnf"
unset DBPW DBPW2
echo "  akun dibuat; kredensial disimpan di $HOME_DIR/.my.cnf (mode 600, hanya $DEPLOY_USER)"

echo "[3/4] Inventaris database (nama, ukuran, jumlah tabel) untuk perencanaan"
INV="$HOME_DIR/tm188-inventory.txt"
{
  echo "# inventaris $(date '+%F %T')"
  mysql -e "SELECT @@version AS mysql_version, @@datadir AS datadir, @@innodb_buffer_pool_size/1024/1024 AS bufpool_mb, @@character_set_server AS charset, @@collation_server AS collation, @@lower_case_table_names AS lctn;"
  mysql -e "SELECT table_schema AS db, COUNT(*) AS tables_n, ROUND(SUM(data_length+index_length)/1024/1024,1) AS size_mb FROM information_schema.tables WHERE table_schema NOT IN ('mysql','sys','performance_schema','information_schema') GROUP BY table_schema ORDER BY table_schema;"
  echo "# akun MySQL"
  mysql -e "SELECT user, host FROM mysql.user ORDER BY user;"
  echo "# nginx dan apache"
  nginx -T 2>/dev/null | grep -E "^\s*(listen|server_name|root)\b" || true
  grep -rhE "^\s*(Listen|ServerName|DocumentRoot)" /etc/apache2 2>/dev/null || true
  echo "# versi"
  php -v | head -1; mysql --version
} > "$INV"
chown "$DEPLOY_USER":"$DEPLOY_USER" "$INV"
echo "  ditulis: $INV"

echo "[4/4] Selesai. Beri tahu Claude: 'setup TM188 sudah dijalankan'."
echo "Catatan: grup www-data untuk $DEPLOY_USER berlaku di sesi SSH BARU (Claude membuka sesi baru)."
