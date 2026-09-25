# Primary-key surgery on an existing table

Two recipes for reshaping keys on a table that already holds data. Both fail
loudly on MySQL/MariaDB if the calls run in the wrong order, and both lean on
the helpers in the skill's "Key-prefix-length indexes, foreign keys, and
primary keys" section.

## Moving the primary key onto a composite key

Keep the `id` column. Models address rows by a single scalar id
(`get($id)`, `update($data, $id)`, `delete($id)`), so a table without one
drops out of the model API entirely — the composite key goes *alongside*
`id`, never instead of it.

`id` still needs a key of its own once the primary key moves off it:
MySQL/MariaDB refuse to leave an AUTO_INCREMENT column unkeyed even
momentarily, failing with *"Incorrect table definition; there can be only
one auto column and it must be defined as a key."* An index satisfies that
without touching the column, so its values and counter are never disturbed.

```php
// up()
$this->add_index(table: 'user_client', columns: ['id'], unique: true);
$this->drop_primary_key('user_client');
$this->add_primary_key(table: 'user_client', columns: ['user_id', 'client_identifier']);

// down()
$this->drop_primary_key('user_client');
$this->add_primary_key(table: 'user_client', columns: ['id']);
$this->drop_index(table: 'user_client', columns: ['id']);
```

The index is created first and dropped last — it stands in for the primary
key for as long as `id` isn't one, so dropping it any earlier fails the same
way. `unique: true` keeps the uniqueness the primary key used to enforce,
which is also what guarantees `down()`'s `add_primary_key(['id'])` can't hit
a duplicate.

## Restoring an AUTO_INCREMENT column

`add_column()` cannot add an AUTO_INCREMENT column to an existing table on
MySQL/MariaDB — the engine rejects the column unless it is keyed in the same
statement. Add it as a plain column, key it, then let `add_auto_increment()`
number the rows. Reversing a migration that dropped the surrogate key
entirely:

```php
$this->drop_primary_key('user_client');
$this->dbforge->add_column('user_client', $this->field(
    name: 'id', type: MgrFieldType::Int, unsigned: true, nullable: false, default: 0
));
$this->add_index(table: 'user_client', columns: ['id']);   // plain: every row still holds 0
$this->add_auto_increment('user_client', 'id');
$this->add_primary_key(table: 'user_client', columns: ['id']);
$this->drop_index(table: 'user_client', columns: ['id']);
```

The index has to be plain and has to come first: `add_auto_increment()`
needs the column keyed before it can number anything, and a unique key
would reject the placeholder zeros it hasn't replaced yet.

`add_auto_increment()` numbers every row holding `0` or `NULL` and leaves
the rest alone, then positions the counter past the highest existing value —
so it both fills a fresh column and resumes a populated one. On Postgres it
builds the sequence under the name a `SERIAL` column would have gotten
(`{table}_{column}_seq`). It throws on SQL Server and SQLite, which cannot
add `IDENTITY`/`AUTOINCREMENT` to an existing column at all.
