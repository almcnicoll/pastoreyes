<?php

namespace Tests\Feature;

use App\Actions\ImportPersonFromGoogle;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImportKeyDatesTest extends TestCase
{
    use RefreshDatabase;

    private function importDates(array $rawData): Person
    {
        $user = (new User)->forceFill(['encryption_salt' => Str::random(64), 'first_name' => 'T', 'last_name' => 'U',
            'email' => Str::random(8) . '@example.test', 'google_oauth_id' => Str::random(12), 'is_active' => true]);
        $user->save();
        $this->actingAs($user);
        $person = Person::create(['user_id' => $user->id]);

        $import = new ImportPersonFromGoogle;
        (new \ReflectionMethod($import, 'importKeyDates'))->invoke($import, $person, ['rawData' => $rawData]);

        return $person;
    }

    private const DATE = ['year' => 1980, 'month' => 3, 'day' => 14];

    public function test_google_birthday_field_becomes_a_birthday_key_date(): void
    {
        $person = $this->importDates(['birthdays' => [['date' => self::DATE]]]);

        $this->assertSame(['birthday'], $person->keyDates()->pluck('type')->all());
    }

    public function test_a_custom_date_labelled_birthday_becomes_a_birthday_not_other(): void
    {
        $person = $this->importDates(['events' => [['type' => 'birthday', 'formattedType' => 'Birthday', 'date' => self::DATE]]]);

        $this->assertSame(['birthday'], $person->keyDates()->pluck('type')->all());
    }

    public function test_birthday_field_and_birthday_labelled_date_do_not_duplicate(): void
    {
        $person = $this->importDates([
            'birthdays' => [['date' => self::DATE]],
            'events'    => [['type' => 'birthday', 'formattedType' => 'Birthday', 'date' => self::DATE]],
        ]);

        $this->assertSame(1, $person->keyDates()->count());
    }

    public function test_other_dates_stay_other_and_anniversaries_are_recognised(): void
    {
        $person = $this->importDates(['events' => [
            ['type' => 'anniversary', 'formattedType' => 'Anniversary', 'date' => self::DATE],
            ['type' => 'other', 'formattedType' => 'Other', 'date' => self::DATE],
        ]]);

        $this->assertEqualsCanonicalizing(['wedding_anniversary', 'other'], $person->keyDates()->pluck('type')->all());
    }
}
