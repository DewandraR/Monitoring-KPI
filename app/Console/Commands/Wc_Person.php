<?php

namespace App\Console\Commands;

use Symfony\Component\Process\Process as SymfonyProcess;
use Illuminate\Console\Command;

class Wc_Person extends Command
{
    /**
     * Waktu timeout dalam detik (6 jam * 3600 detik/jam).
     */
    private const TIMEOUT_SECONDS = 21600;

    protected $signature = 'Sync:wc_person';
    protected $description = 'Menjalankan proses sinkronisasi data Wc Person dari SAP ke database lokal dengan output real-time';

    public function handle(): int
    {
        $apiPath = base_path('wc_person_to_mysql.py');
        if (!file_exists($apiPath)) {
            $this->error("api.py tidak ditemukan di: {$apiPath}");
            return self::FAILURE;
        }

        $python = $this->detectPython();
        $this->info("Python: {$python}");

        $cmd = [$python, $apiPath];
        $process = new SymfonyProcess($cmd, base_path());

        // ðŸš€ Set timeout menjadi 6 jam (21600 detik)
        $process->setTimeout(self::TIMEOUT_SECONDS);
        $this->comment('Process timeout set to ' . (self::TIMEOUT_SECONDS / 3600) . ' hours.');


        $process->run(function ($type, $buffer) {
            // Menampilkan output dari script Python secara real-time
            $this->output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            // Jika proses gagal karena timeout, isSuccessful() akan false, 
            // dan kita bisa mengetahui detailnya.
            if ($process->getExitCode() === SymfonyProcess::EXIT_TIMEOUT) {
                $this->error('Sync failed: The process exceeded the allocated timeout of ' . self::TIMEOUT_SECONDS . ' seconds.');
            } else {
                $this->error('Sync failed with exit code ' . $process->getExitCode());
                $this->error('Error Output: ' . $process->getErrorOutput());
            }
            return self::FAILURE;
        }

        $this->info('Sync done');
        return self::SUCCESS;
    }

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
            if (preg_match('/[\\\\\\/]/', $bin)) {
                if (file_exists($bin)) return $bin;
            } else {
                try {
                    $p = new SymfonyProcess([$bin, '--version']);
                    // Timeout deteksi Python tetap 5 detik, ini aman.
                    $p->setTimeout(5);
                    $p->run();
                    if ($p->isSuccessful()) return $bin;
                } catch (\Throwable $e) {
                }
            }
        }
        return 'python';
    }
}
