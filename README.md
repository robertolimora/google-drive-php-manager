# Google Drive PHP Manager

Este projeto fornece uma interface web moderna para gerenciar arquivos do Google Drive com PHP. Ele permite **upload, download, criação de pastas, renomear, navegação entre diretórios, pesquisa e paginação**, além de autenticação de usuários e armazenamento seguro de tokens no **MySQL**.

---

## 🚀 Funcionalidades

- Login de usuário (sistema interno com MySQL)
- Integração com a Google Drive API (OAuth 2.0)
- Upload e download de arquivos
- Criação e renomeação de pastas/arquivos
- Miniaturas (thumbnails) com proxy autenticado
- Pesquisa de arquivos e pastas
- Paginação e breadcrumbs de navegação
- Armazenamento seguro de tokens no banco de dados (MySQL)

---

## 🧰 Requisitos

- PHP 8.0 ou superior
- Servidor Apache (com mod_rewrite habilitado)
- Composer instalado
- Banco de dados MySQL
- Credenciais OAuth 2.0 da Google API (Drive API habilitada)

---

## ⚙️ Instalação

### 1. Clonar o projeto
```bash
git clone https://seu-repositorio.git
tar -C /var/www/html/google-drive-manager
cd /var/www/html/google-drive-manager
```

### 2. Instalar dependências
```bash
composer require google/apiclient
```

### 3. Criar o banco de dados
Crie um banco MySQL e execute o script abaixo:

```sql
CREATE DATABASE drive_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE drive_manager;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    access_token TEXT NOT NULL,
    refresh_token TEXT,
    expires_in INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 4. Criar o primeiro usuário
```php
<?php
$pdo = new PDO('mysql:host=localhost;dbname=drive_manager', 'usuario', 'senha');
$pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)')
    ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
echo "Usuário criado!";
```

### 5. Configurar o acesso ao Google API
1. Acesse o [Google Cloud Console](https://console.cloud.google.com/)
2. Crie um projeto e habilite a **Drive API**
3. Vá em **APIs e Serviços → Credenciais → Criar credenciais → ID do cliente OAuth 2.0**
4. Configure o **URI de redirecionamento** como:
   ```
   https://seu-dominio.com/google-drive-php-manager.php
   ```
5. Baixe o arquivo `credentials.json` e coloque-o na raiz do projeto.

### 6. Configurar variáveis do projeto
Edite as variáveis no topo do arquivo principal `google-drive-php-manager.php`:

```php
$db_host = 'localhost';
$db_name = 'drive_manager';
$db_user = 'usuario';
$db_pass = 'senha';
```

---

## 🧑‍💻 Uso

1. Acesse o sistema via navegador:  
   `https://seu-dominio.com/google-drive-php-manager.php`

2. Faça login com o usuário criado.
3. Conecte sua conta Google quando solicitado.
4. Navegue, envie, baixe e gerencie arquivos diretamente da interface.

---

## 🔒 Segurança

- As senhas são armazenadas com `password_hash()` e verificadas com `password_verify()`.
- Tokens são salvos por usuário e criptografados antes de ir ao banco.
- É recomendado usar HTTPS em produção.
- Defina permissões seguras na pasta do projeto (`chmod -R 755`).

---

## 🧩 Estrutura do Projeto
```
/drive-manager
│
├── google-drive-php-manager.php   # Script principal
├── credentials.json               # Credenciais da Google API
├── README.md                      # Este arquivo
└── vendor/                        # Dependências do Composer
```

---

## 🧱 Tecnologias Usadas
- PHP 8+
- Google API Client Library for PHP
- MySQL (PDO)
- Bootstrap 5

---

## 📜 Licença

Este projeto é distribuído sob a licença MIT. Você pode usar, modificar e redistribuir livremente, desde que mantenha os créditos.

