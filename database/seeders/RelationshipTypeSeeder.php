<?php

namespace Database\Seeders;

use App\Models\RelationshipType;
use Illuminate\Database\Seeder;

class RelationshipTypeSeeder extends Seeder
{
    /**
     * Seed global preset relationship types.
     * These have user_id = null and are available to all users.
     * Users can add their own custom types on top of these.
     */
    public function run(): void
    {
        $presets = [

            // Family — directional
            ['name' => 'parent',       'inverse_name' => 'child',       'is_directional' => true],
            ['name' => 'father',       'inverse_name' => 'child',       'is_directional' => true],
            ['name' => 'mother',       'inverse_name' => 'child',       'is_directional' => true],
            ['name' => 'son',          'inverse_name' => 'parent',      'is_directional' => true],
            ['name' => 'daughter',     'inverse_name' => 'parent',      'is_directional' => true],
            ['name' => 'grandparent',  'inverse_name' => 'grandchild',  'is_directional' => true],
            ['name' => 'grandchild',   'inverse_name' => 'grandparent', 'is_directional' => true],
            ['name' => 'step-parent',  'inverse_name' => 'step-child',  'is_directional' => true],
            ['name' => 'step-child',   'inverse_name' => 'step-parent', 'is_directional' => true],
            ['name' => 'foster parent','inverse_name' => 'foster child','is_directional' => true],
            ['name' => 'foster child', 'inverse_name' => 'foster parent','is_directional' => true],
            ['name' => 'guardian',     'inverse_name' => 'ward',        'is_directional' => true],

            // Family — non-directional
            ['name' => 'spouse',       'inverse_name' => null,          'is_directional' => false],
            ['name' => 'partner',      'inverse_name' => null,          'is_directional' => false],
            ['name' => 'sibling',      'inverse_name' => null,          'is_directional' => false],
            ['name' => 'brother',      'inverse_name' => null,          'is_directional' => false],
            ['name' => 'sister',       'inverse_name' => null,          'is_directional' => false],
            ['name' => 'twin',         'inverse_name' => null,          'is_directional' => false],
            ['name' => 'aunt / uncle', 'inverse_name' => null,          'is_directional' => false],
            ['name' => 'niece / nephew','inverse_name' => null,         'is_directional' => false],
            ['name' => 'cousin',       'inverse_name' => null,          'is_directional' => false],
            ['name' => 'in-law',       'inverse_name' => null,          'is_directional' => false],
            ['name' => 'ex-spouse',    'inverse_name' => null,          'is_directional' => false],

            // Pastoral / ministry — directional
            ['name' => 'mentor',       'inverse_name' => 'mentee',      'is_directional' => true],
            ['name' => 'pastor',       'inverse_name' => 'parishioner', 'is_directional' => true],
            ['name' => 'small group leader','inverse_name' => 'small group member','is_directional' => true],
            ['name' => 'discipler',    'inverse_name' => 'disciple',    'is_directional' => true],
            ['name' => 'counsellor',   'inverse_name' => 'counsellee',  'is_directional' => true],

            // Social — non-directional
            ['name' => 'friend',       'inverse_name' => null,          'is_directional' => false],
            ['name' => 'colleague',    'inverse_name' => null,          'is_directional' => false],
            ['name' => 'neighbour',    'inverse_name' => null,          'is_directional' => false],
            ['name' => 'housemate',    'inverse_name' => null,          'is_directional' => false],

            // Other — directional
            ['name' => 'employer',     'inverse_name' => 'employee',    'is_directional' => true],
            ['name' => 'teacher',      'inverse_name' => 'student',     'is_directional' => true],
            ['name' => 'carer',        'inverse_name' => 'care recipient','is_directional' => true],
        ];

        foreach ($presets as $preset) {
            RelationshipType::firstOrCreate(
                [
                    'user_id' => null,
                    'name'    => $preset['name'],
                ],
                [
                    'inverse_name'   => $preset['inverse_name'],
                    'is_directional' => $preset['is_directional'],
                    'is_preset'      => true,
                ]
            );
        }
    }
}
