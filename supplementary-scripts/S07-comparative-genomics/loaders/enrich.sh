#!/bin/bash
# Copy each species' top NR and UniProt hit into og_family_member_new.
#
# The family page used to look these up live, one query per species per page
# view, against <abbr>_nr / <abbr>_uniprot, which have no index on `gene` -- a
# 200k-row scan every time.  Doing the join once here turns the page into a
# single indexed read.
#
# The gene columns are utf8mb4_0900_ai_ci in the site tables but
# utf8mb4_unicode_ci in ours, so the join needs an explicit collation; a temp
# table carries that collation and gives us a primary key to join against.
set -u
MY="mysql -ujackie -p<REDACTED> cnidaria"
cd "$(dirname "$0")"
TBL=og_family_member

$MY -N -e "SELECT DISTINCT abbr FROM $TBL ORDER BY abbr;" 2>/dev/null > abbr_used.txt
echo "species to enrich: $(wc -l < abbr_used.txt)"

while read -r ab; do
  [ -n "$ab" ] || continue
  for kind in nr uniprot; do
    src="${ab}_${kind}"
    has=$($MY -N -e "SELECT COUNT(*) FROM information_schema.tables
                     WHERE table_schema=DATABASE() AND table_name='$src';" 2>/dev/null)
    [ "$has" = "1" ] || continue
    if [ "$kind" = "nr" ]; then idc=nr_id;  dsc=nr_desc;  else idc=uni_id; dsc=uni_desc; fi
    $MY -e "
      CREATE TEMPORARY TABLE tmp_h (
        gene VARCHAR(191) COLLATE utf8mb4_unicode_ci NOT NULL PRIMARY KEY,
        id   VARCHAR(64)  NULL,
        dsc  VARCHAR(400) NULL) ENGINE=InnoDB;
      INSERT IGNORE INTO tmp_h
        SELECT gene COLLATE utf8mb4_unicode_ci, ID, LEFT(description,400) FROM $src;
      UPDATE $TBL m JOIN tmp_h t ON t.gene = m.gene
         SET m.$idc = NULLIF(t.id,''), m.$dsc = NULLIF(t.dsc,'')
       WHERE m.abbr = '$ab';
      DROP TEMPORARY TABLE tmp_h;" 2>&1 | grep -v 'Using a password'
  done
  echo "enriched $ab"
done < abbr_used.txt

$MY -e "SELECT COUNT(*) AS members, COUNT(nr_id) AS with_nr, COUNT(uni_id) AS with_uniprot
        FROM $TBL;" 2>&1 | grep -v 'Using a password'
echo "ENRICH DONE"
