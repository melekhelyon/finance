<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'finance:backup';

    protected $description = 'Создать резервную копию базы данных';

    public function handle(DatabaseBackup $backup): int
    {
        try {
            $this->info('Резервная копия сохранена: ' . $backup->create());
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error('Не удалось создать резервную копию базы данных.');
            return self::FAILURE;
        }
    }
}
