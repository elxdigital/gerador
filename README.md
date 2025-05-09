# 🚀 Gerador de Código CLI – `elxdigital/gerador`

Este pacote é um conjunto de comandos CLI em PHP para acelerar a criação de estruturas de banco de dados e cadastros a partir de arquivos `.php` com campos HTML dinâmicos (`data-field-name`).  

Ele é ideal para projetos institucionais estruturados em PHP + MVC.

---

## 📦 Instalação

Adicione ao seu projeto via Composer:

```bash
composer require elxdigital/gerador
```

---

## ⚙️ Requisitos

- PHP >= 8.1
- `CONF_VIEW_THEME` definido no seu projeto (em constante ou `.env`)
- Diretório de views: `themes/{CONF_VIEW_THEME}/`
- Arquivos `.php` contendo tags com `data-field-name`

---

## 🛠️ Comandos disponíveis

### 🔹 `map:views`

Mapeia os arquivos `.php` em `themes/{CONF_VIEW_THEME}/`, ignorando `error.php` e `_theme.php`.

---

### 🔹 `read:fields`

Lê os arquivos `.php` mapeados e identifica as tags HTML com `data-field-name`.  
Gera:

- `storage/tags_mapeadas.txt` → log técnico dos campos
- `storage/tabelas.sql` → instruções `CREATE TABLE`
- `storage/inserts.sql` → instruções `INSERT`

#### 🧩 Tipos reconhecidos (`data-field-type`)
| HTML / data-field-type | Tipo SQL gerado                      |
|------------------------|--------------------------------------|
| `textarea`, `mce`      | `TEXT DEFAULT NULL`                  |
| `text`, `varchar`      | `VARCHAR(255) DEFAULT NULL`          |
| `int`                  | `INT(11) UNSIGNED DEFAULT NULL`      |
| `date`                 | `DATE DEFAULT NULL`                  |
| `timestamp`            | `TIMESTAMP NULL DEFAULT NULL`        |
| `foreign`              | `INT(11) UNSIGNED DEFAULT NULL` + `FOREIGN KEY` (requer `data-table-ref`)

---

### 🔹 `create:model`

Gera o arquivo `Model` em `source/Models/` baseado nos campos detectados, com docblocks e construtor padrão.  
A classe gerada se chama `PaginaNomeDaPagina` e a tabela `pagina_nome_da_pagina`.

---

### 🔹 `create:controller`

Gera o `Controller` com os métodos:
- `nomepagina()` → renderiza a view
- `save(array $data)` → salva o registro único
- `active(array $data)` → ativa ou desativa o registro

A classe gerada se chama `PaginaNomeDaPagina` e vai para `source/App/Admin/`.

---

### 🔹 `create:view`

Gera a view padrão (`themes/admin/widgets/{menu}/{funcao}/{funcao}.php`) contendo os campos dinâmicos.  
Cada campo usa o tipo correto de `input`, `textarea`, `mce`, etc.

---

### 🔹 `create:routes`

Gera o bloco de rotas em `storage/rotas.php` para ser copiado para o `index.php`:

```php
// exemplo
$route->get('/contato/contato/contato', 'PaginaContato:contato');
$route->post('/contato/contato/save', 'PaginaContato:save');
$route->post('/contato/contato/active', 'PaginaContato:active');
```

---

### 🔹 `db:apply`

Aplica os arquivos `storage/tabelas.sql` e `storage/inserts.sql` no banco de dados atual.

Configure via `.env`:

```env
CONF_DB_HOST=localhost
CONF_DB_NAME=seubanco
CONF_DB_USER=root
CONF_DB_PASS=
```

---

### 🔹 `generate:all`

Executa os seguintes comandos em sequência:

1. `map:views`
2. `read:fields`
3. `create:model`
4. `create:controller`
5. `create:view`
6. `create:routes`
7. `db:apply`

---

## ✅ Estrutura esperada

```
project-root/
│
├── themes/
│   └── {CONF_VIEW_THEME}/
│       ├── home.php
│       ├── contato.php
│       └── ...
│
├── storage/
│   ├── teste.txt
│   ├── tabelas.sql
│   ├── inserts.sql
│   └── rotas.php
│
├── source/
│   ├── Models/
│   └── App/Admin/
│
└── composer.json
```
