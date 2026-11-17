<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class DatabaseBackup
{
    public function create()
    {
        $lock = Cache::lock('finance-database-backup', 600);
        if (!$lock->get()) throw ValidationException::withMessages(['backup' => 'Резервная копия уже создаётся. Повторите попытку позже.']);
        $connection = DB::connection();
        $path = null;
        $file = null;
        try {
            $driver = $connection->getDriverName();
            if (!in_array($driver, ['mysql', 'sqlite'])) throw new \RuntimeException();
            $directory = storage_path('app/private/backups');
            File::ensureDirectoryExists($directory);
            $path = $directory . DIRECTORY_SEPARATOR . 'finance_' . now()->format('Y-m-d_His') . '_' . bin2hex(random_bytes(4)) . '.sql';
            $file = fopen($path, 'xb');
            if (!$file) throw new \RuntimeException();
            $pdo = $connection->getPdo();
            if ($driver === 'mysql') $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $connection->beginTransaction();
            $this->write($file, $driver === 'mysql' ? "SET NAMES utf8mb4;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSET FOREIGN_KEY_CHECKS=0;\n" : "PRAGMA foreign_keys=OFF;\n");
            $this->write($file, "BEGIN;\n");
            $tables = $driver === 'mysql'
                ? $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(\PDO::FETCH_COLUMN)
                : $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $name = $this->identifier($table, $driver);
                if ($driver === 'mysql') {
                    $schema = $pdo->query('SHOW CREATE TABLE ' . $name)->fetch(\PDO::FETCH_NUM)[1];
                } else {
                    $statement = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name=?");
                    $statement->execute([$table]);
                    $schema = $statement->fetchColumn();
                }
                $this->write($file, 'DROP TABLE IF EXISTS ' . $name . ";\n" . $schema . ";\n");
                $rows = $pdo->query('SELECT * FROM ' . $name);
                while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
                    $columns = array_map(fn ($column) => $this->identifier($column, $driver), array_keys($row));
                    $values = array_map(function ($value) use ($pdo, $driver) {
                        if ($value === null) return 'NULL';
                        if (is_resource($value)) $value = stream_get_contents($value);
                        if ($driver === 'mysql' || (is_string($value) && str_contains($value, "\0"))) return "X'" . bin2hex((string) $value) . "'";
                        return $pdo->quote((string) $value);
                    }, array_values($row));
                    $this->write($file, 'INSERT INTO ' . $name . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n");
                }
                $rows->closeCursor();
            }
            if ($driver === 'sqlite') {
                foreach ($pdo->query("SELECT sql FROM sqlite_master WHERE type IN ('index', 'trigger', 'view') AND sql IS NOT NULL ORDER BY type, name") as $object) $this->write($file, $object['sql'] . ";\n");
                $sequence = $pdo->query("SELECT count(*) FROM sqlite_master WHERE name='sqlite_sequence'")->fetchColumn();
                if ($sequence) {
                    $this->write($file, "DELETE FROM sqlite_sequence;\n");
                    foreach ($pdo->query('SELECT name, seq FROM sqlite_sequence') as $row) $this->write($file, 'INSERT INTO sqlite_sequence VALUES (' . $pdo->quote($row['name']) . ', ' . (int) $row['seq'] . ");\n");
                }
            }
            $this->write($file, "COMMIT;\n" . ($driver === 'mysql' ? "SET FOREIGN_KEY_CHECKS=1;\n" : "PRAGMA foreign_keys=ON;\n"));
            $connection->commit();
            fclose($file);
            return $path;
        } catch (\Throwable $error) {
            if ($connection->transactionLevel()) $connection->rollBack();
            if (is_resource($file)) fclose($file);
            if ($path) File::delete($path);
            report($error);
            throw ValidationException::withMessages(['backup' => 'Не удалось создать резервную копию. Проверьте подключение к базе данных и доступное место.']);
        } finally {
            $lock->release();
        }
    }

    private function identifier($name, $driver)
    {
        $quote = $driver === 'mysql' ? '`' : '"';
        return $quote . str_replace($quote, $quote . $quote, $name) . $quote;
    }

    private function write($file, $text)
    {
        while ($text !== '') {
            $written = fwrite($file, $text);
            if (!$written) throw new \RuntimeException();
            $text = substr($text, $written);
        }
    }
}
