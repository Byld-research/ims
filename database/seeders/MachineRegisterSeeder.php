<?php

namespace Database\Seeders;

use App\Models\Machine;
use App\Models\MachineType;
use App\Models\Site;
use Illuminate\Database\Seeder;

/**
 * Machine types and the machines installed at US sites (SPEC 1a, 9). Real data, safe in every
 * environment. Machines in Poland are not registered; their serials are reserved in last_serial.
 */
class MachineRegisterSeeder extends Seeder
{
    /** letter => [type name, last serial issued as of October 2026] */
    public const TYPES = [
        'A' => ['Wall Machine 6"', 7],
        'B' => ['Strapping & Header Machine', 6],
        'S' => ['Strapping Machine', 0],
        'H' => ['Header Machine', 0],
        'C' => ['Truss Saw', 6],
        'D' => ['Wall Machine 3.5"', 1],
        'W' => ['Wall JIG', 9],
        'T' => ['Truss JIG', 0],
    ];

    /** [sku, proper name, revision, site code] */
    public const MACHINES = [
        ['003C', 'Truss Saw', '1.0', 'BPC001'],
        ['004C', 'Truss Saw', '2.0', 'BPC002'],
        ['003A', 'Wall Machine 6" Standard no HD punch', '1.0', 'BPC002'],
        ['004A', 'Wall Machine 6" Standard no HD punch', '1.0', 'BPC001'],
        ['005A', 'Wall Machine 6" Standard no HD punch', '1.0', 'BPC001'],
        ['004B', 'Header & Strapping Machine', '1.0', 'BPC001'],
        ['006W', 'Wall JIG', '1.0', 'BPC001'],
        ['007W', 'Wall JIG', '1.0', 'BPC001'],
        ['008W', 'Wall JIG', '1.0', 'BPC002'],
        ['009W', 'Wall JIG', '1.0', 'BPC002'],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $code => [$name, $lastSerial]) {
            $type = MachineType::query()->firstOrCreate(['code' => $code], ['name' => $name]);

            // Only ever raise the counter: serials are never reused.
            if ($type->last_serial < $lastSerial) {
                $type->update(['last_serial' => $lastSerial]);
            }
        }

        $types = MachineType::query()->pluck('id', 'code');
        $sites = Site::query()->pluck('id', 'code');

        foreach (self::MACHINES as [$sku, $name, $revision, $siteCode]) {
            Machine::query()->firstOrCreate(['sku' => $sku], [
                'machine_type_id' => $types[substr($sku, -1)],
                'name' => $name,
                'revision' => $revision,
                'site_id' => $sites[$siteCode],
            ]);
        }
    }
}
