<?php
namespace ElxDigital\Gerador;

class Helpers
{
    public function findViewDirectory(string $actualPath, int $count = 3): string
    {
        if ($count == 0) {
            throw new \RuntimeException("Não foi possível detectar o diretório que deseja. Verifique o caminho solicitado e tente novamente!");
        }

        $array = explode(DIRECTORY_SEPARATOR, $actualPath);

        if (end($array) !== CONF_VIEW_THEME) {
            array_pop($array);
            $newPath = implode(DIRECTORY_SEPARATOR, $array);
            return findViewDirectory($newPath, --$count);
        }

        return implode(DIRECTORY_SEPARATOR, $array);
    }
}
