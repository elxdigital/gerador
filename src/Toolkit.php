<?php
declare(strict_types=1);

namespace ElxDigital\Gerador;

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
        try {
            $files = $this->getArquivos();
        } catch (\Exception $e) {
            echo $e->getMessage();
            return;
        }

        $views = [];
        $arquivos = $files->arquivos;
        $clienteDir = $files->diretorio;

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

    public function scanFieldTags(): void
    {
        try {
            $files = $this->getArquivos();
        } catch (\Exception $e) {
            echo $e->getMessage();
            return;
        }

        $arquivos = $files->arquivos;
        $clienteDir = $files->diretorio;

        foreach ($arquivos as $arquivo) {
            $path = $clienteDir . DIRECTORY_SEPARATOR . $arquivo;

            if (
                is_file($path) &&
                str_ends_with($arquivo, '.php') &&
                $arquivo !== '_theme.php' &&
                $arquivo !== 'error.php'
            ) {
                $conteudo = file_get_contents($path);

                preg_match_all('/<([a-z1-6]+)[^>]*data-field-name="([^"]+)"[^>]*>/i', $conteudo, $matches, PREG_SET_ORDER);

                if (!empty($matches)) {
                    echo "Arquivo: $arquivo" . PHP_EOL;
                    foreach ($matches as $match) {
                        $tag = $match[1];
                        $fieldName = $match[2];
                        echo "- Tag: <$tag>, data-field-name: \"$fieldName\"" . PHP_EOL;
                    }
                    echo str_repeat('=', 40) . PHP_EOL;
                }
            }
        }
    }

    /**
     * @return object
     * @throws \Exception
     */
    private function getArquivos(): object
    {
        $projectRoot = $this->helpers->findViewDirectory(getcwd());

        $themesDir = $projectRoot . DIRECTORY_SEPARATOR . 'themes';
        if (!is_dir($themesDir)) {
            throw new \Exception("Diretório 'themes' não encontrado em: $projectRoot" . PHP_EOL);
        }

        $clienteDir = $themesDir . DIRECTORY_SEPARATOR . CONF_VIEW_THEME;
        if (!is_dir($clienteDir)) {
            throw new \Exception("Nenhum diretório de cliente encontrado dentro de 'themes'." . PHP_EOL);
        }

        echo "Mapeando views no diretório: $clienteDir" . PHP_EOL;

        return (object)['diretorio' => $clienteDir, "arquivos" => scandir($clienteDir)];
    }
}
