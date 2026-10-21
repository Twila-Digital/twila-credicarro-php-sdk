<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

use Psr\Http\Message\StreamInterface;
use Twila\Credicarro\Errors\CredicarroValidationException;
use Twila\Credicarro\Internal\Masking\MasksSensitiveData;
use Twila\Credicarro\Internal\Validation;

/**
 * Arquivo de documento pronto para envio. Qualquer forma de construção valida o que a API validaria: nome sem
 * caminho com até 255 caracteres, extensão `.pdf`, `.jpg`, `.jpeg` ou `.png`, e conteúdo de 1 byte a 10 MiB. O Base64
 * gerado é puro (sem prefixo `data:` nem quebras de linha) e é materializado uma única vez: o mesmo objeto é
 * reaproveitado em todas as tentativas. A API ainda confere se a extensão corresponde ao conteúdo real.
 */
final class DocumentFile
{
    use MasksSensitiveData;

    public const MAX_BYTES = 10485760;

    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    private const CHUNK_BYTES = 65536;

    /** @var string */
    public $fileName;

    /** @var string Base64 puro do conteúdo */
    public $base64Content;

    /** @var int tamanho em bytes do conteúdo original */
    public $sizeBytes;

    /**
     * @param string $content bytes do arquivo (1 byte a 10 MiB)
     */
    public function __construct(
        #[\SensitiveParameter]
        string $content,
        string $fileName
    ) {
        self::validateName($fileName);

        $size = strlen($content);
        if ($size === 0) {
            throw Validation::fail('Validation.File.Empty', 'O arquivo está vazio.', 'conteudoBase64');
        }

        if ($size > self::MAX_BYTES) {
            throw self::tooLarge();
        }

        $this->fileName = $fileName;
        $this->base64Content = base64_encode($content);
        $this->sizeBytes = $size;
    }

    public static function fromBytes(
        #[\SensitiveParameter]
        string $content,
        string $fileName
    ): self {
        return new self($content, $fileName);
    }

    /**
     * Lê em laço até o fim do stream ou até ultrapassar o limite (`MAX_BYTES` + 1), sem confiar em `size`.
     *
     * @param resource|StreamInterface $stream lido a partir da posição atual
     */
    public static function fromStream($stream, string $fileName): self
    {
        self::validateName($fileName);

        $content = '';
        if ($stream instanceof StreamInterface) {
            while (!$stream->eof() && strlen($content) <= self::MAX_BYTES) {
                $chunk = $stream->read(self::CHUNK_BYTES);
                if ($chunk === '') {
                    break;
                }
                $content .= $chunk;
            }
        } elseif (is_resource($stream)) {
            while (!feof($stream) && strlen($content) <= self::MAX_BYTES) {
                $chunk = fread($stream, self::CHUNK_BYTES);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $content .= $chunk;
            }
        } else {
            throw new \InvalidArgumentException('O stream informado deve ser um resource ou um StreamInterface.');
        }

        return new self($content, $fileName);
    }

    /**
     * @param string|null $fileName nome enviado à API; quando omitido, usa o nome do arquivo em `$path`
     */
    public static function fromFile(string $path, ?string $fileName = null): self
    {
        $name = $fileName ?? basename($path);
        self::validateName($name);

        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException('O arquivo informado não existe ou não pode ser lido.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException('Não foi possível ler o arquivo informado.');
        }

        try {
            return self::fromStream($handle, $name);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function sensitiveFields(): array
    {
        return ['base64Content' => 'full'];
    }

    private static function validateName(string $fileName): void
    {
        $length = function_exists('mb_strlen') ? mb_strlen($fileName, 'UTF-8') : strlen($fileName);

        if (
            trim($fileName) === ''
            || $length > 255
            || preg_match('#[/\\\\\x00-\x1f]#', $fileName) === 1
        ) {
            throw Validation::fail(
                'Validation.File.InvalidName',
                'O nome do arquivo deve ter até 255 caracteres e não pode conter caminho.',
                'nomeArquivo'
            );
        }

        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw Validation::fail(
                'Validation.File.InvalidExtension',
                'A extensão do arquivo deve ser .pdf, .jpg, .jpeg ou .png.',
                'nomeArquivo'
            );
        }
    }

    private static function tooLarge(): CredicarroValidationException
    {
        return Validation::fail(
            'Validation.File.TooLarge',
            'O arquivo excede 10 MiB. Comprima ou reduza a resolução.',
            'conteudoBase64'
        );
    }
}
