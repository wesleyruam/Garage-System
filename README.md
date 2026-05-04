# 🚗 Garage System - ERP para Oficina Mecânica

Sistema completo de gestão (ERP) para oficinas mecânicas, desenvolvido
com foco em organização operacional, controle financeiro e
rastreabilidade de serviços.

------------------------------------------------------------------------

## 📌 Visão Geral

O **Garage System** é uma aplicação web desenvolvida em PHP com
arquitetura modular baseada em MVC, projetada para gerenciar todas as
operações de uma oficina mecânica.

------------------------------------------------------------------------

## 🏗️ Arquitetura

Estrutura baseada em separação de responsabilidades:

-   Controllers → requisições
-   Models → dados
-   Repositories → banco
-   Services → regras de negócio
-   Middlewares → segurança
-   Core → base do sistema

------------------------------------------------------------------------

## ⚙️ Funcionalidades

-   Gestão de clientes
-   Controle de veículos
-   Ordens de serviço (OS)
-   Estoque
-   Financeiro
-   Relatórios
-   Autenticação e permissões

------------------------------------------------------------------------

## 🛡️ Segurança

-   CSRF Protection
-   Rate Limiting
-   Controle de acesso por roles
-   Validação de dados

------------------------------------------------------------------------

## 🗄️ Banco de Dados

Principais entidades:

-   clientes
-   veiculos
-   ordem_servico
-   produtos
-   pagamentos
-   usuarios

------------------------------------------------------------------------

## 🚀 Como rodar

``` bash
git clone https://github.com/wesleyruam/Garage-System.git
cd Garage-System
php -S localhost:8000 -t public
```

------------------------------------------------------------------------

## 🧠 Aprendizados

-   Arquitetura MVC real
-   Organização escalável
-   Segurança web
-   ERP na prática

------------------------------------------------------------------------

## 👨‍💻 Autor

Wesley Ruan
