<?php

namespace App\Backup;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * SQL-дамп бази засобами PHP (ТЗ 4, надійність): на віртуальному хостингу exec() і mysqldump часто недоступні.
 * Пише потоком у .sql.gz, тому великі таблиці не тримаються в пам’яті. Підтримує MySQL/MariaDB і SQLite.
 */
class DatabaseDumper
{
    private const CHUNK = 500;

    public function dump(string $path, ?string $connection = null): void
    {
        $db = DB::connection($connection);
        $out = gzopen($path, 'wb6');
        if ($out === false) {
            throw new RuntimeException("Cannot write backup to {$path}");
        }

        try {
            $mysql = in_array($db->getDriverName(), ['mysql', 'mariadb'], true);
            gzwrite($out, '-- graj.club backup '.now()->toIso8601String().' ('.$db->getDriverName().")\n");
            gzwrite($out, $mysql ? "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n" : "PRAGMA foreign_keys=OFF;\n");

            foreach ($this->tables($db) as $table) {
                gzwrite($out, "\n".$this->createStatement($db, $table, $mysql).";\n");
                $this->writeRows($db, $table, $out);
            }

            gzwrite($out, $mysql ? "\nSET FOREIGN_KEY_CHECKS=1;\n" : "\nPRAGMA foreign_keys=ON;\n");
        } finally {
            gzclose($out);
        }
    }

    /** @return list<string> */
    private function tables(Connection $db): array
    {
        $schema = $db->getDriverName() === 'sqlite' ? 'main' : $db->getDatabaseName();

        return array_values(array_filter(
            $db->getSchemaBuilder()->getTableListing($schema, false),
            fn (string $name) => ! str_starts_with($name, 'sqlite_'),
        ));
    }

    private function createStatement(Connection $db, string $table, bool $mysql): string
    {
        $name = $db->getQueryGrammar()->wrapTable($table);

        if ($mysql) {
            return "DROP TABLE IF EXISTS {$name};\n".((array) $db->selectOne("SHOW CREATE TABLE {$name}"))['Create Table'];
        }

        $sql = $db->selectOne("select sql from sqlite_master where type = 'table' and name = ?", [$table])->sql;
        $indexes = $db->select("select sql from sqlite_master where type = 'index' and tbl_name = ? and sql is not null", [$table]);

        return "DROP TABLE IF EXISTS {$name};\n".$sql.collect($indexes)->map(fn ($i) => ";\n".$i->sql)->implode('');
    }

    /** @param resource $out */
    private function writeRows(Connection $db, string $table, $out): void
    {
        $pdo = $db->getPdo();
        $name = $db->getQueryGrammar()->wrapTable($table);
        $batch = [];

        foreach ($db->table($table)->cursor() as $row) {
            $values = array_map(fn ($v) => match (true) {
                $v === null => 'NULL',
                is_int($v), is_float($v) => (string) $v,
                default => $pdo->quote((string) $v),
            }, (array) $row);
            $batch[] = '('.implode(',', $values).')';

            if (count($batch) === self::CHUNK) {
                gzwrite($out, $this->insert($db, $name, $row, $batch));
                $batch = [];
            }
        }

        if ($batch) {
            gzwrite($out, $this->insert($db, $name, $row, $batch));
        }
    }

    private function insert(Connection $db, string $name, object $row, array $batch): string
    {
        $columns = implode(',', array_map(fn ($c) => $db->getQueryGrammar()->wrap($c), array_keys((array) $row)));

        return "INSERT INTO {$name} ({$columns}) VALUES\n".implode(",\n", $batch).";\n";
    }
}
