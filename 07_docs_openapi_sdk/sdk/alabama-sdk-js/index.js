/**
 * Alabama SDK JS - cliente mínimo para a Test Prompt API.
 *
 * Observação importante (formato de resposta):
 * A API responde no formato padronizado (ApiResponse):
 *   { ok: boolean, data: any, error: {code,message}|null, meta: object }
 *
 * Exemplos:
 *
 * Browser:
 *   import { createClient } from './sdk/alabama-sdk-js/index.js';
 *   const client = createClient({ baseUrl: 'http://localhost:8000' });
 *   const res = await client.testPrompt('Olá, IA!');
 *   console.log(res.data?.answer);
 *
 * Node 18+ (fetch global):
 *   import { createClient } from './sdk/alabama-sdk-js/index.js';
 *   const client = createClient({ baseUrl: process.env.ALABAMA_API_BASE_URL });
 *   const res = await client.testPrompt('Olá!');
 *   console.log(res.data?.answer);
 */

function resolveBaseUrl(options) {
  if (options && options.baseUrl) return options.baseUrl;
  if (typeof process !== 'undefined' && process.env && process.env.ALABAMA_API_BASE_URL) {
    return process.env.ALABAMA_API_BASE_URL;
  }
  return 'http://localhost:8000';
}

function extractApiErrorMessage(json, fallbackText) {
  if (json && typeof json === 'object') {
    // Formato atual (ApiResponse)
    if (json.error && typeof json.error === 'object') {
      return json.error.message || json.error.code || fallbackText;
    }
    // Compat (legado)
    if (typeof json.erro === 'string') return json.erro;
    if (typeof json.message === 'string') return json.message;
  }
  return fallbackText;
}

async function httpPostJson(url, body) {
  const payload = JSON.stringify(body);

  const resp = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: payload
  });

  const text = await resp.text();
  let json;
  try {
    json = text ? JSON.parse(text) : null;
  } catch (e) {
    throw new Error('Resposta inválida da API (não-JSON): ' + text);
  }

  // Alguns endpoints podem retornar ok=false com status 200 (evitamos surpresa)
  const apiOk = json && typeof json === 'object' ? json.ok !== false : true;

  if (!resp.ok || !apiOk) {
    const msg = extractApiErrorMessage(json, text);
    throw new Error('Erro HTTP ' + resp.status + ': ' + msg);
  }

  return json;
}

export function createClient(options = {}) {
  const baseUrl = resolveBaseUrl(options).replace(/\/$/, '');

  return {
    /**
     * Envia um prompt para o LLM.
     *
     * @param {string} prompt
     * @param {object} params
     * @param {number=} params.temperature
     * @param {number=} params.max_tokens
     * @param {string=} params.model
     * @returns {Promise<object>} ApiResponse
     */
    async testPrompt(prompt, params = {}) {
      const payload = {
        prompt,
        temperature: params.temperature ?? 0.2,
        max_tokens: params.max_tokens ?? 256,
        model: params.model
      };
      const url = baseUrl + '/api/test_prompt.php';
      return httpPostJson(url, payload);
    }
  };
}
