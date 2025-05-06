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
- `CONF_VIEW_THEME` definido no seu projeto (pode estar em uma constante ou `.env`)
- Diretório de views: `themes/{CONF_VIEW_THEME}/`
- Arquivos `.php` contendo tags com `data-field-name`

---

## 🛠️ Comandos disponíveis

### 🔹 `map:views`

Mapeia todos os arquivos `.php` encontrados no diretório `themes/{CONF_VIEW_THEME}/`, ignorando `error.php` e `_theme.php`.  
Lista os nomes dos arquivos que representam páginas/visões.

---

### 🔹 `read:fields`

Analisa os arquivos `.php` encontrados e identifica todas as tags HTML que contêm o atributo `data-field-name`.  
Gera automaticamente:

- `storage/teste.txt` → Log técnico
- `storage/tabelas.sql` → DDL (CREATE TABLE)
- `storage/inserts.sql` → INSERTs com os conteúdos das tags

#### 🧩 Tipos reconhecidos (`data-field-type`)
| HTML / data-field-type | Tipo SQL gerado                      |
|------------------------|--------------------------------------|
| `textarea`, `mce`      | `TEXT DEFAULT NULL`                  |
| `text`, `varchar`      | `VARCHAR(255) DEFAULT NULL`          |
| `int`                  | `INT(11) UNSIGNED DEFAULT NULL`      |
| `date`                 | `DATE DEFAULT NULL`                  |
| `timestamp`            | `TIMESTAMP NULL DEFAULT NULL`        |
| `foreign`              | `INT(11) UNSIGNED DEFAULT NULL` + `FOREIGN KEY` (requer `data-table-ref`)

#### 📌 Estrutura fixa incluída em todas as tabelas:
```sql
id INT AUTO_INCREMENT PRIMARY KEY,
ativar INT(1) DEFAULT 1,
data_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
data_update TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
```

#### 🔁 Exemplo de campo foreign:
```html
<p data-field-name="imagem" data-field-type="foreign" data-table-ref="arquivo">
```
Gera no SQL:
```sql
imagem INT(11) UNSIGNED DEFAULT NULL,
FOREIGN KEY (`imagem`) REFERENCES `arquivo`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
```

---

### 🔹 `db:apply`

Aplica os arquivos `storage/tabelas.sql` e `storage/inserts.sql` diretamente no banco de dados.  
**Certifique-se de que as credenciais do banco estejam configuradas corretamente** via constantes ou `.env`:

```env
CONF_DB_HOST=localhost
CONF_DB_NAME=seubanco
CONF_DB_USER=root
CONF_DB_PASS=
```

---

### 🔹 `generate:all`

Executa todos os comandos na sequência:

1. `map:views`
2. `read:fields`
3. `db:apply`

Ideal para rodar tudo de uma vez com um único comando:

```bash
php vendor/bin/generate generate:all
```

---

## ✅ Estrutura esperada no projeto

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
│   └── inserts.sql
│
├── .env
└── composer.json
```
