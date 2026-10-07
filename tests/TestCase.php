<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Las vistas cargan los componentes de React con @vite; en los tests no hace falta compilarlos.
        $this->withoutVite();
    }
}
