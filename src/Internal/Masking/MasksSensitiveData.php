<?php

declare(strict_types=1);

namespace Twila\Credicarro\Internal\Masking;

/**
 * Faz `var_dump`/`print_r` (`__debugInfo`) mostrarem os campos sensíveis mascarados. A máscara vale só para
 * depuração e log: `serialize`, `json_encode` e a leitura das propriedades públicas carregam os valores REAIS,
 * porque os modelos são usados para persistir (filas, sessão, cache) e responder. Limitação: `var_export` e
 * `(array)` ignoram `__debugInfo` e mostram as propriedades públicas como são.
 *
 * @internal
 */
trait MasksSensitiveData
{
    /**
     * Campos sensíveis: nome da propriedade => 'cpf' (máscara de CPF) ou 'full' (`***`).
     *
     * @return array<string, string>
     */
    abstract protected function sensitiveFields(): array;

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $properties = get_object_vars($this);
        foreach ($this->sensitiveFields() as $name => $kind) {
            if (array_key_exists($name, $properties)) {
                $properties[$name] = $this->maskValue($properties[$name], $kind);
            }
        }

        return $properties;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function maskValue($value, string $kind)
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return array_map(function ($item) use ($kind) {
                return $this->maskValue($item, $kind);
            }, $value);
        }

        return $kind === 'cpf' ? Mask::cpf($value) : Mask::FULL;
    }
}
