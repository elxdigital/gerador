<?php
namespace ElxDigital\Gerador;

class Helpers
{
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
