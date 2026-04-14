# 🚗 GarageSystem

Sistema ERP completo para oficinas mecânicas, focado em gestão de ordens de serviço, clientes, veículos, estoque e financeiro.

---

## 📌 Sobre o Projeto

O **GarageSystem** é um sistema web desenvolvido para facilitar o gerenciamento de oficinas mecânicas, permitindo controle total sobre:

* Ordens de serviço (O.S)
* Clientes e veículos
* Produtos e estoque
* Financeiro básico
* Histórico de atendimentos

O objetivo é centralizar todas as operações da oficina em um único sistema simples, eficiente e escalável.

---

## 🎯 Objetivo

* Organizar processos da oficina
* Reduzir erros operacionais
* Controlar custos e lucros
* Melhorar atendimento ao cliente
* Facilitar crescimento do negócio

---

## 🛠️ Tecnologias Utilizadas

* **Backend:** PHP 8+
* **Frontend:** HTML5, CSS3, JavaScript
* **Banco de Dados:** MySQL
* **Arquitetura:** MVC (Model-View-Controller)

---

## 📂 Estrutura do Projeto

```
garage-system/
├── app/
│   ├── controllers/
│   ├── models/
│   ├── services/
│   ├── repositories/
│   ├── middlewares/
│   ├── helpers/
│   └── core/
│
├── config/
├── public/
├── views/
├── storage/
├── database/
├── routes/
├── .env
├── composer.json
└── README.md
```

---

## ⚙️ Funcionalidades

### 👤 Clientes

* Cadastro completo
* Histórico de serviços

### 🚘 Veículos

* Cadastro por cliente
* Histórico de manutenção

### 📄 Ordens de Serviço (O.S)

* Criação e gerenciamento
* Controle de status
* Alteração de valores com histórico
* Descrição de problemas e diagnósticos

### 🧰 Produtos / Estoque

* Cadastro de peças e insumos
* Controle de entrada e saída
* Associação com O.S

### 💰 Financeiro

* Registro de pagamentos
* Controle de faturamento
* Relatórios básicos

### 👨‍🔧 Usuários

* Controle de acesso (Admin, Mecânico, Atendente)

---

## 🧠 Regras de Negócio

* Não é permitido finalizar O.S sem valor final
* Alterações de valor exigem justificativa
* Estoque não pode ficar negativo
* O.S finalizada não pode ser editada
* Cada veículo pertence a um único cliente

---

## 🚀 Instalação

### 1. Clone o projeto

```bash
git clone https://github.com/seu-usuario/garage-system.git
cd garage-system
```

### 2. Configure o ambiente

Crie o arquivo `.env`:

```
DB_HOST=localhost
DB_NAME=garage_system
DB_USER=root
DB_PASS=
```

### 3. Configure o banco de dados

* Crie o banco:

```sql
CREATE DATABASE garage_system;
```

* Execute os arquivos em:

```
database/migrations/
```

---

### 4. Suba o servidor local

```bash
php -S localhost:8000 -t public
```

Acesse:

```
http://localhost:8000
```

---

## 🔐 Segurança

* Senhas criptografadas (bcrypt)
* Proteção contra SQL Injection (PDO)
* Controle de sessão
* Middleware de autenticação

---

## 📊 Funcionalidades Futuras

* Dashboard com gráficos
* Integração com WhatsApp
* Geração de PDF (O.S e recibos)
* Sistema de agendamento
* Controle de manutenção preventiva
* API REST completa
* Aplicativo mobile

---

## 🧩 Padrões Utilizados

* MVC (Model-View-Controller)
* Service Layer
* Repository Pattern (opcional)
* Front Controller
* Separation of Concerns

---

## 📌 Roadmap

### ✅ MVP

* Clientes
* Veículos
* O.S
* Produtos
* Financeiro básico

### 🔜 V2

* Relatórios
* Dashboard
* Logs de auditoria

### 🚀 V3

* Notificações
* Mobile
* Integrações externas

---

## 🤝 Contribuição

Contribuições são bem-vindas!

1. Fork o projeto
2. Crie uma branch
3. Commit suas alterações
4. Abra um Pull Request

---

## 📄 Licença

Este projeto está sob a licença MIT.

---

## 👨‍💻 Autor

Desenvolvido por **Wesley Ruan**

---

## 💡 Observação

Este projeto foi estruturado com foco em boas práticas de arquitetura e pode evoluir facilmente para um sistema SaaS completo.

---
