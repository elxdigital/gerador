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

                $tabela = str_replace('-', '_', $tabela);

                // EXCLUI TABELA SE EXISTIR
                $ddl .= "DROP TABLE IF EXISTS `{$tabela}`;\n";

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
                $columns = implode(", ", array_map(fn($c) => explode(" ", trim($c))[0], $campos));
                $values = implode(", ", $valores);
                $insert .= "INSERT INTO `{$tabela}` ({$columns}) VALUES ({$values});\n\n";

                $log .= str_repeat("=", 40) . "\n";
            }
        }

        file_put_contents($logFile, $log, FILE_APPEND);
        file_put_contents($ddlFile, $ddl, FILE_APPEND);
        file_put_contents($insertFile, $insert, FILE_APPEND);

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
        $stmtDDL->closeCursor();

        $stmtInserts = \ElxDigital\Gerador\Connect::getInstance()->prepare($insertContent);
        if (!$stmtInserts->execute()) {
            echo "Erro ao fazer inserts em tabelas no banco de dados.";
            return;
        }
        $stmtInserts->closeCursor();
    }

    /**
     * @return void
     */
    public function createModel(): void
    {
        echo str_repeat("#", 100) . "\n";

        try {
            $files = $this->getArquivos();
        } catch (\Exception $exception) {
            echo $exception->getMessage();
            return;
        }

        $filePath = $files->diretorio . '/storage/tags_mapeadas.txt';
        if (!file_exists($filePath)) {
            echo "O arquivo contendo as tags mapeadas não foi encontrado.\n";
            return;
        }

        $conteudo = file_get_contents($filePath);
        $blocos = preg_split('/=+\s*/', $conteudo);
        $blocos = array_filter(array_map('trim', $blocos));

        foreach ($blocos as $bloco) {
            preg_match('/Arquivo:\s*(.+\.php)/', $bloco, $matchArquivo);
            if (empty($matchArquivo)) continue;

            $arquivo = trim($matchArquivo[1]);
            $baseName = pathinfo($arquivo, PATHINFO_FILENAME);
            $className = "Pagina" . str_replace(" ", "", ucwords(str_replace("-", " ", $baseName)));
            $tableName = "pagina_" . $baseName;

            preg_match_all('/data-field-name:\s*"([^"]+)"\s*data-field-type:\s*"([^"]+)"(?:\s*data-table-ref:\s*"([^"]+)")?/', $bloco, $matches, PREG_SET_ORDER);

            $properties = [];
            $methods = [];
            $requiredFields = [];

            $properties[] = "* Properties";
            foreach ($matches as $match) {
                $fieldName = $match[1];
                $fieldType = strtolower($match[2]);
                $tableRef = $match[3] ?? null;

                $phpType = in_array($fieldType, ['number', 'foreign']) ? 'int' : 'string';
                $properties[] = "* @property {$phpType}|null \${$fieldName}";

                if ($fieldType === 'foreign' && $tableRef) {
                    $refClass = str_replace(" ", "", ucwords(str_replace("_", " ", $tableRef)));
                    $methods[] = "* @method {$refClass}|null {$fieldName}";
                }

                // Para o construtor
                // $requiredFields[] = "\"$fieldName\""; todo aplicar lógica para campos required
            }

            $properties[] = "* @property bool \$ativar";
            $properties[] = "* @property string \$data_create";
            $properties[] = "* @property string \$data_update";
            $properties[] = "* ";

            $phpDoc = implode("\n\t", array_merge($properties, ["", "* Methods"], $methods));
            $requiredStr = implode(",\n\t\t\t\t", $requiredFields);

            $model = <<<PHP
<?php

namespace Source\Models;

use Source\Core\Model;

/**
* Class {$className}
* @package Source\Models
*
{$phpDoc}
* 
*/
class {$className} extends Model
{
\t/**
\t * {$className} constructor.
\t */
\tpublic function __construct()
\t{
\t\tparent::__construct(
\t\t\t"{$tableName}",
\t\t\t["id"],
\t\t\t[
\t\t\t\t{$requiredStr}
\t\t\t]
\t\t);
\t}
}
PHP;

            $modelDir = ROOT_DIR . 'source/Models';
            if (!is_dir($modelDir)) {
                mkdir($modelDir, 0777, true);
            }

            $filePath = "{$modelDir}/{$className}.php";
            file_put_contents($filePath, $model, FILE_APPEND);
            echo "Model gerada: {$filePath}\n";
        }
    }

    /**
     * @return void
     */
    public function createController(): void
    {
        echo str_repeat("#", 100) . "\n";

        try {
            $files = $this->getArquivos();
        } catch (\Exception $exception) {
            echo $exception->getMessage();
            return;
        }

        $filePath = $files->diretorio . '/storage/tags_mapeadas.txt';
        if (!file_exists($filePath)) {
            echo "O arquivo contendo as tags mapeadas não foi encontrado.\n";
            return;
        }

        $conteudo = file_get_contents($filePath);
        $blocos = preg_split('/=+\s*/', $conteudo);
        $blocos = array_filter(array_map('trim', $blocos));

        foreach ($blocos as $bloco) {
            preg_match('/Arquivo:\s*(.+\.php)/', $bloco, $matchArquivo);
            if (empty($matchArquivo)) continue;

            $arquivo = trim($matchArquivo[1]);
            $baseName = pathinfo($arquivo, PATHINFO_FILENAME);
            $className = "Pagina" . str_replace(" ", "", ucwords(str_replace("-", " ", $baseName)));
            $functionName = (str_replace(" ", "_", strtolower(str_replace("-", "_", $baseName))));
            $menuName = "pagina";
            $crudName = ucfirst(str_replace("-", " ", $baseName));

            preg_match_all('/data-field-name:\s*"([^"]+)"\s*data-field-type:\s*"([^"]+)"/', $bloco, $matches, PREG_SET_ORDER);

            $fields = [];
            foreach ($matches as $match) {
                $fields[] = [
                    'name' => $match[1],
                    'type' => strtolower($match[2]),
                    'required' => true // por padrão, todos são required no seu gerador antigo
                ];
            }

            $mceFields = array_filter($fields, fn($f) => $f['type'] === 'mce');
            $requiredFields = array_filter($fields, fn($f) => $f['required']);
            $nonMceFields = array_filter($fields, fn($f) => $f['type'] !== 'mce');

            $mceAssignments = implode("\n\t\t", array_map(fn($f) => "\${$f['name']} = \$data['{$f['name']}'] ?? null;", $mceFields));
            $requiredChecks = '';

// todo habilitar aqui abaixo quando implementar lógica de campos obrigatórios
//implode("\n\t\t", array_map(fn($f) => <<<PHP
//if (empty(\$data["{$f['name']}"])) {
//\t\$json["message"] = \$this->message->error("{$f['name']} é um campo obrigatório")->render();
//\techo json_encode(\$json);
//\treturn;
//}
//PHP, $requiredFields));

            $assignments = implode("\n\t\t", array_map(fn($f) => "\${$functionName}->{$f['name']} = (!empty(\$data[\"{$f['name']}\"]) ? \$data[\"{$f['name']}\"] : null);", $nonMceFields));
            $mceAssignmentsToModel = implode("\n\t\t", array_map(fn($f) => "\${$functionName}->{$f['name']} = (!empty(\${$f['name']}) ? \${$f['name']} : null);", $mceFields));

            $controllerCode = <<<PHP
<?php

namespace Source\App\Admin;

class {$className} extends Admin
{
\tpublic function __construct()
\t{
\t\tparent::__construct();
\t}

\tpublic function {$functionName}(): void
\t{
\t\t\${$functionName} = (new \\Source\\Models\\{$className}())->findById(1);

\t\t\$head = \$this->seo->render(
\t\t\t"{$crudName} | " . CONF_SITE_NAME,
\t\t\tCONF_SITE_DESC,
\t\t\turl(),
\t\t\ttheme("/assets/images/share.jpg"),
\t\t\tfalse
\t\t);

\t\techo \$this->view->render("widgets/{$menuName}/{$functionName}/{$functionName}", [
\t\t\t"head" => \$head,
\t\t\t"menu" => "{$menuName}s",
\t\t\t"submenu" => "{$functionName}",
\t\t\t"data" => \${$functionName}
\t\t]);
\t}

\tpublic function save(array \$data): void
\t{
\t\t{$mceAssignments}
\t\t\$data = filter_var_array(\$data, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

\t\t{$requiredChecks}

\t\t\${$functionName} = (new \\Source\\Models\\{$className}())->findById(1);
\t\tif (empty(\${$functionName})) {
\t\t\t\${$functionName} = new \\Source\\Models\\{$className}();
\t\t}

\t\t{$assignments}
\t\t{$mceAssignmentsToModel}
\t\t\${$functionName}->ativar = (!empty(\$data["ativar"]) ? 1 : 0);

\t\tif (!\${$functionName}->save()) {
\t\t\t\$json["message"] = \${$functionName}->message()->render();
\t\t\techo json_encode(\$json);
\t\t\treturn;
\t\t}

\t\t\$json["message"] = \$this->message->success("{$crudName} salvo com sucesso")->render();
\t\t\$json["redirect"] = url("/admin/{$menuName}/{$functionName}/{$functionName}");
\t\techo json_encode(\$json);
\t}

\tpublic function active(array \$data)
\t{
\t\tif (!empty(\$data) && !empty(\$data["id"])) {
\t\t\t\$data_id_original = \$data["id"];
\t\t\t\$data["id"] = intval(\$data["id"]);
\t\t\t\$data = filter_var(\$data["id"], FILTER_VALIDATE_INT);

\t\t\t\${$functionName} = (new \\Source\\Models\\{$className}())->findById(\$data);
\t\t\tif(empty(\${$functionName})) {
\t\t\t\t\$this->message->error("Você tentou ativar um {$crudName} que não existe ou foi removido")->flash();
\t\t\t\techo json_encode(["redirect" => url("/admin/{$menuName}/{$functionName}/{$functionName}")]);
\t\t\t\treturn;
\t\t\t}

\t\t\tif(\${$functionName}->ativar == 1) {
\t\t\t\t\${$functionName}->ativar = 0;

\t\t\t\tif(!\${$functionName}->save()) {
\t\t\t\t\t\$json["message"] = \${$functionName}->message()->render();
\t\t\t\t\techo json_encode(\$json);
\t\t\t\t\treturn;
\t\t\t\t}

\t\t\t\t\$json["message"] = \$this->message->success("{$crudName} desativado com sucesso")->render();
\t\t\t\t\$json["active"] = 'desactive';
\t\t\t\t\$json["data_id_original"] = \$data_id_original;
\t\t\t\techo json_encode(\$json);
\t\t\t\treturn;
\t\t\t} else {
\t\t\t\t\${$functionName}->ativar = 1;

\t\t\t\tif(!\${$functionName}->save()) {
\t\t\t\t\t\$json["message"] = \${$functionName}->message()->render();
\t\t\t\t\techo json_encode(\$json);
\t\t\t\t\treturn;
\t\t\t\t}

\t\t\t\t\$json["message"] = \$this->message->success("{$crudName} ativado com sucesso")->render();
\t\t\t\t\$json["active"] = 'active';
\t\t\t\t\$json["data_id_original"] = \$data_id_original;
\t\t\t\techo json_encode(\$json);
\t\t\t\treturn;
\t\t\t}

\t\t\t\$json["message"] = \$this->message->warning("Ocorreu um erro, não é possível continuar")->render();
\t\t\techo json_encode(\$json);
\t\t\treturn;
\t\t}
\t}
}
PHP;

            $controllerDir = ROOT_DIR . 'source/App/Admin';
            if (!is_dir($controllerDir)) {
                mkdir($controllerDir, 0777, true);
            }

            $filePath = "{$controllerDir}/{$className}.php";
            file_put_contents($filePath, $controllerCode, FILE_APPEND);
            echo "Controller gerado: {$filePath}\n";
        }
    }

    /**
     * @return void
     */
    public function createView(): void
    {
        echo str_repeat("#", 100) . "\n";

        try {
            $files = $this->getArquivos();
        } catch (\Exception $exception) {
            echo $exception->getMessage();
            return;
        }

        $filePath = $files->diretorio . '/storage/tags_mapeadas.txt';
        if (!file_exists($filePath)) {
            echo "O arquivo contendo as tags mapeadas não foi encontrado.\n";
            return;
        }

        $conteudo = file_get_contents($filePath);
        $blocos = preg_split('/=+\s*/', $conteudo);
        $blocos = array_filter(array_map('trim', $blocos));

        foreach ($blocos as $bloco) {
            preg_match('/Arquivo:\s*(.+\.php)/', $bloco, $matchArquivo);
            if (empty($matchArquivo)) continue;

            $arquivo = trim($matchArquivo[1]);
            $baseName = pathinfo($arquivo, PATHINFO_FILENAME);
            $className = "Pagina" . str_replace(" ", "", ucwords(str_replace("-", " ", $baseName)));
            $functionName = (str_replace(" ", "_", strtolower(str_replace("-", "_", $baseName))));
            $menuName = 'pagina';
            $crudName = ucfirst(str_replace("-", " ", $baseName));

            preg_match_all('/data-field-name:\s*"([^"]+)"\s*data-field-type:\s*"([^"]+)"/', $bloco, $matches, PREG_SET_ORDER);

            $fields = [];
            foreach ($matches as $match) {
                $fields[] = [
                    'name' => $match[1],
                    'type' => strtolower($match[2]),
                    'titulo' => ucwords(str_replace("_", " ", $match[1])),
                    'required' => true
                ];
            }

            $fieldsHtml = '';
            foreach ($fields as $field) {
                $fieldsHtml .= "\n";
                $name = $field['name'];
                $titulo = $field['titulo'];
                $required = $field['required'] ? 'required' : '';
                $value = "<?= !empty(\$data->{$name}) ? \$data->{$name} : null ?>";

                switch ($field['type']) {
                    case 'textarea':
                    case 'mce':
                        $extraClass = $field['type'] === 'mce' ? ' mce' : '';
                        $fieldsHtml .= <<<HTML
    <div class="col-lg-12">
        <div class="form-group has-float-label">
            <label for="{$name}">{$titulo}</label>
            <textarea class="form-control{$extraClass}" id="{$name}" name="{$name}" rows="3" {$required}>{$value}</textarea>
        </div>
    </div>

HTML;
                        break;
                    case 'number':
                        $fieldsHtml .= <<<HTML
    <div class="col-lg-12">
        <div class="form-group has-float-label">
            <label for="{$name}">{$titulo}</label>
            <input type="number" class="form-control" id="{$name}" name="{$name}" value="{$value}" placeholder="{$titulo}" {$required}>
        </div>
    </div>

HTML;
                        break;
                    case 'date':
                        $fieldsHtml .= <<<HTML
    <div class="col-lg-12">
        <div class="form-group has-float-label">
            <label for="{$name}">{$titulo}</label>
            <input type="date" class="form-control" id="{$name}" name="{$name}" value="{$value}" placeholder="{$titulo}" {$required}>
        </div>
    </div>

HTML;
                        break;
                    case 'datatime':
                        $fieldsHtml .= <<<HTML
    <div class="col-lg-12">
        <div class="form-group has-float-label">
            <label for="{$name}">{$titulo}</label>
            <input type="datetime-local" class="form-control" id="{$name}" name="{$name}" value="{$value}" placeholder="{$titulo}" {$required}>
        </div>
    </div>

HTML;
                        break;
                    case 'foreign':
                        $fieldsHtml .= <<<HTML
    <div class="col-lg-12">
        <div class="form-group has-float-label">
            <hr>
            <h6 class="tx-primary mb-4">{$titulo}</h6>
            <?php \$this->insert("components/select-file", [
                "name" => "{$name}",
                "selected" => (!empty(\$data->{$name}) ? \$data->{$name} : null),
                "tipoDeArquivo" => "images"
            ]); ?>
            <hr>
        </div>
    </div>

HTML;
                        break;
                    default:
                        $fieldsHtml .= <<<HTML
    <div class="col-lg-12">
        <div class="form-group has-float-label">
            <label for="{$name}">{$titulo}</label>
            <input type="text" class="form-control" id="{$name}" name="{$name}" value="{$value}" placeholder="{$titulo}" {$required}>
        </div>
    </div>

HTML;
                }
            }

            $viewContent = <<<PHP
<?php
/**
 * @var \\League\\Plates\\Template\\Template \$this
 * @var \\Source\\Models\\{$className}|null \$data
 */
 
\$this->layout("_admin");
?>

<main>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <h1>{$crudName}</h1>
                <nav class="breadcrumb-container d-none d-sm-block d-lg-inline-block" aria-label="breadcrumb">
                    <ol class="breadcrumb pt-0">
                        <li class="breadcrumb-item"><a href="<?= url("/admin/dash/home") ?>">Dashboard</a></li>
                        <li class="breadcrumb-item">{$menuName}</li>
                        <li class="breadcrumb-item active" aria-current="page">{$crudName}</li>
                    </ol>
                </nav>
                <div class="separator mb-5"></div>
            </div>
        </div>

        <form action="<?= url("/admin/{$menuName}/{$functionName}/save"); ?>" method="post" enctype="multipart/form-data">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-body row">
                            <div class="col-lg-12">
                                <h5 class='mb-4'>Informações</h5>
                            </div>
{$fieldsHtml}
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <?php if (!empty(\$data)) : ?>
                                <div class="custom-switch custom-switch-primary-inverse mb-4">
                                    <input class="custom-switch-input js_active float-left" id="ativar" type="checkbox"
                                        name="ativar" value="1"
                                        data-action="<?= url("/admin/{$menuName}/{$functionName}/active") ?>"
                                        data-id="<?= \$data->id ?>" <?= (!empty(\$data->ativar) && \$data->ativar == 1 ? "checked" : "") ?>>
                                    <label class="custom-switch-btn" for="ativar"></label>
                                    <h6 class="float-left ml-2 mt-1">Ativar</h6>
                                </div>
                            <?php else : ?>
                                <div class="custom-switch custom-switch-primary-inverse mb-4">
                                    <input class="custom-switch-input float-left" id="ativar" type="checkbox"
                                        name="ativar" value="1" checked>
                                    <label class="custom-switch-btn" for="ativar"></label>
                                    <h6 class="float-left ml-2 mt-1">Ativar</h6>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <button type="submit" class="btn btn-success mb-1 js_save">SALVAR</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>
PHP;

            $viewDir = ROOT_DIR . "themes/admin/widgets/{$menuName}/{$functionName}";
            if (!is_dir($viewDir)) {
                mkdir($viewDir, 0777, true);
            }

            $filePath = "{$viewDir}/{$functionName}.php";
            file_put_contents($filePath, $viewContent, FILE_APPEND);
            echo "View gerada: {$filePath}\n";
        }
    }

    /**
     * @return void
     */
    public function createRoutes(): void
    {
        echo str_repeat("#", 100) . "\n";

        try {
            $files = $this->getArquivos();
        } catch (\Exception $exception) {
            echo $exception->getMessage();
            return;
        }

        $filePath = $files->diretorio . '/storage/tags_mapeadas.txt';
        if (!file_exists($filePath)) {
            echo "O arquivo contendo as tags mapeadas não foi encontrado.\n";
            return;
        }

        $conteudo = file_get_contents($filePath);
        $blocos = preg_split('/=+\s*/', $conteudo);
        $blocos = array_filter(array_map('trim', $blocos));

        $routesContent = "";

        foreach ($blocos as $bloco) {
            preg_match('/Arquivo:\s*(.+\.php)/', $bloco, $matchArquivo);
            if (empty($matchArquivo)) continue;

            $arquivo = trim($matchArquivo[1]);
            $baseName = pathinfo($arquivo, PATHINFO_FILENAME);

            $className = "Pagina" . str_replace(" ", "", ucwords(str_replace("-", " ", $baseName)));
            $functionName = (str_replace(" ", "_", strtolower(str_replace("-", "_", $baseName))));
            $menuName = 'pagina';

            $routesContent .= "// {$functionName}\n";
            $routesContent .= "\$route->get('/{$menuName}/{$functionName}/{$functionName}', '{$className}:{$functionName}');\n";
            $routesContent .= "\$route->post('/{$menuName}/{$functionName}/save', '{$className}:save');\n";
            $routesContent .= "\$route->post('/{$menuName}/{$functionName}/active', '{$className}:active');\n\n";
        }

        if (!is_dir($files->diretorio . '/storage')) {
            mkdir($files->diretorio . '/storage', 0777, true);
        }

        $destino = $files->diretorio . '/storage/rotas.txt';
        file_put_contents($destino, $routesContent, FILE_APPEND);
        echo "Arquivo de rotas gerado: storage/rotas.txt\n";
    }

    /**
     * @return void
     */
    public function processFinalAdjustments(): void
    {
        try {
            $files = $this->getArquivos();
            echo "Mapeando views no diretório: {$files->diretorio}" . PHP_EOL;
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
                $arquivo !== 'error.php' &&
                $arquivo !== '_theme.php'
            ) {
                $fileName = "{$clienteDir}/{$arquivo}";
                $html = file_get_contents($fileName);
                if (!str_contains($html, 'data-field-name') &&
                    !str_contains($html, 'data-field-type') &&
                    !str_contains($html, 'data-table-ref')) {
                    continue;
                }

                $replaced = $this->replaceFieldTagsWithPHPVariables($html);
                $final = $this->injectPhpDocModelHint($fileName, $replaced);
                file_put_contents($fileName, $final);
            }
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
        echo "Iniciando rotinas para criar CRUD's.\n";

        try {
            $this->mapViews();
        } catch (\Exception $mpExcep) {
            echo $mpExcep->getMessage();
            return;
        }

        echo "Views mapeadas com sucesso!\nIniciando leitura de campos nestes arquivos mapeados.\n";

        try {
            $this->scanFieldTags();
        } catch (\Exception $scanExcep) {
            echo $scanExcep->getMessage();
            return;
        }

        echo "Os campos foras escaneados com sucesso!\n";

        try {
            $this->applyDatabaseChanges();
        } catch (\Exception $dbExcep) {
            echo $dbExcep->getMessage();
            return;
        }

        echo "Banco de dados criado com sucesso!\n";

        try {
            $this->createRoutes();
        } catch (\Exception $routesExcep) {
            echo $routesExcep->getMessage();
            return;
        }

        echo "Arquivo com rotas criado com sucesso!\n";

        try {
            $this->createModel();
        } catch (\Exception $modelExcep) {
            echo $modelExcep->getMessage();
            return;
        }

        echo "Model criada com sucesso!\n";

        try {
            $this->createController();
        } catch (\Exception $controllerExcep) {
            echo $controllerExcep->getMessage();
            return;
        }

        echo "Controller criado com sucesso!\n";

        try {
            $this->createView();
        } catch (\Exception $viewExcep) {
            echo $viewExcep->getMessage();
            return;
        }

        echo "View criada com sucesso!\n";
        echo str_repeat("#", 100) . "\n";

        $this->processFinalAdjustments();
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

    /**
     * Substitui os campos com data-field-* para variáveis PHP
     */
    private function replaceFieldTagsWithPHPVariables(string $content): string
    {
        $pattern = '/<(?P<tag>\w+)(?P<attrs>[^>]*?)\sdata-field-name=["\'](?P<field>[^"\']+)["\'](?P<rest>[^>]*)>(?P<inner>.*?)<\/\1>/si';

        return preg_replace_callback($pattern, function ($matches) {
            $tag = $matches['tag'];
            $attrs = $matches['attrs'] . $matches['rest'];

            $attrs = preg_replace('/\s(data-field-name|data-field-type|data-table-ref)=["\'][^"\']*["\']/', '', $attrs);
            $attrs = trim($attrs);

            $openTag = "<{$tag}" . ($attrs ? " {$attrs}" : '') . ">";

            return "{$openTag}<?= \$pagina->{$matches['field']} ?></{$tag}>";
        }, $content);
    }

    /**
     * Insere o PHPDoc com o tipo da variável $pagina
     */
    private function injectPhpDocModelHint(string $filename, string $content): string
    {
        $base = basename($filename, ".php");
        $className = 'Pagina' . str_replace(' ', '', ucwords(str_replace('-', ' ', $base)));

        if (preg_match('/<\?php\s+\/\*\*\s+\* @var \\\\League\\\\Plates\\\\Template\\\\Template \$this.*?\*\//s', $content)) {
            return preg_replace(
                '/(<\?php\s+\/\*\*\s+\* @var \\\\League\\\\Plates\\\\Template\\\\Template \$this)(.*?\*\/)/s',
                '$1' . "\n * @var \\\\Source\\\\Models\\\\{$className} \$pagina\n */",
                $content
            );
        }

        return $content;
    }
}
