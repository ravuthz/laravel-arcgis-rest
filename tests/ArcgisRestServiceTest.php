<?php

namespace Ravuthz\ArcgisRest\Tests;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Ravuthz\ArcgisRest\ArcgisRestService;

class ArcgisRestServiceTest extends TestCase
{
    protected static $arcgisRest = null;

    private function service(): ArcgisRestService
    {
        $this->app['config']->set('layers', []);
        $this->app['config']->set('cache.default', 'array');

        $service = new ArcgisRestService(0);

        if (!$service->getToken()) {
            $service->generateToken(
                env('ARCGIS_USERNAME'),
                env('ARCGIS_PASSWORD'),
                env('ARCGIS_URL')
            );
        }

        return $service;
    }

    public function test_generate_token()
    {
        $res = $this->service()->getToken();
        $this->assertNotNull($res);
    }

    public function test_save_features()
    {
        $arcgisRest = $this->service();

        $created = $arcgisRest->createFeatures([
            [
                'attributes' => [
                    'name' => 'Test Create Features',
                ],
                'geometry' => [
                    'x' => 1,
                    'y' => 2,
                ],
            ],
        ])['addResults'];

        $this->assertTrue($created[0]['success']);

        $updated = $arcgisRest->updateFeatures([
            [
                'attributes' => [
                    'name' => 'Test Update Features 1 at' . now()->timestamp,
                    ...$created[0]
                ],
                'geometry' => [
                    'x' => 1,
                    'y' => 3,
                ],
            ],
            [
                'attributes' => [
                    'name' => 'Test Update Features 2 at' . now()->timestamp,
                    'id_card' => '1111',
                    'year_divorce' => '99999999999999',
                    'objectid' => 1613007,
                ],
                'geometry' => [
                    'x' => 1,
                    'y' => 2,
                ],
            ],
        ])['updateResults'];

        $this->assertTrue($updated[0]['success']);

        $this->assertFalse($updated[1]['success']);

        $this->assertEquals([
            'code' => 1016,
            'description' => 'Setting of value for year_divorce failed.',
        ], $updated[1]['error']);

        $this->service()->deleteFeatures([
            'objectIds' => [
                $updated[0]['objectId']
            ]
        ]);
    }

    public function test_fetch_features()
    {
        $arcgisRest = $this->service();

        $res1 = $arcgisRest->fetchFeature(1613007);

        $res2 = $arcgisRest->fetchFeatures([
            'f' => 'json',
            'where' => '1=1',
            'outFields' => '*',
        ]);

        $this->assertNotNull($res1);

        $this->assertNotNull($res2);

        $this->assertThrows(
            fn() => $arcgisRest->fetchFeature(161300799),
            NotFoundHttpException::class
        );
    }
}
