# Gerador CLI para Sites Institucionais

Ferramenta CLI desenvolvida pela **ElxDigital** para auxiliar na criação e manutenção de projetos PHP institucionais baseados em estrutura modular de temas.

Este pacote pode ser adicionado como dependência via Composer e executado diretamente a partir do terminal, realizando operações como mapeamento de views, geração de arquivos, entre outros.

---

## 🚀 Instalação

No projeto que você deseja utilizar o gerador, adicione o repositório do GitHub como fonte:

```json
"repositories": [
  {
    "type": "vcs",
    "url": "https://github.com/elxdigital/gerador"
  }
]
```

Então execute:

```bash
composer require elxdigital/gerador:dev-main
```

---

## 📦 Requisitos

- PHP 8.1 ou superior
- Composer
- Estrutura do projeto contendo a pasta `themes/[NOME_DO_TEMA]/`

> ⚠️ Por padrão, o gerador busca o nome do tema na constante `CONF_VIEW_THEME`. Essa constante deve estar definida no seu projeto como, por exemplo:

```php
define("CONF_VIEW_THEME", "testes_gerador");
```

---

## 🧪 Como Usar

Após instalado, execute os comandos diretamente na raiz do projeto, usando:

```bash
php vendor/bin/generate <comando>
```

---

## 🛠️ Comandos Disponíveis

### `map:views`

Lista todas as views `.php` encontradas na raiz do diretório de tema, exceto os arquivos `_theme.php` e `error.php`.

**Exemplo:**

```bash
php vendor/bin/generate map:views
```

**Saída esperada:**

```
Mapeando views no diretório: C:\xampp\htdocs\meuprojeto\themes\testes_gerador
Views encontradas:
- home.php
- contato.php
- quem-somos.php
```

---

## 📁 Estrutura Esperada

```
/meuprojeto/
└── themes/
    └── testes_gerador/
        ├── home.php
        ├── contato.php
        ├── _theme.php        ← ignorado
        ├── error.php         ← ignorado
        ├── components/       ← ignorado
        └── ...
```

---

## 📌 Desenvolvimento Futuro

Em breve novos comandos serão adicionados, como:

- `create:view` – Gerar views com base em templates
- `scan:components` – Listar componentes reutilizáveis
- `build:menu` – Gerar menus com base nas views existentes
- `sync:assets` – Copiar arquivos de estilo/padrão entre projetos

---

## 📄 Licença

MIT © [ElxDigital](https://github.com/elxdigital)
