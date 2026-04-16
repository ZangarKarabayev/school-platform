#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'EOF'
Usage:
  MYSQL_PWD='...' PGPASSWORD='...' ./tools/migrate_schoolmeal_students.sh \
    --source-org-id 602 \
    --target-school-id 22 \
    --mysql-user schoolmeal \
    --pg-user altyn_as_dbadmin \
    --commit

Required:
  --source-org-id       organization_id in schoolmeal
  --target-school-id    school_id in altyn_as_prod
  --mysql-user          MySQL username
  --pg-user             PostgreSQL username

Optional:
  --mysql-host          default: 127.0.0.1
  --mysql-port          default: 3306
  --mysql-db            default: schoolmeal
  --pg-host             default: 127.0.0.1
  --pg-port             default: 5432
  --pg-db               default: altyn_as_prod
  --work-file           default: /tmp/schoolmeal_students_<org>.tsv
  --commit              actually write changes
  --dry-run             force rollback, default mode

Passwords:
  MySQL password must be passed via MYSQL_PWD env var.
  PostgreSQL password must be passed via PGPASSWORD env var.
EOF
}

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "Missing required command: $1" >&2
    exit 1
  }
}

source_org_id=""
target_school_id=""
mysql_host="127.0.0.1"
mysql_port="3306"
mysql_db="schoolmeal"
mysql_user=""
pg_host="127.0.0.1"
pg_port="5432"
pg_db="altyn_as_prod"
pg_user=""
work_file=""
commit_mode="0"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --source-org-id)
      source_org_id="${2:-}"
      shift 2
      ;;
    --target-school-id)
      target_school_id="${2:-}"
      shift 2
      ;;
    --mysql-host)
      mysql_host="${2:-}"
      shift 2
      ;;
    --mysql-port)
      mysql_port="${2:-}"
      shift 2
      ;;
    --mysql-db)
      mysql_db="${2:-}"
      shift 2
      ;;
    --mysql-user)
      mysql_user="${2:-}"
      shift 2
      ;;
    --pg-host)
      pg_host="${2:-}"
      shift 2
      ;;
    --pg-port)
      pg_port="${2:-}"
      shift 2
      ;;
    --pg-db)
      pg_db="${2:-}"
      shift 2
      ;;
    --pg-user)
      pg_user="${2:-}"
      shift 2
      ;;
    --work-file)
      work_file="${2:-}"
      shift 2
      ;;
    --commit)
      commit_mode="1"
      shift
      ;;
    --dry-run)
      commit_mode="0"
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage
      exit 1
      ;;
  esac
done

require_cmd mysql
require_cmd psql
require_cmd wc

[[ -n "$source_org_id" ]] || { echo "--source-org-id is required" >&2; exit 1; }
[[ -n "$target_school_id" ]] || { echo "--target-school-id is required" >&2; exit 1; }
[[ -n "$mysql_user" ]] || { echo "--mysql-user is required" >&2; exit 1; }
[[ -n "$pg_user" ]] || { echo "--pg-user is required" >&2; exit 1; }
[[ "${MYSQL_PWD:-}" != "" ]] || { echo "MYSQL_PWD env var is required" >&2; exit 1; }
[[ "${PGPASSWORD:-}" != "" ]] || { echo "PGPASSWORD env var is required" >&2; exit 1; }
[[ "$source_org_id" =~ ^[0-9]+$ ]] || { echo "--source-org-id must be numeric" >&2; exit 1; }
[[ "$target_school_id" =~ ^[0-9]+$ ]] || { echo "--target-school-id must be numeric" >&2; exit 1; }

if [[ -z "$work_file" ]]; then
  work_file="/tmp/schoolmeal_students_${source_org_id}.tsv"
fi

echo "Exporting students from MySQL organization_id=${source_org_id} ..."
mysql \
  -h "$mysql_host" \
  -P "$mysql_port" \
  -u "$mysql_user" \
  --batch --raw --skip-column-names \
  "$mysql_db" \
  -e "
SELECT
    iin,
    surname,
    name,
    patronymic,
    birthday,
    phone,
    classroom_id,
    year_name,
    created_at,
    updated_at
FROM students
WHERE organization_id = ${source_org_id}
  AND iin IS NOT NULL
  AND iin <> '';
" > "$work_file"

row_count="$(wc -l < "$work_file" | tr -d ' ')"
echo "Exported rows: ${row_count}"

if [[ "$row_count" == "0" ]]; then
  echo "Nothing to import. File is empty: $work_file" >&2
  exit 1
fi

if [[ "$commit_mode" == "1" ]]; then
  finalizer="COMMIT;"
  echo "Running in COMMIT mode."
else
  finalizer="ROLLBACK;"
  echo "Running in DRY-RUN mode. No changes will be saved."
fi

psql \
  -h "$pg_host" \
  -p "$pg_port" \
  -U "$pg_user" \
  -d "$pg_db" \
  -v ON_ERROR_STOP=1 \
  -v work_file="$work_file" \
  -v target_school_id="$target_school_id" <<SQL
BEGIN;

DROP TABLE IF EXISTS tmp_schoolmeal_students_import;

CREATE TEMP TABLE tmp_schoolmeal_students_import (
    iin text,
    surname text,
    name text,
    patronymic text,
    birthday text,
    phone text,
    classroom_id text,
    year_name text,
    created_at text,
    updated_at text
);

\copy tmp_schoolmeal_students_import FROM :'work_file' WITH (FORMAT text);

CREATE TEMP TABLE tmp_schoolmeal_students_normalized AS
SELECT
    NULLIF(btrim(iin), '') AS iin,
    NULLIF(btrim(name), '') AS first_name,
    NULLIF(btrim(surname), '') AS last_name,
    NULLIF(btrim(patronymic), '') AS middle_name,
    CASE
        WHEN NULLIF(btrim(birthday), '') IS NOT NULL THEN birthday::date
        ELSE NULL
    END AS birth_date,
    CASE
        WHEN NULLIF(btrim(iin), '') ~ '^\d{12}$'
            THEN CASE
                WHEN substring(iin from 7 for 1)::int % 2 = 1 THEN 'male'
                ELSE 'female'
            END
        ELSE NULL
    END AS gender,
    CASE
        WHEN NULLIF(btrim(classroom_id), '') ~ '^\d+$' THEN classroom_id::int
        ELSE NULL
    END AS classroom_id,
    :target_school_id::int AS school_id,
    NULLIF(btrim(phone), '') AS phone,
    NULL::text AS address,
    NULL::varchar(255) AS photo,
    'active'::varchar(20) AS status,
    NULL::varchar(20) AS student_number,
    NULL::varchar(10) AS language,
    NULL::int AS shift,
    NULLIF(btrim(year_name), '') AS school_year,
    COALESCE(NULLIF(btrim(created_at), '')::timestamp, NOW()) AS created_at,
    COALESCE(NULLIF(btrim(updated_at), '')::timestamp, NOW()) AS updated_at,
    row_number() OVER (
        PARTITION BY NULLIF(btrim(iin), '')
        ORDER BY COALESCE(NULLIF(btrim(updated_at), '')::timestamp, NOW()) DESC
    ) AS rn
FROM tmp_schoolmeal_students_import
WHERE NULLIF(btrim(iin), '') ~ '^\d{12}$';

SELECT count(*) AS total_import_rows FROM tmp_schoolmeal_students_import;
SELECT count(*) AS normalized_rows FROM tmp_schoolmeal_students_normalized WHERE rn = 1;
SELECT count(*) AS existing_target_rows
FROM students s
WHERE EXISTS (
    SELECT 1
    FROM tmp_schoolmeal_students_normalized t
    WHERE t.rn = 1
      AND t.iin = s.iin
);

INSERT INTO students (
    iin,
    first_name,
    last_name,
    middle_name,
    birth_date,
    gender,
    classroom_id,
    school_id,
    phone,
    address,
    photo,
    status,
    student_number,
    language,
    shift,
    school_year,
    created_at,
    updated_at
)
SELECT
    iin,
    first_name,
    last_name,
    middle_name,
    birth_date,
    gender,
    classroom_id,
    school_id,
    phone,
    address,
    photo,
    status,
    student_number,
    language,
    shift,
    school_year,
    created_at,
    updated_at
FROM tmp_schoolmeal_students_normalized
WHERE rn = 1
ON CONFLICT (iin) DO UPDATE SET
    first_name = EXCLUDED.first_name,
    last_name = EXCLUDED.last_name,
    middle_name = EXCLUDED.middle_name,
    birth_date = EXCLUDED.birth_date,
    gender = EXCLUDED.gender,
    classroom_id = EXCLUDED.classroom_id,
    school_id = EXCLUDED.school_id,
    phone = EXCLUDED.phone,
    address = EXCLUDED.address,
    photo = NULL,
    status = EXCLUDED.status,
    student_number = EXCLUDED.student_number,
    language = EXCLUDED.language,
    shift = EXCLUDED.shift,
    school_year = EXCLUDED.school_year,
    updated_at = NOW();

INSERT INTO meal_benefits (
    student_id,
    type,
    voucher_update_datetime,
    start_date,
    end_date,
    created_at,
    updated_at
)
SELECT
    s.id,
    'voucher',
    NOW(),
    NULL,
    NULL,
    NOW(),
    NOW()
FROM students s
JOIN tmp_schoolmeal_students_normalized t
    ON t.iin = s.iin
WHERE t.rn = 1
  AND s.school_id = :target_school_id::int
  AND NOT EXISTS (
      SELECT 1
      FROM meal_benefits mb
      WHERE mb.student_id = s.id
        AND mb.type = 'voucher'
  );

SELECT count(*) AS target_students_after_import
FROM students s
JOIN tmp_schoolmeal_students_normalized t
    ON t.iin = s.iin
WHERE t.rn = 1
  AND s.school_id = :target_school_id::int;

SELECT count(*) AS target_vouchers_after_import
FROM meal_benefits mb
JOIN students s ON s.id = mb.student_id
JOIN tmp_schoolmeal_students_normalized t ON t.iin = s.iin
WHERE t.rn = 1
  AND s.school_id = :target_school_id::int
  AND mb.type = 'voucher';

${finalizer}
SQL

echo "Done."
