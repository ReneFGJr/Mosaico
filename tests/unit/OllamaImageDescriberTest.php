<?php

use App\Libraries\OllamaImageDescriber;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class OllamaImageDescriberTest extends CIUnitTestCase
{
    private string $directory;
    private string $key;
    private string $writablePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writablePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mosaico-ollama-' . bin2hex(random_bytes(6)) . DIRECTORY_SEPARATOR;
        $this->key = 'repository/image.png';
        $this->directory = dirname($this->writablePath . $this->key);
        mkdir($this->directory, 0750, true);
        $image = imagecreatetruecolor(3200, 1600);
        imagepng($image, $this->writablePath . $this->key);
        imagedestroy($image);
    }

    protected function tearDown(): void
    {
        unlink($this->writablePath . $this->key);
        rmdir($this->directory);
        rmdir($this->writablePath);
        parent::tearDown();
    }

    public function testSendsImageBytesAndParsesDescription(): void
    {
        $client = $this->getMockBuilder(CURLRequest::class)->disableOriginalConstructor()->onlyMethods(['post'])->getMock();
        $response = (new Response(config('App')))->setBody(json_encode(['done' => true, 'message' => ['content' => ' Uma imagem escura. ']]));
        $client->expects($this->once())->method('post')->with(
            $this->equalTo('https://ollama.brapci.inf.br/api/chat'),
            $this->callback(function (array $options): bool {
                $this->assertFalse($options['allow_redirects']);
                $this->assertNotFalse($options['verify']);
                $this->assertFalse($options['json']['stream']);
                $this->assertSame('qwen3.5:2b', $options['json']['model']);
                $image = base64_decode($options['json']['messages'][0]['images'][0], true);
                $dimensions = getimagesizefromstring($image);
                $this->assertSame([1600, 800], [$dimensions[0], $dimensions[1]]);
                $this->assertSame('image/jpeg', $dimensions['mime']);
                $this->assertStringNotContainsString('storage_key', json_encode($options['json']));
                return true;
            }),
        )->willReturn($response);

        $result = (new OllamaImageDescriber($client, $this->writablePath))->describe(['storage_key' => $this->key]);
        $this->assertSame('Uma imagem escura.', $result['description']);
    }

    public function testRejectsPathOutsideRepositoryBeforeNetworkRequest(): void
    {
        $client = $this->getMockBuilder(CURLRequest::class)->disableOriginalConstructor()->onlyMethods(['post'])->getMock();
        $client->expects($this->never())->method('post');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('O arquivo da imagem não está disponível.');
        (new OllamaImageDescriber($client, $this->writablePath))->describe(['storage_key' => '../app/Config/Routes.php']);
    }

    public function testRejectsInvalidAndIncompleteRemoteResponses(): void
    {
        foreach ([
            [301, 'Ollama is running'],
            [500, '{"error":"internal details"}'],
            [200, 'invalid json'],
            [200, '{"done":true,"message":{"content":" "}}'],
            [200, '{"done":false,"message":{"content":"partial"}}'],
            [200, '{"done":true,"done_reason":"length","message":{"content":"partial"}}'],
        ] as [$status, $body]) {
            $client = $this->getMockBuilder(CURLRequest::class)->disableOriginalConstructor()->onlyMethods(['post'])->getMock();
            $client->method('post')->willReturn((new Response(config('App')))->setStatusCode($status)->setBody($body));
            try {
                (new OllamaImageDescriber($client, $this->writablePath))->describe(['storage_key' => $this->key]);
                $this->fail('Uma resposta inválida não deve gerar uma descrição.');
            } catch (RuntimeException $exception) {
                $this->assertStringNotContainsString('internal details', $exception->getMessage());
            }
        }
    }

    public function testNetworkFailureDoesNotExposeConnectionDetails(): void
    {
        $client = $this->getMockBuilder(CURLRequest::class)->disableOriginalConstructor()->onlyMethods(['post'])->getMock();
        $client->method('post')->willThrowException(new RuntimeException('private network detail'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Não foi possível conectar ao Ollama. Verifique a conexão e tente novamente.');
        (new OllamaImageDescriber($client, $this->writablePath))->describe(['storage_key' => $this->key]);
    }

    public function testRejectsInvalidImageBeforeNetworkRequest(): void
    {
        file_put_contents($this->writablePath . $this->key, 'not an image');
        $client = $this->getMockBuilder(CURLRequest::class)->disableOriginalConstructor()->onlyMethods(['post'])->getMock();
        $client->expects($this->never())->method('post');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Selecione uma imagem JPEG, PNG, GIF ou WebP válida.');
        (new OllamaImageDescriber($client, $this->writablePath))->describe(['storage_key' => $this->key]);
    }

    public function testPeopleDetectionUsesSchemaAndOnlyReturnsVisibleAttributes(): void
    {
        $client = $this->getMockBuilder(CURLRequest::class)->disableOriginalConstructor()->onlyMethods(['post'])->getMock();
        $data = ['count_estimate' => 1, 'notes' => 'Parcialmente encoberta.', 'people' => [[
            'position' => ' À esquerda ', 'clothing' => 'Camisa azul', 'activity' => 'Lendo', 'name' => 'Nome indevido',
        ]]];
        $client->expects($this->once())->method('post')->with($this->anything(), $this->callback(function (array $options): bool {
            $this->assertSame('object', $options['json']['format']['type']);
            $this->assertSame(['position', 'clothing', 'activity'], $options['json']['format']['properties']['people']['items']['required']);
            $this->assertStringContainsString('Não identifique pessoas', $options['json']['messages'][0]['content']);
            return true;
        }))->willReturn((new Response(config('App')))->setBody(json_encode(['done' => true, 'message' => ['content' => "```json\n" . json_encode($data) . "\n```"]])));
        $result = (new OllamaImageDescriber($client, $this->writablePath))->detectPeople(['storage_key' => $this->key]);
        $this->assertSame(1, $result['count_estimate']);
        $this->assertSame('À esquerda', $result['people'][0]['position']);
        $this->assertArrayNotHasKey('name', $result['people'][0]);
    }

    public function testPeopleDetectionAcceptsZeroAndUnknownCount(): void
    {
        foreach ([0, null] as $count) {
            $client = $this->getMockBuilder(CURLRequest::class)->disableOriginalConstructor()->onlyMethods(['post'])->getMock();
            $data = ['count_estimate' => $count, 'notes' => '', 'people' => []];
            $client->method('post')->willReturn((new Response(config('App')))->setBody(json_encode(['done' => true, 'message' => ['content' => json_encode($data)]])));
            $result = (new OllamaImageDescriber($client, $this->writablePath))->detectPeople(['storage_key' => $this->key]);
            $this->assertSame($count, $result['count_estimate']);
            $this->assertSame([], $result['people']);
        }
    }

    public function testPeopleDetectionRejectsMalformedResults(): void
    {
        foreach ([
            'not json',
            '{"count_estimate":-1,"notes":"","people":[]}',
            '{"count_estimate":"2","notes":"","people":[]}',
            '{"count_estimate":0,"notes":"","people":[{"position":"left","clothing":"blue","activity":"reading"}]}',
            '{"count_estimate":1,"notes":"","people":[{"position":"left"}]}',
        ] as $content) {
            $client = $this->getMockBuilder(CURLRequest::class)->disableOriginalConstructor()->onlyMethods(['post'])->getMock();
            $client->method('post')->willReturn((new Response(config('App')))->setBody(json_encode(['done' => true, 'message' => ['content' => $content]])));
            try {
                (new OllamaImageDescriber($client, $this->writablePath))->detectPeople(['storage_key' => $this->key]);
                $this->fail('Uma detecção inválida não deve ser aceita.');
            } catch (RuntimeException $exception) {
                $this->assertSame('O Ollama retornou uma detecção inválida. Tente novamente.', $exception->getMessage());
            }
        }
    }
}
