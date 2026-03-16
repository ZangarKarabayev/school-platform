<?php

namespace App\Console\Commands;

use App\Modules\Organizations\Models\City;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportKatoCommand extends Command
{
    protected $signature = 'org:import-kato
        {--ru=storage/app/private/KATO_18.02.2026_ru.csv : Path to Russian KATO CSV}
        {--kk=storage/app/private/KATO_18.02.2026_kz.csv : Path to Kazakh KATO CSV}
        {--fresh : Clear organization dictionaries before import}';

    protected $description = 'Import KATO regions, districts, and cities from RU/KK CSV files';

    public function handle(): int
    {
        $ruPath = base_path((string) $this->option('ru'));
        $kkPath = base_path((string) $this->option('kk'));

        if (! is_file($ruPath)) {
            $this->components->error("RU file not found: {$ruPath}");

            return self::FAILURE;
        }

        if (! is_file($kkPath)) {
            $this->components->error("KK file not found: {$kkPath}");

            return self::FAILURE;
        }

        $ruRows = $this->readCsv($ruPath);
        $kkRows = $this->readCsv($kkPath);

        if ($ruRows === [] || $kkRows === []) {
            $this->components->error('One of the CSV files is empty or invalid.');

            return self::FAILURE;
        }

        $rowsByCode = $this->mergeRows($ruRows, $kkRows);
        $rowsById = [];

        foreach ($rowsByCode as $row) {
            $rowsById[$row['id']] = $row;
        }

        $regionRows = [];
        $districtRows = [];
        $districtCandidateIds = [];
        $cityRows = [];

        foreach ($rowsByCode as $row) {
            if ($row['parent_id'] === '0') {
                $regionRows[$row['code']] = $row;
                continue;
            }

            $parent = $rowsById[$row['parent_id']] ?? null;

            if ($parent !== null && $parent['parent_id'] === '0') {
                $districtRows[$row['code']] = $row;
                $districtCandidateIds[$row['id']] = $row['code'];
            }
        }

        foreach ($rowsByCode as $row) {
            if (! $this->isCityRow($row)) {
                continue;
            }

            $districtCode = $this->findNearestDistrictCode($row, $rowsById, $districtCandidateIds);

            if ($districtCode === null) {
                continue;
            }

            $cityRows[$row['code']] = $row + ['district_code' => $districtCode];
        }

        DB::transaction(function () use ($regionRows, $districtRows, $cityRows, $rowsById): void {
            if ($this->option('fresh')) {
                City::query()->delete();
                District::query()->delete();
                Region::query()->delete();
            }

            $now = now();

            Region::query()->upsert(
                array_values(array_map(fn (array $row): array => [
                    'code' => $row['code'],
                    'name' => $row['name_ru'],
                    'name_ru' => $row['name_ru'],
                    'name_kk' => $row['name_kk'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $regionRows)),
                ['code'],
                ['name', 'name_ru', 'name_kk', 'updated_at']
            );

            $regions = Region::query()->get()->keyBy('code');

            $districtPayload = [];
            foreach ($districtRows as $row) {
                $parent = $rowsById[$row['parent_id']] ?? null;
                $region = $parent !== null ? $regions->get($parent['code']) : null;

                if ($region === null) {
                    continue;
                }

                $districtPayload[] = [
                    'region_id' => $region->id,
                    'code' => $row['code'],
                    'name' => $row['name_ru'],
                    'name_ru' => $row['name_ru'],
                    'name_kk' => $row['name_kk'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            District::query()->upsert(
                $districtPayload,
                ['code'],
                ['region_id', 'name', 'name_ru', 'name_kk', 'updated_at']
            );

            $districts = District::query()->get()->keyBy('code');

            $cityPayload = [];
            foreach ($cityRows as $row) {
                $district = $districts->get($row['district_code']);

                if ($district === null) {
                    continue;
                }

                $cityPayload[] = [
                    'district_id' => $district->id,
                    'code' => $row['code'],
                    'name' => $row['name_ru'],
                    'name_ru' => $row['name_ru'],
                    'name_kk' => $row['name_kk'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            City::query()->upsert(
                $cityPayload,
                ['code'],
                ['district_id', 'name', 'name_ru', 'name_kk', 'updated_at']
            );
        });

        $this->components->info('KATO import completed.');
        $this->table(
            ['Entity', 'Count'],
            [
                ['Regions', Region::query()->count()],
                ['Districts', District::query()->count()],
                ['Cities', City::query()->count()],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{id:string,parent_id:string,code:string,name:string}>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle, separator: ';');

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = array_map(static function ($value): string {
            $value = (string) $value;

            return trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $value));
        }, $header);

        if ($header !== ['id', 'parent_id', 'code', 'name']) {
            fclose($handle);

            throw new RuntimeException("Unexpected CSV header in {$path}");
        }

        $rows = [];

        while (($data = fgetcsv($handle, separator: ';')) !== false) {
            if (count($data) < 4) {
                continue;
            }

            $rows[] = [
                'id' => trim((string) $data[0]),
                'parent_id' => trim((string) $data[1]),
                'code' => trim((string) $data[2]),
                'name' => trim((string) $data[3]),
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param array<int, array{id:string,parent_id:string,code:string,name:string}> $ruRows
     * @param array<int, array{id:string,parent_id:string,code:string,name:string}> $kkRows
     * @return array<string, array{id:string,parent_id:string,code:string,name_ru:string,name_kk:string}>
     */
    private function mergeRows(array $ruRows, array $kkRows): array
    {
        $kkByCode = [];

        foreach ($kkRows as $row) {
            $kkByCode[$row['code']] = $row;
        }

        $merged = [];

        foreach ($ruRows as $row) {
            $kk = $kkByCode[$row['code']] ?? null;

            if ($kk === null) {
                continue;
            }

            if ($row['id'] !== $kk['id'] || $row['parent_id'] !== $kk['parent_id']) {
                throw new RuntimeException("CSV structure mismatch for code {$row['code']}");
            }

            $merged[$row['code']] = [
                'id' => $row['id'],
                'parent_id' => $row['parent_id'],
                'code' => $row['code'],
                'name_ru' => $row['name'],
                'name_kk' => $kk['name'],
            ];
        }

        return $merged;
    }

    /**
     * @param array{id:string,parent_id:string,code:string,name_ru:string,name_kk:string} $row
     */
    private function isCityRow(array $row): bool
    {
        return preg_match('/^\x{0433}\./u', $row['name_ru']) === 1
            || preg_match('/\x{049B}\.$/u', $row['name_kk']) === 1;
    }

    /**
     * @param array{id:string,parent_id:string,code:string,name_ru:string,name_kk:string} $row
     * @param array<string, array{id:string,parent_id:string,code:string,name_ru:string,name_kk:string}> $rowsById
     * @param array<string, string> $districtCandidateIds
     */
    private function findNearestDistrictCode(array $row, array $rowsById, array $districtCandidateIds): ?string
    {
        $parentId = $row['parent_id'];

        while ($parentId !== '0') {
            if (isset($districtCandidateIds[$parentId])) {
                return $districtCandidateIds[$parentId];
            }

            $parent = $rowsById[$parentId] ?? null;

            if ($parent === null) {
                return null;
            }

            $parentId = $parent['parent_id'];
        }

        return null;
    }
}