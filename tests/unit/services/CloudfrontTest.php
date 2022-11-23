<?php

namespace dyerc\fluxtests\unit\services;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;

use craft\base\Fs;
use craft\elements\Asset;
use craft\models\Volume;
use craft\test\TestCase;
use dyerc\flux\Flux;
use dyerc\flux\services\Cloudfront;
use dyerc\flux\services\S3;
use UnitTester;
use Craft;

class CloudfrontTest extends TestCase
{
    /**
     * @var UnitTester
     */
    protected $tester;

    public function testPurgingAssets()
    {
        $asset = $this->make(Asset::class, [
            'getVolume' => $this->make(Volume::class, [
                'getFs' => $this->make(Fs::class, [
                    'hasUrls' => true,
                ]),
                'getTransformFs' => $this->make(Fs::class, [
                    'hasUrls' => true,
                ]),
                'handle' => 'volume',
            ]),
            'folderId' => 2,
            'filename' => 'foo.jpg',
        ]);

        Flux::getInstance()->set('s3', $this->make(S3::class, [
            'listObjects' => Expected::once(function () {
                return [
                    'Flux/volume/foo.jpg',
                    'Flux/volume/bar.jpg',
                    'Flux/volume/_159x240_crop_center-center_80/foo.jpg',
                    'Flux/volume/_159x240_crop_center-center_80/bar.jpg',
                ];
            }),
            'deleteObjects' => Expected::once(function ($objects) {
                $this->assertSame([
                    "Flux/volume/foo.jpg",
                    "Flux/volume/_159x240_crop_center-center_80/foo.jpg"
                ], $objects);
            })
        ]));

        Flux::getInstance()->set('cloudfront', $this->make(Cloudfront::class, [
            'invalidateCache' => Expected::once(function ($paths) {
                $this->assertSame([
                    "/volume/foo.jpg*",
                    "/volume/_159x240_crop_center-center_80/foo.jpg*"
                ], $paths);
            })
        ]));

        Flux::getInstance()->cloudfront->purgeAsset($asset);
    }
}
