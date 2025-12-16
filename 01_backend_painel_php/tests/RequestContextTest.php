<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RedeAlabama\Support\RequestContext;



final class RequestContextTest extends TestCase
{
    public function testRequestIdIsStableDuringRequest(): void
    {
        RequestContext::init();
        $id1 = RequestContext::id();
        $id2 = RequestContext::id();

        $this->assertNotNull($id1);
        $this->assertSame($id1, $id2);
    }
}
