# Alabama Navigation System

Sistema de navegação inteligente completo para o painel administrativo da Rede Alabama.

## 📋 Visão Geral

O sistema inclui:

- **Sidebar colapsável** com 10 categorias e ~45 páginas
- **Busca global** com Ctrl+K
- **Sistema de badges** para notificações em tempo real
- **Favoritos** persistentes por usuário
- **Design responsivo** para desktop, tablet e mobile
- **Controle de acesso** baseado em roles (Admin/Gerente/Vendedor)

## 🚀 Componentes

### 1. Sidebar Menu

**Arquivo:** `includes/sidebar_menu.php`

Menu lateral com 10 categorias:

1. 📊 Dashboard
2. 👥 CRM
3. 💰 Vendas
4. 📢 Marketing
5. 💬 WhatsApp
6. 🧠 Inteligência Artificial
7. 📦 Estoque
8. 📊 Relatórios
9. ⚡ Automação
10. ⚙️ Configurações

#### Recursos:

- **Largura padrão:** 280px
- **Modo mini:** 70px (apenas ícones)
- **Estado persistente:** localStorage
- **Categorias expansíveis:** Salva estado no localStorage
- **Badges:** Contadores em tempo real
- **Favoritos:** Estrelas para marcar páginas importantes

### 2. Busca Global

**Arquivo:** `includes/global_search.php`

Modal de busca universal acessível via Ctrl+K.

#### Recursos:

- **Atalho:** Ctrl+K (Windows/Linux) ou Cmd+K (Mac)
- **Busca em tempo real** em nome, categoria e URL
- **Navegação por teclado:** ↑↓ para navegar, Enter para abrir, ESC para fechar
- **Histórico:** Usa localStorage para lembrar buscas recentes (futuro)

### 3. API de Badges

**Arquivo:** `api/menu_badges.php`

Endpoint que retorna contadores para badges do menu.

#### Badges disponíveis:

- `new_leads`: Leads criados hoje
- `unread_messages`: Conversas WhatsApp não lidas
- `active_campaigns`: Campanhas de remarketing ativas
- `pending_tasks`: Tarefas pendentes do usuário

#### Uso:

```javascript
GET api/menu_badges.php

// Resposta:
{
  "success": true,
  "badges": {
    "new_leads": 5,
    "unread_messages": 12,
    "active_campaigns": 3,
    "pending_tasks": 0
  },
  "timestamp": 1702744800
}
```

**Atualização automática:** A cada 30 segundos via AJAX.

### 4. API de Favoritos

**Arquivo:** `api/favorites.php`

Gerencia favoritos do usuário (adicionar, remover, reordenar).

#### Endpoints:

**Listar favoritos:**
```javascript
GET api/favorites.php

// Resposta:
{
  "success": true,
  "favorites": [
    {
      "id": 1,
      "page_url": "nova_venda.php",
      "page_label": "Nova Venda",
      "page_icon": "fa-cash-register",
      "sort_order": 0,
      "created_at": "2024-01-15 10:30:00"
    }
  ]
}
```

**Adicionar favorito:**
```javascript
POST api/favorites.php
{
  "action": "add",
  "page_url": "nova_venda.php",
  "page_label": "Nova Venda",
  "page_icon": "fa-cash-register",
  "_csrf_token": "TOKEN"
}
```

**Remover favorito:**
```javascript
POST api/favorites.php
{
  "action": "remove",
  "page_url": "nova_venda.php",
  "_csrf_token": "TOKEN"
}
```

**Reordenar favoritos:**
```javascript
POST api/favorites.php
{
  "action": "reorder",
  "order": ["nova_venda.php", "leads.php", "vendas.php"],
  "_csrf_token": "TOKEN"
}
```

### 5. Assets

#### CSS
**Arquivo:** `assets/css/alabama-navigation.css`

- Sidebar e componentes
- Modal de busca
- Badges e favoritos
- Design responsivo
- Animações e transições
- Compatível com Alabama Design System

#### JavaScript
**Arquivo:** `assets/js/navigation.js`

- Toggle do sidebar
- Collapse de categorias
- Modal de busca com Ctrl+K
- AJAX para badges (30s)
- Gerenciamento de favoritos
- Navegação por teclado

## 🗄️ Banco de Dados

### Tabela: user_favorites

```sql
CREATE TABLE user_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    page_url VARCHAR(255) NOT NULL,
    page_label VARCHAR(255) NOT NULL,
    page_icon VARCHAR(100) DEFAULT 'fa-star',
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_page (user_id, page_url),
    INDEX idx_user_sort (user_id, sort_order)
);
```

**Migration:** Executada automaticamente via `database/auto_migrate.php`.

## 🎨 Customização

### Adicionar nova categoria ao menu

Edite `includes/sidebar_menu.php` e adicione ao array `$menuCategories`:

```php
'minha_categoria' => [
    'icon' => 'fa-icon-name',
    'label' => '🎯 Minha Categoria',
    'roles' => ['Administrador', 'Gerente'],
    'items' => [
        [
            'url' => 'minha_pagina.php',
            'label' => 'Minha Página',
            'icon' => 'fa-page-icon',
            'roles' => ['Administrador', 'Gerente'],
            'badge_type' => 'my_badge' // Opcional
        ]
    ]
]
```

### Adicionar nova página à busca

Edite `includes/global_search.php` e adicione ao array `$searchablePages`:

```php
[
    'url' => 'minha_pagina.php',
    'label' => 'Minha Página',
    'icon' => 'fa-page-icon',
    'category' => 'Minha Categoria',
    'roles' => ['Administrador', 'Gerente']
]
```

### Adicionar novo badge

1. Edite `api/menu_badges.php` e adicione a query:

```php
// my_badge: COUNT de algo
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tabela WHERE condicao");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $badges['my_badge'] = (int)($result['total'] ?? 0);
} catch (Throwable $e) {
    $badges['my_badge'] = 0;
}
```

2. No menu, adicione `'badge_type' => 'my_badge'` ao item.

### Alterar cores do tema

Edite `assets/css/alabama-navigation.css` ou use variáveis CSS do Alabama Design System:

```css
:root {
    --al-primary: #8b5cf6;      /* Roxo */
    --al-accent: #3b82f6;       /* Azul */
    --al-success: #22c55e;      /* Verde */
    --al-bg-surface: #12122a;   /* Fundo escuro */
}
```

## 📱 Responsividade

### Breakpoints:

- **Desktop (>1024px):** Sidebar fixo à esquerda
- **Tablet (768-1024px):** Sidebar drawer com overlay
- **Mobile (<768px):** Sidebar fullscreen drawer

### Comportamento mobile:

1. Sidebar oculto por padrão
2. Botão hamburger abre o drawer
3. Overlay escuro ao fundo
4. Clique fora fecha o drawer

## ⌨️ Atalhos de Teclado

| Atalho | Ação |
|--------|------|
| `Ctrl+K` ou `Cmd+K` | Abrir busca global |
| `ESC` | Fechar busca |
| `↑` `↓` | Navegar resultados |
| `Enter` | Abrir página selecionada |

## 🔐 Controle de Acesso

O sistema respeita os níveis de acesso definidos:

- **Administrador:** Acesso total
- **Gerente:** Acesso a operações e relatórios
- **Vendedor:** Acesso limitado às suas vendas e operações

Cada item do menu e página da busca define seus próprios roles permitidos.

## 🚀 Instalação

O sistema já está instalado e integrado. Para usar em novas páginas:

### Opção 1: Usar layout_header.php e layout_footer.php

```php
<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/acesso_restrito.php';

$page_title = 'Minha Página';
include __DIR__ . '/includes/layout_header.php';
?>

<!-- Seu conteúdo aqui -->

<?php include __DIR__ . '/includes/layout_footer.php'; ?>
```

### Opção 2: Incluir manualmente

```php
<!-- No <head> -->
<link rel="stylesheet" href="assets/css/alabama-navigation.css">

<!-- Após o <body> -->
<?php include __DIR__ . '/includes/sidebar_menu.php'; ?>
<?php include __DIR__ . '/includes/global_search.php'; ?>

<div class="alabama-main-wrapper">
    <main class="alabama-content">
        <!-- Seu conteúdo aqui -->
    </main>
</div>

<!-- Antes do </body> -->
<script src="assets/js/navigation.js"></script>
```

## 🧪 Testes

Acesse `navigation_demo.php` para ver todas as funcionalidades em ação.

## 🐛 Troubleshooting

### Sidebar não aparece

- Verifique se `includes/sidebar_menu.php` existe
- Confirme que a sessão está ativa (`$_SESSION['usuario_id']` definido)
- Verifique o console do navegador para erros JS

### Busca não abre com Ctrl+K

- Verifique se `includes/global_search.php` está incluído
- Confirme que `assets/js/navigation.js` está carregado
- Verifique conflitos com outros atalhos de teclado

### Badges não atualizam

- Confirme que `api/menu_badges.php` está acessível
- Verifique logs do servidor para erros SQL
- Confirme que as tabelas necessárias existem

### Favoritos não salvam

- Confirme que a tabela `user_favorites` existe
- Execute `database/auto_migrate.php` se necessário
- Verifique se o CSRF token está sendo passado
- Confirme que `api/favorites.php` está acessível

### Sidebar não recolhe em mobile

- Verifique se o viewport está configurado corretamente
- Confirme que o CSS está carregado
- Teste em diferentes tamanhos de tela (F12 > responsive mode)

## 📝 Changelog

### v1.0.0 (2024-12-16)

- ✅ Sidebar colapsável com 10 categorias
- ✅ Busca global com Ctrl+K
- ✅ Sistema de badges
- ✅ API de favoritos
- ✅ Design responsivo
- ✅ Controle de acesso por roles
- ✅ Documentação completa

## 🤝 Contribuindo

Para adicionar novas funcionalidades:

1. Adicione páginas ao menu em `sidebar_menu.php`
2. Adicione à busca em `global_search.php`
3. Crie badges conforme necessário em `menu_badges.php`
4. Teste em diferentes resoluções
5. Documente mudanças neste arquivo

## 📄 Licença

Sistema proprietário da Rede Alabama.

## 👨‍💻 Suporte

Para suporte, entre em contato com a equipe de desenvolvimento.
