<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupRun extends Command
{
    protected $signature   = 'barberia:respaldo {--keep=30 : Cuántos respaldos conservar}';
    protected $description = 'Respaldo cifrado de la base de datos y de los comprobantes SINPE';

    public function handle(): int
    {
        $dir  = config('barberia.backup_dir');
        $pass = (string) config('barberia.backup_password');

        if (! $dir)                             return $this->stop('Falta BACKUP_DIR en el .env.');
        if (strlen($pass) < 12)                 return $this->stop('BACKUP_PASSWORD debe tener al menos 12 caracteres.');
        if (! class_exists(ZipArchive::class))  return $this->stop('Falta la extensión zip de PHP.');
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) return $this->stop("No se pudo crear la carpeta {$dir}.");

        $stamp = now()->format('Y-m-d_His');
        $sql   = storage_path("app/respaldo-{$stamp}.sql");
        $zip   = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . "barberia-{$stamp}.zip";

        // 1) Base de datos (la clave viaja por variable de entorno, no por la línea de comandos)
        $db = config('database.connections.' . config('database.default'));

        $args = array_merge(
            [config('barberia.mysqldump') ?: 'mysqldump',
             '--host=' . $db['host'], '--port=' . $db['port'], '--user=' . $db['username'],
             '--single-transaction', '--default-character-set=utf8mb4'],
            config('barberia.dump_no_tablespaces') ? ['--no-tablespaces'] : [],
            ['--result-file=' . $sql, $db['database']]
        );

        $p = new Process($args, base_path(), ['MYSQL_PWD' => (string) ($db['password'] ?? '')]);
        $p->setTimeout(300);
        $p->run();

        if (! $p->isSuccessful() || ! is_file($sql) || filesize($sql) < 100) {
            @unlink($sql);
            return $this->stop('mysqldump falló: ' . trim($p->getErrorOutput()));
        }

        // 2) Zip cifrado con la base y las fotos
        $z = new ZipArchive();
        if ($z->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($sql);
            return $this->stop('No se pudo crear el zip.');
        }

        $z->addFile($sql, 'base-de-datos.sql');
        $z->setEncryptionName('base-de-datos.sql', ZipArchive::EM_AES_256, $pass);

        $photos = 0;
        $root   = Storage::disk('local')->path('proofs');

        if (is_dir($root)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (! $file->isFile()) continue;

                $name = 'comprobantes/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $z->addFile($file->getPathname(), $name);
                $z->setEncryptionName($name, ZipArchive::EM_AES_256, $pass);
                $photos++;
            }
        }

        $ok = $z->close();
        @unlink($sql);
        if (! $ok) return $this->stop('No se pudo terminar el zip.');

        // 3) Solo se conservan los más recientes
        $files = glob(rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . 'barberia-*.zip') ?: [];
        rsort($files);
        foreach (array_slice($files, max(1, (int) $this->option('keep'))) as $old) {
            @unlink($old);
        }

        $this->info('Respaldo listo: ' . $zip . ' (' . round(filesize($zip) / 1048576, 1) . ' MB, ' . $photos . ' fotos)');

        return self::SUCCESS;
    }

    private function stop(string $msg): int
    {
        $this->error($msg);
        return self::FAILURE;
    }
}