# 🤖 Modo Copiloto - Sistema de Confiança da IA

## Visão Geral

O **Modo Copiloto** é um sistema inteligente que permite à IA evoluir de um assistente passivo (apenas sugere respostas) para um copiloto ativo (responde automaticamente em casos simples). A evolução é baseada em um **score de confiança (0-100%)** que aumenta ou diminui conforme o uso e feedback do usuário.

## Como Funciona o Score

### Fórmula de Cálculo

O score de confiança (0-100%) é calculado com base em 4 componentes:

1. **Feedback Score (max 40 pontos)**
   - Baseado na taxa de respostas boas vs ruins
   - Fórmula: `(total_good / (total_good + total_bad)) * 40`

2. **Knowledge Base Score (max 20 pontos)**
   - Baseado na quantidade de conhecimento cadastrado
   - Fórmula: `min(20, (total_faq * 0.5) + (total_products * 0.3) + (total_examples * 1.0))`

3. **Usage Score (max 25 pontos)**
   - Baseado em sugestões usadas sem editar
   - Fórmula: `(total_suggestions_used / total_suggestions) * 25`

4. **Auto-Send Score (max 15 pontos)**
   - Baseado em envios automáticos bem sucedidos
   - Fórmula: `min(15, total_auto_sent * 0.5)`

**Score Total = min(100, soma dos 4 componentes)**

### Sistema de Pontos

Cada ação do usuário ganha ou perde pontos:

| Ação | Pontos | Campo Incrementado |
|------|--------|-------------------|
| ✅ Marcar resposta como boa | +2.0 | `total_good` |
| ❌ Marcar resposta como ruim | -3.0 | `total_bad` |
| ✏️ Fazer correção manual | -2.0 | `total_corrections` |
| 💡 Usar sugestão sem editar | +1.0 | `total_suggestions_used` |
| ✏️ Editar sugestão antes de enviar | -0.5 | `total_suggestions_edited` |
| 🚀 Envio automático bem sucedido | +1.5 | `total_auto_sent` |
| 📚 FAQ adicionada | +0.5 | `total_faq` |
| 🛒 Produto adicionado | +0.5 | `total_products` |
| 📝 Exemplo de treinamento | +1.0 | `total_examples` |

## Níveis de Confiança

O sistema classifica a IA em 5 níveis baseados no score:

| Score | Nível | Emoji | Comportamento |
|-------|-------|-------|--------------|
| 90-100% | 🔵 Autônomo | 🔵 | IA responde automaticamente em quase todos os casos |
| 70-89% | 🟢 Copiloto | 🟢 | **IA pode responder automaticamente casos simples** |
| 50-69% | 🟡 Assistido | 🟡 | IA sugere, você decide |
| 30-49% | 🟠 Aprendendo | 🟠 | IA em treinamento |
| 0-29% | 🔴 Iniciante | 🔴 | IA apenas sugere respostas |

## Comportamento do Modo Copiloto

### Quando o Modo Copiloto Está Ativo

Para a IA responder automaticamente, **3 condições** devem ser atendidas:

1. ✅ **Copilot Mode Enabled** (toggle ativado no popup)
2. ✅ **Score >= Threshold** (padrão 70%, configurável 50-95%)
3. ✅ **Tipo de mensagem compatível** (veja abaixo)

### Tipos de Mensagens que a IA Responde Automaticamente

#### 1. Saudações Simples (confiança 95%)
- "oi", "olá", "bom dia", "boa tarde", "boa noite"
- A IA responde automaticamente com saudação + pergunta de ajuda

#### 2. Match com FAQ (confiança > 80%)
- Quando a mensagem do cliente tem alta similaridade com FAQ cadastrada
- A IA responde automaticamente com a resposta da FAQ

#### 3. Respostas Rápidas (confiança 90%)
- Quando a mensagem match exato com uma resposta rápida cadastrada

#### 4. Informações sobre Produtos (confiança > 75%)
- Quando a mensagem tem match com produtos cadastrados
- A IA fornece automaticamente informações do produto

### Tipos de Mensagens em Modo Assistido

Conversas complexas permanecem no **modo assistido** (IA sugere, você decide):

- ❌ Negociações de preço
- ❌ Reclamações ou problemas
- ❌ Pedidos customizados
- ❌ Perguntas sobre prazo/entrega específicos
- ❌ Conversas com múltiplos tópicos

## Como Aumentar o Score de Confiança

### 1. Forneça Feedback Positivo (mais impacto)
- ✅ Marque respostas boas como "Boa" no painel do chatbot
- Use as sugestões da IA sem editar quando estiverem corretas
- Evite correções manuais frequentes

### 2. Cadastre Base de Conhecimento (médio impacto)
- 📚 Cadastre FAQs no painel de IA
- 🛒 Cadastre produtos com descrições completas
- 📝 Adicione exemplos de conversas de treinamento

### 3. Permita Envios Automáticos (baixo impacto inicial)
- 🚀 Quando a IA responder automaticamente casos simples
- Cada envio bem sucedido aumenta o score gradualmente

### 4. Evite Ações Negativas
- ❌ Não marque respostas corretas como ruins (-3 pontos)
- ✏️ Evite editar sugestões quando não necessário (-0.5 pontos)

## API Endpoints

### GET `/api/ai_confidence.php`

Retorna score atual, estatísticas e configurações.

```json
{
  "ok": true,
  "score": 72.5,
  "level": {
    "level": "copilot",
    "label": "Copiloto",
    "color": "#22c55e",
    "emoji": "🟢",
    "description": "IA pode responder casos simples"
  },
  "metrics": {
    "total_good": 50,
    "total_bad": 5,
    "total_corrections": 3,
    "total_auto_sent": 10,
    "total_suggestions_used": 30,
    "total_suggestions_edited": 8,
    "total_faq": 15,
    "total_products": 20,
    "total_examples": 5
  },
  "config": {
    "copilot_enabled": true,
    "copilot_threshold": 70.0
  },
  "points_to_threshold": 0
}
```

### POST `/api/ai_confidence.php`

#### Registrar Feedback

```json
POST /api/ai_confidence.php
{
  "action": "feedback",
  "type": "good",  // "good" | "bad" | "correction"
  "reason": "Resposta perfeita para pergunta sobre horário",
  "metadata": { "message_id": 123 }
}
```

#### Registrar Uso de Sugestão

```json
POST /api/ai_confidence.php
{
  "action": "suggestion_used",
  "edited": false,  // true se editou antes de enviar
  "metadata": { "suggestion_id": 456 }
}
```

#### Registrar Envio Automático

```json
POST /api/ai_confidence.php
{
  "action": "auto_sent",
  "metadata": { "message_type": "greeting", "confidence": 95 }
}
```

#### Ativar/Desativar Copiloto

```json
POST /api/ai_confidence.php
{
  "action": "toggle_copilot",
  "enabled": true
}
```

#### Definir Threshold

```json
POST /api/ai_confidence.php
{
  "action": "set_threshold",
  "threshold": 75
}
```

#### Atualizar Base de Conhecimento

```json
POST /api/ai_confidence.php
{
  "action": "knowledge_update",
  "faq_count": 20,
  "product_count": 35,
  "example_count": 8
}
```

## Tabelas do Banco de Dados

### `ai_confidence_metrics`

Armazena métricas agregadas por usuário.

```sql
CREATE TABLE ai_confidence_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    score DECIMAL(5,2) DEFAULT 0,
    total_good INT DEFAULT 0,
    total_bad INT DEFAULT 0,
    total_corrections INT DEFAULT 0,
    total_auto_sent INT DEFAULT 0,
    total_suggestions_used INT DEFAULT 0,
    total_suggestions_edited INT DEFAULT 0,
    total_faq INT DEFAULT 0,
    total_products INT DEFAULT 0,
    total_examples INT DEFAULT 0,
    copilot_enabled TINYINT(1) DEFAULT 0,
    copilot_threshold DECIMAL(5,2) DEFAULT 70.00,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user (user_id)
);
```

### `ai_confidence_log`

Registra eventos históricos de confiança.

```sql
CREATE TABLE ai_confidence_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    points DECIMAL(5,2) NOT NULL,
    reason TEXT,
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_action (action)
);
```

## Extensão Chrome - Componentes

### Popup (Interface)

Localização: `05chromeextensionwhatsapp/popup/`

- **popup.html**: Seção "🤖 Modo Copiloto" com barra de progresso
- **popup.css**: Estilos do copilot (barra gradiente, stats, toggle)
- **popup.js**: Carrega dados de confiança e gerencia UI

### Service Worker (Backend da Extensão)

Localização: `05chromeextensionwhatsapp/background/serviceWorker.js`

Handlers disponíveis:
- `GET_CONFIDENCE`: Retorna dados de confiança
- `UPDATE_CONFIDENCE`: Envia feedback para backend
- `TOGGLE_COPILOT`: Ativa/desativa modo copiloto
- `SET_THRESHOLD`: Define threshold de confiança

### Content Script (Lógica de Decisão)

Localização: `05chromeextensionwhatsapp/content/content.js`

Funções principais:
- `canAutoSend()`: Decide se pode enviar automaticamente
- `isSimpleGreeting()`: Detecta saudações simples
- `findFAQMatch()`: Busca match com FAQs
- `findProductMatch()`: Busca match com produtos

## Troubleshooting

### Score não está aumentando

1. **Verifique o backend**: Certifique-se que o backend está configurado e acessível
2. **Verifique logs**: Abra DevTools (F12) e veja o Console
3. **Teste API**: Faça um GET em `/api/ai_confidence.php` manualmente

### Copiloto não ativa mesmo com score alto

1. **Verifique threshold**: Score deve ser >= threshold (padrão 70%)
2. **Verifique toggle**: O toggle deve estar ativado no popup
3. **Verifique tipo de mensagem**: Apenas alguns tipos são auto-respondidos

### Envios automáticos não estão funcionando

1. **Verifique copilot_enabled**: Deve estar `true`
2. **Verifique score**: Deve estar acima do threshold
3. **Verifique tipo de mensagem**: Saudações e FAQs têm prioridade
4. **Verifique logs**: Console do DevTools mostra decisões da IA

### Backend retorna erro 401

- Configure `ALABAMA_EXTENSION_SECRET` no `.env`
- Envie header `X-Extension-Secret` nas requisições

### Score está negativo ou muito baixo

- **Causa comum**: Muitos feedbacks negativos ou edições
- **Solução**: Cadastre FAQs e produtos para ganhar pontos base
- **Reset**: Pode resetar manualmente no banco de dados se necessário

## Melhores Práticas

### Para Usuários

1. 📚 **Comece cadastrando conhecimento** (FAQs, produtos, exemplos)
2. ✅ **Forneça feedback positivo** quando a IA acertar
3. 🎯 **Ajuste o threshold** conforme sua confiança na IA
4. 📊 **Monitore as estatísticas** para entender padrões

### Para Desenvolvedores

1. 🔒 **Sempre use CORS headers** nos endpoints
2. 📝 **Registre eventos no log** para auditoria
3. ⚡ **Use cache local** como fallback se backend offline
4. 🧪 **Teste com diferentes níveis** de confiança

## Roadmap Futuro

- [ ] Dashboard de analytics do score ao longo do tempo
- [ ] Notificações quando atingir novos níveis
- [ ] A/B testing de thresholds diferentes
- [ ] Machine learning para melhorar matches de FAQ
- [ ] Integração com feedback do cliente final
- [ ] Modo "shadow" (IA responde mas não envia, apenas compara)

## Suporte

Para dúvidas ou problemas:

1. Consulte a documentação do projeto principal
2. Verifique os logs no console (F12)
3. Teste a API manualmente com curl ou Postman
4. Abra uma issue no repositório

---

**Versão**: 1.0.0  
**Última Atualização**: Dezembro 2024
