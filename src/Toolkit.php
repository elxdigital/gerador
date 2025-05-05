<?php
declare(strict_types=1);

namespace src;

/**
 * class Toolkit
 */
class Toolkit
{
    private Helpers $helpers;

    public function __construct()
    {
        $this->helpers = new Helpers();
    }

    /**
     * @return void
     */
    public function mapViews(): void
    {
        $projectRoot = $this->helpers->findViewDirectory(getcwd());
        $themesDir = $projectRoot . DIRECTORY_SEPARATOR . 'themes';

        if (!is_dir($themesDir)) {
            echo "Diretório 'themes' não encontrado em: $projectRoot" . PHP_EOL;
            return;
        }

        $clienteDir = $themesDir . DIRECTORY_SEPARATOR . CONF_VIEW_THEME;
        if (!is_dir($clienteDir)) {
            echo "Nenhum diretório de cliente encontrado dentro de 'themes'." . PHP_EOL;
            return;
        }

        echo "Mapeando views no diretório: $clienteDir" . PHP_EOL;

        $arquivos = scandir($clienteDir);
        $views = [];

        foreach ($arquivos as $arquivo) {
            $path = $clienteDir . DIRECTORY_SEPARATOR . $arquivo;
            if (
                is_file($path) &&
                str_ends_with($arquivo, '.php') &&
                $arquivo !== 'error.php' &&
                $arquivo !== '_theme.php'
            ) {
                $views[] = $arquivo;
            }
        }

        if (empty($views)) {
            echo "Nenhuma view encontrada." . PHP_EOL;
        } else {
            echo "Views encontradas:" . PHP_EOL;
            foreach ($views as $view) {
                echo "- $view" . PHP_EOL;
            }
        }
    }
}
