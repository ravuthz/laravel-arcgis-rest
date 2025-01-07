<?php

namespace Ravuthz\ArcgisRest\Tests;

use Ravuthz\ArcgisRest\ArcgisRestServiceProvider;

use Illuminate\Foundation\Application;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    use CreatesApplication;

    /**
     * Set up
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Define environment setup.
     *
     * @param  Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Alter the testing timezone to America/Los_Angeles
        // $app['config']->set('simpletdd.timezone', 'America/Los_Angeles');
    }

    /**
     * @param Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [ArcgisRestServiceProvider::class];
        // return ['Ravuthz\ArcgisRest\ArcgisRestServiceProvider'];
    }
}
