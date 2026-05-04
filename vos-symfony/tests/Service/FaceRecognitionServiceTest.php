<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Service\FaceRecognitionService;

class FaceRecognitionServiceTest extends TestCase
{
    private FaceRecognitionService $svc;

    protected function setUp(): void
    {
        $this->svc = new FaceRecognitionService();
    }

    public function testIsValidDescriptor(): void
    {
        $this->assertFalse($this->svc->isValidDescriptor(null));
        $this->assertFalse($this->svc->isValidDescriptor([1,2,3]));
        $arr = array_fill(0, 128, 0.123);
        $this->assertTrue($this->svc->isValidDescriptor($arr));
    }

    public function testSerializeDeserialize(): void
    {
        $arr = array_map(fn($i) => $i * 0.1, range(0, 127));
        $json = $this->svc->serializeDescriptor($arr);
        $this->assertIsString($json);
        $decoded = $this->svc->deserializeDescriptor($json);
        $this->assertIsArray($decoded);
        $this->assertCount(128, $decoded);
        $this->assertSame((float)$arr[0], $decoded[0]);
    }

    public function testMatches(): void
    {
        $arr = array_fill(0, 128, 0.5);
        $json = $this->svc->serializeDescriptor($arr);
        $this->assertTrue($this->svc->matches($json, $arr));
        $arr2 = $arr;
        $arr2[0] = 10.0;
        $this->assertFalse($this->svc->matches($json, $arr2));
    }

    public function testEuclideanDistanceThreshold(): void
    {
        $a = array_fill(0, 128, 0.0);
        $b = array_fill(0, 128, 0.1);
        $json = $this->svc->serializeDescriptor($a);
        $this->assertTrue($this->svc->matches($json, $b, 2.0));
        $this->assertFalse($this->svc->matches($json, $b, 0.01));
    }
}
