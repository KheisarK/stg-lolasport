<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UpdatePrototypeTariffs extends Command
{
    protected $signature = 'lola:rates-v2 {--apply : Apply after preview and confirmation} {--court=* : Reviewed court IDs to update}';

    protected $description = 'Preview prototype v2 tariffs; preserve custom rates and booking snapshots';

    public function handle(): int
    {
        $selected = $this->option('court');
        foreach ($selected as $id) {
            if (! ctype_digit($id) || (int) $id < 1) {
                $this->error('Gunakan ID lapangan positif pada --court.');

                return self::FAILURE;
            }
        }
        $selected = array_map('intval', $selected);
        $courts = DB::table('courts')->orderBy('id')->get();
        if (array_diff($selected, $courts->pluck('id')->all())) {
            $this->error('Ada ID lapangan yang tidak ditemukan. Tidak ada tarif diubah.');

            return self::FAILURE;
        }
        $rows = [];
        $plan = [];
        foreach ($courts as $court) {
            $target = config('lola_tariffs.'.$court->name);
            $eligible = $target && $court->sport === $target['sport'] && (int) $court->hourly_rate === $target['previous'];
            $chosen = ! $selected || in_array($court->id, $selected);
            $state = $target && $court->sport === $target['sport'] && (int) $court->hourly_rate === $target['hourly_rate']
                ? 'Sudah sesuai'
                : ($eligible ? ($chosen ? 'Dapat diperbarui' : 'Tidak dipilih') : 'Dilewati: tarif/nama/jenis khusus');
            $rows[] = [$court->id, $court->name, $court->hourly_rate, $target['hourly_rate'] ?? '-', $state];
            if ($eligible && $chosen) {
                $plan[] = ['id' => $court->id, 'name' => $court->name, 'sport' => $court->sport, 'old' => (int) $court->hourly_rate, 'new' => $target['hourly_rate']];
            }
        }
        $this->warn('Tarif prototipe v2, perlu ditinjau pengelola. Nilai dalam rupiah per jam.');
        $this->table(['ID', 'Lapangan', 'Sekarang', 'Usulan v2', 'Tindakan'], $rows);
        if (! $this->option('apply')) {
            $this->info('Preview saja. Untuk menerapkan, pilih ID yang sudah ditinjau: --apply --court=ID. Tarif khusus dan booking lama tidak diubah.');

            return self::SUCCESS;
        }
        if (! $selected) {
            $this->error('--apply memerlukan --court=ID yang telah ditinjau. Tidak ada tarif diubah.');

            return self::FAILURE;
        }
        if (! $plan) {
            $this->info('Tidak ada tarif lama yang cocok untuk diperbarui. Tarif khusus tetap dipertahankan.');

            return self::SUCCESS;
        }
        if (! $this->confirm('Terapkan hanya tarif lama yang cocok pada ID terpilih?', false)) {
            $this->info('Dibatalkan. Tidak ada tarif diubah.');

            return self::SUCCESS;
        }
        try {
            DB::transaction(function () use ($plan) {
                foreach ($plan as $item) {
                    $changed = DB::table('courts')->where('id', $item['id'])->where('name', $item['name'])->where('sport', $item['sport'])->where('hourly_rate', $item['old'])->update(['hourly_rate' => $item['new']]);
                    if ($changed !== 1) {
                        throw new RuntimeException('Tarif berubah setelah preview. Seluruh penerapan dibatalkan; ulangi preview.');
                    }
                }
            }, 3);
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
        $this->info(count($plan).' tarif diperbarui. Snapshot booking dan catatan pembayaran tetap utuh.');

        return self::SUCCESS;
    }
}
