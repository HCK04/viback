<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProfilesAuditCommand extends Command
{
    protected $signature = 'profiles:audit {--fix : Attempt safe normalization when parsing fails} {--limit=0 : Limit number of profiles per type (0 = no limit)} {--types= : Comma-separated types (medecin,kine,orthophoniste,psychologue)} {--format=table : Output format: table|json|log}';

    protected $description = 'Audit all professional profiles to ensure diplomas and experiences parse correctly; optional safe fixes';

    public function handle()
    {
        $typesOpt = (string)$this->option('types');
        $types = $typesOpt !== '' ? array_values(array_filter(array_map('trim', explode(',', $typesOpt)))) : ['medecin', 'kine', 'orthophoniste', 'psychologue'];
        $limit = (int)$this->option('limit');
        $doFix = (bool)$this->option('fix');
        $format = (string)$this->option('format');

        $map = [
            'medecin' => 'medecin_profiles',
            'kine' => 'kine_profiles',
            'orthophoniste' => 'orthophoniste_profiles',
            'psychologue' => 'psychologue_profiles',
        ];

        $report = [
            'summary' => [
                'total' => 0,
                'checked' => 0,
                'fail_diplomes' => 0,
                'fail_experiences' => 0,
                'fixed_diplomes' => 0,
                'fixed_experiences' => 0,
            ],
            'items' => [],
        ];

        foreach ($types as $type) {
            if (!isset($map[$type])) continue;
            $tbl = $map[$type];
            if (!Schema::hasTable($tbl)) {
                $this->warn("Table $tbl does not exist; skipping $type.");
                continue;
            }
            $hasDiplomes = Schema::hasColumn($tbl, 'diplomes');
            $hasDiplomas = Schema::hasColumn($tbl, 'diplomas');
            $hasExperiences = Schema::hasColumn($tbl, 'experiences');

            $query = DB::table($tbl)->join('users', "$tbl.user_id", '=', 'users.id')
                ->select("$tbl.*", 'users.id as user_id', 'users.name as user_name', 'users.role_id', 'users.email');
            if ($limit > 0) $query->limit($limit);

            $rows = $query->get();
            foreach ($rows as $row) {
                $report['summary']['total']++;
                $rawDiplomes = null;
                if ($hasDiplomes && isset($row->diplomes)) $rawDiplomes = $row->diplomes;
                elseif ($hasDiplomas && isset($row->diplomas)) $rawDiplomes = $row->diplomas;
                $rawExperiences = $hasExperiences ? ($row->experiences ?? null) : null;

                $parsedDiplomes = $this->parseObjectArrayLoose($rawDiplomes);
                $parsedExperiences = $this->parseObjectArrayLoose($rawExperiences);

                // Determine failures only if raw appears non-empty but parsing produced empty
                $rawDiplomesNonEmpty = $this->rawNonEmpty($rawDiplomes);
                $rawExperiencesNonEmpty = $this->rawNonEmpty($rawExperiences);

                $failDiplomes = $rawDiplomesNonEmpty && empty($parsedDiplomes);
                $failExperiences = $rawExperiencesNonEmpty && empty($parsedExperiences);

                $fixedD = false; $fixedE = false;

                if ($doFix && ($failDiplomes || $failExperiences)) {
                    // Build safe normalization from string-list to object-list when possible
                    if ($failDiplomes) {
                        $strings = $this->parseStringListLoose($rawDiplomes);
                        if (!empty($strings) && ($hasDiplomes || $hasDiplomas)) {
                            $objects = array_values(array_map(function ($s) { return ['nom' => $s]; }, $strings));
                            $col = $hasDiplomes ? 'diplomes' : 'diplomas';
                            DB::table($tbl)->where('id', $row->id)->update([$col => json_encode($objects, JSON_UNESCAPED_UNICODE)]);
                            $fixedD = true;
                            $parsedDiplomes = $objects;
                        }
                    }
                    if ($failExperiences && $hasExperiences) {
                        $strings = $this->parseStringListLoose($rawExperiences);
                        if (!empty($strings)) {
                            $objects = array_values(array_map(function ($s) { return ['description' => $s]; }, $strings));
                            DB::table($tbl)->where('id', $row->id)->update(['experiences' => json_encode($objects, JSON_UNESCAPED_UNICODE)]);
                            $fixedE = true;
                            $parsedExperiences = $objects;
                        }
                    }
                }

                $report['summary']['checked']++;
                if ($failDiplomes) $report['summary']['fail_diplomes']++;
                if ($failExperiences) $report['summary']['fail_experiences']++;
                if ($fixedD) $report['summary']['fixed_diplomes']++;
                if ($fixedE) $report['summary']['fixed_experiences']++;

                $report['items'][] = [
                    'type' => $type,
                    'table' => $tbl,
                    'profile_id' => $row->id,
                    'user_id' => $row->user_id,
                    'user_name' => $row->user_name,
                    'email' => $row->email,
                    'raw_diplomes_non_empty' => $rawDiplomesNonEmpty,
                    'raw_experiences_non_empty' => $rawExperiencesNonEmpty,
                    'diplomes_parse_ok' => !empty($parsedDiplomes) || !$rawDiplomesNonEmpty,
                    'experiences_parse_ok' => !empty($parsedExperiences) || !$rawExperiencesNonEmpty,
                    'fixed_diplomes' => $fixedD,
                    'fixed_experiences' => $fixedE,
                ];
            }
        }

        if ($format === 'json') {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } elseif ($format === 'log') {
            Log::info('profiles:audit report', $report);
            $this->info('Report logged to storage logs.');
        } else {
            // table
            $this->table(
                ['Type', 'User', 'Email', 'ProfileID', 'Diplomes', 'Experiences', 'FixedD', 'FixedE'],
                array_map(function ($it) {
                    return [
                        $it['type'],
                        $it['user_name'],
                        $it['email'],
                        (string)$it['profile_id'],
                        $it['diplomes_parse_ok'] ? 'OK' : 'FAIL',
                        $it['experiences_parse_ok'] ? 'OK' : 'FAIL',
                        $it['fixed_diplomes'] ? 'Y' : '',
                        $it['fixed_experiences'] ? 'Y' : '',
                    ];
                }, $report['items'])
            );
            $s = $report['summary'];
            $this->info("Checked: {$s['checked']} | Fail D: {$s['fail_diplomes']} | Fail E: {$s['fail_experiences']} | Fixed D: {$s['fixed_diplomes']} | Fixed E: {$s['fixed_experiences']}");
        }

        return Command::SUCCESS;
    }

    private function rawNonEmpty($raw): bool
    {
        if ($raw === null) return false;
        if (is_array($raw)) return count($raw) > 0;
        if (is_string($raw)) return trim($raw) !== '';
        return false;
    }

    private function parseStringListLoose($value): array
    {
        if ($value === null) return [];
        $cleanOne = function ($s) {
            $s = (string)$s;
            $s = trim($s);
            if (strlen($s) >= 2 && ((($s[0] === '"') && substr($s, -1) === '"') || (($s[0] === "'") && substr($s, -1) === "'"))) {
                $s = substr($s, 1, -1);
            }
            $s = str_replace(['\\r', '\\n'], ' ', $s);
            $s = str_replace(['\\"'], '"', $s);
            $s = str_replace(['\\\\'], '\\', $s);
            $s = str_replace(['\\/'], '/', $s);
            $s = rtrim($s, '\\');
            $s = trim($s, " \t\n\r\0\x0B\"'[]");
            if (preg_match('/\\\\u[0-9a-fA-F]{4}/', $s)) {
                $s = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
                    $code = hexdec($m[1]);
                    return mb_convert_encoding(pack('n', $code), 'UTF-8', 'UTF-16BE');
                }, $s);
            }
            return trim($s);
        };
        $flatten = function (array $list) use ($cleanOne) {
            $out = [];
            foreach ($list as $it) {
                $parts = preg_split('/\\\\\s*|\r\n|\r|\n/', (string)$it);
                foreach ($parts as $p) {
                    $p = $cleanOne($p);
                    if ($p !== '') $out[] = $p;
                }
            }
            return array_values(array_unique($out));
        };

        if (is_array($value)) {
            $arr = [];
            foreach ($value as $v) if (is_string($v)) $arr[] = $v; elseif (is_array($v)) foreach ($v as $vv) if (is_string($vv)) $arr[] = $vv;
            return $flatten($arr);
        }
        if (is_string($value)) {
            $s = trim($value);
            if ($s === '') return [];
            $j = json_decode($s, true);
            if (is_array($j)) return $flatten($j);
            if (is_string($j)) { $j2 = json_decode($j, true); if (is_array($j2)) return $flatten($j2); }
            $s2 = stripcslashes(str_replace(["\r", "\n"], ["\\r", "\\n"], $s));
            $j3 = json_decode($s2, true);
            if (is_array($j3)) return $flatten($j3);
            if (is_string($j3)) { $j4 = json_decode($j3, true); if (is_array($j4)) return $flatten($j4); }
            if (preg_match_all('/"(?:\\\\.|[^"\\\\])*"/u', $s, $m) && !empty($m[0])) {
                $items = [];
                foreach ($m[0] as $q) { $v = @json_decode($q, true); $items[] = $v !== null ? $cleanOne($v) : $cleanOne(trim($q, '"')); }
                $items = $flatten(array_values(array_filter($items, fn($v) => $v !== '')));
                if (!empty($items)) return $items;
            }
            if (strpos($s, "\n") !== false) $parts = preg_split('/\n+/', $s);
            elseif (strpos($s, ';') !== false) $parts = explode(';', $s);
            elseif (strpos($s, ',') !== false) $parts = explode(',', $s);
            else $parts = [$s];
            return $flatten($parts);
        }
        return [];
    }

    private function parseObjectArrayLoose($value)
    {
        if ($value === null) return [];

        if (is_array($value)) {
            $out = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    $out[] = $item;
                } elseif (is_string($item)) {
                    $d = json_decode($item, true);
                    if (is_array($d)) {
                        if (isset($d[0]) || empty($d)) {
                            foreach ($d as $el) if (is_array($el)) $out[] = $el;
                        } else {
                            $out[] = $d;
                        }
                    }
                }
            }
            return $out;
        }

        $tryDecode = function (string $s) {
            $j = json_decode($s, true);
            if (is_array($j)) {
                if (isset($j[0]) || empty($j)) {
                    $out = [];
                    foreach ($j as $el) if (is_array($el)) $out[] = $el;
                    return $out;
                }
                return [$j];
            }
            if (is_string($j)) {
                $j2 = json_decode($j, true);
                if (is_array($j2)) {
                    if (isset($j2[0]) || empty($j2)) {
                        $out = [];
                        foreach ($j2 as $el) if (is_array($el)) $out[] = $el;
                        return $out;
                    }
                    return [$j2];
                }
            }
            return null;
        };

        if (is_string($value)) {
            $s = trim((string) $value);
            if ($s === '') return [];

            $out = $tryDecode($s);
            if ($out !== null) return $out;

            $s2 = stripcslashes(str_replace(["\r", "\n"], ["\\r", "\\n"], $s));
            $out = $tryDecode($s2);
            if ($out !== null) return $out;

            $fix = $s2;
            $fix = preg_replace('/,\s*([}\]])/', '$1', $fix);
            $fix = preg_replace('/([\{\s,])([A-Za-z0-9_]+)\s*:/', '$1"$2":', $fix);
            $fix = preg_replace_callback("/'(?:\\'|[^'])*'/", function ($m) {
                $inner = substr($m[0], 1, -1);
                $inner = str_replace(['\\"', '"'], ['"', '\\"'], $inner);
                return '"' . $inner . '"';
            }, $fix);
            $out = $tryDecode($fix);
            if ($out !== null) return $out;

            if (preg_match_all('/\{[^\{\}]*\}/s', $fix, $m) && !empty($m[0])) {
                $arr = [];
                foreach ($m[0] as $obj) {
                    $obj2 = preg_replace('/,\s*}/', '}', $obj);
                    $obj2 = preg_replace('/([\{\s,])([A-Za-z0-9_]+)\s*:/', '$1"$2":', $obj2);
                    $obj2 = preg_replace_callback("/'(?:\\'|[^'])*'/", function ($mm) {
                        $inner = substr($mm[0], 1, -1);
                        $inner = str_replace(['\\"', '"'], ['"', '\\"'], $inner);
                        return '"' . $inner . '"';
                    }, $obj2);
                    $d = json_decode($obj2, true);
                    if (is_array($d)) $arr[] = $d;
                }
                if (!empty($arr)) return $arr;
            }
        }

        return [];
    }
}
