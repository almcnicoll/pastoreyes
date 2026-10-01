<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_guests_see_the_public_index_page(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_guests_are_redirected_from_the_app_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dev_login_does_not_exist_outside_the_local_environment(): void
    {
        $this->get('/dev-login')->assertNotFound();
    }
}
