<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process as SymfonyProcess;

class Yppr058_sync_yesterday extends Command
{
    /**
     * Waktu timeout dalam detik (6 jam * 3600 detik/jam).
     */
    private const TIMEOUT_SECONDS = 21600;

    /**
     * The name and signature of the console command.
     *
     * Kamu bisa panggil:
     *   php artisan app:yppr058_sync_yesterday
     */
    protected $signature = 'app:yppr058_yesterday';

    /**
     * The console command description.
     */
    protected $description = 'Sync Yppr058 data dari SAP hanya untuk tanggal kemarin (begda=endda=yesterday).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $apiPath = base_path('yppr058_loader.py');

        if (!file_exists($apiPath)) {
            $this->error("yppr058_loader.py tidak ditemukan di: {$apiPath}");
            return self::FAILURE;
        }

        $python = $this->detectPython();
        $this->info("Python: {$python}");

        // Tambahan argumen --yesterday
        $cmd = [$python, $apiPath, '--yesterday'];

        $process = new SymfonyProcess($cmd, base_path());

        // Set timeout 6 jam (21600 detik)
        $process->setTimeout(self::TIMEOUT_SECONDS);
        $this->comment('Process timeout set to ' . (self::TIMEOUT_SECONDS / 3600) . ' hours.');

        // Jalankan dan stream output python ke console Laravel
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            // Kalau gagal karena timeout
            if ($process->getExitCode() === SymfonyProcess::EXIT_TIMEOUT) {
                $this->error(
                    'Sync failed: The process exceeded the allocated timeout of ' .
                    self::TIMEOUT_SECONDS . ' seconds.'
                );
            } else {
                $this->error('Sync failed with exit code ' . $process->getExitCode());
                $this->error('Error Output: ' . $process->getErrorOutput());
            }

            return self::FAILURE;
        }

        $this->info('Sync (yesterday) done');
        return self::SUCCESS;
    }

    /**
     * Deteksi binary Python yang akan dipakai (mirip command utama).
     */
    private function detectPython(): string
    {
        $candidates = [
            base_path('venv\\Scripts\\python.exe'),
            base_path('venv\\Scripts\\python'),
            base_path('venv/bin/python'),
            'py',
            'python',
            'python3',
        ];

        foreach ($candidates as $bin) {
            // Kalau path mengandung slash/backslash → cek file_exists
            if (preg_match('/[\\\\\\/]/', $bin)) {
                if (file_exists($bin)) {
                    return $bin;
                }
            } else {
                // Kalau nama command saja → tes dengan `--version`
                try {
                    $p = new SymfonyProcess([$bin, '--version']);
                    $p->setTimeout(5);
                    $p->run();

                    if ($p->isSuccessful()) {
                        return $bin;
                    }
                } catch (\Throwable $e) {
                    // abaikan dan coba kandidat berikutnya
                }
            }
        }

        // fallback
        return 'python';
    }
}
