<?php

declare(strict_types=1);

namespace Twila\Credicarro\Tests;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Twila\Credicarro\Errors\CredicarroValidationException;
use Twila\Credicarro\Proposals\DocumentFile;

final class DocumentFileTest extends TestCase
{
    public function testFromBytesEncodesPureBase64(): void
    {
        $file = DocumentFile::fromBytes("%PDF-1.7
 conteúdo", 'cnh-comprador.pdf');

        self::assertSame('cnh-comprador.pdf', $file->fileName);
        self::assertSame(base64_encode("%PDF-1.7\n conteúdo"), $file->base64Content);
        self::assertSame(strlen("%PDF-1.7\n conteúdo"), $file->sizeBytes);
        self::assertSame(1, preg_match('/^[A-Za-z0-9+\/]+=*$/', $file->base64Content));
    }

    /**
     * @dataProvider validNamesProvider
     */
    public function testAcceptedExtensionsIgnoreCase(string $name): void
    {
        self::assertSame($name, DocumentFile::fromBytes('x', $name)->fileName);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validNamesProvider(): array
    {
        return [
            'pdf' => ['a.pdf'],
            'jpg' => ['foto.jpg'],
            'jpeg' => ['foto.jpeg'],
            'png' => ['scan.png'],
            'upper' => ['SCAN.PNG'],
            'dots' => ['rg.frente.v2.pdf'],
            'accents' => ['comprovante-residência.pdf'],
        ];
    }

    /**
     * @dataProvider invalidNamesProvider
     */
    public function testInvalidNamesAreRejected(string $name, string $code): void
    {
        try {
            DocumentFile::fromBytes('x', $name);
            self::fail('Esperava CredicarroValidationException.');
        } catch (CredicarroValidationException $e) {
            self::assertSame($code, $e->getErrorCode());
            self::assertSame(400, $e->getStatusCode());
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidNamesProvider(): array
    {
        return [
            'empty' => ['', 'Validation.File.InvalidName'],
            'blank' => ['   ', 'Validation.File.InvalidName'],
            'unix path' => ['pasta/arquivo.pdf', 'Validation.File.InvalidName'],
            'windows path' => ['C:\\docs\\arquivo.pdf', 'Validation.File.InvalidName'],
            'traversal' => ['../arquivo.pdf', 'Validation.File.InvalidName'],
            'too long' => [str_repeat('a', 252) . '.pdf', 'Validation.File.InvalidName'],
            'no extension' => ['arquivo', 'Validation.File.InvalidExtension'],
            'exe' => ['arquivo.exe', 'Validation.File.InvalidExtension'],
            'gif' => ['arquivo.gif', 'Validation.File.InvalidExtension'],
            'double extension' => ['arquivo.pdf.exe', 'Validation.File.InvalidExtension'],
        ];
    }

    public function testNameWith255CharactersIsAccepted(): void
    {
        $name = str_repeat('a', 251) . '.pdf';

        self::assertSame(255, strlen($name));
        self::assertSame($name, DocumentFile::fromBytes('x', $name)->fileName);
    }

    public function testEmptyContentIsRejected(): void
    {
        try {
            DocumentFile::fromBytes('', 'a.pdf');
            self::fail('Esperava CredicarroValidationException.');
        } catch (CredicarroValidationException $e) {
            self::assertSame('Validation.File.Empty', $e->getErrorCode());
        }
    }

    public function testExactly10MiBIsAcceptedAndOneByteMoreIsRejected(): void
    {
        $limit = DocumentFile::MAX_BYTES;
        self::assertSame(10 * 1024 * 1024, $limit);

        $ok = DocumentFile::fromBytes(str_repeat('a', $limit), 'grande.pdf');
        self::assertSame($limit, $ok->sizeBytes);

        try {
            DocumentFile::fromBytes(str_repeat('a', $limit + 1), 'grande.pdf');
            self::fail('Esperava CredicarroValidationException.');
        } catch (CredicarroValidationException $e) {
            self::assertSame('Validation.File.TooLarge', $e->getErrorCode());
        }
    }

    public function testFromStreamAcceptsResourcesAndPsrStreams(): void
    {
        $resource = fopen('php://memory', 'r+');
        self::assertIsResource($resource);
        fwrite($resource, 'conteudo-resource');
        rewind($resource);

        $fromResource = DocumentFile::fromStream($resource, 'r.png');
        $fromPsr = DocumentFile::fromStream(Utils::streamFor('conteudo-psr'), 'p.png');
        fclose($resource);

        self::assertSame(base64_encode('conteudo-resource'), $fromResource->base64Content);
        self::assertSame(base64_encode('conteudo-psr'), $fromPsr->base64Content);
    }

    public function testFromStreamRejectsOversizedStreams(): void
    {
        $this->expectException(CredicarroValidationException::class);
        DocumentFile::fromStream(Utils::streamFor(str_repeat('a', DocumentFile::MAX_BYTES + 10)), 'grande.pdf');
    }

    public function testFromStreamRejectsInvalidArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DocumentFile::fromStream('nao-e-stream', 'a.pdf');
    }

    public function testFromFileUsesBasenameByDefaultAndAllowsOverride(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cred');
        self::assertIsString($path);
        file_put_contents($path, 'arquivo-em-disco');

        try {
            $withOverride = DocumentFile::fromFile($path, 'cnh.pdf');
            self::assertSame('cnh.pdf', $withOverride->fileName);
            self::assertSame(base64_encode('arquivo-em-disco'), $withOverride->base64Content);

            // O nome temporário não tem extensão válida: sem override, a validação de extensão falha.
            $this->expectException(CredicarroValidationException::class);
            DocumentFile::fromFile($path);
        } finally {
            unlink($path);
        }
    }

    public function testFromFileRejectsMissingFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DocumentFile::fromFile(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nao-existe-credicarro.pdf');
    }
}
