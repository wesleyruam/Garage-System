---

# 🗂️ Estrutura de Diretórios — GarageSystem

```bash
garage-system/
│
├── app/
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── ClienteController.php
│   │   ├── VeiculoController.php
│   │   ├── OrdemServicoController.php
│   │   ├── ProdutoController.php
│   │   ├── EstoqueController.php
│   │   ├── FinanceiroController.php
│   │   └── DashboardController.php
│   │
│   ├── models/
│   │   ├── Cliente.php
│   │   ├── Veiculo.php
│   │   ├── OrdemServico.php
│   │   ├── Produto.php
│   │   ├── Usuario.php
│   │   ├── Pagamento.php
│   │   └── LogAlteracao.php
│   │
│   ├── services/
│   │   ├── OrdemServicoService.php
│   │   ├── EstoqueService.php
│   │   ├── FinanceiroService.php
│   │   ├── NotificacaoService.php
│   │   └── RelatorioService.php
│   │
│   ├── repositories/   # (Opcional, mas profissional)
│   │   ├── ClienteRepository.php
│   │   ├── OrdemServicoRepository.php
│   │   └── ProdutoRepository.php
│   │
│   ├── middlewares/
│   │   ├── AuthMiddleware.php
│   │   ├── AdminMiddleware.php
│   │   └── PermissionMiddleware.php
│   │
│   ├── helpers/
│   │   ├── response.php
│   │   ├── validator.php
│   │   └── utils.php
│   │
│   └── core/
│       ├── Controller.php
│       ├── Model.php
│       ├── Database.php
│       ├── Router.php
│       └── Session.php
│
├── config/
│   ├── app.php
│   ├── database.php
│   └── routes.php
│
├── public/   # raiz pública (entrypoint)
│   ├── index.php
│   ├── .htaccess
│   │
│   ├── assets/
│   │   ├── css/
│   │   │   ├── app.css
│   │   │   └── dashboard.css
│   │   │
│   │   ├── js/
│   │   │   ├── app.js
│   │   │   ├── os.js
│   │   │   └── estoque.js
│   │   │
│   │   ├── img/
│   │   └── uploads/
│   │       ├── veiculos/
│   │       └── os/
│
├── views/
│   ├── layouts/
│   │   ├── header.php
│   │   ├── footer.php
│   │   └── main.php
│   │
│   ├── auth/
│   │   ├── login.php
│   │   └── register.php
│   │
│   ├── dashboard/
│   │   └── index.php
│   │
│   ├── clientes/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── edit.php
│   │   └── show.php
│   │
│   ├── veiculos/
│   │   ├── index.php
│   │   ├── create.php
│   │   └── edit.php
│   │
│   ├── os/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── edit.php
│   │   ├── show.php
│   │   └── print.php
│   │
│   ├── produtos/
│   │   ├── index.php
│   │   ├── create.php
│   │   └── edit.php
│   │
│   └── financeiro/
│       ├── index.php
│       └── relatorio.php
│
├── storage/
│   ├── logs/
│   │   └── app.log
│   │
│   ├── cache/
│   └── sessions/
│
├── database/
│   ├── migrations/
│   │   ├── 001_create_clientes.sql
│   │   ├── 002_create_veiculos.sql
│   │   ├── 003_create_os.sql
│   │   └── ...
│   │
│   └── seeds/
│       └── seed.sql
│
├── routes/
│   ├── web.php
│   └── api.php
│
├── vendor/ (se usar composer)
│
├── .env
├── composer.json
└── README.md
```

---

# 🧠 Explicação da Arquitetura

## 🔹 `app/`

Coração do sistema.

* **controllers/** → Recebem requisições (HTTP)
* **models/** → Representam tabelas do banco
* **services/** → Regras de negócio (ESSENCIAL pra escalar)
* **repositories/** → Abstração do banco (opcional, mas top nível senior)
* **middlewares/** → Segurança e controle de acesso
* **core/** → Base do sistema (mini framework)

---

## 🔹 `public/`

Entrada do sistema (front controller)

* Tudo passa pelo `index.php`
* Contém apenas arquivos públicos

---

## 🔹 `views/`

Interface (HTML + PHP)

Separado por domínio:

* clientes/
* veiculos/
* os/
* produtos/

---

## 🔹 `config/`

Configurações globais:

* Banco
* Rotas
* App

---

## 🔹 `storage/`

Arquivos gerados:

* Logs
* Cache
* Sessões

---

## 🔹 `database/`

Controle do banco:

* migrations → versionamento
* seeds → dados iniciais

---

## 🔹 `routes/`

Separação clara:

* `web.php` → rotas normais
* `api.php` → API REST

---

# 🔥 Fluxo Real de Requisição

```text
Usuário → public/index.php → Router → Controller → Service → Model → DB
                                             ↓
                                           View
```

---

# 🧩 Exemplo de Fluxo (Criar O.S)

1. POST `/os/create`
2. Router chama:

   * `OrdemServicoController@store`
3. Controller chama:

   * `OrdemServicoService`
4. Service:

   * valida dados
   * calcula valores
   * salva no banco
5. Retorna resposta → View ou JSON

---

# 🧱 Base mínima de arquivos importantes

## 📌 `public/index.php`

```php
require_once '../vendor/autoload.php';

use App\Core\Router;

$router = new Router();
require_once '../routes/web.php';

$router->dispatch();
```

---

## 📌 `config/database.php`

```php
return [
    'host' => 'localhost',
    'dbname' => 'garage_system',
    'user' => 'root',
    'password' => ''
];
```

---

## 📌 `app/core/Database.php`

```php
class Database {
    private static $instance;

    public static function connect() {
        if (!self::$instance) {
            $config = require __DIR__ . '/../../config/database.php';

            self::$instance = new PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']}",
                $config['user'],
                $config['password']
            );
        }
        return self::$instance;
    }
}
```
