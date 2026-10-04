<?php

use App\Libraries\RangeFileResponse;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class RangeFileResponseTest extends CIUnitTestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = tempnam(sys_get_temp_dir(), 'mosaico-range-');
        file_put_contents($this->path, '0123456789');
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
        parent::tearDown();
    }

    public function testStreamsFileWithoutLoadingItIntoResponseBody(): void
    {
        $response = new RangeFileResponse($this->path, 'video/mp4', 'video.mp4');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('bytes', $response->getHeaderLine('Accept-Ranges'));
        $this->assertSame('10', $response->getHeaderLine('Content-Length'));
        ob_start();
        $response->sendBody();
        $this->assertSame('0123456789', ob_get_clean());
    }

    public function testStreamsRequestedByteRange(): void
    {
        $response = new RangeFileResponse($this->path, 'video/mp4', 'video.mp4', 'bytes=2-5');

        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('bytes 2-5/10', $response->getHeaderLine('Content-Range'));
        $this->assertSame('4', $response->getHeaderLine('Content-Length'));
        ob_start();
        $response->sendBody();
        $this->assertSame('2345', ob_get_clean());
    }
}
