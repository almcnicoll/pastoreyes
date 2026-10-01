<?php

namespace Tests\Unit;

use App\Models\IgnoredSyncDifference;
use App\Models\User;
use Tests\TestCase;

class IgnoredSyncDifferenceTest extends TestCase
{
    public function test_hash_ignores_case_and_whitespace_but_not_value_changes(): void
    {
        $user = (new User)->forceFill(['encryption_salt' => 'salt-a']);

        $base = IgnoredSyncDifference::hashFor($user, 'first_name', 'Jon', 'John');

        $this->assertSame($base, IgnoredSyncDifference::hashFor($user, 'first_name', ' jon ', 'JOHN'));
        $this->assertNotSame($base, IgnoredSyncDifference::hashFor($user, 'first_name', 'Jon', 'Johnny'));
        $this->assertNotSame($base, IgnoredSyncDifference::hashFor($user, 'last_name', 'Jon', 'John'));
    }

    public function test_hash_is_keyed_per_user(): void
    {
        $a = (new User)->forceFill(['encryption_salt' => 'salt-a']);
        $b = (new User)->forceFill(['encryption_salt' => 'salt-b']);

        $this->assertNotSame(
            IgnoredSyncDifference::hashFor($a, 'birthday', '1 May', '2 May'),
            IgnoredSyncDifference::hashFor($b, 'birthday', '1 May', '2 May'),
        );
    }
}
