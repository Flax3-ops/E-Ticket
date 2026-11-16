#!/usr/bin/env bash
# Demonstrasi pembaruan nyata (Unit 17): pasang v1.0 yang berjalan, terapkan patch v1.1, uji asap, rollback, restore.
# Seluruhnya memakai worktree Git terpisah dan database `tiket_upgrade` / `tiket_upgrade_restore`; tiket_demo tidak disentuh.
#   bash scripts/demo_update.sh | tee docs/A17-hasil-pembaruan.txt
set -u
REPO="$(cd "$(dirname "$0")/.." && pwd)"
WT="${TEMP:-/tmp}/tiket-upgrade-app"
PHP=php
MYSQL="/d/xampp/mysql/bin/mysql.exe"
PORT=8092
APP_PW="$(grep '^database.default.password' "$REPO/.env" | sed 's/.*= *//' | tr -d '\r')"
G() { git -c "safe.directory=$REPO" -c "safe.directory=$WT" "$@"; }
step() { echo; echo "=== $* ==="; }
counts() { "$MYSQL" -uroot "$1" -N -e "SELECT CONCAT('users=',(SELECT COUNT(*) FROM users),' tickets=',(SELECT COUNT(*) FROM tickets),' comments=',(SELECT COUNT(*) FROM comments),' ticket_logs=',(SELECT COUNT(*) FROM ticket_logs),' notifications=',(SELECT COUNT(*) FROM notifications))"; }
schema() { "$MYSQL" -uroot "$1" -N -e "SELECT CONCAT('idx_active_list=',(SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema='$1' AND table_name='tickets' AND index_name='idx_active_list'),' alerts.acknowledged_at=',(SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$1' AND table_name='alerts' AND column_name='acknowledged_at'),' migrasi_tercatat=',(SELECT COUNT(*) FROM migrations))"; }
env_dba() { sed -i 's/^database.default.username = .*/database.default.username = root/; s/^database.default.password *=.*/database.default.password = /' "$WT/.env"; }
env_app() { sed -i "s/^database.default.username = .*/database.default.username = tiket_app/; s/^database.default.password *=.*/database.default.password = $APP_PW/" "$WT/.env"; }
serve_start() {
  ($PHP -S "localhost:$PORT" -t "$WT/public" "$WT/vendor/codeigniter4/framework/system/rewrite.php" > "$WT/serve.log" 2>&1 &)
  for i in 1 2 3 4 5 6 7 8 9 10; do sleep 1; [ "$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:$PORT/login")" = "200" ] && { echo "Server uji aktif di port $PORT"; return 0; }; done
  echo 'GAGAL: server uji tidak aktif'; return 1
}
serve_stop() { powershell -NoProfile -Command "Get-NetTCPConnection -LocalPort $PORT -State Listen -ErrorAction SilentlyContinue | ForEach-Object { Stop-Process -Id \$_.OwningProcess -Force }; Get-CimInstance Win32_Process -Filter \"Name='php.exe'\" | Where-Object { \$_.CommandLine -match 'serve --port $PORT' } | ForEach-Object { Stop-Process -Id \$_.ProcessId -Force }" >/dev/null 2>&1; sleep 1; }
smoke() { TIKET_TEST_DB=tiket_upgrade $PHP "$REPO/tests/http/run.php" "http://localhost:$PORT" 2>&1 | grep -v "^PASS" | grep -v "^$" | grep -v "PHP Warn|^Warn"; }  # uji HTTP versi terbaru; LOG-04/LOG-04b/NTF-10c adalah fitur v1.1 sehingga WAJIB gagal/tidak ada pada v1.0

step "0. Persiapan: worktree v1.0 dan database terpisah"
serve_stop
[ -d "$WT" ] && { G worktree remove --force "$WT" >/dev/null 2>&1; }
G worktree prune
G worktree add --detach "$WT" v1.0 2>&1 | tail -1
# vendor dipasang dari composer.lock di worktree (tidak memakai junction: menghapus junction dengan rm -rf dapat menghapus isi vendor utama)
(cd "$WT" && composer install --no-dev --no-interaction 2>&1 | tail -2)
mkdir -p "$WT/writable/logs" "$WT/writable/cache" "$WT/writable/session" "$WT/writable/uploads" "$WT/writable/backups"
cp "$REPO/.env.example" "$WT/.env"
sed -i "s#^app.baseURL.*#app.baseURL = 'http://localhost:$PORT/'#; s/^database.default.database.*/database.default.database = tiket_upgrade/" "$WT/.env"
grep -q '^DEMO_OTP' "$WT/.env" || echo 'DEMO_OTP = true' >> "$WT/.env"
grep -q '^BACKUP_DB_USER' "$WT/.env" || { echo 'BACKUP_DB_USER = root' >> "$WT/.env"; echo 'BACKUP_DB_PASSWORD = ' >> "$WT/.env"; }
grep -q '^CI_ENVIRONMENT' "$WT/.env" || echo 'CI_ENVIRONMENT = development' >> "$WT/.env"
"$MYSQL" -uroot -e "DROP DATABASE IF EXISTS tiket_upgrade; CREATE DATABASE tiket_upgrade CHARACTER SET utf8mb4; DROP DATABASE IF EXISTS tiket_upgrade_restore;"
echo "Versi kode terpasang: $(G -C "$WT" describe --tags --exact-match 2>/dev/null)"

step "1. Pasang v1.0 (migrasi dengan akun DBA, seed demo)"
env_dba
(cd "$WT" && $PHP spark migrate --all 2>&1 | grep -v "^$" | head -20)
(cd "$WT" && $PHP spark db:seed DemoSeeder 2>&1 | tail -1)
sed 's/tiket_demo/tiket_upgrade/g' "$REPO/database/sql/06_grants.sql" | grep -v "^SHOW GRANTS\|^--" | "$MYSQL" -uroot
env_app
echo "Data awal   : $(counts tiket_upgrade)"
echo "Skema v1.0  : $(schema tiket_upgrade)"

step "2. Uji asap v1.0 yang berjalan (uji HTTP versi v1.0 milik tag itu sendiri)"
serve_start
smoke
echo "Data setelah uji asap v1.0: $(counts tiket_upgrade)"
serve_stop

step "3. Backup sebelum pembaruan (php spark backup:db)"
(cd "$WT" && $PHP spark backup:db 2>&1 | tail -1)
DUMP="$(ls -t "$WT"/writable/backups/tiket_upgrade-*.sql | head -1)"
echo "Berkas backup: $(basename "$DUMP") ($(stat -c %s "$DUMP") byte)"
BEFORE_COUNTS="$(counts tiket_upgrade)"
echo "Data saat backup: $BEFORE_COUNTS"

step "4. Terapkan patch v1.1: checkout tag, composer install, migrate (DBA)"
G -C "$WT" checkout -q v1.1 && echo "Kode sekarang: $(G -C "$WT" describe --tags --exact-match)"
echo "Ringkasan diff v1.0..v1.1 (app/):"
G -C "$WT" diff --stat v1.0 v1.1 -- app | tail -22
(cd "$WT" && composer install --no-dev --no-interaction 2>&1 | tail -2)
env_dba
(cd "$WT" && $PHP spark migrate 2>&1 | grep -E "Running|Migrations complete|Done|ERROR")
env_app
echo "Skema v1.1  : $(schema tiket_upgrade)"
echo "Data setelah migrasi (tidak boleh berkurang): $(counts tiket_upgrade)"
[ "$(counts tiket_upgrade)" = "$BEFORE_COUNTS" ] && echo "HASIL: jumlah data identik sebelum/sesudah migrasi" || echo "HASIL: JUMLAH DATA BERBEDA"

step "5. Uji asap v1.1 sesudah patch (uji HTTP versi v1.1)"
serve_start
smoke
serve_stop

step "6. Rollback: migrate:rollback lalu kembali ke kode v1.0"
env_dba
(cd "$WT" && $PHP spark migrate:rollback 2>&1 | grep -E "Rolling|Done|Migrations|ERROR")
env_app
echo "Skema setelah rollback: $(schema tiket_upgrade)"
G -C "$WT" checkout -q v1.0 && echo "Kode kembali: $(G -C "$WT" describe --tags --exact-match)"
serve_start
smoke
serve_stop
echo "Data setelah rollback dan uji asap v1.0: $(counts tiket_upgrade)"

step "7. Pulihkan backup ke database TERPISAH dan bandingkan"
powershell -NoProfile -File "$(cygpath -w "$REPO/scripts/restore_db.ps1")" -Dump "$(cygpath -w "$DUMP")" -Database tiket_upgrade_restore
echo "Data hasil restore: $(counts tiket_upgrade_restore)"
[ "$(counts tiket_upgrade_restore)" = "$BEFORE_COUNTS" ] && echo "HASIL: restore identik dengan data saat backup" || echo "HASIL: restore BERBEDA"
echo "Objek hasil restore: triggers=$("$MYSQL" -uroot -N -e "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='tiket_upgrade_restore'") routines=$("$MYSQL" -uroot -N -e "SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema='tiket_upgrade_restore'") views=$("$MYSQL" -uroot -N -e "SELECT COUNT(*) FROM information_schema.views WHERE table_schema='tiket_upgrade_restore'")"
echo "Hak EXECUTE setelah restore (dipasang ulang skrip): $("$MYSQL" -uroot -N -e "SHOW GRANTS FOR 'tiket_app'@'localhost'" | grep -c 'tiket_upgrade_restore.*fn_sla_hours\|EXECUTE ON FUNCTION .tiket_upgrade_restore.\..fn_sla_hours')"

step "8. Terapkan ulang v1.1 sebagai keadaan akhir"
G -C "$WT" checkout -q v1.1
env_dba
(cd "$WT" && $PHP spark migrate 2>&1 | grep -E "Running|Done|ERROR")
env_app
echo "Skema akhir : $(schema tiket_upgrade)"
echo "Kode akhir  : $(G -C "$WT" describe --tags --exact-match)"
echo
echo "Selesai. Worktree: $WT (hapus dengan: git worktree remove --force '$WT')"
