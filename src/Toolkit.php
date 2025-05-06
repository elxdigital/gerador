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

        $storagePath = $files->diretorio . DIRECTORY_SEPARATOR . 'storage';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $logFile = $storagePath . DIRECTORY_SEPARATOR . 'tags_mapeadas.txt';
        $ddlFile = $storagePath . DIRECTORY_SEPARATOR . 'tabelas.sql';
        $insertFile = $storagePath . DIRECTORY_SEPARATOR . 'inserts.sql';

        $log = "";
        $ddl = "";
        $insert = "";

        $arquivos = $files->arquivos;
        foreach ($arquivos as $arquivo) {
            $path = $files->diretorio . DIRECTORY_SEPARATOR . $arquivo;

            if (
                is_file($path) &&
                str_ends_with($arquivo, '.php') &&
                $arquivo !== 'error.php' &&
                $arquivo !== '_theme.php'
            ) {
                $html = file_get_contents($path);
                $html = '<?xml encoding="UTF-8">' . $html;

                libxml_use_internal_errors(true);
                $dom = new \DOMDocument();
                $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new \DOMXPath($dom);
                $nodes = $xpath->query('//*[@data-field-name]');

                if ($nodes->length === 0) {
                    continue;
                }

                $tabela = "pagina_" . pathinfo($arquivo, PATHINFO_FILENAME);
                $log .= "Arquivo: $arquivo" . PHP_EOL;

                $campos = [];
                $valores = [];

                foreach ($nodes as $node) {
                    $tag = $node->nodeName;
                    $fieldName = $node->getAttribute('data-field-name');
                    $fieldType = strtolower($node->getAttribute('data-field-type'));
                    $fieldContent = '';

                    foreach ($node->childNodes as $child) {
                        $fieldContent .= $dom->saveHTML($child);
                    }

                    $sqlType = match ($fieldType) {
                        'textarea', 'mce' => 'TEXT DEFAULT NULL',
                        'int' => 'INT(11) UNSIGNED DEFAULT NULL',
                        'date' => 'DATE DEFAULT NULL',
                        'timestamp' => 'TIMESTAMP NULL DEFAULT NULL',
                        default => 'VARCHAR(255) DEFAULT NULL',
                    };

                    $campos[] = "  {$fieldName} {$sqlType}";
                    $valores[] = "'" . addslashes(trim($fieldContent)) . "'";

                    $log .= "- Tag: <{$tag}>" . PHP_EOL;
                    $log .= "  data-field-name: \"{$fieldName}\"" . PHP_EOL;
                    $log .= "  data-field-type: \"{$fieldType}\"" . PHP_EOL;
                    $log .= "  Conteúdo: {$fieldContent}" . PHP_EOL;
                }

                // Gerar DDL
                $ddl .= "CREATE TABLE {$tabela} (\n";
                $ddl .= "  id INT AUTO_INCREMENT PRIMARY KEY,\n";
                $ddl .= implode(",\n", $campos) . ",\n";
                $ddl .= "  ativar INT(1) DEFAULT 1,\n";
                $ddl .= "  data_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
                $ddl .= "  data_update TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP\n";
                $ddl .= ");\n\n";

                // Gerar INSERT
                $colunas = implode(", ", array_map(fn($f) => explode(" ", trim($f))[0], $campos));
                $valoresSQL = implode(", ", $valores);
                $insert .= "INSERT INTO {$tabela} ({$colunas}) VALUES ({$valoresSQL});\n\n";

                $log .= str_repeat("=", 40) . PHP_EOL;
            }
        }

        file_put_contents($logFile, $log);
        file_put_contents($ddlFile, $ddl);
        file_put_contents($insertFile, $insert);

        echo "Mapeamento finalizado. Arquivos gerados em /storage" . PHP_EOL;
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
