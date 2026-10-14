<?php

namespace Database\Seeders;

use App\Models\MasterProgram;
use Illuminate\Database\Seeder;

class MasterProgramSeeder extends Seeder
{
    /**
     * Seed master programs (PROGRAM code, nama program, periode) from the REALME program sheet.
     */
    public function run(): void
    {
        $programs = [
            ['PROGRAM 324', 'PROGRAM DEALER CLUB APRIL 2026', 'Periode 1-30 April 2026'],
            ['PROGRAM 325', 'PROGRAM DSA MEI 2026', 'Periode 1-31 Mei 2026'],
            ['PROGRAM 326', 'PROGRAM ST SPECIAL DEALER MEI 2026', 'Periode 1-31 Mei 2026'],
            ['PROGRAM 327', 'PROGRAM SO SUPPORT REALME STORE MEI 2026', 'Periode 1-31 Mei 2026'],
            ['PROGRAM 328', 'PROGRAM DEALER CLUB MEI 2026', 'Periode 1-31 Mei 2026'],
            ['PROGRAM 329', 'PROGRAM FS PO C100 & C100X MEI 2026', 'Periode 15-17 Maret 2026'],
            ['PROGRAM 330', 'PROGRAM FS PO C100I MEI 2026', 'Periode 29-31 Mei 2026'],
            ['PROGRAM 331', 'PROGRAM ST C100 & C100X MEI 2026', 'Periode 1-15 Mei 2026'],
            ['PROGRAM 332', 'PROGRAM DSA JUNI 2026', 'Periode 1-30 Juni 2026'],
            ['PROGRAM 333', 'PROGRAM ST Q2 REALME MEI-JUNI 2026', 'Periode 1 Mei-30 Juni 2026'],
            ['PROGRAM 334', 'PROGRAM ST SPECIAL DEALER JUNI 2026', 'Periode 1-30 Juni 2026'],
            ['PROGRAM 335', 'PROGRAM PROMOTION NOTE 80 4+128 JUNI 2026', 'Periode 13-30 Juni 2026'],
            ['PROGRAM 336', 'PROGRAM PROMOTION C100X SERIES JUNI 2026', 'Periode 5-30 Juni 2026'],
            ['PROGRAM 337', 'PROGRAM DEALER CLUB JUNI 2026', 'Periode 1-30 Juni 2026'],
            ['PROGRAM 338', 'PROGRAM SO REALME STORE JUNI 2026', 'Periode 1-30 Juni 2026'],
            ['PROGRAM 339', 'PROGRAM DSA JULI 2026', 'Periode 1-31 Juli 2026'],
            ['PROGRAM 340', 'PROGRAM DEALER CLUB JULI 2026', 'Periode 1-31 Juli 2026'],
            ['PROGRAM 341', 'PROGRAM SO REALME STORE JULI 2026', 'Periode 1-31 Maret 2026'],
            ['PROGRAM 342', 'PROGRAM DSA AGUSTUS 2026', 'Periode 1-31 Agustus 2026'],
            ['PROGRAM 343', 'PROGRAM DEALER CLUB C100I AGUSTUS 2026', 'Periode 1-31 Agustus 2026'],
        ];

        foreach ($programs as [$code, $name, $period]) {
            $program = MasterProgram::where('code', $code)->first()
                ?? MasterProgram::firstOrNew(['name' => $name]);

            $program->fill([
                'code' => $code,
                'name' => $name,
                'period' => $period,
                'is_active' => $program->exists ? $program->is_active : true,
            ])->save();
        }
    }
}
