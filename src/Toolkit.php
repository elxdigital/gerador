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
     * ###########################
     * ### FUNÇÕES INDIVIDUAIS ###
     * ###########################
     */

    /**
     * @return void
     */
    public function mapViews(): void
    {
        echo str_repeat("#", 100) . "\n";

        try {
            $files = $this->getArquivos();
            echo "Mapeando views no diretório: {$files->diretorio}" . PHP_EOL;
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

    /**
     * @return void
     */
    public function scanFieldTags(): void
    {
        echo str_repeat("#", 100) . "\n";

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
                $foreignKeys = [];

                foreach ($nodes as $node) {
                    $tag = $node->nodeName;
                    $fieldName = $node->getAttribute('data-field-name');
                    $fieldType = strtolower($node->getAttribute('data-field-type'));
                    $tableRef = $node->getAttribute('data-table-ref');
                    $fieldContent = '';

                    foreach ($node->childNodes as $child) {
                        $fieldContent .= $dom->saveHTML($child);
                    }

                    // Tipagem
                    $sqlType = match ($fieldType) {
                        'mce', 'textarea' => 'TEXT DEFAULT NULL',
                        'text', 'varchar' => 'VARCHAR(255) DEFAULT NULL',
                        'int' => 'INT(11) UNSIGNED DEFAULT NULL',
                        'date' => 'DATE DEFAULT NULL',
                        'timestamp' => 'TIMESTAMP NULL DEFAULT NULL',
                        'foreign' => 'INT(11) UNSIGNED DEFAULT NULL',
                        default => 'VARCHAR(255) DEFAULT NULL'
                    };

                    $campos[] = "  `{$fieldName}` {$sqlType}";
                    $valores[] = "'" . addslashes(trim($fieldContent)) . "'";

                    if ($fieldType === 'foreign') {
                        if (!empty($tableRef)) {
                            $foreignKeys[] = "  FOREIGN KEY (`{$fieldName}`) REFERENCES `{$tableRef}`(`id`) ON DELETE SET NULL ON UPDATE CASCADE";
                        } else {
                            $log .= "⚠️ Campo \"{$fieldName}\" possui tipo 'foreign' mas está sem 'data-table-ref'. Ignorando FOREIGN KEY.\n";
                        }
                    }

                    $log .= "- Tag: <{$tag}>\n";
                    $log .= "  data-field-name: \"{$fieldName}\"\n";
                    $log .= "  data-field-type: \"{$fieldType}\"\n";
                    if (!empty($tableRef)) {
                        $log .= "  data-table-ref: \"{$tableRef}\"\n";
                    }
                    $log .= "  Conteúdo: {$fieldContent}\n";
                }

                // CREATE TABLE
                $ddl .= "CREATE TABLE `{$tabela}` (\n";
                $ddl .= "  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,\n";
                $ddl .= implode(",\n", $campos) . ",\n";
                $ddl .= "  `ativar` INT(1) DEFAULT 1,\n";
                $ddl .= "  `data_create` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
                $ddl .= "  `data_update` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,\n";
                $ddl .= "  PRIMARY KEY (`id`)";
                if (!empty($foreignKeys)) {
                    $ddl .= ",\n" . implode(",\n", $foreignKeys);
                }
                $ddl .= "\n);\n\n";

                // INSERT
                $columns = implode(", ", array_map(fn($c) => "`" . explode(" ", trim($c))[0] . "`", $campos));
                $values = implode(", ", $valores);
                $insert .= "INSERT INTO `{$tabela}` ({$columns}) VALUES ({$values});\n\n";

                $log .= str_repeat("=", 40) . "\n";
            }
        }

        file_put_contents($logFile, $log);
        file_put_contents($ddlFile, $ddl);
        file_put_contents($insertFile, $insert);

        echo "Mapeamento finalizado com sucesso. Arquivos gerados em /storage\n";
    }

    /**
     * @return void
     */
    public function applyDatabaseChanges(): void
    {
        echo str_repeat("#", 100) . "\n";

        try {
            $files = $this->getArquivos();
        } catch (\Exception $e) {
            echo $e->getMessage();
            return;
        }

        $storagePath = $files->diretorio . DIRECTORY_SEPARATOR . 'storage';
        if (!is_dir($storagePath)) {
            echo "Não há nenhum diretório storage no caminho mencionado: {$storagePath}";
            return;
        }

        $ddlFile = $storagePath . DIRECTORY_SEPARATOR . 'tabelas.sql';
        $insertFile = $storagePath . DIRECTORY_SEPARATOR . 'inserts.sql';

        if (!is_file($ddlFile)) {
            echo "Não há arquivo de DDL para criar o banco de dados.";
            return;
        }

        if (!is_file($insertFile)) {
            echo "Não há arquivo com registros para as tabelas do banco de dados.";
            return;
        }

        $ddlContent = file_get_contents($ddlFile);
        if (empty($ddlContent)) {
            echo "Não há conteúdo no arquivo de DDL.";
            return;
        }

        $insertContent = file_get_contents($insertFile);
        if (empty($insertContent)) {
            echo "Não há conteúdo no arquivo de inserts.";
            return;
        }

        $stmtDDL = \ElxDigital\Gerador\Connect::getInstance()->prepare($ddlContent);
        if (!$stmtDDL->execute()) {
            echo "Erro ao criar tabelas no banco de dados.";
            return;
        }

        $stmtInserts = \ElxDigital\Gerador\Connect::getInstance()->prepare($insertContent);
        if (!$stmtInserts->execute()) {
            echo "Erro ao fazer inserts em tabelas no banco de dados.";
            return;
        }
    }


    /**
     * ########################
     * ### MÉTODO PRINCIPAL ###
     * ########################
     */

    /**
     * @return void
     */
    public function generate(): void
    {
        echo str_repeat("#", 100) . "\n";
        echo "Iniciando rotinas para criar CRUD's.";

        try {
            $this->mapViews();
        } catch (\Exception $mpExcep) {
            echo $mpExcep->getMessage();
            return;
        }

        echo "Views mapeadas com sucesso!\n Iniciando leitura de campos nestes arquivos mapeados.";

        try {
            $this->scanFieldTags();
        } catch (\Exception $scanExcep) {
            echo $scanExcep->getMessage();
            return;
        }

        echo "Os campos foras escaneados com sucesso!";

        try {
            $this->applyDatabaseChanges();
        } catch (\Exception $dbExcep) {
            echo $dbExcep->getMessage();
            return;
        }

        echo "Banco de dados criado com sucesso!";
    }


    /*
     * ######################
     * ### MÉTODO PRIVADO ###
     * ######################
     */

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

        return (object)['diretorio' => $clienteDir, "arquivos" => scandir($clienteDir)];
    }
}
