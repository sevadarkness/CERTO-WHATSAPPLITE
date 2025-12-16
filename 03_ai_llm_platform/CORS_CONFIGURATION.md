# Configuração CORS - Endpoint de IA (chat.php)

## 🔒 Segurança

O endpoint `chat.php` implementa uma **whitelist rigorosa** de origens permitidas para prevenir ataques cross-origin. Por padrão, apenas origens específicas e confiáveis são permitidas.

## ✅ Origens Automaticamente Permitidas

### 1. Extensões de Navegador

As seguintes extensões são sempre permitidas:

```
chrome-extension://*     # Extensões Chrome/Chromium
moz-extension://*        # Extensões Firefox
edge-extension://*       # Extensões Edge
```

**Exemplo:**
```
chrome-extension://abcdefghijklmnop
moz-extension://12345678-1234-1234-1234-123456789012
edge-extension://abcdefghijklmnop
```

### 2. Localhost (Desenvolvimento)

Para desenvolvimento local, as seguintes origens são permitidas:

```
http://localhost
https://localhost
http://localhost:<porta>
https://localhost:<porta>
http://127.0.0.1
https://127.0.0.1
http://127.0.0.1:<porta>
https://127.0.0.1:<porta>
```

**Exemplos:**
```
http://localhost:3000
https://localhost:8080
http://127.0.0.1:5000
```

## 🔧 Configuração de Domínios Adicionais

Para permitir domínios adicionais além das extensões e localhost, configure a variável de ambiente:

### Variável de Ambiente

```bash
ALABAMA_CORS_ALLOWED_ORIGINS=https://seu-dominio.com,https://outro-dominio.com
```

### Exemplos de Configuração

#### Desenvolvimento (.env)

```bash
ALABAMA_CORS_ALLOWED_ORIGINS=http://localhost:3000,https://app-dev.exemplo.com
```

#### Produção (Railway/Heroku)

```bash
ALABAMA_CORS_ALLOWED_ORIGINS=https://app.redealabama.com,https://admin.redealabama.com
```

#### Docker Compose

```yaml
environment:
  - ALABAMA_CORS_ALLOWED_ORIGINS=https://app.exemplo.com,https://painel.exemplo.com
```

## ⚠️ Importante

### O que NÃO fazer

❌ **Não use wildcard (`*`)**
```bash
# VULNERÁVEL - NÃO FAÇA ISSO!
ALABAMA_CORS_ALLOWED_ORIGINS=*
```

❌ **Não adicione domínios não confiáveis**
```bash
# INSEGURO - Permite qualquer site fazer requisições
ALABAMA_CORS_ALLOWED_ORIGINS=http://site-qualquer.com
```

### Validação de Origem

- A validação é **case-sensitive** e **exact match**
- Subdomínios devem ser listados separadamente
- Portas são validadas (`:3000` é diferente de `:8080`)
- Protocolos são validados (`http://` é diferente de `https://`)

### Exemplos de Validação

```bash
# Origem solicitada: https://app.exemplo.com
# Configurado: https://app.exemplo.com
✅ Permitido

# Origem solicitada: http://app.exemplo.com
# Configurado: https://app.exemplo.com
❌ Bloqueado (protocolo diferente)

# Origem solicitada: https://api.exemplo.com
# Configurado: https://app.exemplo.com
❌ Bloqueado (subdomínio diferente)
```

## 🔍 Preflight (OPTIONS)

O endpoint responde adequadamente a requisições preflight:

- **Origem permitida**: Retorna `204 No Content`
- **Origem não permitida**: Retorna `403 Forbidden`

### Exemplo de Requisição Preflight

```http
OPTIONS /ai/chat.php HTTP/1.1
Host: seu-servidor.com
Origin: chrome-extension://abcdefghijklmnop
Access-Control-Request-Method: POST
Access-Control-Request-Headers: Content-Type, X-Alabama-Proxy-Key

HTTP/1.1 204 No Content
Access-Control-Allow-Origin: chrome-extension://abcdefghijklmnop
Access-Control-Allow-Methods: POST, OPTIONS
Access-Control-Allow-Headers: Content-Type, X-Alabama-Proxy-Key
Vary: Origin
```

## 🧪 Testando a Configuração

### Teste com curl

```bash
# Requisição sem Origin (deve funcionar normalmente)
curl -X POST https://seu-servidor.com/ai/chat.php \
  -H "Content-Type: application/json" \
  -H "X-Alabama-Proxy-Key: sua-chave" \
  -d '{"messages": [{"role": "user", "content": "teste"}]}'

# Preflight com origem permitida
curl -X OPTIONS https://seu-servidor.com/ai/chat.php \
  -H "Origin: http://localhost:3000" \
  -v

# Deve retornar 204

# Preflight com origem não permitida
curl -X OPTIONS https://seu-servidor.com/ai/chat.php \
  -H "Origin: https://site-malicioso.com" \
  -v

# Deve retornar 403
```

### Teste com JavaScript

```javascript
// Da extensão Chrome
fetch('https://seu-servidor.com/ai/chat.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-Alabama-Proxy-Key': 'sua-chave'
  },
  body: JSON.stringify({
    messages: [{role: 'user', content: 'teste'}]
  })
})
.then(r => r.json())
.then(data => console.log('✓ Sucesso:', data))
.catch(err => console.error('✗ Erro CORS:', err));
```

## 📊 Monitoramento

### Logs de Requisições Bloqueadas

Para monitorar requisições bloqueadas, verifique:

1. **Console do navegador**: Erros CORS aparecem no console
2. **Logs do servidor**: Requisições OPTIONS com 403
3. **Métricas**: Contagem de requisições 403 no endpoint

### Sinais de Configuração Incorreta

- ❌ Extension retornando erro CORS
- ❌ Localhost não funcionando em desenvolvimento
- ❌ Muitos erros 403 em requisições OPTIONS legítimas

## 🚀 Migração de Código Antigo

Se você estava usando o código vulnerável anterior:

### Antes (VULNERÁVEL)

```php
// CORS permissivo - VULNERÁVEL
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *');
}
```

### Depois (SEGURO)

```php
// CORS com whitelist - SEGURO
// Veja chat.php linhas 12-68 para implementação completa
```

**Nenhuma ação adicional é necessária** - a whitelist já está configurada com valores padrão seguros!

## 📞 Suporte

Para questões sobre configuração CORS:

1. Verifique os logs do servidor
2. Teste com curl (veja seção "Testando a Configuração")
3. Revise a variável `ALABAMA_CORS_ALLOWED_ORIGINS`
4. Consulte a documentação de segurança em `SECURITY_AUDIT.md`

---

**Última atualização:** Dezembro 2024  
**Versão:** 1.0 (Fase 4 - Correções de Segurança)
