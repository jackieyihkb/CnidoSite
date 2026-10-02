# Setup helpers

The full walk-through is [`../../docs/DEPLOYMENT.md`](../../docs/DEPLOYMENT.md). These are the
two things worth having as files rather than as instructions.

## `dbq.php` — run one query from the command line

The site has no shared connection function; every page builds its own `mysqli` handle. This
helper reads the same four environment variables the site reads, so the SQL quoted in
[`../../docs/DATA_OVERVIEW.md`](../../docs/DATA_OVERVIEW.md) can be pasted and run.

```sh
export CNIDO_DB_HOST=localhost CNIDO_DB_USER=cnidosite \
       CNIDO_DB_PASS=... CNIDO_DB_NAME=cnidaria

php scripts/setup/dbq.php "SELECT COUNT(*) FROM classfy"        # -> 326
php scripts/setup/dbq.php -f my_query.sql
```

Output is TSV with a header row, one line per record — so it pipes into `awk`, `column -t`, or
a spreadsheet without further processing. The password is never written to the file and never
printed.

On a host whose CLI `php.ini` is broken (the vendor's PHP 7.4 is), name both files explicitly:

```sh
PHP_INI_SCAN_DIR=/etc/php/7.4/apache2/conf.d \
  php -c /etc/php/7.4/apache2/php.ini scripts/setup/dbq.php "<SQL>"
```

If the environment variables are unset, `dbq.php` falls back to reading them out of
`site/includes/state.php` — which is how the production host is configured and is why the
archived copy of that file keeps its defaults.

## Loading the schema

There is no bootstrap script, because the step is two commands and both need a MySQL
superuser password that cannot be usefully defaulted:

```sh
mysql -u root -p -e "CREATE DATABASE cnidaria
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p cnidaria < schema/cnidaria-schema.sql
```

The `COLLATE` clause is not optional — see [`DEPLOYMENT.md` §3](../../docs/DEPLOYMENT.md) for
why a bare `CHARACTER SET utf8mb4` breaks joins against the `og_*` and `core_*` tables.

After loading, insert a row into `singlecell` **only** with the caveat recorded in
`DATA_OVERVIEW.md` §8 in hand: `singlecell_atlas_map.cnidosite_row` is a row ordinal into a
table with no primary key, so an insertion in the middle silently re-points every dataset
below it at the wrong species.
