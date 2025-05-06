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
            return $this->findViewDirectory($newPath, --$count);
        }

        return implode(DIRECTORY_SEPARATOR, $array);
    }

    /**
     * @param string $path
     * @return string
     */
    public function url(string $path = null): string
    {
        if (strpos($_SERVER['HTTP_HOST'], "localhost")) {
            if ($path) {
                return CONF_URL_TEST . "/" . ($path[0] == "/" ? mb_substr($path, 1) : $path);
            }
            return CONF_URL_TEST;
        }

        if ($path) {
            return CONF_URL_BASE . "/" . ($path[0] == "/" ? mb_substr($path, 1) : $path);
        }

        return CONF_URL_BASE;
    }

    /**
     * @return string
     */
    public function url_back(): string
    {
        return ($_SERVER['HTTP_REFERER'] ?? $this->url());
    }

    public function redirect(string $url): void
    {
        header("HTTP/1.1 302 Redirect");
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            header("Location: {$url}");
            exit;
        }

        if (filter_input(INPUT_GET, "route") != $url) {
            $location = $this->url($url);
            header("Location: {$location}");
            exit;
        }
    }
}
