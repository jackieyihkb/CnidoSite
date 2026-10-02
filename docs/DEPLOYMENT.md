# Standing up a local copy of CnidoSite

The production instance runs LAMP on Ubuntu 24.04:

| | version |
|---|---|
| OS | Ubuntu 24.04 |
| Apache | 2.4.58, `mod_php` |
| PHP | 7.4 (`mysqli`, `mysqlnd`, `gd`, `mbstring`, `json`, `session`, `pdo_mysql`, `exif`, `fileinfo`, `ftp`) |
| MySQL | 8.0.46 |
| vhost | `/etc/apache2/sites-enabled/cnidosite.conf`, `DocumentRoot /var/www/html/CnidoSite` |

Two things about that list matter when you build your own copy:

* **`ZipArchive` is not installed.** The site's Excel export (`includes/xlsx.php`) writes a ZIP
  container by hand for that reason. If you install `php-zip`, nothing breaks — but don't
  "fix" `xlsx.php` by rewriting it around `ZipArchive`, or it will stop working on the
  production host.
* **`mod_rewrite` is not enabled.** Every rule in `site/.htaccess` uses `RedirectMatch`
  (`mod_alias`) instead. Keep it that way, or the hardening rules below silently stop working.

## 1. Code

```sh
sudo rsync -a --delete site/ /var/www/html/CnidoSite/
sudo chown -R www-data:www-data /var/www/html/CnidoSite
```

Everything the site serves is under that one directory. There is no build step: the PHP is
interpreted directly and the JavaScript is plain (jQuery + Highcharts + Leaflet + Cytoscape.js
+ phylotree.js are vendored in `site/js/`, `site/cytoscape/`, `site/phylotree/`).

## 2. Database credentials

**No credentials are in this repository.** Every connection in the site reads four values from
the environment:

```php
new mysqli(getenv('CNIDO_DB_HOST') ?: 'localhost',
           getenv('CNIDO_DB_USER') ?: 'cnidosite',
           getenv('CNIDO_DB_PASS') ?: '',
           getenv('CNIDO_DB_NAME') ?: 'cnidaria')
```

Set them however your SAPI does it — pick one:

```apache
# Apache / mod_php: in the vhost or a snippet
SetEnv CNIDO_DB_HOST localhost
SetEnv CNIDO_DB_USER cnidosite
SetEnv CNIDO_DB_PASS yourpassword
SetEnv CNIDO_DB_NAME cnidaria
```

```ini
; php-fpm pool
env[CNIDO_DB_HOST] = localhost
env[CNIDO_DB_USER] = cnidosite
env[CNIDO_DB_PASS] = yourpassword
env[CNIDO_DB_NAME] = cnidaria
```

```sh
# CLI scripts and `php -S`
export CNIDO_DB_HOST=localhost CNIDO_DB_USER=cnidosite \
       CNIDO_DB_PASS=yourpassword CNIDO_DB_NAME=cnidaria
```

`getenv()` reads the process environment directly, so it does not depend on
`variables_order` including `E`.

## 3. Database

```sh
mysql -u root -p -e "CREATE DATABASE cnidaria
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p cnidaria < schema/cnidaria-schema.sql
```

Then load the curated tables from `data/`. Two notes:

* MySQL 8 defaults a bare `CHARACTER SET utf8mb4` to the **`utf8mb4_0900_ai_ci`** collation,
  which is not comparable with the `utf8mb4_unicode_ci` tables (`og_*` and the `core_*`
  family) — joins across them fail with *Illegal mix of collations*, and if you have
  `2>/dev/null` anywhere that error reads as "no results" rather than as an error. Always
  write the `COLLATE` clause explicitly when creating a table.
* Give InnoDB a large buffer pool before you load anything. The database is ~178 GB and the
  hot set is much smaller than that; on the production host 16 GB was enough to take the
  heavy pages from seconds to milliseconds:

  ```sql
  SET PERSIST innodb_buffer_pool_size = 17179869184;   -- 16 GiB, survives restart
  ```

## 4. Data directories

The site expects five directories next to the PHP files. In production four of them are
symlinks onto a data volume; they can equally be real directories:

```
data/              small curated tables the site reads directly
download/          per-species genome / annotation files served to users
images/            species photos, mitochondrial maps, epigenome figures
jbrowse/           JBrowse instance (static)
singlecell_data/   single-cell metadata + binary assets
```

`data/` ends with a `.htaccess` (`Require all denied`) — those files are staging material for
the database build and are read only through the filesystem, never over HTTP. Leave that rule
in place.

If you skipped `scripts/download-large-data/`, the site still starts and every page still
renders; pages whose data is missing say so instead of erroring.

## 5. `tmp/` — the only writable directory

`site/tmp/` is where the site caches page fragments and parks user-triggered jobs (network
graphs, custom exports). CLI refresh jobs and the web SAPI share it, so it must exist and be
writable by the web user:

```sh
sudo mkdir -p /var/www/html/CnidoSite/tmp
sudo chown www-data:www-data /var/www/html/CnidoSite/tmp
```

After rebuilding any cache-backed table, delete the corresponding file in `tmp/`, or the page
will keep serving the old numbers (`tmp/coverage_cache.json` is the usual suspect).

## 6. Apache vhost

```apache
<VirtualHost *:80>
    ServerName cnidosite.example.org
    DocumentRoot /var/www/html/CnidoSite

    <FilesMatch \.php$>
        SetHandler application/x-httpd-php
    </FilesMatch>

    <Directory /var/www/html/CnidoSite>
        Options -Indexes +FollowSymLinks
        AllowOverride All          # required: the hardening rules live in .htaccess
        Require all granted
    </Directory>
</VirtualHost>
```

`AllowOverride All` is not optional. `site/.htaccess` is what closes directory listing, blocks
every dot-prefixed path segment, blocks `.inc` / `.bak*` / `.py` suffixes and `blast/db/`, and
rate-limits misbehaving crawlers. Without it those paths become downloadable again — and two
of them (`cytoscape/tail.list.inc`, `.menu_bak/core/index.php`) were at one point reachable
over HTTP and exposed the database credentials in plain text. That is the whole reason the
credentials now come from the environment.

## 7. Optional components

Each is self-contained. Skipping one costs you a single page, never the site.

**BLAST** — `site/blast/` is the NCBI WWW-BLAST CGI front end. It reads the database list at
runtime by scanning `blast/db/` for `.pdb` (protein) and `.ndb` (nucleotide) index files:

```sh
# 1. install the NCBI BLAST+ legacy CGIs and rename them to *.REAL
#    (blast/blast.php execs  <name>.REAL; the 200-byte *.cgi files are wrappers)
#    ./config_setup.pl does this for you; edit blast/blast.rc first.
# 2. build the search databases into blast/db/  (one per species, pep + cds)
#    The production build script:
#      /home/jackie/cnidosite-work/blast/build_blast_dbs.sh
#    Two rules that are easy to get wrong:
#      · do NOT pass -parse_seqids when running makeblastdb
#      · strip the '.' characters from sequence lines of the FASTA first
# 3. put the databases on fast storage. On the production host moving blast/db from the
#    spinning disk to NVMe took a whole-database search from 6m51s to 1m35s.
```

**Primer3Plus** — the site ships a patched front end in `site/primer3plus/`. Only the site's
overrides are included; install upstream as its own README says:

```sh
sudo apt install python3 python3-flask python3-flask-cors npm
cd site/primer3plus/client && npm install && npm run build   # node_modules is not in this repo
export PATH=$PATH:/path/to/primer3/src                       # primer3 v2.6.1
python3 site/primer3plus/server/server.py
```

Two site-specific overrides matter: `PRIMER_THERMODYNAMIC_PARAMETERS_PATH` and
`PRIMER_MISPRIMING_LIBRARY` point at files on this server. Without them primer3 falls back to
its defaults, which is **partially** overridden upstream settings — feeding those straight
into the page's form returns a 252 rather than a result.

> **Re-create these two directories, and protect them.** Primer3Plus writes visitor job files
> into `site/primer3plus/data/` (one `p3p_<uuid>_upload.txt` / `_link.txt` per submission —
> the file starts with `SEQUENCE_TEMPLATE=…`) and run logs into `site/primer3plus/log/`.
> Those are **visitor-submitted sequences**, so they are deliberately not in this repository,
> and the package's own `.gitignore` disowns both directories. On a host with `Options Indexes`
> that means they are browsable unless you deny it, so create them and drop a `.htaccess` in
> each, **before** the server first runs:
>
> ```sh
> mkdir -p site/primer3plus/data site/primer3plus/log
> printf 'Require all denied\n' | tee site/primer3plus/data/.htaccess \
>                                      site/primer3plus/log/.htaccess
> ```
>
> Verify from outside — all three of these must answer 403, not 200 and not a listing:
>
> ```sh
> curl -s -o /dev/null -w '%{http_code}\n' https://your.host/primer3plus/data/
> curl -s -o /dev/null -w '%{http_code}\n' https://your.host/primer3plus/log/
> ```

**GSEA** — `site/GSEA/` is the front end. Its 93 MB `gsea/` run directory and the 16 MB
third-party `deploy-all.sh` are not in this repo; install GSEA from the Broad Institute and
re-create `site/GSEA/gsea/` from your own runs.

**JBrowse** — `site/jbrowse/` is static; point it at your own genome tracks.

**E-mail / contact form** — `site/submit/` writes flat files. The production instance has the
user-submitted `.txt` files removed from this archive deliberately: they are visitor messages,
not database content.
